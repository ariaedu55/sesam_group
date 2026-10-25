<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\HttpException;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;
use Sesam\Services\ClientService;

final class CrmTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<int> */
    private array $clientIds = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->clientIds as $id) {
            $pdo->prepare('DELETE FROM client_activities WHERE client_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM client_notes WHERE client_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM client_tags_map WHERE client_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM clients WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM client_tags WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM notifications WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testTenantOwnerCanCreateClientAndStaffCanAddNote(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant();
        $ownerEmail = $this->email('owner');
        $staffEmail = $this->email('staff');
        $this->createUser(Role::TENANT_OWNER, $tenant, $ownerEmail);
        $this->createUser(Role::STAFF, $tenant, $staffEmail);
        $this->login($kernel, $ownerEmail);

        $create = $kernel->handle($this->post('/clients', [
            '_csrf' => $this->csrf($kernel, '/clients/nouveau'),
            'full_name' => 'Awa Diop',
            'email' => 'awa.client@example.com',
            'phone' => '+221771234567',
            'new_tags' => 'VIP',
        ]));
        $this->assertSame(302, $create->status());
        $location = (string) ($create->headers()['Location'] ?? '');
        $this->assertStringContainsString('/clients/', $location);
        preg_match('#/clients/(\d+)#', $location, $matches);
        $clientId = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan(0, $clientId);
        $this->clientIds[] = $clientId;

        $show = $kernel->handle(new Request('GET', '/clients/' . $clientId));
        $this->assertSame(200, $show->status());
        $this->assertStringContainsString('Awa Diop', $show->content());
        $this->assertStringContainsString('VIP', $show->content());

        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
        $this->login($kernel, $staffEmail);

        $note = $kernel->handle($this->post('/clients/' . $clientId . '/notes', [
            '_csrf' => $this->csrf($kernel, '/clients/' . $clientId),
            'body' => 'Première visite confirmée.',
        ]));
        $this->assertSame(302, $note->status());

        $detail = $kernel->handle(new Request('GET', '/clients/' . $clientId));
        $this->assertStringContainsString('Première visite confirmée.', $detail->content());
    }

    public function testStaffCannotCreateClient(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant();
        $staffEmail = $this->email('staff-only');
        $this->createUser(Role::STAFF, $tenant, $staffEmail);
        $this->login($kernel, $staffEmail);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/clients/nouveau'))->status());
    }

    public function testClientIsolationBetweenTenants(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $ownerA = $this->createUser(Role::TENANT_OWNER, $tenantA, $this->email('iso-a'));
        $context = new TenantContext();
        $context->bind($tenantA);
        $service = new ClientService($this->config()['db'], $context);
        $clientId = $service->create($this->actor($ownerA), [
            'full_name' => 'Client A',
            'email' => '',
            'phone' => '',
        ]);
        $this->clientIds[] = $clientId;

        $ownerB = $this->createUser(Role::TENANT_OWNER, $tenantB, $this->email('iso-b'));
        $contextB = new TenantContext();
        $contextB->bind($tenantB);
        $serviceB = new ClientService($this->config()['db'], $contextB);

        try {
            $serviceB->find($this->actor($ownerB), $clientId);
            $this->fail('Le tenant B ne doit pas lire le client du tenant A.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->status());
        }
    }

    public function testSesamStaffGetsForbiddenOnClients(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $email = $this->email('admin-crm');
        $this->createUser(Role::ADMIN, null, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/clients'))->status());
    }

    private function createTenant(): \Sesam\Models\Tenant
    {
        $slug = 'p7-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'CRM test',
            'sector' => Sector::RESERVATION,
            'status' => 'active',
            'display_name' => 'CRM ' . $slug,
        ]);
        $this->tenantIds[] = $id;
        $tenant = (new TenantRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(\Sesam\Models\Tenant::class, $tenant);

        return $tenant;
    }

    private function createUser(string $role, ?\Sesam\Models\Tenant $tenant, string $email): \Sesam\Models\User
    {
        $users = new UserRepository($this->pdo());
        $roles = new \Sesam\Repositories\RoleRepository($this->pdo());
        $id = $users->insert([
            'tenant_id' => $tenant?->id(),
            'full_name' => 'Test CRM',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs([$role]));
        $this->emails[] = $email;
        $user = $users->findById($id);
        $this->assertInstanceOf(\Sesam\Models\User::class, $user);

        return $user;
    }

    private function actor(\Sesam\Models\User $user): \Sesam\Models\User
    {
        $loaded = (new UserRepository($this->pdo()))->findById($user->id());
        $this->assertInstanceOf(\Sesam\Models\User::class, $loaded);

        return $loaded;
    }

    private function login(\Sesam\Core\Kernel $kernel, string $email): void
    {
        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ], '198.51.100.' . random_int(1, 250)));
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
    private function post(string $path, array $body, string $ip = '198.51.100.42'): Request
    {
        return new Request('POST', $path, [], $body, [], [], false, $ip);
    }

    private function email(string $prefix): string
    {
        return 'p7-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
    }

    /** @return array{db: array{host: string, port: string, name: string, user: string, password: string}} */
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
