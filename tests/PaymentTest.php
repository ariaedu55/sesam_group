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

final class PaymentTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<int> */
    private array $paymentIds = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->paymentIds as $id) {
            $pdo->prepare('DELETE FROM payment_transactions WHERE payment_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM payments WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        $pdo->exec('DELETE FROM payment_webhook_events');
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM notifications WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testSandboxPaymentFlow(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant();
        $email = $this->email('pay');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $create = $kernel->handle($this->post('/paiements', [
            '_csrf' => $this->csrf($kernel, '/paiements/nouveau'),
            'amount_minor' => '5000',
            'description' => 'Acompte test',
            'client_id' => '',
        ]));
        $this->assertSame(302, $create->status());
        $location = (string) ($create->headers()['Location'] ?? '');
        $this->assertStringContainsString('/bac-a-sable', $location);
        preg_match('#/paiements/(\d+)/#', $location, $matches);
        $paymentId = (int) ($matches[1] ?? 0);
        $this->paymentIds[] = $paymentId;

        $confirm = $kernel->handle($this->post('/paiements/' . $paymentId . '/bac-a-sable/confirmer', [
            '_csrf' => $this->csrf($kernel, '/paiements/' . $paymentId . '/bac-a-sable'),
        ]));
        $this->assertSame(302, $confirm->status());

        $show = $kernel->handle(new Request('GET', '/paiements/' . $paymentId));
        $this->assertSame(200, $show->status());
        $this->assertStringContainsString('Paiement reçu', $show->content());
    }

    public function testWebhookRejectedWithoutSecret(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $response = $kernel->handle(new Request(
            'POST',
            '/webhooks/naboopay',
            [],
            [],
            ['Content-Type' => 'application/json'],
            [],
            false,
            '127.0.0.1',
            '{"event_id":"evt1","reference":"abc","status":"success"}'
        ));
        $this->assertSame(503, $response->status());
    }

    public function testStaffCannotInitiatePayment(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant();
        $email = $this->email('staff-pay');
        $this->createUser(Role::STAFF, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/paiements/nouveau'))->status());
    }

    private function createTenant(): \Sesam\Models\Tenant
    {
        $slug = 'p8-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Pay test',
            'sector' => Sector::RESERVATION,
            'status' => 'active',
            'display_name' => 'Pay ' . $slug,
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
            'full_name' => 'Pay user',
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
        return 'p8-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
    }

    private function pdo(): \PDO
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
        $config = require dirname(__DIR__) . '/config/app.php';

        return Connection::make($config['db']);
    }
}
