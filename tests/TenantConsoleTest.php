<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Kernel;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Models\Tenant;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\TenantSubscriptionRepository;
use Sesam\Repositories\UserRepository;

final class TenantConsoleTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<string> */
    private array $ips = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM tenant_subscription_events WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_subscriptions WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->ips as $ip) {
            $pdo->prepare('DELETE FROM auth_attempts WHERE ip = :ip')->execute(['ip' => $ip]);
        }
    }

    public function testOnlySesamAdminsReachTheConsole(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant('rbac');
        $ownerEmail = $this->email('owner');
        $this->createUser([Role::TENANT_OWNER], $tenant->id(), $ownerEmail);
        $this->login($kernel, $ownerEmail);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/commerces/' . $tenant->id()))->status());
        $denied = $kernel->handle($this->post('/commerces/' . $tenant->id() . '/abonnement', [
            '_csrf' => $this->csrf($kernel, '/espace'),
            'action' => 'activate',
            'period_start' => '2026-10-01',
        ]));
        $this->assertSame(403, $denied->status());
        $this->assertNull((new TenantSubscriptionRepository($this->pdo()))->find($tenant->id()));

        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
        $commercialEmail = $this->email('commercial');
        $this->createUser([Role::COMMERCIAL], null, $commercialEmail);
        $this->login($kernel, $commercialEmail);
        $this->assertSame(403, $kernel->handle(new Request('GET', '/commerces'))->status());
    }

    public function testActivateRenewSuspendAndReactivate(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant('cycle', 'suspended');
        $this->loginAdmin($kernel);
        $path = '/commerces/' . $tenant->id();
        $repo = new TenantSubscriptionRepository($this->pdo());
        $tenants = new TenantRepository($this->pdo());

        $page = $kernel->handle(new Request('GET', $path));
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('Activer l\'abonnement', $page->content());

        $activated = $kernel->handle($this->post($path . '/abonnement', [
            '_csrf' => $this->csrf($kernel, $path),
            'action' => 'activate',
            'period_start' => '2026-01-31',
            'monthly_amount' => '25 000',
            'note' => 'Lancement',
        ]));
        $this->assertSame(302, $activated->status());
        $subscription = $repo->find($tenant->id());
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status());
        $this->assertSame('2026-02-27', $subscription->periodEnd());
        $this->assertSame(25000, $subscription->monthlyAmount());
        $this->assertSame('active', $tenants->findById($tenant->id())?->status());

        $overdue = $kernel->handle(new Request('GET', '/commerces', ['abonnement' => 'overdue']));
        $this->assertStringContainsString($tenant->slug(), $overdue->content());

        $today = gmdate('Y-m-d');
        $renewed = $kernel->handle($this->post($path . '/abonnement', [
            '_csrf' => $this->csrf($kernel, $path),
            'action' => 'renew',
        ]));
        $this->assertSame(302, $renewed->status());
        $subscription = $repo->find($tenant->id());
        $this->assertSame($today, $subscription?->periodStart());
        $this->assertGreaterThanOrEqual($today, (string) $subscription?->periodEnd());

        $again = $kernel->handle($this->post($path . '/abonnement', [
            '_csrf' => $this->csrf($kernel, $path),
            'action' => 'renew',
        ]));
        $this->assertSame(302, $again->status());
        $next = $repo->find($tenant->id());
        $this->assertSame($today, $next?->periodStart());
        $this->assertGreaterThan((string) $subscription?->periodEnd(), (string) $next?->periodEnd());
        $this->assertLessThanOrEqual(
            (new \DateTimeImmutable((string) $subscription?->periodEnd()))->modify('+1 month')->format('Y-m-d'),
            (string) $next?->periodEnd()
        );

        $suspended = $kernel->handle($this->post($path . '/abonnement', [
            '_csrf' => $this->csrf($kernel, $path),
            'action' => 'suspend',
            'note' => 'Impayé',
        ]));
        $this->assertSame(302, $suspended->status());
        $this->assertSame('suspended', $repo->find($tenant->id())?->status());
        $this->assertSame('suspended', $tenants->findById($tenant->id())?->status());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/t/' . $tenant->slug()))->status());

        $reactivated = $kernel->handle($this->post($path . '/abonnement', [
            '_csrf' => $this->csrf($kernel, $path),
            'action' => 'activate',
            'period_start' => $today,
            'monthly_amount' => '',
        ]));
        $this->assertSame(302, $reactivated->status());
        $this->assertSame(25000, $repo->find($tenant->id())?->monthlyAmount());
        $this->assertSame('active', $tenants->findById($tenant->id())?->status());

        $events = $repo->events($tenant->id());
        $this->assertSame(['activate', 'suspend', 'renew', 'renew', 'activate'], array_column($events, 'action'));
        $history = $kernel->handle(new Request('GET', $path));
        $this->assertStringContainsString('Impayé', $history->content());
        $this->assertStringContainsString('Lancement', $history->content());
    }

    public function testInvalidTransitionsAndInputsAreRejected(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant('invalid');
        $this->loginAdmin($kernel);
        $path = '/commerces/' . $tenant->id();
        $repo = new TenantSubscriptionRepository($this->pdo());

        foreach ([
            ['action' => 'renew'],
            ['action' => 'suspend'],
            ['action' => 'delete'],
            ['action' => 'activate', 'period_start' => '2026-02-30'],
            ['action' => 'activate', 'period_start' => '2026-10-01', 'monthly_amount' => '-5'],
            ['action' => 'activate', 'period_start' => '2026-10-01', 'note' => str_repeat('x', 181)],
        ] as $body) {
            $response = $kernel->handle($this->post($path . '/abonnement', ['_csrf' => $this->csrf($kernel, $path)] + $body));
            $this->assertSame(422, $response->status(), json_encode($body) ?: '');
        }
        $this->assertNull($repo->find($tenant->id()));

        $this->assertSame(404, $kernel->handle(new Request('GET', '/commerces/999999999'))->status());
    }

    public function testConsoleListFiltersBySectorAndSearch(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $boutique = $this->createTenant('filtre-boutique', 'active', Sector::BOUTIQUE);
        $loyer = $this->createTenant('filtre-loyer', 'active', Sector::LOYER);
        $this->loginAdmin($kernel);

        $all = $kernel->handle(new Request('GET', '/commerces'));
        $this->assertSame(200, $all->status());
        $this->assertStringContainsString('Console interne SESAM', $all->content());
        $this->assertStringContainsString('Par secteur', $all->content());

        $bySector = $kernel->handle(new Request('GET', '/commerces', ['secteur' => Sector::BOUTIQUE, 'q' => 'p16-filtre']));
        $this->assertStringContainsString($boutique->slug(), $bySector->content());
        $this->assertStringNotContainsString($loyer->slug(), $bySector->content());

        $search = $kernel->handle(new Request('GET', '/commerces', ['q' => $loyer->slug(), 'secteur' => 'inconnu']));
        $this->assertStringContainsString($loyer->slug(), $search->content());
        $this->assertStringNotContainsString($boutique->slug(), $search->content());

        $edit = $kernel->handle(new Request('GET', '/commerces/' . $boutique->id() . '/modifier'));
        $this->assertSame(200, $edit->status());
        $this->assertStringContainsString('Modifier le commerce', $edit->content());
    }

    private function loginAdmin(Kernel $kernel): void
    {
        $email = $this->email('admin');
        $this->createUser([Role::SUPER_ADMIN], null, $email);
        $this->login($kernel, $email);
    }

    private function createTenant(string $prefix, string $status = 'active', string $sector = Sector::RESERVATION): Tenant
    {
        $slug = 'p16-' . $prefix . '-' . bin2hex(random_bytes(3));
        $repo = new TenantRepository($this->pdo());
        $id = $repo->insert([
            'slug' => $slug,
            'name' => 'Commerce ' . $prefix,
            'sector' => $sector,
            'status' => $status,
            'display_name' => 'Marque ' . $prefix,
        ]);
        $this->tenantIds[] = $id;
        $tenant = $repo->findById($id);
        $this->assertInstanceOf(Tenant::class, $tenant);

        return $tenant;
    }

    /**
     * @param list<string> $roleSlugs
     */
    private function createUser(array $roleSlugs, ?int $tenantId, string $email): int
    {
        $roles = new RoleRepository($this->pdo());
        $id = (new UserRepository($this->pdo()))->insert([
            'tenant_id' => $tenantId,
            'full_name' => 'Test console',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs($roleSlugs));

        return $id;
    }

    private function login(Kernel $kernel, string $email): void
    {
        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ]));
        $this->assertSame(302, $login->status());
    }

    private function csrf(Kernel $kernel, string $path = '/connexion'): string
    {
        $page = $kernel->handle(new Request('GET', $path));
        preg_match('/name="_csrf" value="([^"]+)"/', $page->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return (string) $matches[1];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $path, array $body): Request
    {
        $ip = '198.51.100.' . random_int(1, 250);
        $this->ips[] = $ip;

        return new Request('POST', $path, [], $body, [], [], false, $ip);
    }

    private function email(string $prefix): string
    {
        $email = 'p16-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->emails[] = $email;

        return $email;
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
