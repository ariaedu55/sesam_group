<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Tenant\Sector;
use Sesam\Services\DailyNotifications;
use Sesam\Services\Notifier;
use Sesam\Services\ReminderPump;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Réservation : rappels aux clientes (la veille ou 3 h avant, remplacés ou annulés quand le rendez-vous change),
 * messages WhatsApp quand le salon confirme / déplace / annule, rappel du salon pour les demandes à confirmer.
 * Messagerie en mode simulation pendant les tests : rien ne part réellement.
 */
final class ReservationNotificationsTest extends IntegrationTestCase
{
    public function testRemindersFollowTheAppointmentAndTheClientIsKeptInformed(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $service = $this->insert('reservation_services', ['tenant_id' => $tenantId, 'name' => 'Brushing p20', 'duration_minutes' => 45, 'price_minor' => 8000, 'deposit_minor' => 0, 'is_active' => 1]);
        $day = $this->nextWeekday(2);
        $phone = '77' . random_int(1000000, 9999999);

        // Une employée (rôle « staff », sans droit d'envoi de messages) saisit puis confirme : le rappel part quand même.
        $this->loginAs($kernel, [Role::STAFF], $tenantId);
        $this->submit($kernel, '/reservation/rendez-vous', [
            'client_name' => 'Awa p20', 'client_phone' => $phone, 'service_id' => (string) $service, 'starts_at' => $day . 'T11:00',
        ], '/reservation/rendez-vous/nouveau');
        $id = (int) $this->value('SELECT id FROM reservation_appointments WHERE tenant_id = ?', [$tenantId]);
        $ref = 'rdv-' . $id;
        $this->assertSame(0, $this->reminders($tenantId, $ref, 'pending'), 'Pas de rappel tant que le salon n\'a pas confirmé.');

        $this->submit($kernel, '/reservation/rendez-vous/' . $id . '/confirmer', [], '/reservation/rendez-vous/' . $id);
        $this->assertSame(1, $this->reminders($tenantId, $ref, 'pending'));
        $this->assertSame(gmdate('Y-m-d H:i:s', strtotime($day . ' 11:00:00 UTC') - 86400), $this->value("SELECT send_at FROM message_reminders WHERE tenant_id = ? AND reference = ? AND status = 'pending'", [$tenantId, $ref]));
        $this->assertStringContainsString('est confirmé', $this->lastMessage($tenantId));

        // Déplacé : l'ancien rappel est annulé, un seul nouveau rappel, et la cliente est prévenue.
        $this->submit($kernel, '/reservation/rendez-vous/' . $id . '/reprogrammer', ['starts_at' => $day . 'T15:00'], '/reservation/rendez-vous/' . $id . '/reprogrammer');
        $this->assertSame(1, $this->reminders($tenantId, $ref, 'pending'));
        $this->assertSame(1, $this->reminders($tenantId, $ref, 'cancelled'));
        $this->assertSame(gmdate('Y-m-d H:i:s', strtotime($day . ' 15:00:00 UTC') - 86400), $this->value("SELECT send_at FROM message_reminders WHERE tenant_id = ? AND reference = ? AND status = 'pending'", [$tenantId, $ref]));
        $this->assertStringContainsString('est déplacé au', $this->lastMessage($tenantId));

        // Annulé : plus aucun rappel en attente, la cliente est prévenue.
        $this->submit($kernel, '/reservation/rendez-vous/' . $id . '/annuler', [], '/reservation/rendez-vous/' . $id);
        $this->assertSame(0, $this->reminders($tenantId, $ref, 'pending'));
        $this->assertStringContainsString('est annulé', $this->lastMessage($tenantId));

        // Rendez-vous pris moins de 24 h avant : rappel 3 h avant.
        $soon = gmdate('Y-m-d H:i:s', (int) (ceil((time() + 8 * 3600) / 3600) * 3600));
        $soonId = $this->insert('reservation_appointments', [
            'tenant_id' => $tenantId, 'client_id' => $this->client($tenantId, 'Bineta p20', '771112233'), 'service_id' => $service, 'starts_at' => $soon,
            'duration_minutes' => 45, 'price_minor' => 8000, 'status' => 'pending', 'source' => 'manual', 'deposit_amount_minor' => 0,
        ]);
        $this->submit($kernel, '/reservation/rendez-vous/' . $soonId . '/confirmer', [], '/reservation/rendez-vous/' . $soonId);
        $this->assertSame(gmdate('Y-m-d H:i:s', strtotime($soon . ' UTC') - 3 * 3600), $this->value("SELECT send_at FROM message_reminders WHERE tenant_id = ? AND reference = ? AND status = 'pending'", [$tenantId, 'rdv-' . $soonId]));

        // L'heure arrive : le rappel est envoyé (une seule fois) et reste dans l'historique des envois.
        $this->pdo()->prepare("UPDATE message_reminders SET send_at = ? WHERE tenant_id = ? AND reference = ?")->execute([gmdate('Y-m-d H:i:s', time() - 60), $tenantId, 'rdv-' . $soonId]);
        $pump = ReminderPump::current();
        $this->assertInstanceOf(ReminderPump::class, $pump);
        $this->assertGreaterThanOrEqual(1, $pump->run());
        $this->assertSame(1, $this->reminders($tenantId, 'rdv-' . $soonId, 'processed'));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM message_deliveries WHERE tenant_id = ? AND template_slug = 'rdv_rappel'", [$tenantId]));
        $pump->run();
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM message_deliveries WHERE tenant_id = ? AND template_slug = 'rdv_rappel'", [$tenantId]));
    }

