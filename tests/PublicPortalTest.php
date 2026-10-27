<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Bootstrap;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Models\Tenant;
use Sesam\Repositories\LoyerPropertyRepository;
use Sesam\Repositories\LoyerUnitRepository;
use Sesam\Repositories\ReservationPrestationRepository;
use Sesam\Repositories\ReservationTeamRepository;
use Sesam\Repositories\TenantRepository;

final class PublicPortalTest extends TestCase
{
    /** @var list<int> */
    private array $tenantIds = [];

    protected function tearDown(): void
    {
        $pdo = $this->pdo();
        foreach ($this->tenantIds as $id) {
            $pdo->prepare('DELETE FROM payment_transactions WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM payments WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM message_deliveries WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_appointments WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_staff_availability WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_staff WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM reservation_services WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM boutique_stock_movements WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM boutique_order_items WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM boutique_orders WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM boutique_products WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM boutique_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM loyer_units WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM loyer_properties WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM client_activities WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM clients WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenant_settings WHERE tenant_id = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM tenants WHERE id = :id')->execute(['id' => $id]);
        }
    }

    public function testUnknownSlugIsNotFound(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $this->assertSame(404, $kernel->handle(new Request('GET', '/t/inconnu-' . bin2hex(random_bytes(3))))->status());
    }

