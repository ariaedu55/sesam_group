<?php

declare(strict_types=1);

namespace Sesam\Core\Auth;

final class GoogleProfile
{
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly string $name,
        public readonly bool $emailVerified,
    ) {
    }
}
