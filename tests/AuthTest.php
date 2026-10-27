<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Request\Request;
use Sesam\Core\Auth\Role;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;

final class AuthTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<string> */
    private array $ips = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $statement = $pdo->prepare('DELETE FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
        }
        foreach ($this->ips as $ip) {
            $statement = $pdo->prepare('DELETE FROM auth_attempts WHERE ip = :ip');
            $statement->execute(['ip' => $ip]);
        }
    }

    public function testGuestIsRedirectedFromEspace(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $response = $kernel->handle(new Request('GET', '/espace'));

        $this->assertSame(302, $response->status());
        $this->assertSame('/connexion', $response->headers()['Location'] ?? '');
    }

    public function testLoginPageHasNoPublicSignup(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $response = $kernel->handle(new Request('GET', '/connexion'));
        $html = html_entity_decode($response->content(), ENT_QUOTES, 'UTF-8');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Connexion', $html);
        $this->assertStringContainsString('auth-page', $html);
        $this->assertStringContainsString('auth-card-logo', $html);
        $this->assertStringNotContainsString('Bon retour', $html);
        $this->assertStringNotContainsString('Une seule page', $html);
        $this->assertStringContainsString('data-password-toggle', $html);
        $this->assertStringContainsString('/mot-de-passe', $html);
        $this->assertStringNotContainsString('Créer un compte', $html);
        $this->assertSame(404, $kernel->handle(new Request('GET', '/inscription-compte'))->status());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/register'))->status());
    }

    public function testLoginRequiresCsrfAndRejectsBadCredentials(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $this->assertSame(419, $kernel->handle(new Request('POST', '/connexion', [], [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password-12',
        ]))->status());

        $token = $this->csrf($kernel);
        $ip = $this->ip();
        $denied = $kernel->handle($this->post('/connexion', [
            '_csrf' => $token,
            'email' => 'nobody@example.com',
            'password' => 'wrong-password-12',
        ], $ip));
        $this->assertSame(422, $denied->status());
        $this->assertStringContainsString('E-mail ou mot de passe incorrect.', $denied->content());
    }

    public function testLoginLogoutAndProtectedPages(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $email = $this->email();
        $password = 'ValidPass-1234';
        $this->createUser($email, $password, [Role::COMMERCIAL]);
        $token = $this->csrf($kernel);

        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $token,
            'email' => $email,
            'password' => $password,
        ]));
        $this->assertSame(302, $login->status());
        $this->assertSame('/espace', $login->headers()['Location'] ?? '');

        $home = $kernel->handle(new Request('GET', '/espace'));
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString('Bonjour', $home->content());
        $this->assertStringNotContainsString('Gérer les utilisateurs', $home->content());
        $this->assertSame(302, $kernel->handle(new Request('GET', '/connexion'))->status());
        $this->assertSame(403, $kernel->handle(new Request('GET', '/utilisateurs'))->status());

        $logout = $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
        $this->assertSame(302, $logout->status());
        $this->assertSame('/connexion', $logout->headers()['Location'] ?? '');
        $this->assertSame(302, $kernel->handle(new Request('GET', '/espace'))->status());
    }

    public function testInactiveUserCannotLogin(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $email = $this->email();
        $this->createUser($email, 'ValidPass-1234', [Role::STAFF], false);
        $denied = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ]));
        $this->assertSame(422, $denied->status());
    }

    public function testPasswordResetWithIssuedToken(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $email = $this->email();
        $userId = $this->createUser($email, 'OldPassword-123', [Role::SUPPORT]);
        $auth = new \Sesam\Services\AuthService($this->config()['db'], new \Sesam\Core\Session\Session());
        $token = $auth->issueResetToken($userId);

        $form = $kernel->handle(new Request('GET', '/mot-de-passe/reinitialiser/' . $token));
        $this->assertSame(200, $form->status());

        $reset = $kernel->handle($this->post('/mot-de-passe/reinitialiser/' . $token, [
            '_csrf' => $this->csrf($kernel),
            'password' => 'NewPassword-123',
            'password_confirmation' => 'NewPassword-123',
        ]));
        $this->assertSame(302, $reset->status());

        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'NewPassword-123',
        ]));
        $this->assertSame(302, $login->status());
        $this->assertSame('/espace', $login->headers()['Location'] ?? '');
    }

    public function testLoginRateLimit(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $token = $this->csrf($kernel);
        $ip = $this->ip();
        for ($i = 0; $i < 5; $i++) {
            $kernel->handle($this->post('/connexion', [
                '_csrf' => $token,
                'email' => 'rate@example.com',
                'password' => 'wrong-password-12',
            ], $ip));
        }
        $blocked = $kernel->handle($this->post('/connexion', [
            '_csrf' => $token,
            'email' => 'rate@example.com',
            'password' => 'wrong-password-12',
        ], $ip));
        $this->assertSame(422, $blocked->status());
        $this->assertStringContainsString('Trop de tentatives', $blocked->content());
    }

    /**
     * @param list<string> $roleSlugs
     */
    private function createUser(string $email, string $password, array $roleSlugs, bool $active = true): int
    {
        $pdo = $this->pdo();
        $users = new UserRepository($pdo);
        $roles = new RoleRepository($pdo);
        $id = $users->insert([
            'full_name' => 'Compte test',
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => $active ? 1 : 0,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs($roleSlugs));

        return $id;
    }

    private function csrf(\Sesam\Core\Kernel $kernel, string $path = '/connexion'): string
    {
        $page = $kernel->handle(new Request('GET', $path));
        preg_match('/name="_csrf" value="([^"]+)"/', $page->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return (string) $matches[1];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, array $body, ?string $ip = null): Request
    {
        return new Request('POST', $path, [], $body, [], [], false, $ip ?? $this->ip());
    }

    private function email(): string
    {
        $email = 'p4-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->emails[] = $email;

        return $email;
    }

    private function ip(): string
    {
        $ip = '203.0.113.' . random_int(1, 250);
        $this->ips[] = $ip;

        return $ip;
    }

    private function pdo(): \PDO
    {
        return Connection::make($this->config()['db']);
    }

    /**
     * @return array{db: array{host: string, port: string, name: string, user: string, password: string}}
     */
    private function config(): array
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
        $config = require dirname(__DIR__) . '/config/app.php';

        return $config;
    }
}
