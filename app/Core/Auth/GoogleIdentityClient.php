<?php

declare(strict_types=1);

namespace Sesam\Core\Auth;

interface GoogleIdentityClient
{
    public function isConfigured(): bool;

    /**
     * @return array<string, string>
     */
    public function webConfig(): array;

    public function profileFromIdToken(string $idToken): GoogleProfile;
}
