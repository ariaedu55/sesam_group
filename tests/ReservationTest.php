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

final class ReservationTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<int> */
    private array $clientIds = [];

    /** @var list<int> */
    private array $paymentIds = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->paymentIds as $id) {
            $pdo->prepare('DELETE FROM payment_transactions WHERE payment_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM payments WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM message_reminders WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_appointments WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_staff_availability WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_staff WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_services WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_categories WHERE tenant_id = :id')->execute(['id' => $id]);
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

    public function testNonReservationSectorGetsForbidden(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::BOUTIQUE);
        $email = $this->email('cmd');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/reservation/prestations'))->status());
    }

    public function testAppointmentOverlapIsRejected(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::RESERVATION);
        $email = $this->email('rdv');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $clientId = $this->createClientViaHttp($kernel);
        $serviceId = $this->storePrestation($kernel, $tenant->id());
        $staffId = $this->storeStaffWithAvailability($kernel);

        $startsAt = $this->futureSlotUtc(2, 10, 0);
        $payload = [
            '_csrf' => $this->csrf($kernel, '/reservation/rendez-vous/nouveau'),
            'client_id' => (string) $clientId,
            'service_id' => (string) $serviceId,
            'staff_id' => (string) $staffId,
            'starts_at' => $startsAt,
            'notes' => '',
        ];

        $first = $kernel->handle($this->post('/reservation/rendez-vous', $payload));
        $this->assertSame(302, $first->status());

        $second = $kernel->handle($this->post('/reservation/rendez-vous', $payload));
        $this->assertSame(422, $second->status());
        $this->assertStringContainsString('déjà réservé', $second->content());
    }

    public function testDepositLinksPaymentSandbox(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::RESERVATION);
        $email = $this->email('dep');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $clientId = $this->createClientViaHttp($kernel);
        $serviceId = $this->storePrestation($kernel, $tenant->id(), depositMinor: '2000');
        $staffId = $this->storeStaffWithAvailability($kernel);
        $startsAt = $this->futureSlotUtc(3, 11, 0);

        $create = $kernel->handle($this->post('/reservation/rendez-vous', [
            '_csrf' => $this->csrf($kernel, '/reservation/rendez-vous/nouveau'),
            'client_id' => (string) $clientId,
            'service_id' => (string) $serviceId,
            'staff_id' => (string) $staffId,
            'starts_at' => $startsAt,
            'notes' => '',
        ]));
        $this->assertSame(302, $create->status());
        preg_match('#/reservation/rendez-vous/(\d+)#', (string) ($create->headers()['Location'] ?? ''), $matches);
        $appointmentId = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan(0, $appointmentId);

        $deposit = $kernel->handle($this->post('/reservation/rendez-vous/' . $appointmentId . '/acompte', [
            '_csrf' => $this->csrf($kernel, '/reservation/rendez-vous/' . $appointmentId),
        ]));
        $this->assertSame(302, $deposit->status());
        $this->assertStringContainsString('/bac-a-sable', (string) ($deposit->headers()['Location'] ?? ''));
    }

    private function storePrestation(\Sesam\Core\Kernel $kernel, int $tenantId, string $depositMinor = '0'): int
    {
        $response = $kernel->handle($this->post('/reservation/prestations', [
            '_csrf' => $this->csrf($kernel, '/reservation/prestations/nouveau'),
            'name' => 'Coupe',
            'duration_minutes' => '30',
            'price_minor' => '10000',
            'deposit_minor' => $depositMinor,
        ]));
        $this->assertSame(302, $response->status());
        $statement = $this->pdo()->prepare(
            'SELECT id FROM reservation_services WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $id = (int) $statement->fetchColumn();
        $this->assertGreaterThan(0, $id);

        return $id;
    }

    private function storeStaffWithAvailability(\Sesam\Core\Kernel $kernel): int
    {
        $create = $kernel->handle($this->post('/reservation/equipe', [
            '_csrf' => $this->csrf($kernel, '/reservation/equipe/nouveau'),
            'name' => 'Fatou',
        ]));
        $this->assertSame(302, $create->status());
        preg_match('#/reservation/equipe/(\d+)/disponibilites#', (string) ($create->headers()['Location'] ?? ''), $matches);
        $staffId = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan(0, $staffId);

        $availability = [];
        for ($day = 1; $day <= 7; ++$day) {
            $availability['day_' . $day . '_start'] = '08:00';
            $availability['day_' . $day . '_end'] = '20:00';
        }
        $save = $kernel->handle($this->post('/reservation/equipe/' . $staffId . '/disponibilites', [
            '_csrf' => $this->csrf($kernel, '/reservation/equipe/' . $staffId . '/disponibilites'),
        ] + $availability));
        $this->assertSame(302, $save->status());

        return $staffId;
    }

    private function createClientViaHttp(\Sesam\Core\Kernel $kernel): int
    {
        $create = $kernel->handle($this->post('/clients', [
            '_csrf' => $this->csrf($kernel, '/clients/nouveau'),
            'full_name' => 'Client RDV',
            'email' => '',
            'phone' => '',
        ]));
        $this->assertSame(302, $create->status());
        preg_match('#/clients/(\d+)#', (string) ($create->headers()['Location'] ?? ''), $matches);
        $clientId = (int) ($matches[1] ?? 0);
        $this->clientIds[] = $clientId;

        return $clientId;
    }

    /** Jours à partir d'aujourd'hui (UTC), heure HH:MM pour datetime-local */
    private function futureSlotUtc(int $daysAhead, int $hour, int $minute): string
    {
        $timestamp = strtotime('+' . $daysAhead . ' days', strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC'));
        $this->assertNotFalse($timestamp);

        return gmdate('Y-m-d', $timestamp) . sprintf('T%02d:%02d', $hour, $minute);
    }

    private function createTenant(string $sector): \Sesam\Models\Tenant
    {
        $slug = 'p10-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Res test',
            'sector' => $sector,
            'status' => 'active',
            'display_name' => 'Res ' . $slug,
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
            'full_name' => 'Res user',
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
        return 'p10-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
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
