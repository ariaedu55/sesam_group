<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Security\SecurityHeaders;
use Sesam\Core\Session\Session;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;
use Sesam\Services\QuoteService;

final class SecurityHardeningTest extends TestCase
{
    private const QUOTE_IP = '192.0.2.31';

    /** @var list<string> */
    private array $emails = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        $pdo->prepare("DELETE FROM contact_requests WHERE email LIKE 'p19-%@example.com'")->execute();
        $pdo->prepare('DELETE FROM auth_attempts WHERE ip IN (:quote, :login)')->execute(['quote' => self::QUOTE_IP, 'login' => '192.0.2.32']);
    }

    public function testEveryResponseCarriesTheSecurityHeadersIncludingErrors(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $responses = [
            'page' => $kernel->handle(new Request('GET', '/')),
            'not-found' => $kernel->handle(new Request('GET', '/page-inexistante-p19')),
            'csrf' => $kernel->handle(new Request('POST', '/devis', [], ['full_name' => 'x'])),
            'redirect' => $kernel->handle(new Request('GET', '/espace')),
        ];

        $this->assertSame([200, 404, 419, 302], array_values(array_map(static fn (Response $r): int => $r->status(), $responses)));
        foreach ($responses as $label => $response) {
            $headers = $response->headers();
            $this->assertSame(SecurityHeaders::CONTENT_SECURITY_POLICY, $headers['Content-Security-Policy'] ?? null, $label);
            $this->assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null, $label);
            $this->assertSame('DENY', $headers['X-Frame-Options'] ?? null, $label);
            $this->assertSame('no-store', $headers['Cache-Control'] ?? null, $label);
            $this->assertArrayHasKey('Permissions-Policy', $headers, $label);
            $this->assertArrayNotHasKey('Strict-Transport-Security', $headers, $label);
        }
        $this->assertStringContainsString("script-src 'self';", SecurityHeaders::CONTENT_SECURITY_POLICY);
        $this->assertStringContainsString("frame-ancestors 'none'", SecurityHeaders::CONTENT_SECURITY_POLICY);
    }

    public function testHstsOnlyOverHttpsAndExplicitCacheControlIsKept(): void
    {
        $headers = new SecurityHeaders();
        $secure = $headers->apply(new Request('GET', '/', [], [], [], [], true), Response::html('ok'));
        $this->assertStringStartsWith('max-age=31536000', $secure->headers()['Strict-Transport-Security'] ?? '');

        $image = new Response('img', 200, ['Cache-Control' => 'private, max-age=86400']);
        $this->assertSame('private, max-age=86400', $headers->apply(new Request('GET', '/'), $image)->headers()['Cache-Control']);
    }

    public function testViewsContainNoInlineScriptForTheContentSecurityPolicy(): void
    {
        $offenders = [];
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__) . '/app/Views', \FilesystemIterator::SKIP_DOTS));
        foreach ($views as $file) {
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/\son(click|submit|change|load|input|error|mouseover)\s*=|javascript:|<script(?![^>]*\ssrc=)/i', $source) === 1) {
                $offenders[] = $file->getFilename();
            }
        }

        $this->assertSame([], $offenders);
    }

    public function testRequestsShareOneDatabaseConnection(): void
    {
        $config = $this->config();

        $this->assertSame(Connection::make($config['db']), Connection::make($config['db']));
    }

    public function testIdleSessionIsEmptiedAfterTwoHours(): void
    {
        $session = new Session();
        $this->assertFalse($session->expireIdle(1_000_000));
        $session->put('auth_user_id', 42);

        $this->assertFalse($session->expireIdle(1_000_000 + Session::IDLE_SECONDS));
        $this->assertSame(42, $session->get('auth_user_id'));
        $this->assertTrue($session->expireIdle(1_000_000 + 2 * Session::IDLE_SECONDS + 1));
        $this->assertNull($session->get('auth_user_id'));
    }

    public function testCsrfTokenIsRenewedAtLogin(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $email = 'p19-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->emails[] = $email;
        $pdo = $this->pdo();
        $users = new UserRepository($pdo);
        $roles = new RoleRepository($pdo);
        $id = $users->insert([
            'full_name' => 'Compte p19',
            'email' => $email,
            'password_hash' => password_hash('Motdepasse-p19!', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs(['admin']));

        $before = $this->csrf($kernel, '/connexion');
        $login = $kernel->handle(new Request('POST', '/connexion', [], [
            '_csrf' => $before,
            'email' => $email,
            'password' => 'Motdepasse-p19!',
        ], [], [], false, '192.0.2.32'));
        $this->assertSame(302, $login->status());

        $after = $this->csrf($kernel, '/profil');
        $this->assertNotSame($before, $after);
        $stale = $kernel->handle(new Request('POST', '/deconnexion', [], ['_csrf' => $before]));
        $this->assertSame(419, $stale->status());
    }

    public function testQuoteFormIsRateLimitedPerIp(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $token = $this->csrf($kernel, '/devis');
        $statuses = [];
        for ($i = 0; $i <= QuoteService::RATE_MAX; ++$i) {
            $statuses[] = $kernel->handle(new Request('POST', '/devis', [], [
                '_csrf' => $token,
                'full_name' => 'Test p19',
                'email' => 'p19-' . $i . '@example.com',
                'phone' => '770000000',
                'activity' => 'autre',
                'message' => 'Demande de test.',
                'site_state' => 'nouveau',
                'site_type' => 'vitrine',
            ], [], [], false, self::QUOTE_IP))->status();
        }

        $this->assertSame(array_merge(array_fill(0, QuoteService::RATE_MAX, 200), [429]), $statuses);
        $stored = $this->pdo()->query("SELECT COUNT(*) FROM contact_requests WHERE email LIKE 'p19-%@example.com'")->fetchColumn();
        $this->assertSame(QuoteService::RATE_MAX, (int) $stored);
    }

    private function csrf(\Sesam\Core\Kernel $kernel, string $path): string
    {
        $page = $kernel->handle(new Request('GET', $path));
        preg_match('/name="_csrf" value="([^"]+)"/', $page->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, $path);

        return (string) $matches[1];
    }

    /**
     * @return array{db: array{host: string, port: string, name: string, user: string, password: string}}
     */
    private function config(): array
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        return require dirname(__DIR__) . '/config/app.php';
    }

    private function pdo(): \PDO
    {
        return Connection::make($this->config()['db']);
    }
}
