<?php

declare(strict_types=1);

namespace Sesam\Core\Response;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $content,
        private readonly int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $content, int $status = 200): self
    {
        return new self($content, $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public static function json(string $content, int $status = 200): self
    {
        return new self($content, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, [
            'Location' => $location,
        ]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function send(): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }
}
