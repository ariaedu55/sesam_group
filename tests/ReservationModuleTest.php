<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Kernel;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Module Réservation : créneaux libres, horaires du salon, fermetures, absences, prestations par personne,
 * statuts d'un rendez-vous et page « Mon salon ».
 */
final class ReservationModuleTest extends IntegrationTestCase
{
    public function testSlotsFollowSalonHoursClosuresAbsencesAndStaffServices(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $monday = $this->nextWeekday(1);
        $sunday = $this->nextWeekday(7);

        $save = $this->submit($kernel, '/reservation/salon', $this->salonInput() + [
            'day_1_open' => '1', 'day_1_a_from' => '09:00', 'day_1_a_to' => '12:00', 'day_1_b_from' => '14:00', 'day_1_b_to' => '16:00',
        ], '/reservation/salon');
        $this->assertSame(302, $save->status());

        $coupe = $this->service($tenantId, 'Coupe', 60);
        $soin = $this->service($tenantId, 'Soin', 60);
        $awa = $this->insert('reservation_staff', ['tenant_id' => $tenantId, 'name' => 'Awa', 'is_active' => 1]);
        $this->insert('reservation_staff_services', ['tenant_id' => $tenantId, 'staff_id' => $awa, 'service_id' => $coupe]);

        $slots = $this->slots($kernel, $coupe, $monday);
        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00', '14:00', '14:30', '15:00'], $slots);
        $this->assertSame([], $this->slots($kernel, $coupe, $sunday), 'Dimanche : salon fermé');
        $this->assertSame([], $this->slots($kernel, $coupe, $monday, $awa + 0, $soin), 'Awa ne fait pas de soin');

        // Un rendez-vous bloque le créneau (plus le temps de battement de 10 minutes).
        $this->insert('reservation_appointments', [
            'tenant_id' => $tenantId, 'client_id' => $this->client($tenantId), 'service_id' => $coupe, 'staff_id' => $awa,
            'starts_at' => $monday . ' 10:00:00', 'duration_minutes' => 60, 'status' => 'confirmed', 'deposit_amount_minor' => 0,
        ]);
        // 09:00 finit à 10:00 sans pause ; 11:00 tombe dans la pause ; 11:30 dépasse la fermeture de midi.
        $this->assertSame(['14:00', '14:30', '15:00'], $this->slots($kernel, $coupe, $monday));

        $absence = $this->submit($kernel, '/reservation/equipe/' . $awa . '/absences', ['starts_on' => $monday, 'ends_on' => $monday, 'reason' => 'Congé'], '/reservation/equipe/' . $awa . '/disponibilites');
        $this->assertSame(302, $absence->status());
        $this->assertSame([], $this->slots($kernel, $coupe, $monday, $awa));

        $closure = $this->submit($kernel, '/reservation/salon/fermetures', ['starts_on' => $monday, 'ends_on' => $monday, 'reason' => 'Fête'], '/reservation/salon');
        $this->assertSame(302, $closure->status());
        $this->assertSame([], $this->slots($kernel, $soin, $monday));
    }

