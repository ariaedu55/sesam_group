<?php

declare(strict_types=1);

namespace Sesam\Core\Push;

/**
 * Firebase Cloud Messaging, API HTTP v1. Le jeton d'accès Google est obtenu en signant un JWT
 * avec la clé du compte de service (RS256, OpenSSL), puis gardé en cache jusqu'à son expiration.
 * Les messages sont envoyés en « data » : le service worker affiche lui-même la notification
 * et ouvre le bon écran au clic (le lien peut ainsi rester relatif à l'application).
 */
final class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array{client_email: string, private_key: string, token_uri: string, project_id: string}|null */
    private ?array $credentials = null;

    public function __construct(
        private readonly string $credentialsPath,
        private readonly string $projectId,
        private readonly string $cacheDir,
        private readonly string $caBundle = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->projectId !== '' && is_file($this->credentialsPath);
    }

    /**
     * @param array<string, string> $data
     * @return array{ok: bool, status: int, error: string, unregistered: bool, name: string}
     */
    public function send(string $token, array $data, bool $validateOnly = false): array
    {
        $payload = [
            'validate_only' => $validateOnly,
            'message' => [
                'token' => $token,
                'data' => array_map('strval', $data),
                'webpush' => ['headers' => ['Urgency' => 'high', 'TTL' => '86400']],
            ],
        ];
        $response = $this->request(
            'https://fcm.googleapis.com/v1/projects/' . rawurlencode($this->projectId) . '/messages:send',
            (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Authorization: Bearer ' . $this->accessToken(), 'Content-Type: application/json; charset=UTF-8']
        );
        $body = json_decode($response['body'], true);
        $body = is_array($body) ? $body : [];
        if ($response['status'] === 200) {
            return ['ok' => true, 'status' => 200, 'error' => '', 'unregistered' => false, 'name' => (string) ($body['name'] ?? '')];
        }
        $error = is_array($body['error'] ?? null) ? $body['error'] : [];
        $code = '';
        foreach ((array) ($error['details'] ?? []) as $detail) {
            if (is_array($detail) && isset($detail['errorCode'])) {
                $code = (string) $detail['errorCode'];
            }
        }
        $message = trim($code . ' ' . (string) ($error['message'] ?? $response['error']));

        return [
            'ok' => false,
            'status' => $response['status'],
            'error' => mb_substr($message !== '' ? $message : 'Erreur HTTP ' . $response['status'], 0, 250),
            // Jeton périmé ou désinstallé : l'appareil doit être oublié.
            'unregistered' => $code === 'UNREGISTERED' || ($response['status'] === 404) || ($code === 'INVALID_ARGUMENT' && str_contains((string) ($error['message'] ?? ''), 'registration token')),
            'name' => '',
        ];
    }

    /**
     * Jeton OAuth 2.0 du compte de service, mis en cache (fichier) jusqu'à 5 minutes avant son expiration.
     */
    public function accessToken(): string
    {
        $cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'fcm-token-' . substr(hash('sha256', $this->credentialsPath), 0, 12) . '.json';
        if (is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && is_string($cached['token'] ?? null) && (int) ($cached['expires'] ?? 0) > time() + 300) {
                return $cached['token'];
            }
        }
        $credentials = $this->credentials();
        $now = time();
        $segments = [
            $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];
        $signature = '';
        $key = openssl_pkey_get_private($credentials['private_key']);
        if ($key === false || !openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new PushException('Impossible de signer la demande d\'accès Firebase (clé du compte de service invalide).');
        }
        $segments[] = $this->base64Url($signature);
        $response = $this->request(
            $credentials['token_uri'],
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => implode('.', $segments)]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        $body = json_decode($response['body'], true);
        if ($response['status'] !== 200 || !is_array($body) || !is_string($body['access_token'] ?? null)) {
            $detail = is_array($body) ? (string) ($body['error_description'] ?? $body['error'] ?? '') : $response['error'];
            throw new PushException('Accès Firebase refusé (' . $response['status'] . ') : ' . mb_substr($detail, 0, 200));
        }
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
        @file_put_contents($cacheFile, (string) json_encode(['token' => $body['access_token'], 'expires' => $now + (int) ($body['expires_in'] ?? 3600)]), LOCK_EX);

        return $body['access_token'];
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri: string, project_id: string}
     */
    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }
        if (!is_file($this->credentialsPath)) {
            throw new PushException('Clé du compte de service Firebase introuvable : ' . basename($this->credentialsPath) . '.');
        }
        $json = json_decode((string) file_get_contents($this->credentialsPath), true);
        if (!is_array($json) || ($json['type'] ?? '') !== 'service_account' || !is_string($json['private_key'] ?? null) || !is_string($json['client_email'] ?? null)) {
            throw new PushException('La clé Firebase n\'est pas une clé de compte de service valide.');
        }

        return $this->credentials = [
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
            'token_uri' => is_string($json['token_uri'] ?? null) ? $json['token_uri'] : 'https://oauth2.googleapis.com/token',
            'project_id' => (string) ($json['project_id'] ?? ''),
        ];
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string, error: string}
     */
    private function request(string $url, string $body, array $headers): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new PushException('cURL indisponible.');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        if ($this->caBundle !== '' && is_file($this->caBundle)) {
            curl_setopt($curl, CURLOPT_CAINFO, $this->caBundle);
        }
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return ['status' => $status, 'body' => is_string($result) ? $result : '', 'error' => $error];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
