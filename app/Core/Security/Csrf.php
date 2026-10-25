<?php

declare(strict_types=1);

namespace Sesam\Core\Security;

use Sesam\Core\Session\Session;

final class Csrf
{
    public const SESSION_KEY = '_csrf';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $current = $this->session->get(self::SESSION_KEY);
        if (is_string($current) && $current !== '') {
            return $current;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->put(self::SESSION_KEY, $token);

        return $token;
    }

    public function validate(?string $provided): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);
        if (!is_string($stored) || $stored === '' || !is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals($stored, $provided);
    }
}