    public function testAppointmentLifecycleAndQuickClientCreation(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $service = $this->service($tenantId, 'Brushing', 45, 8000);
        $when = $this->nextWeekday(2) . 'T11:00';

        $create = $this->submit($kernel, '/reservation/rendez-vous', [
            'client_name' => 'Nouvelle Cliente',
            'client_phone' => '771234567',
            'service_id' => (string) $service,
            'starts_at' => $when,
            'confirm_now' => '1',
        ], '/reservation/rendez-vous/nouveau');
        $this->assertSame(302, $create->status());
        $id = (int) $this->value('SELECT id FROM reservation_appointments WHERE tenant_id = ?', [$tenantId]);
        $this->assertSame('confirmed', $this->value('SELECT status FROM reservation_appointments WHERE id = ?', [$id]));
        $this->assertSame(8000, (int) $this->value('SELECT price_minor FROM reservation_appointments WHERE id = ?', [$id]));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND phone = ?', [$tenantId, '771234567']));

        $page = html_entity_decode($this->get($kernel, '/reservation/rendez-vous/' . $id)->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('https://wa.me/221771234567', $page);
        $this->assertStringContainsString('Prestation réalisée', $page);

        $this->submit($kernel, '/reservation/rendez-vous/' . $id . '/statut', ['status' => 'completed'], '/reservation/rendez-vous/' . $id);
        $this->assertSame('completed', $this->value('SELECT status FROM reservation_appointments WHERE id = ?', [$id]));
        $this->assertNotNull($this->value('SELECT completed_at FROM reservation_appointments WHERE id = ?', [$id]));
        $refused = $this->submit($kernel, '/reservation/rendez-vous/' . $id . '/statut', ['status' => 'no_show'], '/reservation/rendez-vous/' . $id);
        $this->assertSame(422, $refused->status());

        $this->submit($kernel, '/reservation/rendez-vous/' . $id . '/paiement', [], '/reservation/rendez-vous/' . $id);
        $this->assertSame(1, (int) $this->value('SELECT is_paid FROM reservation_appointments WHERE id = ?', [$id]));

        $agenda = $this->fetch($kernel, '/reservation/agenda?date=' . substr($when, 0, 10));
        $this->assertSame(200, $agenda->status());
        $this->assertStringContainsString('Nouvelle Cliente', $agenda->content());
        $week = $this->fetch($kernel, '/reservation/agenda?vue=semaine&date=' . substr($when, 0, 10));
        $this->assertStringContainsString('Nouvelle Cliente', $week->content());

        $list = $this->fetch($kernel, '/reservation/rendez-vous?periode=tous&statut=completed');
        $this->assertStringContainsString('Nouvelle Cliente', $list->content());
    }

    public function testPrestationEditAndOnlineBookingAssignsAFreePerson(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $service = $this->service($tenantId, 'Tresses', 120, 20000);

        $edit = $this->submit($kernel, '/reservation/prestations/' . $service, [
            'name' => 'Tresses longues', 'category' => 'coiffure', 'duration_minutes' => '180',
            'price_minor' => '25 000', 'deposit_minor' => '0', 'is_active' => '1',
        ], '/reservation/prestations/' . $service . '/modifier');
        $this->assertSame(302, $edit->status());
        $this->assertSame('Coiffure', $this->value('SELECT category FROM reservation_services WHERE id = ?', [$service]));
        $this->assertSame(180, (int) $this->value('SELECT duration_minutes FROM reservation_services WHERE id = ?', [$service]));

        $fatou = $this->insert('reservation_staff', ['tenant_id' => $tenantId, 'name' => 'Fatou', 'is_active' => 1]);
        $this->submit($kernel, '/reservation/salon', $this->salonInput() + ['auto_confirm' => '1'], '/reservation/salon');
        $slug = $this->slug($tenantId);
        $day = $this->nextWeekday(3);

        $public = html_entity_decode($this->get($kernel, '/t/' . $slug)->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Tresses longues', $public);
        $this->assertStringContainsString('Choisissez le jour et l\'heure', $public);
        $json = json_decode($this->fetch($kernel, '/t/' . $slug . '/creneaux?prestation=' . $service . '&date=' . $day)->content(), true);
        $this->assertNotEmpty($json['slots'] ?? []);

        $booking = $kernel->handle($this->post('/t/' . $slug, [
            '_csrf' => $this->csrf($kernel, '/t/' . $slug),
            'service_id' => (string) $service,
            'starts_at' => $day . 'T09:00',
            'full_name' => 'Cliente En Ligne',
            'phone' => '770001122',
        ]));
        $this->assertSame(302, $booking->status());
        $row = $this->row('SELECT staff_id, status, source FROM reservation_appointments WHERE tenant_id = ? ORDER BY id DESC LIMIT 1', [$tenantId]);
        $this->assertSame($fatou, (int) $row['staff_id']);
        $this->assertSame('confirmed', $row['status'], 'Confirmation automatique choisie par le salon');
        $this->assertSame('online', $row['source']);

        $again = $kernel->handle($this->post('/t/' . $slug, [
            '_csrf' => $this->csrf($kernel, '/t/' . $slug),
            'service_id' => (string) $service,
            'starts_at' => $day . 'T10:00',
            'full_name' => 'Autre Cliente',
            'phone' => '770003344',
        ]));
        $this->assertSame(422, $again->status(), 'Fatou est déjà prise : pas de double réservation');
    }

    /**
     * @return array<string, string>
     */
    public function testNewAppointmentTypesClientOrPicksAnExistingOne(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $service = $this->service($tenantId, 'Tresses', 60);
        $form = '/reservation/rendez-vous/nouveau';

        // Aucune cliente en base : le formulaire s'ouvre et demande nom + numéro.
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM clients WHERE tenant_id = ?', [$tenantId]));
        $page = $this->get($kernel, $form);
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('data-client-picker', $page->content());

        $missing = $this->submit($kernel, '/reservation/rendez-vous', [
            'client_name' => 'Khady Fall', 'client_phone' => '', 'service_id' => (string) $service,
            'starts_at' => $this->nextWeekday(3) . 'T10:00',
        ], $form);
        $this->assertSame(422, $missing->status());
        $this->assertStringContainsString('Indiquez le numéro', html_entity_decode($missing->content(), ENT_QUOTES, 'UTF-8'));
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM clients WHERE tenant_id = ?', [$tenantId]));

        $created = $this->submit($kernel, '/reservation/rendez-vous', [
            'client_name' => 'Khady Fall', 'client_phone' => '+221 77 555 66 77', 'service_id' => (string) $service,
            'starts_at' => $this->nextWeekday(3) . 'T10:00',
        ], $form);
        $this->assertSame(302, $created->status());
        $clientId = (int) $this->value('SELECT id FROM clients WHERE tenant_id = ? AND full_name = ?', [$tenantId, 'Khady Fall']);
        $this->assertGreaterThan(0, $clientId);

        // Suggestions en temps réel : par le début du nom ou par le numéro, sans les clientes d'un autre commerce.
        $other = $this->tenant(Sector::RESERVATION);
        $this->client($other, 'Khady Autre', '775556677');
        $byName = json_decode($this->fetch($kernel, '/reservation/clientes?q=kha')->content(), true);
        $this->assertSame([$clientId], array_column($byName['clients'], 'id'));
        $this->assertSame('+221 77 555 66 77', $byName['clients'][0]['phone']);
        $byPhone = json_decode($this->fetch($kernel, '/reservation/clientes?q=555 66')->content(), true);
        $this->assertSame([$clientId], array_column($byPhone['clients'], 'id'));

        // Cliente choisie dans la liste : sa fiche est réutilisée, aucune nouvelle fiche.
        $picked = $this->submit($kernel, '/reservation/rendez-vous', [
            'client_id' => (string) $clientId, 'client_name' => 'Khady Fall', 'client_phone' => '+221 77 555 66 77',
            'service_id' => (string) $service, 'starts_at' => $this->nextWeekday(4) . 'T10:00',
        ], $form);
        $this->assertSame(302, $picked->status());
        // Même numéro écrit autrement : c'est la même cliente.
        $sameNumber = $this->submit($kernel, '/reservation/rendez-vous', [
            'client_name' => 'Khady F.', 'client_phone' => '775556677',
            'service_id' => (string) $service, 'starts_at' => $this->nextWeekday(5) . 'T10:00',
        ], $form);
        $this->assertSame(302, $sameNumber->status());
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM clients WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(3, (int) $this->value('SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = ? AND client_id = ?', [$tenantId, $clientId]));
    }

    private function salonInput(): array
    {
        return [
            'display_name' => 'Salon test',
            'whatsapp_phone' => '77 000 00 00',
            'theme' => 'ocean',
            'is_open' => '1',
            'slot_step_minutes' => '30',
            'min_notice_hours' => '0',
            'max_advance_days' => '60',
            'buffer_minutes' => '10',
        ];
    }

    private function service(int $tenantId, string $name, int $duration, int $price = 10000): int
    {
        return $this->insert('reservation_services', [
            'tenant_id' => $tenantId, 'name' => $name, 'duration_minutes' => $duration,
            'price_minor' => $price, 'deposit_minor' => 0, 'is_active' => 1,
        ]);
    }

    /**
     * @return list<string>
     */
    private function slots(Kernel $kernel, int $service, string $date, ?int $staff = null, ?int $serviceOverride = null): array
    {
        $path = '/reservation/creneaux?prestation=' . ($serviceOverride ?? $service) . '&date=' . $date . ($staff !== null ? '&personne=' . $staff : '');
        $data = json_decode($this->fetch($kernel, $path)->content(), true);

        return array_map(static fn (array $slot): string => (string) $slot['time'], is_array($data['slots'] ?? null) ? $data['slots'] : []);
    }

    /** GET avec une chaîne de requête (le chemin et les paramètres sont passés séparément à la requête). */
    private function fetch(Kernel $kernel, string $url): Response
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $kernel->handle(new Request('GET', $path, $query));
    }

    private function nextWeekday(int $weekday): string
    {
        $day = strtotime('+2 days', strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC'));
        while ((int) gmdate('N', $day) !== $weekday) {
            $day += 86400;
        }

        return gmdate('Y-m-d', $day);
    }

    /**
     * @param list<int|string> $params
     * @return array<string, mixed>
     */
    private function row(string $sql, array $params): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }
}
