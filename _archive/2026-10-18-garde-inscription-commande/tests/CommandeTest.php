<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Auth\Role;
use Sesam\Core\Bootstrap;
use Sesam\Core\Commande\OrderStatus;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class CommandeTest extends TestCase
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
            $pdo->prepare('DELETE FROM commande_order_measurements WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM commande_order_items WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM commande_deliveries WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM commande_orders WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM commande_products WHERE tenant_id = :id')->execute(['id' => $id]);
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

    public function testReservationSectorCannotAccessCommande(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::RESERVATION);
        $email = $this->email('res');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/commande/produits'))->status());
    }

    public function testOrderWorkflowAndMeasurement(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::COMMANDE);
        $email = $this->email('cmd');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $productId = $this->storeProduct($kernel, $tenant->id());
        $clientId = $this->createClientViaHttp($kernel);
        $orderId = $this->storeOrder($kernel, $clientId, $productId);

        $measure = $kernel->handle($this->post('/commande/commandes/' . $orderId . '/mesures', [
            '_csrf' => $this->csrf($kernel, '/commande/commandes/' . $orderId),
            'chest_cm' => '100',
            'waist_cm' => '80',
            'hips_cm' => '',
            'length_cm' => '140',
            'notes' => 'Première prise',
        ]));
        $this->assertSame(302, $measure->status());

        $statement = $this->pdo()->prepare('SELECT status FROM commande_orders WHERE id = :id');
        $statement->execute(['id' => $orderId]);
        $this->assertSame(OrderStatus::MEASUREMENTS, $statement->fetchColumn());

        $advance = $kernel->handle($this->post('/commande/commandes/' . $orderId . '/avancer', [
            '_csrf' => $this->csrf($kernel, '/commande/commandes/' . $orderId),
        ]));
        $this->assertSame(302, $advance->status());
        $statement->execute(['id' => $orderId]);
        $this->assertSame(OrderStatus::CONFECTION, $statement->fetchColumn());
    }

    public function testDepositRedirectsToSandbox(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::COMMANDE);
        $email = $this->email('dep');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $productId = $this->storeProduct($kernel, $tenant->id(), '3000');
        $clientId = $this->createClientViaHttp($kernel);
        $orderId = $this->storeOrder($kernel, $clientId, $productId);

        $deposit = $kernel->handle($this->post('/commande/commandes/' . $orderId . '/acompte', [
            '_csrf' => $this->csrf($kernel, '/commande/commandes/' . $orderId),
        ]));
        $this->assertSame(302, $deposit->status());
        $this->assertStringContainsString('/bac-a-sable', (string) ($deposit->headers()['Location'] ?? ''));
    }

    private function storeProduct(\Sesam\Core\Kernel $kernel, int $tenantId, string $deposit = '1000'): int
    {
        $response = $kernel->handle($this->post('/commande/produits', [
            '_csrf' => $this->csrf($kernel, '/commande/produits/nouveau'),
            'name' => 'Boubou',
            'price_minor' => '25000',
            'deposit_minor' => $deposit,
        ]));
        $this->assertSame(302, $response->status());
        $statement = $this->pdo()->prepare(
            'SELECT id FROM commande_products WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $id = (int) $statement->fetchColumn();
        $this->assertGreaterThan(0, $id);

        return $id;
    }

    private function storeOrder(\Sesam\Core\Kernel $kernel, int $clientId, int $productId): int
    {
        $create = $kernel->handle($this->post('/commande/commandes', [
            '_csrf' => $this->csrf($kernel, '/commande/commandes/nouveau'),
            'client_id' => (string) $clientId,
            'product_id' => (string) $productId,
            'quantity' => '1',
        ]));
        $this->assertSame(302, $create->status());
        preg_match('#/commande/commandes/(\d+)#', (string) ($create->headers()['Location'] ?? ''), $matches);
        $orderId = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan(0, $orderId);

        return $orderId;
    }

    private function createClientViaHttp(\Sesam\Core\Kernel $kernel): int
    {
        $create = $kernel->handle($this->post('/clients', [
            '_csrf' => $this->csrf($kernel, '/clients/nouveau'),
            'full_name' => 'Client tailleur',
            'email' => '',
            'phone' => '',
        ]));
        $this->assertSame(302, $create->status());
        preg_match('#/clients/(\d+)#', (string) ($create->headers()['Location'] ?? ''), $matches);
        $clientId = (int) ($matches[1] ?? 0);
        $this->clientIds[] = $clientId;

        return $clientId;
    }

    private function createTenant(string $sector): \Sesam\Models\Tenant
    {
        $slug = 'p11-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Cmd test',
            'sector' => $sector,
            'status' => 'active',
            'display_name' => 'Cmd ' . $slug,
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
            'full_name' => 'Cmd user',
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
        return 'p11-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
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