    public function testSuspendedTenantPortalIsNotFound(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::RESERVATION, 'suspended');
        $this->assertSame(404, $kernel->handle(new Request('GET', '/t/' . $tenant->slug()))->status());
    }

    public function testCatalogIsIsolatedAndOverlapIsRejected(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenantA = $this->createTenant(Sector::RESERVATION);
        $tenantB = $this->createTenant(Sector::RESERVATION);
        $nameA = 'CoupeA-' . bin2hex(random_bytes(2));
        $nameB = 'CoupeB-' . bin2hex(random_bytes(2));
        $serviceA = $this->addPrestation($tenantA->id(), $nameA, 0);
        $this->addPrestation($tenantB->id(), $nameB, 0);
        $staffA = $this->addStaffWithHours($tenantA->id());

        $pageA = $kernel->handle(new Request('GET', '/t/' . $tenantA->slug()));
        $this->assertSame(200, $pageA->status());
        $this->assertStringContainsString($nameA, $pageA->content());
        $this->assertStringNotContainsString($nameB, $pageA->content());
        $pageB = $kernel->handle(new Request('GET', '/t/' . $tenantB->slug()));
        $this->assertStringContainsString($nameB, $pageB->content());
        $this->assertStringNotContainsString($nameA, $pageB->content());

        $slot = $this->futureSlot();
        $payload = [
            '_csrf' => $this->csrf($kernel, '/t/' . $tenantA->slug()),
            'service_id' => (string) $serviceA,
            'staff_id' => (string) $staffA,
            'starts_at' => $slot,
            'full_name' => 'Awa Diop',
            'phone' => '770000001',
            'email' => '',
        ];
        $first = $kernel->handle($this->post('/t/' . $tenantA->slug(), $payload));
        $this->assertSame(302, $first->status());
        $this->assertStringContainsString('/confirmation', (string) ($first->headers()['Location'] ?? ''));

        $second = $kernel->handle($this->post('/t/' . $tenantA->slug(), $payload));
        $this->assertSame(422, $second->status());
        $this->assertStringContainsString('déjà réservé', $second->content());
    }

    public function testDepositUsesPortalSandboxAndStaysIsolated(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenantA = $this->createTenant(Sector::RESERVATION);
        $tenantB = $this->createTenant(Sector::RESERVATION);
        $serviceA = $this->addPrestation($tenantA->id(), 'Brushing', 2500);
        $staffA = $this->addStaffWithHours($tenantA->id());
        $payload = [
            '_csrf' => $this->csrf($kernel, '/t/' . $tenantA->slug()),
            'service_id' => (string) $serviceA,
            'staff_id' => (string) $staffA,
            'starts_at' => $this->futureSlot(),
            'full_name' => 'Mamadou Fall',
            'phone' => '770000002',
        ];
        $created = $kernel->handle($this->post('/t/' . $tenantA->slug(), $payload));
        $this->assertSame(302, $created->status());
        $location = (string) ($created->headers()['Location'] ?? '');
        $this->assertStringContainsString('/t/' . $tenantA->slug() . '/paiement/', $location);
        $this->assertStringContainsString('/bac-a-sable', $location);
        preg_match('#/paiement/(\d+)/bac-a-sable#', $location, $matches);
        $paymentId = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan(0, $paymentId);

        $sandbox = $kernel->handle(new Request('GET', '/t/' . $tenantA->slug() . '/paiement/' . $paymentId . '/bac-a-sable'));
        $this->assertSame(200, $sandbox->status());
        $this->assertSame(404, $kernel->handle(new Request('GET', '/t/' . $tenantB->slug() . '/paiement/' . $paymentId . '/bac-a-sable'))->status());

        $confirm = $kernel->handle($this->post('/t/' . $tenantA->slug() . '/paiement/' . $paymentId . '/confirmer', [
            '_csrf' => $this->csrf($kernel, '/t/' . $tenantA->slug() . '/paiement/' . $paymentId . '/bac-a-sable'),
        ]));
        $this->assertSame(302, $confirm->status());
        $confirmLocation = (string) ($confirm->headers()['Location'] ?? '');
        $this->assertStringContainsString('/confirmation?k=', $confirmLocation);
        parse_str((string) parse_url($confirmLocation, PHP_URL_QUERY), $query);
        $token = is_string($query['k'] ?? null) ? $query['k'] : '';
        $done = $kernel->handle(new Request('GET', '/t/' . $tenantA->slug() . '/confirmation', ['k' => $token]));
        $this->assertSame(200, $done->status());
        $this->assertStringContainsString('Et maintenant ?', html_entity_decode($done->content(), ENT_QUOTES, 'UTF-8'));
        $this->assertSame(404, $kernel->handle(new Request('GET', '/t/' . $tenantA->slug() . '/confirmation'))->status());
    }

    public function testBoutiquePortalSendsTheOrderToWhatsApp(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $shop = $this->createTenant(Sector::BOUTIQUE);
        $this->pdo()->prepare(
            'INSERT INTO boutique_settings (tenant_id, whatsapp_phone, theme, is_open, created_at, updated_at)
             VALUES (?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute([$shop->id(), '221771234567', 'emeraude']);
        $productName = 'Boubou-' . bin2hex(random_bytes(2));
        $this->pdo()->prepare(
            'INSERT INTO boutique_products (tenant_id, name, price_minor, track_stock, stock_quantity, is_published, created_at, updated_at)
             VALUES (?, ?, 15000, 1, 4, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute([$shop->id(), $productName]);
        $productId = (int) $this->pdo()->lastInsertId();
        $this->pdo()->prepare(
            'INSERT INTO boutique_products (tenant_id, name, price_minor, track_stock, stock_quantity, is_published, created_at, updated_at)
             VALUES (?, ?, 9000, 1, 0, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute([$shop->id(), 'ProduitCacheXYZ']);

        $home = $kernel->handle(new Request('GET', '/t/' . $shop->slug()));
        $this->assertSame(200, $home->status());
        $this->assertStringContainsString($productName, $home->content());
        $this->assertStringContainsString('Commander sur WhatsApp', $home->content());
        $this->assertStringNotContainsString('ProduitCacheXYZ', $home->content());

        $tooMany = $kernel->handle($this->post('/t/' . $shop->slug() . '/commander', [
            '_csrf' => $this->csrf($kernel, '/t/' . $shop->slug()),
            'items' => [(string) $productId => '9'],
            'customer_name' => 'Khady Sow',
            'customer_phone' => '770000003',
        ]));
        $this->assertSame(422, $tooMany->status());
        $this->assertStringContainsString('Il ne reste que 4', html_entity_decode($tooMany->content(), ENT_QUOTES, 'UTF-8'));

        $order = $kernel->handle($this->post('/t/' . $shop->slug() . '/commander', [
            '_csrf' => $this->csrf($kernel, '/t/' . $shop->slug()),
            'items' => [(string) $productId => '2'],
            'customer_name' => 'Khady Sow',
            'customer_phone' => '770000003',
            'customer_address' => 'Mermoz',
        ]));
        $this->assertSame(303, $order->status());
        $location = (string) ($order->headers()['Location'] ?? '');
        $this->assertStringStartsWith('https://wa.me/221771234567?text=', $location);
        $message = rawurldecode(substr($location, strpos($location, '=') + 1));
        $this->assertStringContainsString('2 × ' . $productName, $message);
        $this->assertStringContainsString('Total : 30 000 FCFA', $message);
        $this->assertStringContainsString('Adresse : Mermoz', $message);
        // Le lien du produit commandé suit sa ligne : WhatsApp en affiche la carte (photo, nom, prix) dans la discussion.
        $productPath = '/t/' . $shop->slug() . '/produit/' . $productId;
        $this->assertMatchesRegularExpression('#2 × ' . preg_quote($productName, '#') . ' — 30 000 FCFA\nhttps?://[^\s]+' . preg_quote($productPath, '#') . '\n#u', $message);

        $productPage = $kernel->handle(new Request('GET', $productPath));
        $this->assertSame(200, $productPage->status());
        $html = html_entity_decode($productPage->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('<meta property="og:title" content="' . $productName . ' — 15 000 FCFA">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="', $html);
        $this->assertStringContainsString('?ajouter=' . $productId, $html);
        $hiddenId = (int) $this->value('SELECT id FROM boutique_products WHERE tenant_id = ? AND name = ?', [$shop->id(), 'ProduitCacheXYZ']);
        $this->assertSame(404, $kernel->handle(new Request('GET', '/t/' . $shop->slug() . '/produit/' . $hiddenId))->status());
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM boutique_orders WHERE tenant_id = ?', [$shop->id()]));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND phone = ?', [$shop->id(), '770000003']));
        // Le stock ne bouge qu'à la confirmation par le commerçant.
        $this->assertSame(4, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));
    }

    public function testLoyerPortalFlow(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $loyer = $this->createTenant(Sector::LOYER);
        $propertyId = (new LoyerPropertyRepository($this->pdo()))->insert($loyer->id(), [
            'name' => 'Immeuble Plateau',
            'address' => 'Dakar',
        ]);
        (new LoyerUnitRepository($this->pdo()))->insert($loyer->id(), $propertyId, [
            'label' => 'F3-12',
            'default_rent_minor' => 250000,
            'is_active' => 1,
        ]);
        $list = $kernel->handle(new Request('GET', '/t/' . $loyer->slug()));
        $this->assertSame(200, $list->status());
        $this->assertStringContainsString('im-hero', $list->content());
        $this->assertStringContainsString('im-card', $list->content());
        $this->assertStringContainsString('Immeuble Plateau', $list->content());
        $this->assertStringContainsString('F3-12', $list->content());
        $this->assertStringNotContainsString('/bien/' . $propertyId . '/image', $list->content());
        $ask = $kernel->handle($this->post('/t/' . $loyer->slug(), [
            '_csrf' => $this->csrf($kernel, '/t/' . $loyer->slug()),
            'unit_label' => 'F3-12',
            'message' => 'Visite possible samedi ?',
            'full_name' => 'Omar Ba',
            'phone' => '770000005',
        ]));
        $this->assertSame(302, $ask->status());
        $this->assertStringContainsString('/confirmation', (string) ($ask->headers()['Location'] ?? ''));
    }

    public function testPortalPostWithoutCsrfIsRejected(): void
    {
        $kernel = Bootstrap::kernel(dirname(__DIR__));
        $tenant = $this->createTenant(Sector::LOYER);
        $denied = $kernel->handle($this->post('/t/' . $tenant->slug(), [
            'full_name' => 'Sans Jeton',
            'phone' => '770000006',
        ]));
        $this->assertSame(419, $denied->status());
    }

    private function addPrestation(int $tenantId, string $name, int $deposit): int
    {
        return (new ReservationPrestationRepository($this->pdo()))->insert($tenantId, [
            'name' => $name,
            'duration_minutes' => 30,
            'price_minor' => 10000,
            'deposit_minor' => $deposit,
            'is_active' => 1,
            'category_id' => null,
        ]);
    }

    private function addStaffWithHours(int $tenantId): int
    {
        $team = new ReservationTeamRepository($this->pdo());
        $staffId = $team->insert($tenantId, 'Fatou');
        $slots = [];
        for ($day = 1; $day <= 7; ++$day) {
            $slots[] = ['weekday' => $day, 'starts_at' => '08:00:00', 'ends_at' => '20:00:00'];
        }
        $team->replaceAvailability($tenantId, $staffId, $slots);

        return $staffId;
    }

    private function futureSlot(): string
    {
        $timestamp = strtotime('+3 days', strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC'));
        $this->assertNotFalse($timestamp);

        return gmdate('Y-m-d', $timestamp) . 'T10:00';
    }

    private function createTenant(string $sector, string $status = 'active'): Tenant
    {
        $slug = 'p15-' . bin2hex(random_bytes(3));
        $id = (new TenantRepository($this->pdo()))->insert([
            'slug' => $slug,
            'name' => 'Portal test',
            'sector' => $sector,
            'status' => $status,
            'display_name' => 'Commerce ' . $slug,
        ]);
        $this->tenantIds[] = $id;
        $tenant = (new TenantRepository($this->pdo()))->findById($id);
        $this->assertInstanceOf(Tenant::class, $tenant);

        return $tenant;
    }

    private function csrf(\Sesam\Core\Kernel $kernel, string $path): string
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
        return new Request('POST', $path, [], $body, [], [], false, '203.0.113.' . random_int(1, 250));
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

    /**
     * @param list<int|string> $params
     */
    private function value(string $sql, array $params): mixed
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }
}
