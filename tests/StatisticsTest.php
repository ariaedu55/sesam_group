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
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

final class StatisticsTest extends TestCase
{
    private const PREFIX = 'p18-';

    /** @var list<string> */
    private array $emails = [];

    /** @var list<int> */
    private array $tenantIds = [];

    /** @var list<string> */
    private array $ips = [];

    private ?\PDO $connection = null;

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->emails as $email) {
            $pdo->prepare('DELETE FROM users WHERE email = :email')->execute(['email' => $email]);
        }
        foreach ($this->tenantIds as $id) {
            foreach ([
                'boutique_order_items',
                'boutique_orders',
                'boutique_products',
                'reservation_appointments',
                'reservation_services',
                'loyer_rent_dues',
                'loyer_leases',
                'loyer_units',
                'loyer_properties',
                'payments',
                'client_activities',
                'clients',
                'tenant_settings',
            ] as $table) {
                $pdo->prepare('DELETE FROM ' . $table . ' WHERE tenant_id = :id')->execute(['id' => $id]);
            }
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
        foreach ($this->ips as $ip) {
            $pdo->prepare('DELETE FROM auth_attempts WHERE ip = :ip')->execute(['ip' => $ip]);
        }
        $this->connection = null;
    }

    public function testStatisticsAreReservedToTheCommerceOwnerAndManager(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $this->assertSame(302, $kernel->handle(new Request('GET', '/statistiques'))->status());

        $tenantId = $this->createTenant(Sector::RESERVATION);
        $this->loginAs($kernel, [Role::STAFF], $tenantId);
        $this->assertSame(403, $kernel->handle(new Request('GET', '/statistiques'))->status());
        $this->assertStringNotContainsString('title="Statistiques"', $kernel->handle(new Request('GET', '/espace'))->content());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::SUPER_ADMIN]);
        $this->assertSame(403, $kernel->handle(new Request('GET', '/statistiques'))->status());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::MANAGER], $tenantId);
        $page = $kernel->handle(new Request('GET', '/statistiques'));
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('aria-current="page" title="Statistiques"', $page->content());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $this->assertStringContainsString('title="Statistiques"', $kernel->handle(new Request('GET', '/espace'))->content());
        $fallback = $kernel->handle(new Request('GET', '/statistiques', ['periode' => '7j']));
        $this->assertSame(200, $fallback->status());
        $this->assertStringContainsString('Vos chiffres sur 30 jours', $fallback->content());
        $this->assertStringContainsString('Aucun encaissement sur les deux périodes', $fallback->content());
    }

    public function testRevenueConversionClientsAndReservationsStayInsideTheTenant(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenantId = $this->createTenant(Sector::RESERVATION);
        $otherId = $this->createTenant(Sector::RESERVATION);
        $faithful = $this->insertClient($tenantId, 'Awa', 0);
        $older = $this->insertClient($tenantId, 'Binta', 40);
        $this->insertPayment($tenantId, $faithful, 10000, 'success', 0);
        $this->insertPayment($tenantId, $faithful, 5000, 'success', 10);
        $this->insertPayment($tenantId, $older, 3000, 'success', 5);
        $this->insertPayment($tenantId, null, 7000, 'pending', 2);
        $this->insertPayment($tenantId, null, 4000, 'failed', 3);
        $this->insertPayment($tenantId, null, 20000, 'success', 45);
        $this->insertPayment($otherId, $this->insertClient($otherId, 'Autre', 0), 999999, 'success', 0);

        $serviceId = $this->insert('reservation_services', [
            'tenant_id' => $tenantId,
            'name' => 'Tresses',
            'duration_minutes' => 60,
            'price_minor' => 15000,
            'deposit_minor' => 0,
            'is_active' => 1,
            'created_at' => $this->ago(60),
            'updated_at' => $this->ago(60),
        ]);
        foreach ([[3, 'completed'], [4, 'completed'], [6, 'cancelled'], [0, 'pending'], [50, 'completed']] as [$days, $status]) {
            $this->insert('reservation_appointments', [
                'tenant_id' => $tenantId,
                'client_id' => $faithful,
                'service_id' => $serviceId,
                'starts_at' => $this->ago($days, '01:00:00'),
                'duration_minutes' => 60,
                'status' => $status,
                'deposit_amount_minor' => 0,
                'created_at' => $this->ago($days),
                'updated_at' => $this->ago($days),
            ]);
        }

        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $page = $kernel->handle(new Request('GET', '/statistiques'));
        $this->assertSame(200, $page->status());
        $html = $page->content();
        $this->assertStringContainsString('<strong>18 000 FCFA</strong>', $html);
        $this->assertStringContainsString('−10 % par rapport à la période précédente', $html);
        $this->assertStringContainsString('<strong>60 %</strong>', $html);
        $this->assertStringContainsString('3 payés sur 5 demandés', $html);
        $this->assertStringContainsString('<strong>6 000 FCFA</strong>', $html);
        $this->assertStringContainsString('<strong>7 000 FCFA</strong>', $html);
        $this->assertStringContainsString('1 paiement non réglé', $html);
        $this->assertStringNotContainsString('999 999', $html);
        $this->assertFigure($html, 'Nouveaux', '1');
        $this->assertFigure($html, 'Actifs', '2');
        $this->assertFigure($html, 'Payeurs', '2');
        $this->assertFigure($html, 'Fidèles', '1');
        $this->assertFigure($html, 'Rendez-vous', '4');
        $this->assertFigure($html, 'Taux de confirmation', '50 %');
        $this->assertFigure($html, 'Annulations', '25 %');
        $this->assertStringContainsString('3 rdv', $html);
        $this->assertStringContainsString('45 000 FCFA au tarif', $html);

        $quarter = $kernel->handle(new Request('GET', '/statistiques', ['periode' => '90j']))->content();
        $this->assertStringContainsString('<strong>38 000 FCFA</strong>', $quarter);
        $this->assertStringContainsString('Aucun encaissement sur la période précédente', $quarter);
        $this->assertFigure($quarter, 'Rendez-vous', '5');
        $this->assertStringContainsString('aria-current="page">90 jours', $quarter);
    }

    public function testRentRecoveryAndOverdueDues(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenantId = $this->createTenant(Sector::LOYER);
        $clientId = $this->insertClient($tenantId, 'Locataire', 90);
        $propertyId = $this->insert('loyer_properties', [
            'tenant_id' => $tenantId,
            'name' => 'Immeuble',
            'created_at' => $this->ago(90),
            'updated_at' => $this->ago(90),
        ]);
        $unitId = $this->insert('loyer_units', [
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'label' => 'A1',
            'default_rent_minor' => 100000,
            'is_active' => 1,
            'created_at' => $this->ago(90),
            'updated_at' => $this->ago(90),
        ]);
        $leaseId = $this->insert('loyer_leases', [
            'tenant_id' => $tenantId,
            'unit_id' => $unitId,
            'client_id' => $clientId,
            'rent_minor' => 100000,
            'due_day' => 5,
            'starts_on' => substr($this->ago(90), 0, 10),
            'status' => 'active',
            'created_at' => $this->ago(90),
            'updated_at' => $this->ago(90),
        ]);
        foreach ([[10, 100000, 'paid'], [5, 50000, 'pending'], [-5, 50000, 'pending'], [40, 80000, 'pending']] as [$days, $amount, $status]) {
            $this->insert('loyer_rent_dues', [
                'tenant_id' => $tenantId,
                'lease_id' => $leaseId,
                'due_date' => substr($this->ago($days), 0, 10),
                'amount_minor' => $amount,
                'status' => $status,
                'paid_at' => $status === 'paid' ? $this->ago($days) : null,
                'created_at' => $this->ago(90),
                'updated_at' => $this->ago(90),
            ]);
        }

        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $html = $kernel->handle(new Request('GET', '/statistiques'))->content();
        $this->assertStringContainsString('Impayés en retard', $html);
        $this->assertStringContainsString('<strong>130 000 FCFA</strong>', $html);
        $this->assertStringContainsString('2 échéances en retard', $html);
        $this->assertFigure($html, 'Loyers attendus', '150 000 FCFA');
        $this->assertFigure($html, 'Encaissé', '100 000 FCFA');
        $this->assertFigure($html, 'Taux de recouvrement', '67 %');
        $this->assertFigure($html, 'Contrats actifs', '1');
        $this->assertStringContainsString('Échéances de la période', $html);
    }

    public function testEachSectorShowsItsOwnBlock(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $shopId = $this->createTenant(Sector::BOUTIQUE);
        $clientId = $this->insertClient($shopId, 'Cliente', 3);
        $productId = $this->insert('boutique_products', [
            'tenant_id' => $shopId,
            'name' => 'Boubou brodé',
            'price_minor' => 25000,
            'track_stock' => 0,
            'stock_quantity' => 0,
            'is_published' => 1,
            'created_at' => $this->ago(3),
            'updated_at' => $this->ago(3),
        ]);
        foreach ([['delivered', 2], ['cancelled', 1]] as $index => [$status, $quantity]) {
            $orderId = $this->insert('boutique_orders', [
                'tenant_id' => $shopId,
                'client_id' => $clientId,
                'order_number' => self::PREFIX . $index,
                'status' => $status,
                'source' => 'manual',
                'customer_name' => 'Cliente',
                'total_minor' => 25000 * $quantity,
                'created_at' => $this->ago(3),
                'updated_at' => $this->ago(3),
            ]);
            $this->insert('boutique_order_items', [
                'tenant_id' => $shopId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_name' => 'Boubou brodé',
                'quantity' => $quantity,
                'unit_price_minor' => 25000,
                'line_total_minor' => 25000 * $quantity,
            ]);
        }

        $this->loginAs($kernel, [Role::TENANT_OWNER], $shopId);
        $boutique = $kernel->handle(new Request('GET', '/statistiques'))->content();
        $this->assertStringContainsString('<h2 id="stats-module-title">Boutique</h2>', $boutique);
        $this->assertFigure($boutique, 'Commandes', '2');
        $this->assertFigure($boutique, 'Ventes', '50 000 FCFA');
        $this->assertFigure($boutique, 'Annulations', '50 %');
        $this->assertStringContainsString('2 pièces', $boutique);
        $this->logout($kernel);
    }

    private function assertFigure(string $html, string $label, string $value): void
    {
        $this->assertStringContainsString('<dt>' . $label . '</dt><dd>' . $value . '</dd>', str_replace(["\r", "\n", '    '], '', $html), $label);
    }

    private function createTenant(string $sector): int
    {
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => self::PREFIX . bin2hex(random_bytes(3)),
            'name' => 'Commerce statistiques',
            'sector' => $sector,
            'status' => 'active',
            'display_name' => 'Statistiques',
        ]);
        $this->tenantIds[] = $id;

        return $id;
    }

    private function insertClient(int $tenantId, string $name, int $daysAgo): int
    {
        return $this->insert('clients', [
            'tenant_id' => $tenantId,
            'full_name' => $name,
            'status' => 'active',
            'created_at' => $this->ago($daysAgo),
            'updated_at' => $this->ago($daysAgo),
        ]);
    }

    private function insertPayment(int $tenantId, ?int $clientId, int $amount, string $status, int $daysAgo): void
    {
        $this->insert('payments', [
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'reference' => bin2hex(random_bytes(16)),
            'amount_minor' => $amount,
            'status' => $status,
            'description' => 'Test statistiques',
            'paid_at' => $status === 'success' ? $this->ago($daysAgo) : null,
            'created_at' => $this->ago($daysAgo),
            'updated_at' => $this->ago($daysAgo),
        ]);
    }

    /**
     * @param array<string, int|string|null> $data
     */
    private function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $this->pdo()->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')'
        )->execute($data);

        return (int) $this->pdo()->lastInsertId();
    }

    private function ago(int $days, string $time = ''): string
    {
        $moment = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify(($days >= 0 ? '-' : '+') . abs($days) . ' days');

        return $time === '' ? $moment->format('Y-m-d H:i:s') : $moment->format('Y-m-d') . ' ' . $time;
    }

    /**
     * @param list<string> $roleSlugs
     */
    private function loginAs(Kernel $kernel, array $roleSlugs, ?int $tenantId = null): void
    {
        $email = self::PREFIX . bin2hex(random_bytes(4)) . '@example.com';
        $this->emails[] = $email;
        $roles = new RoleRepository($this->pdo());
        $id = (new UserRepository($this->pdo()))->insert([
            'tenant_id' => $tenantId,
            'full_name' => 'Test statistiques',
            'email' => $email,
            'password_hash' => password_hash('ValidPass-1234', PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $roles->replaceUserRoles($id, $roles->idsForSlugs($roleSlugs));
        $login = $kernel->handle($this->post('/connexion', [
            '_csrf' => $this->csrf($kernel),
            'email' => $email,
            'password' => 'ValidPass-1234',
        ]));
        $this->assertSame(302, $login->status());
    }

    private function logout(Kernel $kernel): void
    {
        $kernel->handle($this->post('/deconnexion', ['_csrf' => $this->csrf($kernel, '/espace')]));
    }

    private function csrf(Kernel $kernel, string $path = '/connexion'): string
    {
        $page = $kernel->handle(new Request('GET', $path));
        preg_match('/name="_csrf" value="([^"]+)"/', $page->content(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Pas de jeton sur ' . $path . ' (' . $page->status() . ')');

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

    private function pdo(): \PDO
    {
        if ($this->connection === null) {
            Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
            /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
            $config = require dirname(__DIR__) . '/config/app.php';
            $this->connection = Connection::make($config['db']);
        }

        return $this->connection;
    }
}
