<?php

declare(strict_types=1);

namespace Sesam\Core\Auth;

use Sesam\Core\Env;
use Sesam\Core\HttpException;

final class GoogleOAuthClient implements GoogleIdentityClient
{
    /**
     * @param array{api_key: string, project_id: string, auth_domain: string, web: array<string, string>} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function isConfigured(): bool
    {
        return $this->config['api_key'] !== '' && $this->config['project_id'] !== '' && $this->config['auth_domain'] !== '';
    }

    /**
     * @return array<string, string>
     */
    public function webConfig(): array
    {
        return $this->config['web'];
    }

    public function profileFromIdToken(string $idToken): GoogleProfile
    {
        $payload = $this->request(
            'POST',
            'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode($this->config['api_key']),
            ['Content-Type: application/json', 'Accept: application/json'],
            json_encode(['idToken' => $idToken], JSON_THROW_ON_ERROR)
        );
        $users = $payload['users'] ?? null;
        if (!is_array($users) || !isset($users[0]) || !is_array($users[0])) {
            throw new HttpException(401, 'Session Google invalide. Réessayez.');
        }
        $info = $users[0];
        $email = strtolower(trim((string) ($info['email'] ?? '')));
        $name = trim((string) ($info['displayName'] ?? ''));
        $verified = ($info['emailVerified'] ?? false) === true || ($info['emailVerified'] ?? '') === 'true';
        $sub = '';
        $providers = $info['providerUserInfo'] ?? [];
        if (is_array($providers)) {
            foreach ($providers as $provider) {
                if (is_array($provider) && ($provider['providerId'] ?? '') === 'google.com') {
                    $sub = trim((string) ($provider['rawId'] ?? $provider['federatedId'] ?? ''));
                    break;
                }
            }
        }
        if ($sub === '') {
            $sub = trim((string) ($info['localId'] ?? ''));
        }
        if ($sub === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(502, 'Profil Google incomplet.');
        }
        if (!$verified) {
            throw new HttpException(403, 'Cet e-mail Google n\'est pas vérifié.');
        }
        if ($name === '') {
            $name = explode('@', $email)[0];
        }

        return new GoogleProfile($sub, $email, mb_substr($name, 0, 120), true);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        if (function_exists('curl_init')) {
            $raw = $this->curl($method, $url, $headers, $body);
        } else {
            $raw = $this->stream($method, $url, $headers, $body);
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw['body'], true);
        if ($raw['status'] < 200 || $raw['status'] >= 300 || !is_array($decoded)) {
            throw new HttpException(502, 'Impossible de contacter Google. Réessayez.');
        }

        return $decoded;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new HttpException(502, 'Impossible de contacter Google. Réessayez.');
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $ca = $this->caBundlePath();
        if ($ca !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $ca);
        }
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($response)) {
            throw new HttpException(502, 'Impossible de contacter Google. Réessayez.');
        }

        return ['status' => $status, 'body' => $response];
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function stream(string $method, string $url, array $headers, ?string $body): array
    {
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true];
        $ca = $this->caBundlePath();
        if ($ca !== null) {
            $ssl['cafile'] = $ca;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
            'ssl' => $ssl,
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m) === 1) {
            $status = (int) $m[1];
        }
        if (!is_string($response)) {
            throw new HttpException(502, 'Impossible de contacter Google. Réessayez.');
        }

        return ['status' => $status, 'body' => $response];
    }

    private function caBundlePath(): ?string
    {
        $configured = trim(Env::get('NABOOPAY_CA_BUNDLE', Env::get('SSL_CERT_FILE', '')));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }
        $local = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';

        return is_file($local) ? $local : null;
    }
}
