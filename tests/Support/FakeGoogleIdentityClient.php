<?php

declare(strict_types=1);

namespace Sesam\Tests\Support;

use Sesam\Core\Auth\GoogleIdentityClient;
use Sesam\Core\Auth\GoogleProfile;
use Sesam\Core\HttpException;

final class FakeGoogleIdentityClient implements GoogleIdentityClient
{
    public function __construct(
        private readonly GoogleProfile $profile,
        private readonly bool $configured = true,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function webConfig(): array
    {
        return [
            'apiKey' => 'test',
            'authDomain' => 'sesam-e1c82.firebaseapp.com',
            'projectId' => 'sesam-e1c82',
        ];
    }

    public function profileFromIdToken(string $idToken): GoogleProfile
    {
        if ($idToken !== 'ok-token') {
            throw new HttpException(401, 'Session Google invalide. Réessayez.');
        }

        return $this->profile;
    }
}
