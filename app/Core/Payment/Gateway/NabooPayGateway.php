<?php

declare(strict_types=1);

namespace Sesam\Core\Payment\Gateway;

use Sesam\Core\Env;
use Sesam\Core\HttpException;
use Sesam\Core\Payment\PaymentGatewayInterface;
use Sesam\Models\Payment;

final class NabooPayGateway implements PaymentGatewayInterface
{
    /**
     * @param array{api_key: string, webhook_secret: string, api_base: string, signature_header: string, live: bool} $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $appUrl,
    ) {
    }

    public function slug(): string
    {
        return 'naboopay';
    }

    public function isLive(): bool
    {
        return !empty($this->config['live']) && trim($this->config['api_key']) !== '';
    }

    /**
     * @param array{methods?: list<string>, success_url?: string, error_url?: string} $options
     * @return array{mode: string, checkout_url: string, external_id: ?string}
     */
    public function createCheckout(Payment $payment, string $returnUrl, array $options = []): array
    {
        if (!$this->isLive()) {
            return [
                'mode' => 'sandbox',
                'checkout_url' => $returnUrl,
                'external_id' => null,
            ];
        }

        $methods = $options['methods'] ?? ['wave', 'orange_money'];
        $methods = array_values(array_filter(
            array_map([$this, 'normalizeMethod'], $methods),
            static fn (string $method): bool => $method === 'wave' || $method === 'orange_money'
        ));
        if ($methods === []) {
            $methods = ['wave', 'orange_money'];
        }

        $successUrl = $this->plainUrl((string) ($options['success_url'] ?? $returnUrl));
        $errorUrl = $this->plainUrl((string) ($options['error_url'] ?? $returnUrl));
        $name = trim($payment->description());
        if ($name === '') {
            $name = 'Paiement SESAM';
        }
        $name = mb_substr($name, 0, 120);

        $payload = [
            'method_of_payment' => $methods,
            'products' => [[
                'name' => $name,
                'price' => $payment->amountMinor(),
                'quantity' => 1,
                'description' => $payment->reference(),
            ]],
        ];
        // NabooPay refuse localhost / IP privées : sans URL publique, la page NabooPay reste le retour.
        if ($this->isPublicHttpUrl($successUrl)) {
            $payload['success_url'] = $successUrl;
        }
        if ($this->isPublicHttpUrl($errorUrl)) {
            $payload['error_url'] = $errorUrl;
        }

        $response = $this->request('POST', '/api/v2/transactions', $payload);
        $orderId = trim((string) ($response['order_id'] ?? ''));
        $checkoutUrl = trim((string) ($response['checkout_url'] ?? ''));
        if ($orderId === '' || $checkoutUrl === '') {
            throw new HttpException(502, 'Réponse NabooPay incomplète (order_id ou checkout_url manquant).');
        }

        return [
            'mode' => 'live',
            'checkout_url' => $checkoutUrl,
            'external_id' => $orderId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchTransaction(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            throw new HttpException(400, 'Identifiant de transaction NabooPay manquant.');
        }

        return $this->request('GET', '/api/v2/transactions/' . rawurlencode($orderId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAccounts(): array
    {
        $response = $this->request('GET', '/api/v2/accounts');
        if (array_is_list($response)) {
            /** @var list<array<string, mixed>> $response */
            return $response;
        }

        return [];
    }

    private function normalizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match ($method) {
            'orange-money', 'orange_money', 'om' => 'orange_money',
            'wave' => 'wave',
            default => $method,
        };
    }

    private function plainUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new HttpException(500, 'URL de retour paiement manquante.');
        }
        if (str_contains($url, '%')) {
            throw new HttpException(500, 'L\'URL de retour NabooPay ne doit pas contenir de caractères encodés.');
        }

        return $url;
    }

    private function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($scheme !== 'https' && $scheme !== 'http') || $host === '') {
            return false;
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }
        if (str_ends_with($host, '.local') || str_ends_with($host, '.test') || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    private function caBundlePath(): ?string
    {
        $configured = trim(Env::get('NABOOPAY_CA_BUNDLE', Env::get('SSL_CERT_FILE', '')));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }
        $local = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem';
        if (is_file($local)) {
            return $local;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $json
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $json = null): array
    {
        $base = rtrim($this->config['api_base'], '/');
        $url = $base . $path;
        $headers = [
            'Authorization: Bearer ' . trim($this->config['api_key']),
            'Accept: application/json',
        ];
        $body = null;
        if ($json !== null) {
            $body = json_encode($json, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
        }

        if (function_exists('curl_init')) {
            return $this->curlRequest($method, $url, $headers, $body);
        }

        return $this->streamRequest($method, $url, $headers, $body);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function curlRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new HttpException(502, 'Impossible de contacter NabooPay.');
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $caBundle = $this->caBundlePath();
        if ($caBundle !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $caBundle);
        }
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($raw)) {
            throw new HttpException(502, 'NabooPay inaccessible' . ($error !== '' ? ' : ' . $error : '.'));
        }

        return $this->decodeResponse($status, $raw);
    }

    /**
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function streamRequest(string $method, string $url, array $headers, ?string $body): array
    {
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true];
        $caBundle = $this->caBundlePath();
        if ($caBundle !== null) {
            $ssl['cafile'] = $caBundle;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => $ssl,
        ]);
        $raw = file_get_contents($url, false, $context);
        if (!is_string($raw)) {
            throw new HttpException(502, 'NabooPay inaccessible.');
        }
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $matches) === 1) {
            $status = (int) $matches[1];
        }

        return $this->decodeResponse($status, $raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(int $status, string $raw): array
    {
        /** @var array<string, mixed>|list<mixed>|null $decoded */
        $decoded = json_decode($raw, true);
        if ($status >= 200 && $status < 300) {
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }

            throw new HttpException(502, 'Réponse NabooPay invalide.');
        }

        $message = 'Erreur NabooPay HTTP ' . $status;
        if (is_array($decoded) && isset($decoded['error']) && is_scalar($decoded['error'])) {
            $message = (string) $decoded['error'];
        }
        $code = $status >= 400 && $status < 500 ? $status : 502;
        throw new HttpException($code, $message);
    }
}
