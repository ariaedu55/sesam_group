<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Dashboard\SectorDashboard;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class DashboardTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM payment_transactions WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM payments WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM notifications WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testSectorDashboardConfigForLoyer(): void
    {
        $config = SectorDashboard::config(Sector::LOYER);
        $this->assertSame('Échéances du jour', $config['kpis'][0]['label'] ?? '');
        $this->assertSame('/immo/baux/nouveau', $config['quickActions'][0]['href'] ?? '');
        $this->assertSame('loyer.leases', $config['quickActions'][0]['permission'] ?? '');
    }

    public function testTenantDashboardShowsSectorKpisAndEmptyActivity(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::LOYER);
        $email = $this->email('dash');
        $this->createUser(Role::MANAGER, $tenant, $email);
        $this->login($kernel, $email);

        $home = $kernel->handle(new Request('GET', '/espace'));
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString('Bonjour', $home->content());
        $this->assertStringContainsString($tenant->displayName(), $home->content());
        // Un espace Immo a son propre accueil : loyers du mois, impayés, occupation, alertes.
        $html = html_entity_decode($home->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Encaisser un loyer', $html);
        $this->assertStringContainsString('Impayés', $html);
        $this->assertStringContainsString('Occupation', $html);
        $this->assertStringContainsString('À traiter', $html);
        $this->assertStringContainsString('/immo/baux/nouveau', $html);
        $this->assertStringContainsString('/immo/loyers', $html);
        $this->assertStringContainsString('aria-label="SESAM"', $home->content());
        $this->assertStringContainsString('data-sidebar-close', $home->content());
        $this->assertStringContainsString('data-sidebar-scrim', $home->content());
    }

    public function testStaffHomeRemainsInternalLanding(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $email = $this->email('staff');
        $this->createUser(Role::COMMERCIAL, null, $email);
        $this->login($kernel, $email);

        $home = $kernel->handle(new Request('GET', '/espace'));
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString('Personnel SESAM', $home->content());
        $this->assertStringNotContainsString('Actions rapides', $home->content());
    }

    public function testNotificationsAndSearchAreTenantOnlyShell(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::RESERVATION);
        $email = $this->email('shell');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $notifications = $kernel->handle(new Request('GET', '/notifications'));
        $this->assertSame(200, $notifications->status());
        $this->assertStringContainsString('aucune notification', strtolower($notifications->content()));

        $search = $kernel->handle(new Request('GET', '/recherche', ['q' => 'shell']));
        $this->assertSame(200, $search->status());

        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));

        $staffEmail = $this->email('staff-notif');
        $this->createUser(Role::ADMIN, null, $staffEmail);
        $this->login($kernel, $staffEmail);
        // Le personnel SESAM a sa propre boîte de notifications (sans les envois WhatsApp réservés aux commerces).
        $this->assertSame(200, $kernel->handle(new Request('GET', '/notifications'))->status());
        $this->assertSame(200, $kernel->handle(new Request('GET', '/notifications/reglages'))->status());
        $this->assertSame(403, $kernel->handle(new Request('GET', '/notifications/envoyer'))->status());
        $this->assertSame(200, $kernel->handle(new Request('GET', '/profil'))->status());
    }

    private function createTenant(string $sector): \Sesam\Models\Tenant
    {
        $slug = 'p6-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Commerce test',
            'sector' => $sector,
            'status' => 'active',
            'display_name' => 'Vitrine ' . $slug,
        ]);
        $this->tenantIds[] = $id;
        $tenant = (new TenantRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(\Sesam\Models\Tenant::class, $tenant);

        return $tenant;
    }

    private function createUser(string $role, ?\Sesam\Models\Tenant $tenant, string $email): void
    {
        $users = new UserRepository($this->pdo());
        $roles = new RoleRepository($this->pdo());
        $id = $users->insert([
            'tenant_id' => $tenant?->id(),
            'full_name' => 'Test user',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs([$role]));
    }

    private function login(\Sesam\Core\Kernel $kernel, string $email): void
    {
        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ]));
        $this->assertSame(302, $login->status());
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
    private function post(string $path, array $body): Request
    {
        return new Request('POST', $path, [], $body, [], [], false, '198.51.100.' . random_int(1, 250));
    }

    private function email(string $prefix): string
    {
        $email = 'p6-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->emails[] = $email;

        return $email;
    }

    private function pdo(): \PDO
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
        $config = require dirname(__DIR__) . '/config/app.php';

        return Connection::make($config['db']);
    }
}
