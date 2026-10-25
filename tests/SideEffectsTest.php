<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Kernel;
use Sesam\Core\Messaging\MessageChannel;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\ReservationTeamRepository;
use Sesam\Services\Portal\PublicPortalService;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Double soumission, double paiement et demandes refusées : aucune écriture parasite.
 */
final class SideEffectsTest extends IntegrationTestCase
{
    public function testSandboxConfirmTwiceRecordsASingleSuccess(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $paymentId = $this->payment($tenantId);
        $path = '/paiements/' . $paymentId . '/bac-a-sable';

        $this->assertSame(302, $this->submit($kernel, $path . '/confirmer', [], $path)->status());
        $this->assertSame(302, $this->submit($kernel, $path . '/confirmer', [], $path)->status());
        $this->assertSame(302, $this->submit($kernel, $path . '/annuler', [], $path)->status());

        $this->assertSame('success', $this->value('SELECT status FROM payments WHERE id = ?', [$paymentId]));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM payment_transactions WHERE payment_id = ?', [$paymentId]));
    }

    public function testRentDueCannotBeCollectedTwice(): void
    {
        $tenantId = $this->tenant(Sector::LOYER);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $propertyId = $this->insert('loyer_properties', ['tenant_id' => $tenantId, 'name' => 'Résidence p20']);
        $unitId = $this->insert('loyer_units', ['tenant_id' => $tenantId, 'property_id' => $propertyId, 'label' => 'C3', 'default_rent_minor' => 50000, 'is_active' => 1]);
        $leaseId = $this->insert('loyer_leases', [
            'tenant_id' => $tenantId, 'unit_id' => $unitId, 'client_id' => $this->client($tenantId), 'rent_minor' => 50000,
            'due_day' => 5, 'starts_on' => gmdate('Y-m-01'), 'status' => 'active',
        ]);
        $dueId = $this->insert('loyer_rent_dues', [
            'tenant_id' => $tenantId, 'lease_id' => $leaseId, 'due_date' => gmdate('Y-m-05'), 'amount_minor' => 50000, 'status' => 'pending',
        ]);
        $link = '/immo/loyers/' . $dueId . '/lien';

        $first = $this->submit($kernel, $link);
        $paymentPage = $this->location($first);
        $this->assertMatchesRegularExpression('#^/paiements/\d+$#', $paymentPage);
        $second = $this->submit($kernel, $link);
        $this->assertSame(422, $second->status());
        $this->assertStringContainsString('déjà en attente', html_entity_decode($second->content(), ENT_QUOTES, 'UTF-8'));
        $this->assertSame(1, $this->paymentCount($tenantId));

        // Le locataire paie (simulation) : le loyer est soldé une seule fois, même si l'on confirme deux fois.
        $this->submit($kernel, $paymentPage . '/bac-a-sable/confirmer', [], $paymentPage . '/bac-a-sable');
        $this->submit($kernel, $paymentPage . '/bac-a-sable/confirmer', [], $paymentPage . '/bac-a-sable');
        $this->assertSame('paid', $this->value('SELECT status FROM loyer_rent_dues WHERE id = ?', [$dueId]));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM immo_payments WHERE due_id = ?', [$dueId]));
        $this->assertSame(422, $this->submit($kernel, $link)->status());
        $manual = $this->submit($kernel, '/immo/loyers/' . $dueId . '/paiement', ['amount' => '50000', 'method' => 'especes', 'paid_on' => gmdate('Y-m-d')]);
        $this->assertSame(422, $manual->status());
        $this->assertSame(1, $this->paymentCount($tenantId));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM immo_payments WHERE due_id = ?', [$dueId]));
    }

