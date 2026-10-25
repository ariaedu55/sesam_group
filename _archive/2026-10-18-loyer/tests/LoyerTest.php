<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class LoyerTest extends TestCase
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
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM loyer_rent_dues WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM loyer_leases WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM loyer_units WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM loyer_properties WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM message_deliveries WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM notifications WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
        }
        foreach ($this->clientIds as $id) {
            $pdo->prepare('DELETE FROM client_activities WHERE client_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM clients WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testBoutiqueSectorCannotAccessLoyer(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::BOUTIQUE);
        $email = $this->email('cmd');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/loyer/biens'))->status());
    }

    public function testLeaseGeneratesTwelveDuesAndPaymentLink(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::LOYER);
        $email = $this->email('loy');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $propertyId = $this->createProperty($kernel);
        $unitId = $this->createUnit($kernel, $propertyId);
        $clientId = $this->createClient($kernel, '+221771112233');
        $leaseId = $this->createLease($kernel, $clientId, $unitId);

        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM loyer_rent_dues WHERE tenant_id = :t AND lease_id = :l'
        );
        $statement->execute(['t' => $tenant->id(), 'l' => $leaseId]);
        $this->assertSame(12, (int) $statement->fetchColumn());

        $dueId = (int) $this->pdo()->query(
            'SELECT id FROM loyer_rent_dues WHERE lease_id = ' . $leaseId . ' ORDER BY due_date ASC LIMIT 1'
        )->fetchColumn();
        $this->assertGreaterThan(0, $dueId);

        $pay = $kernel->handle($this->post('/loyer/echeances/' . $dueId . '/encaisser', [
            '_csrf' => $this->csrf($kernel, '/loyer/echeances/' . $dueId),
        ]));
        $this->assertSame(302, $pay->status());
        $sandbox = (string) ($pay->headers()['Location'] ?? '');
        $this->assertStringContainsString('/bac-a-sable', $sandbox);

        // Paiement simulé confirmé : l'échéance est soldée tout de suite et le reçu l'affiche.
        $sandboxPath = (string) parse_url($sandbox, PHP_URL_PATH);
        $confirm = $kernel->handle($this->post($sandboxPath . '/confirmer', ['_csrf' => $this->csrf($kernel, $sandboxPath)]));
        $this->assertSame(302, $confirm->status());
        $statement = $this->pdo()->prepare('SELECT status FROM loyer_rent_dues WHERE id = ?');
        $statement->execute([$dueId]);
        $this->assertSame('paid', $statement->fetchColumn());
        $receipt = $kernel->handle(new \Sesam\Core\Request\Request('GET', (string) ($confirm->headers()['Location'] ?? '')));
        $this->assertStringContainsString('Paiement reçu', $receipt->content());
    }

    private function createProperty(\Sesam\Core\Kernel $kernel): int
    {
        $response = $kernel->handle($this->post('/loyer/biens', [
            '_csrf' => $this->csrf($kernel, '/loyer/biens/nouveau'),
            'name' => 'Immeuble A',
            'address' => 'Dakar',
        ]));
        $this->assertSame(302, $response->status());
        preg_match('#/loyer/biens/(\d+)#', (string) ($response->headers()['Location'] ?? ''), $matches);

        return (int) ($matches[1] ?? 0);
    }

    private function createUnit(\Sesam\Core\Kernel $kernel, int $propertyId): int
    {
        $response = $kernel->handle($this->post('/loyer/biens/' . $propertyId . '/unites', [
            '_csrf' => $this->csrf($kernel, '/loyer/biens/' . $propertyId),
            'label' => 'Appt 1',
            'default_rent_minor' => '150000',
        ]));
        $this->assertSame(302, $response->status());
        $statement = $this->pdo()->prepare(
            'SELECT id FROM loyer_units WHERE property_id = :p ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['p' => $propertyId]);

        return (int) $statement->fetchColumn();
    }

    private function createClient(\Sesam\Core\Kernel $kernel, string $phone): int
    {
        $response = $kernel->handle($this->post('/clients', [
            '_csrf' => $this->csrf($kernel, '/clients/nouveau'),
            'full_name' => 'Locataire Test',
            'email' => 'loc-' . bin2hex(random_bytes(2)) . '@example.com',
            'phone' => $phone,
        ]));
        $this->assertSame(302, $response->status());
        preg_match('#/clients/(\d+)#', (string) ($response->headers()['Location'] ?? ''), $matches);
        $clientId = (int) ($matches[1] ?? 0);
        $this->clientIds[] = $clientId;

        return $clientId;
    }

    private function createLease(\Sesam\Core\Kernel $kernel, int $clientId, int $unitId): int
    {
        $starts = gmdate('Y-m-01', strtotime('+1 month'));
        $response = $kernel->handle($this->post('/loyer/contrats', [
            '_csrf' => $this->csrf($kernel, '/loyer/contrats/nouveau'),
            'client_id' => (string) $clientId,
            'unit_id' => (string) $unitId,
            'rent_minor' => '150000',
            'due_day' => '5',
            'starts_on' => $starts,
        ]));
        $this->assertSame(302, $response->status());
        preg_match('#/loyer/contrats/(\d+)#', (string) ($response->headers()['Location'] ?? ''), $matches);

        return (int) ($matches[1] ?? 0);
    }

    private function createTenant(string $sector): \Sesam\Models\Tenant
    {
        $slug = 'p12-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Loyer test',
            'sector' => $sector,
            'status' => 'active',
            'display_name' => 'Loyer ' . $slug,
        ]);
        $this->tenantIds[] = $id;
        $tenant = (new TenantRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(\Sesam\Models\Tenant::class, $tenant);

        return $tenant;
    }

    private function createUser(string $role, \Sesam\Models\Tenant $tenant, string $email): void
    {
        $users = new UserRepository($this->pdo());
        $roles = new \Sesam\Repositories\RoleRepository($this->pdo());
        $id = $users->insert([
            'tenant_id' => $tenant->id(),
            'full_name' => 'Loyer user',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs([$role]));
        $this->emails[] = $email;
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
        return 'p12-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
    }

    private function pdo(): \PDO
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        return Connection::make((require dirname(__DIR__) . '/config/app.php')['db']);
    }
}
