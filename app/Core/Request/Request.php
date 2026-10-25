<?php

declare(strict_types=1);

namespace Sesam\Core\Request;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, string> $attributes
     * @param array<string, mixed> $files
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $headers = [],
        private readonly array $attributes = [],
        private readonly bool $secure = false,
        private readonly string $ip = '127.0.0.1',
        private readonly string $rawBody = '',
        private readonly array $files = [],
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = self::normalizePath(
            rawurldecode(is_string($uri) ? $uri : '/'),
            rawurldecode($_SERVER['SCRIPT_NAME'] ?? '')
        );

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        $rawBody = file_get_contents('php://input');
        $rawBody = is_string($rawBody) ? $rawBody : '';
        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (is_string($contentType) && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            $method,
            $path,
            $_GET,
            is_array($body) ? $body : [],
            $headers,
            [],
            self::httpsEnabled(),
            self::clientIp(),
            $rawBody,
            is_array($_FILES) ? $_FILES : [],
        );
    }

    public static function normalizePath(string $uri, string $scriptName): string
    {
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') {
            $scriptDir = '';
        }

        $base = preg_replace('#/public$#', '', $scriptDir) ?? $scriptDir;
        if ($scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        } elseif ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $path = '/' . trim($uri, '/');
        if ($path === '/index.php') {
            return '/';
        }

        return $path === '//' ? '/' : $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        return $this->query[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp($header, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function attribute(string $key, ?string $default = null): ?string
    {
        return $this->attributes[$key] ?? $default;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    /**
     * @param array<string, string> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $this->headers,
            $attributes,
            $this->secure,
            $this->ip,
            $this->rawBody,
            $this->files,
        );
    }

    private static function httpsEnabled(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        // Nginx reverse proxy local (Webuzo) : Apache voit souvent du HTTP même si le client est en HTTPS.
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (
            is_string($remote)
            && ($remote === '127.0.0.1' || $remote === '::1')
            && is_string($forwarded)
            && strtolower($forwarded) === 'https'
        ) {
            return true;
        }

        return false;
    }

    private static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (is_string($remote) && filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }

        return '127.0.0.1';
    }
}
