<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Kernel;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Un gérant connecté au commerce A ne lit, ne modifie ni ne supprime aucune ressource du commerce B.
 */
final class CrossTenantAccessTest extends IntegrationTestCase
{
    public function testClientsAndPaymentsOfAnotherTenantAreSealed(): void
    {
        [$kernel, $own, $other] = $this->pair(Sector::RESERVATION);
        $clientId = $this->client($other, 'Client du voisin');
        $paymentId = $this->payment($other);

        $this->assertSame(200, $this->get($kernel, '/clients/' . $this->client($own))->status());
        $this->assertSame(200, $this->get($kernel, '/paiements/' . $this->payment($own))->status());

        $this->assertSealedGet($kernel, '/clients/' . $clientId);
        $this->assertSealedGet($kernel, '/clients/' . $clientId . '/modifier');
        $this->assertSealedPost($kernel, '/clients/' . $clientId, ['full_name' => 'Renommé', 'status' => 'active'], 'clients', $clientId);
        $this->assertSealedPost($kernel, '/clients/' . $clientId . '/archiver', [], 'clients', $clientId);
        $this->assertSealedPost($kernel, '/clients/' . $clientId . '/notes', ['body' => 'Intrusion'], 'clients', $clientId);
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM client_notes WHERE client_id = ?', [$clientId]));

        $this->assertSealedGet($kernel, '/paiements/' . $paymentId);
        $this->assertSealedGet($kernel, '/paiements/' . $paymentId . '/bac-a-sable');
        $this->assertSealedPost($kernel, '/paiements/' . $paymentId . '/bac-a-sable/confirmer', [], 'payments', $paymentId);
        $this->assertSealedPost($kernel, '/paiements/' . $paymentId . '/bac-a-sable/annuler', [], 'payments', $paymentId);
    }

    public function testReservationAppointmentsOfAnotherTenantAreSealed(): void
    {
        [$kernel, , $other] = $this->pair(Sector::RESERVATION);
        $serviceId = $this->insert('reservation_services', [
            'tenant_id' => $other, 'name' => 'Coupe', 'duration_minutes' => 30, 'price_minor' => 5000, 'deposit_minor' => 1000, 'is_active' => 1,
        ]);
        $appointmentId = $this->insert('reservation_appointments', [
            'tenant_id' => $other, 'client_id' => $this->client($other), 'service_id' => $serviceId,
            'starts_at' => gmdate('Y-m-d 10:00:00', strtotime('+3 days')), 'duration_minutes' => 30,
            'status' => 'pending', 'deposit_amount_minor' => 1000,
        ]);

        $path = '/reservation/rendez-vous/' . $appointmentId;
        $this->assertSealedGet($kernel, $path);
        foreach (['/confirmer', '/annuler', '/acompte'] as $action) {
            $this->assertSealedPost($kernel, $path . $action, [], 'reservation_appointments', $appointmentId);
        }
        $this->assertSealedPost($kernel, $path . '/reprogrammer', [
            'date' => gmdate('Y-m-d', strtotime('+5 days')), 'time' => '11:00',
        ], 'reservation_appointments', $appointmentId);
    }

    public function testBoutiqueProductsAndOrdersOfAnotherTenantAreSealed(): void
    {
        [$kernel, , $other] = $this->pair(Sector::BOUTIQUE);
        $productId = $this->insert('boutique_products', [
            'tenant_id' => $other, 'name' => 'Robe du voisin', 'price_minor' => 20000, 'track_stock' => 1, 'stock_quantity' => 5,
        ]);
        $orderId = $this->insert('boutique_orders', [
            'tenant_id' => $other, 'client_id' => $this->client($other), 'order_number' => 'P20-' . random_int(1000, 9999),
            'status' => 'new', 'source' => 'manual', 'customer_name' => 'Client voisin', 'total_minor' => 20000,
        ]);

        $this->assertSealedGet($kernel, '/boutique/produits/' . $productId . '/modifier');
        $this->assertSealedPost($kernel, '/boutique/produits/' . $productId, ['name' => 'Renommé', 'price_minor' => '1'], 'boutique_products', $productId);
        $this->assertSealedPost($kernel, '/boutique/produits/' . $productId . '/visibilite', [], 'boutique_products', $productId);
        $this->assertSealedPost($kernel, '/boutique/produits/' . $productId . '/supprimer', [], 'boutique_products', $productId);
        $this->assertSame(5, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));