    public function testAppointmentDepositIsLinkedOnce(): void
    {
        $reservation = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $reservation);
        $serviceId = $this->insert('reservation_services', [
            'tenant_id' => $reservation, 'name' => 'Soin', 'duration_minutes' => 30, 'price_minor' => 8000, 'deposit_minor' => 2000, 'is_active' => 1,
        ]);
        $appointmentId = $this->insert('reservation_appointments', [
            'tenant_id' => $reservation, 'client_id' => $this->client($reservation), 'service_id' => $serviceId,
            'starts_at' => gmdate('Y-m-d 10:00:00', strtotime('+4 days')), 'duration_minutes' => 30,
            'status' => 'pending', 'deposit_amount_minor' => 2000,
        ]);
        $deposit = '/reservation/rendez-vous/' . $appointmentId . '/acompte';
        $this->assertStringContainsString('/bac-a-sable', $this->location($this->submit($kernel, $deposit)));
        $this->assertSame('/reservation/rendez-vous/' . $appointmentId, $this->location($this->submit($kernel, $deposit)));
        $this->assertSame(1, $this->paymentCount($reservation));
    }

    public function testNotificationSendRejectsInvalidInputAndUnauthorisedActors(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $valid = ['channel' => MessageChannel::INTERNAL, 'template_slug' => 'test_message', 'recipient' => 'Équipe', 'name' => 'Test', 'amount' => ''];

        foreach ([['recipient' => ''], ['channel' => 'pigeon'], ['template_slug' => ''], ['template_slug' => 'inexistant']] as $override) {
            $response = $this->submit($kernel, '/notifications/envoyer', $override + $valid, '/notifications/envoyer');
            $this->assertSame(422, $response->status(), (string) json_encode($override));
        }
        $this->assertSame(0, $this->deliveries($tenantId));

        $this->logout($kernel);
        $this->loginAs($kernel, [Role::STAFF], $tenantId);
        $this->assertSame(403, $this->submit($kernel, '/notifications/envoyer', $valid)->status());
        $this->assertSame(0, $this->deliveries($tenantId));
    }

    public function testRefusedPortalRequestsLeaveNoGuestBehind(): void
    {
        [$kernel, $tenantId, $slug, $payload] = $this->portal();

        $missing = $kernel->handle($this->post('/t/' . $slug, ['full_name' => '', 'phone' => ''] + $payload));
        $this->assertSame(422, $missing->status());
        $badService = $kernel->handle($this->post('/t/' . $slug, ['service_id' => '999999999'] + $payload));
        $this->assertSame(422, $badService->status());
        $this->assertSame(0, $this->clientCount($tenantId));

        $this->assertSame(302, $kernel->handle($this->post('/t/' . $slug, $payload))->status());
        $this->assertSame(1, $this->clientCount($tenantId));
        $overlap = $kernel->handle($this->post('/t/' . $slug, ['full_name' => 'Autre visiteur', 'phone' => '770000099'] + $payload));
        $this->assertSame(422, $overlap->status());
        $this->assertSame(1, $this->clientCount($tenantId));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = ?', [$tenantId]));
    }

    public function testPortalSubmissionsAreRateLimitedPerIp(): void
    {
        [$kernel, $tenantId, $slug, $payload] = $this->portal();
        $ip = '198.51.100.' . random_int(1, 250);
        $invalid = ['full_name' => ''] + $payload;

        $statuses = [];
        for ($i = 0; $i <= PublicPortalService::RATE_MAX; ++$i) {
            $statuses[] = $kernel->handle($this->post('/t/' . $slug, $invalid, $ip))->status();
        }
        $this->assertSame(array_merge(array_fill(0, PublicPortalService::RATE_MAX, 422), [429]), $statuses);
        $this->assertSame(429, $kernel->handle($this->post('/t/' . $slug, $payload, $ip))->status());
        $this->assertSame(0, $this->clientCount($tenantId));
        $this->assertSame(302, $kernel->handle($this->post('/t/' . $slug, $payload))->status());
    }

    /**
     * @return array{Kernel, int, string, array<string, string>}
     */
    private function portal(): array
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $slug = $this->slug($tenantId);
        $serviceId = $this->insert('reservation_services', [
            'tenant_id' => $tenantId, 'name' => 'Coupe p20', 'duration_minutes' => 30, 'price_minor' => 6000, 'deposit_minor' => 0, 'is_active' => 1,
        ]);
        $team = new ReservationTeamRepository($this->pdo());
        $staffId = $team->insert($tenantId, 'Fatou');
        $team->replaceAvailability($tenantId, $staffId, array_map(
            static fn (int $day): array => ['weekday' => $day, 'starts_at' => '08:00:00', 'ends_at' => '20:00:00'],
            range(1, 7),
        ));
        $kernel = $this->kernel();
        $payload = [
            '_csrf' => $this->csrf($kernel, '/t/' . $slug),
            'service_id' => (string) $serviceId,
            'staff_id' => (string) $staffId,
            'starts_at' => gmdate('Y-m-d', strtotime('+3 days')) . 'T10:00',
            'full_name' => 'Awa Diop',
            'phone' => '770000001',
            'email' => '',
        ];

        return [$kernel, $tenantId, $slug, $payload];
    }

    private function location(\Sesam\Core\Response\Response $response): string
    {
        $this->assertSame(302, $response->status());

        return (string) ($response->headers()['Location'] ?? '');
    }

    private function paymentCount(int $tenantId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM payments WHERE tenant_id = ?', [$tenantId]);
    }

    private function clientCount(int $tenantId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM clients WHERE tenant_id = ?', [$tenantId]);
    }

    private function deliveries(int $tenantId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM message_deliveries WHERE tenant_id = ?', [$tenantId]);
    }
}
