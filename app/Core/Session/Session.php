<?php

declare(strict_types=1);

namespace Sesam\Core\Session;

final class Session
{
    public const IDLE_SECONDS = 7200;

    private const LAST_SEEN = '_last_seen';

    /** @var array<string, mixed> */
    private array $store = [];

    private bool $native = false;

    public function start(bool $secure): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->native = true;

            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_name('SESAMSESSID');
        session_set_cookie_params(self::cookieOptions($secure));
        session_start();
        $this->native = true;
    }

    /**
     * Vide la session (connexion, jeton CSRF, flash) après IDLE_SECONDS sans requête.
     */
    public function expireIdle(?int $now = null): bool
    {
        $now ??= time();
        $seen = $this->get(self::LAST_SEEN);
        $expired = is_int($seen) && $now - $seen > self::IDLE_SECONDS;
        if ($expired) {
            $this->invalidate();
        }
        $this->put(self::LAST_SEEN, $now);

        return $expired;
    }

    /**
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    public static function cookieOptions(bool $secure): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->native) {
            return $_SESSION[$key] ?? $default;
        }

        return $this->store[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        if ($this->native) {
            $_SESSION[$key] = $value;

            return;
        }

        $this->store[$key] = $value;
    }

    public function forget(string $key): void
    {
        if ($this->native) {
            unset($_SESSION[$key]);

            return;
        }

        unset($this->store[$key]);
    }

    public function regenerate(): void
    {
        if ($this->native) {
            session_regenerate_id(true);
        }
    }

    public function invalidate(): void
    {
        if ($this->native) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        }

        $this->store = [];
    }
}