        $this->assertSealedGet($kernel, '/boutique/commandes/' . $orderId);
        $this->assertSealedPost($kernel, '/boutique/commandes/' . $orderId . '/statut', ['status' => 'confirmed'], 'boutique_orders', $orderId);
        $this->assertSealedPost($kernel, '/boutique/commandes/' . $orderId . '/paiement', [], 'boutique_orders', $orderId);
    }

    public function testLoyerDuesOfAnotherTenantAreSealed(): void
    {
        [$kernel, , $other] = $this->pair(Sector::LOYER);
        $propertyId = $this->insert('loyer_properties', ['tenant_id' => $other, 'name' => 'Immeuble voisin']);
        $unitId = $this->insert('loyer_units', ['tenant_id' => $other, 'property_id' => $propertyId, 'label' => 'A1', 'default_rent_minor' => 60000, 'is_active' => 1]);
        $leaseId = $this->insert('loyer_leases', [
            'tenant_id' => $other, 'unit_id' => $unitId, 'client_id' => $this->client($other), 'rent_minor' => 60000,
            'due_day' => 5, 'starts_on' => gmdate('Y-m-01'), 'status' => 'active',
        ]);
        $dueId = $this->insert('loyer_rent_dues', [
            'tenant_id' => $other, 'lease_id' => $leaseId, 'due_date' => gmdate('Y-m-05'), 'amount_minor' => 60000, 'status' => 'pending',
        ]);

        $this->assertSealedGet($kernel, '/immo/biens/' . $propertyId);
        $this->assertSealedGet($kernel, '/immo/baux/' . $leaseId);
        $this->assertSealedGet($kernel, '/immo/loyers/' . $dueId);
        $this->assertSealedGet($kernel, '/immo/logements/' . $unitId . '/modifier');
        $this->assertSealedPost($kernel, '/immo/loyers/' . $dueId . '/paiement', ['amount' => '60000', 'method' => 'especes', 'paid_on' => gmdate('Y-m-d')], 'loyer_rent_dues', $dueId);
        $this->assertSealedPost($kernel, '/immo/loyers/' . $dueId . '/lien', [], 'loyer_rent_dues', $dueId);
        $this->assertSealedPost($kernel, '/immo/loyers/' . $dueId . '/relance', [], 'loyer_rent_dues', $dueId);
        $this->assertSealedPost($kernel, '/immo/baux/' . $leaseId . '/terminer', ['ended_on' => gmdate('Y-m-d')], 'loyer_leases', $leaseId);
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM payments WHERE tenant_id = ?', [$other]));
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM immo_payments WHERE tenant_id = ?', [$other]));
    }

    public function testNotificationsStayScopedToTheirRecipient(): void
    {
        [$kernel, $own, $other] = $this->pair(Sector::RESERVATION);
        $foreign = $this->insert('notifications', [
            'tenant_id' => $other, 'user_id' => $this->userId($this->user([Role::TENANT_OWNER], $other)), 'title' => 'Privé', 'body' => 'Voisin',
        ]);
        $colleague = $this->insert('notifications', [
            'tenant_id' => $own, 'user_id' => $this->userId($this->user([Role::STAFF], $own)), 'title' => 'Privé', 'body' => 'Collègue',
        ]);

        foreach ([$foreign, $colleague] as $id) {
            $this->assertSealedPost($kernel, '/notifications/' . $id . '/lu', [], 'notifications', $id);
            $this->assertFalse($this->value('SELECT id FROM notifications WHERE id = ? AND read_at IS NOT NULL', [$id]));
        }
    }

    public function testPortalNeverServesAPaymentOfAnotherTenant(): void
    {
        $kernel = $this->kernel();
        $own = $this->tenant(Sector::RESERVATION);
        $paymentId = $this->payment($this->tenant(Sector::RESERVATION));
        $slug = $this->slug($own);

        $this->assertSame(404, $this->get($kernel, '/t/' . $slug . '/paiement/' . $paymentId . '/bac-a-sable')->status());
        $before = $this->row('payments', $paymentId);
        $token = $this->csrf($kernel, '/t/' . $slug . '/paiement/' . $this->payment($own) . '/bac-a-sable');
        foreach (['confirmer', 'annuler'] as $action) {
            $response = $kernel->handle($this->post('/t/' . $slug . '/paiement/' . $paymentId . '/' . $action, ['_csrf' => $token]));
            $this->assertSame(404, $response->status(), $action);
        }
        $this->assertSame($before, $this->row('payments', $paymentId));
    }

    /**
     * @return array{Kernel, int, int}
     */
    private function pair(string $sector): array
    {
        $kernel = $this->kernel();
        $own = $this->tenant($sector);
        $other = $this->tenant($sector);
        $this->loginAs($kernel, [Role::TENANT_OWNER], $own);

        return [$kernel, $own, $other];
    }

    private function assertSealedGet(Kernel $kernel, string $path): void
    {
        $status = $kernel->handle(new Request('GET', $path))->status();
        $this->assertContains($status, [403, 404], 'GET ' . $path . ' a répondu ' . $status);
    }

    /**
     * @param array<string, string> $body
     */
    private function assertSealedPost(Kernel $kernel, string $path, array $body, string $table, int $id): void
    {
        $before = $this->row($table, $id);
        $status = $this->submit($kernel, $path, $body)->status();
        $this->assertContains($status, [403, 404], 'POST ' . $path . ' a répondu ' . $status);
        $this->assertSame($before, $this->row($table, $id), 'POST ' . $path . ' a modifié ' . $table);
    }

    /**
     * @return array<string, mixed>|false
     */
    private function row(string $table, int $id): array|false
    {
        $statement = $this->pdo()->prepare('SELECT * FROM `' . $table . '` WHERE id = ?');
        $statement->execute([$id]);

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }
}
