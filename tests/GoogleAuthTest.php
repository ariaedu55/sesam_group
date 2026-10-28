<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\GoogleProfile;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Request\Request;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Validation\Validator;
use Sesam\Services\AuthService;
use Sesam\Services\SaasService;
use Sesam\Tests\Support\FakeGoogleIdentityClient;
use Sesam\Tests\Support\IntegrationTestCase;

final class GoogleAuthTest extends IntegrationTestCase
{
    public function testGoogleButtonIsHiddenUntilConfigured(): void
    {
        $kernel = $this->kernel();
        $login = $kernel->handle(new Request('GET', '/connexion'));
        $this->assertSame(200, $login->status());
        $this->assertStringNotContainsString('Continuer avec Google', $login->content());
        $this->assertStringNotContainsString('/connexion/google', $login->content());

        // La route Google n'accepte que POST (jeton Firebase) : un simple GET est refusé.
        $start = $kernel->handle(new Request('GET', '/connexion/google'));
        $this->assertContains($start->status(), [404, 405]);
    }

    public function testUnknownGoogleAccountIsSentToSignup(): void
    {
        $email = 'p20-google-' . bin2hex(random_bytes(3)) . '@example.com';
        $auth = $this->auth(new GoogleProfile('gid-' . bin2hex(random_bytes(4)), $email, 'Awa Google', true));
        $target = $auth->completeGoogleIdToken('ok-token', '', '203.0.113.40');
        $this->assertSame('/commencer', $target);
        $pending = $auth->pendingGoogle();
        $this->assertNotNull($pending);
        $this->assertSame($email, $pending['email']);
    }

    public function testExistingAccountLogsInWithGoogleAndSignupStoresGoogleSub(): void
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE);
        $ownerEmail = $this->user([Role::TENANT_OWNER], $tenantId);
        $sub = 'gid-' . bin2hex(random_bytes(4));
        $auth = $this->auth(new GoogleProfile($sub, $ownerEmail, 'Awa Boutique', true));
        $this->assertSame('/espace', $auth->completeGoogleIdToken('ok-token', '', '203.0.113.41'));
        $this->assertSame($sub, $this->value('SELECT google_sub FROM users WHERE email = ?', [$ownerEmail]));

        $newEmail = 'p20-gsignup-' . bin2hex(random_bytes(3)) . '@example.com';
        $newSub = 'gid-' . bin2hex(random_bytes(4));
        $userId = (new SaasService($this->config()['db']))->signup([
            'sector' => Sector::RESERVATION,
            'full_name' => 'Awa Google',
            'business_name' => 'Salon Google p20',
            'phone' => '77 222 33 44',
            'email' => $newEmail,
            'google_sub' => $newSub,
            'terms' => '1',
        ], '203.0.113.42');
        $this->track((int) $this->value('SELECT tenant_id FROM users WHERE id = ?', [$userId]), $newEmail);
        $this->assertSame($newSub, $this->value('SELECT google_sub FROM users WHERE id = ?', [$userId]));
    }

    private function auth(GoogleProfile $profile): AuthService
    {
        return new AuthService(
            $this->config()['db'],
            new Session(),
            new Validator(),
            new FakeGoogleIdentityClient($profile),
        );
    }
}
