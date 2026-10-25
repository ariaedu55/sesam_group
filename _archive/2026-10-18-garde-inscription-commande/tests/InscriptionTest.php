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

final class InscriptionTest extends TestCase
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
            $pdo->prepare('DELETE FROM inscription_installments WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM inscription_enrollments WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM inscription_fee_types WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM inscription_classes WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM payments WHERE tenant_id = :id')->execute(['id' => $id]);
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

    public function testLoyerSectorCannotAccessInscription(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::LOYER);
        $email = $this->email('loy');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $this->assertSame(403, $kernel->handle(new Request('GET', '/inscription/dossiers'))->status());
    }

    public function testEnrollmentGeneratesInstallmentsAndPaymentLink(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::INSCRIPTION);
        $email = $this->email('ins');
        $this->createUser(Role::TENANT_OWNER, $tenant, $email);
        $this->login($kernel, $email);

        $classId = $this->createClass($kernel);
        $feeId = $this->createFeeType($kernel);
        $clientId = $this->createClient($kernel);
        $enrollmentId = $this->createEnrollment($kernel, $classId, $feeId, $clientId);

        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM inscription_installments WHERE tenant_id = :t AND enrollment_id = :e'
        );
        $statement->execute(['t' => $tenant->id(), 'e' => $enrollmentId]);
        $this->assertSame(3, (int) $statement->fetchColumn());

        $installmentId = (int) $this->pdo()->query(
            'SELECT id FROM inscription_installments WHERE enrollment_id = ' . $enrollmentId . ' ORDER BY due_date ASC LIMIT 1'
        )->fetchColumn();
        $this->assertGreaterThan(0, $installmentId);

        $pay = $kernel->handle($this->post(
            '/inscription/dossiers/' . $enrollmentId . '/tranches/' . $installmentId . '/encaisser',
            ['_csrf' => $this->csrf($kernel, '/inscription/dossiers/' . $enrollmentId)],
        ));
        $this->assertSame(302, $pay->status());
        $this->assertStringContainsString('/bac-a-sable', (string) ($pay->headers()['Location'] ?? ''));
    }

    private function createClass(\Sesam\Core\Kernel $kernel): int
    {
        $response = $kernel->handle($this->post('/inscription/classes', [
            '_csrf' => $this->csrf($kernel, '/inscription/classes/nouveau'),
            'name' => 'CM2 A',
        ]));
        $this->assertSame(302, $response->status());
        $statement = $this->pdo()->query('SELECT id FROM inscription_classes ORDER BY id DESC LIMIT 1');

        return (int) $statement->fetchColumn();
    }

    private function createFeeType(\Sesam\Core\Kernel $kernel): int
    {
        $response = $kernel->handle($this->post('/inscription/frais', [
            '_csrf' => $this->csrf($kernel, '/inscription/frais/nouveau'),
            'name' => 'Scolarité annuelle',
            'total_minor' => '300000',
            'installment_count' => '3',
        ]));
        $this->assertSame(302, $response->status());
        $statement = $this->pdo()->query('SELECT id FROM inscription_fee_types ORDER BY id DESC LIMIT 1');

        return (int) $statement->fetchColumn();
    }

    private function createClient(\Sesam\Core\Kernel $kernel): int
    {
        $response = $kernel->handle($this->post('/clients', [
            '_csrf' => $this->csrf($kernel, '/clients/nouveau'),
            'full_name' => 'Parent Test',
            'email' => 'par-' . bin2hex(random_bytes(2)) . '@example.com',
            'phone' => '+221771122334',
        ]));
        $this->assertSame(302, $response->status());
        preg_match('#/clients/(\d+)#', (string) ($response->headers()['Location'] ?? ''), $matches);
        $clientId = (int) ($matches[1] ?? 0);
        $this->clientIds[] = $clientId;

        return $clientId;
    }

    private function createEnrollment(\Sesam\Core\Kernel $kernel, int $classId, int $feeId, int $clientId): int
    {
        $response = $kernel->handle($this->post('/inscription/dossiers', [
            '_csrf' => $this->csrf($kernel, '/inscription/dossiers/nouveau'),
            'student_name' => 'Awa Diop',
            'class_id' => (string) $classId,
            'guardian_client_id' => (string) $clientId,
            'first_due_date' => gmdate('Y-m-01', strtotime('+1 month')),
            'fee_type_ids' => [(string) $feeId],
        ]));
        $this->assertSame(302, $response->status());
        preg_match('#/inscription/dossiers/(\d+)#', (string) ($response->headers()['Location'] ?? ''), $matches);

        return (int) ($matches[1] ?? 0);
    }

    private function createTenant(string $sector): \Sesam\Models\Tenant
    {
        $slug = 'p13-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Inscription test',
            'sector' => $sector,
            'status' => 'active',
            'display_name' => 'Inscription ' . $slug,
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
            'full_name' => 'Inscription user',
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
        return 'p13-' . $prefix . '-' . bin2hex(random_bytes(3)) . '@example.com';
    }

    private function pdo(): \PDO
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        return Connection::make((require dirname(__DIR__) . '/config/app.php')['db']);
    }
}