    public function testOnlineBookingGetsAReminderAndTheSalonIsAskedToConfirm(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $owner = $this->userId($this->user([Role::TENANT_OWNER], $tenantId));
        $kernel = $this->kernel();
        $service = $this->insert('reservation_services', ['tenant_id' => $tenantId, 'name' => 'Tresses p20', 'duration_minutes' => 60, 'price_minor' => 15000, 'deposit_minor' => 0, 'is_active' => 1]);
        $this->insert('reservation_settings', ['tenant_id' => $tenantId, 'is_open' => 1, 'auto_confirm' => 1, 'min_notice_hours' => 0, 'max_advance_days' => 60, 'slot_step_minutes' => 30, 'buffer_minutes' => 0]);
        $slug = $this->slug($tenantId);
        $day = $this->nextWeekday(3);

        // Réservation en ligne confirmée automatiquement → rappel programmé la veille.
        $kernel->handle($this->post('/t/' . $slug, [
            '_csrf' => $this->csrf($kernel, '/t/' . $slug),
            'service_id' => (string) $service, 'starts_at' => $day . 'T10:00', 'full_name' => 'Cliente p20', 'phone' => '770009988',
        ]));
        $id = (int) $this->value('SELECT id FROM reservation_appointments WHERE tenant_id = ?', [$tenantId]);
        $this->assertSame('confirmed', $this->value('SELECT status FROM reservation_appointments WHERE id = ?', [$id]));
        $this->assertSame(1, $this->reminders($tenantId, 'rdv-' . $id, 'pending'));

        // Une demande reste « à confirmer » : le salon reçoit le rappel du matin (une seule fois).
        $this->insert('reservation_appointments', [
            'tenant_id' => $tenantId, 'client_id' => $this->client($tenantId), 'service_id' => $service, 'starts_at' => $day . ' 16:00:00',
            'duration_minutes' => 60, 'price_minor' => 15000, 'status' => 'pending', 'source' => 'online', 'deposit_amount_minor' => 0,
        ]);
        $notifier = Notifier::current();
        $this->assertInstanceOf(Notifier::class, $notifier);
        $jobs = new DailyNotifications($this->config()['db'], $notifier, sys_get_temp_dir() . '/sesam-daily-test.txt');
        $jobs->run();
        $jobs->run();
        $this->assertSame('1 réservation(s) à confirmer', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'reservation.pending'", [$owner]));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND category = 'reservation.pending'", [$owner]));
    }

    public function testEmailTakesOverWhenWhatsAppCannotDeliver(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $mailer = \Sesam\Core\Mail\Mailer::current();
        $this->assertInstanceOf(\Sesam\Core\Mail\Mailer::class, $mailer);
        $service = $this->insert('reservation_services', ['tenant_id' => $tenantId, 'name' => 'Soin p20', 'duration_minutes' => 60, 'price_minor' => 12000, 'deposit_minor' => 0, 'is_active' => 1]);
        $this->insert('reservation_settings', ['tenant_id' => $tenantId, 'is_open' => 1, 'auto_confirm' => 0, 'min_notice_hours' => 0, 'max_advance_days' => 60, 'slot_step_minutes' => 30, 'buffer_minutes' => 0]);
        $slug = $this->slug($tenantId);
        $day = $this->nextWeekday(4);
        $email = 'cliente-p20-' . bin2hex(random_bytes(3)) . '@example.com';

        // Réservation en ligne avec e-mail : WhatsApp est simulé, la confirmation part aussi par e-mail.
        $kernel->handle($this->post('/t/' . $slug, [
            '_csrf' => $this->csrf($kernel, '/t/' . $slug),
            'service_id' => (string) $service, 'starts_at' => $day . 'T10:00', 'full_name' => 'Cliente Mail p20', 'phone' => '770004455', 'email' => $email,
        ]));
        $mails = array_values(array_filter($mailer->outbox(), static fn (array $mail): bool => $mail['to'] === $email));
        $this->assertCount(1, $mails);
        $this->assertStringContainsString('Demande de rendez-vous envoyée', $mails[0]['subject']);

        // Le salon confirme : message de confirmation par e-mail aussi.
        $id = (int) $this->value('SELECT id FROM reservation_appointments WHERE tenant_id = ?', [$tenantId]);
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $this->submit($kernel, '/reservation/rendez-vous/' . $id . '/confirmer', [], '/reservation/rendez-vous/' . $id);
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM message_deliveries WHERE tenant_id = ? AND channel = 'email' AND recipient = ? AND body LIKE '%est confirmé%'", [$tenantId, $email]));

        // Le rappel de la veille : WhatsApp ne le remet pas → il part par e-mail.
        $this->pdo()->prepare('UPDATE message_reminders SET send_at = ? WHERE tenant_id = ? AND reference = ?')->execute([gmdate('Y-m-d H:i:s', time() - 60), $tenantId, 'rdv-' . $id]);
        $pump = ReminderPump::current();
        $this->assertInstanceOf(ReminderPump::class, $pump);
        $pump->run();
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM message_deliveries WHERE tenant_id = ? AND channel = 'email' AND template_slug = 'rdv_rappel' AND recipient = ?", [$tenantId, $email]));
        $this->assertSame('Rappel de votre rendez-vous', $this->value("SELECT subject FROM message_deliveries WHERE tenant_id = ? AND channel = 'email' AND template_slug = 'rdv_rappel'", [$tenantId]));
    }

    private function reminders(int $tenantId, string $reference, string $status): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM message_reminders WHERE tenant_id = ? AND reference = ? AND status = ?', [$tenantId, $reference, $status]);
    }

    private function lastMessage(int $tenantId): string
    {
        return (string) $this->value("SELECT body FROM message_deliveries WHERE tenant_id = ? AND template_slug = 'portal_confirm' ORDER BY id DESC LIMIT 1", [$tenantId]);
    }

    private function nextWeekday(int $weekday): string
    {
        $day = strtotime('+2 days', strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC'));
        while ((int) gmdate('N', $day) !== $weekday) {
            $day += 86400;
        }

        return gmdate('Y-m-d', $day);
    }
}
