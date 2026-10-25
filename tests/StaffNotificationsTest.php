<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Tenant\Sector;
use Sesam\Services\DailyNotifications;
use Sesam\Services\Notifier;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Notifications de l'équipe SESAM : chaque rôle reçoit ce qui le concerne (commercial, support, finance, admin),
 * rappels du matin (essais perdus, impayés, demandes de devis, agence), encaissements, sécurité.
 */
final class StaffNotificationsTest extends IntegrationTestCase
{
    public function testEachRoleGetsWhatConcernsIt(): void
    {
        $kernel = $this->kernel();
        $commercial = $this->userId($this->user([Role::COMMERCIAL], null));
        $support = $this->userId($this->user([Role::SUPPORT], null));
        $finance = $this->userId($this->user([Role::FINANCE], null));

        // Inscription depuis le site : le support est prévenu ; le commercial (sans accès aux commerces) non.
        $email = 'p20-staff-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->submit($kernel, '/commencer', [
            'sector' => Sector::RESERVATION, 'full_name' => 'Mariama p20', 'business_name' => 'Salon p20 équipe', 'phone' => '771234567',
            'email' => $email, 'password' => 'SignupPass-2026', 'password_confirm' => 'SignupPass-2026', 'terms' => '1',
        ], '/commencer');
        $this->track((int) $this->value('SELECT tenant_id FROM users WHERE email = ?', [$email]), $email);
        $this->logout($kernel);
        $this->assertSame('Nouvelle inscription : Salon p20 équipe', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.signup' ORDER BY id DESC LIMIT 1", [$support]));
        $this->assertFalse($this->value("SELECT id FROM notifications WHERE user_id = ? AND category = 'sesam.signup'", [$commercial]));

        // Encaissement saisi dans la console par un admin : la finance est prévenue, pas l'admin lui-même.
        $shop = $this->tenant(Sector::BOUTIQUE);
        $adminEmail = $this->loginAs($kernel, [Role::ADMIN], null);
        $admin = $this->userId($adminEmail);
        $this->submit($kernel, '/commerces/' . $shop . '/encaissement', [
            'amount' => '15000', 'months' => '1', 'paid_on' => gmdate('Y-m-d'), 'method' => 'wave', 'reference' => 'p20', 'note' => '',
        ], '/commerces/' . $shop);
        $this->assertStringStartsWith('Paiement encaissé : ', (string) $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.payment' ORDER BY id DESC LIMIT 1", [$finance]));
        $this->assertFalse($this->value("SELECT id FROM notifications WHERE user_id = ? AND category = 'sesam.payment' AND title LIKE 'Paiement encaissé%'", [$admin]));

        // Nouveau compte dans l'équipe : les autres admins sont prévenus (sécurité).
        $otherAdmin = $this->userId($this->user([Role::SUPER_ADMIN], null));
        $newEmail = 'p20-new-' . bin2hex(random_bytes(3)) . '@example.com';
        try {
            $this->submit($kernel, '/utilisateurs', [
                'full_name' => 'Nouveau p20', 'email' => $newEmail, 'password' => 'NouveauPass-2026!', 'is_active' => '1', 'roles' => [Role::SUPPORT],
            ], '/utilisateurs/nouveau');
            $this->assertSame('Nouveau compte dans l\'équipe : Nouveau p20', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.security' ORDER BY id DESC LIMIT 1", [$otherAdmin]));
            $this->assertFalse($this->value("SELECT id FROM notifications WHERE user_id = ? AND category = 'sesam.security'", [$admin]));
        } finally {
            $newId = $this->value('SELECT id FROM users WHERE email = ?', [$newEmail]);
            if ($newId !== false) {
                $this->pdo()->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$newId]);
                $this->pdo()->prepare('DELETE FROM users WHERE id = ?')->execute([$newId]);
            }
        }
        $this->logout($kernel);

        // Cinq mots de passe faux sur un compte de l'équipe : connexion bloquée, alerte unique.
        $ip = '198.51.100.251';
        for ($i = 0; $i < 6; $i++) {
            $kernel->handle($this->post('/connexion', ['_csrf' => $this->csrf($kernel, '/connexion'), 'email' => $adminEmail, 'password' => 'mauvais-' . $i], $ip));
        }
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND category = 'sesam.security' AND title = 'Connexion bloquée sur un compte de l''équipe'", [$otherAdmin]));
    }

    public function testMorningRemindersForTheTeam(): void
    {
        $commercial = $this->userId($this->user([Role::COMMERCIAL], null));
        $support = $this->userId($this->user([Role::SUPPORT], null));
        $finance = $this->userId($this->user([Role::FINANCE], null));
        $monday = gmdate('Y-m-d', strtotime('next monday'));

        // Essai terminé la veille sans abonnement, abonnement impayé depuis 10 jours.
        $lost = $this->tenant(Sector::BOUTIQUE);
        $this->insert('tenant_subscriptions', ['tenant_id' => $lost, 'status' => 'trial', 'period_start' => gmdate('Y-m-d', strtotime($monday . ' -31 days')), 'period_end' => gmdate('Y-m-d', strtotime($monday . ' -1 day'))]);
        $late = $this->tenant(Sector::LOYER);
        $this->insert('tenant_subscriptions', ['tenant_id' => $late, 'status' => 'active', 'monthly_amount_minor' => 15000, 'period_start' => gmdate('Y-m-d', strtotime($monday . ' -40 days')), 'period_end' => gmdate('Y-m-d', strtotime($monday . ' -10 days'))]);

        // Agence : demande non traitée, prospect à relancer, devis sans réponse, facture en retard.
        $request = $this->insert('contact_requests', ['full_name' => 'Demande p20', 'email' => 'p20@example.com', 'phone' => '770000000', 'activity' => 'autre', 'message' => 'Site vitrine', 'created_at' => gmdate('Y-m-d H:i:s', strtotime('-3 days'))]);
        $prospect = $this->insert('agency_prospects', ['full_name' => 'Prospect p20', 'business_name' => 'Boulangerie p20', 'activity' => 'autre', 'source' => 'manual', 'stage' => 'contacte', 'next_action_on' => gmdate('Y-m-d')]);
        $quote = $this->insert('agency_quotes', ['prospect_id' => $prospect, 'reference' => 'DV-P20-' . random_int(100, 999), 'offer' => 'vitrine', 'site_path' => 'sur-mesure', 'amount' => 300000, 'status' => 'sent', 'sent_at' => gmdate('Y-m-d H:i:s', strtotime('-10 days'))]);
        $project = $this->insert('agency_projects', ['prospect_id' => $prospect, 'quote_id' => $quote, 'title' => 'Site p20', 'offer' => 'vitrine', 'amount' => 300000, 'status' => 'in_progress']);
        $this->insert('agency_invoices', ['project_id' => $project, 'reference' => 'FA-P20-' . random_int(100, 999), 'label' => 'Acompte', 'amount' => 150000, 'status' => 'issued', 'due_on' => gmdate('Y-m-d', strtotime('-2 days'))]);

        try {
            $this->kernel();
            $notifier = Notifier::current();
            $this->assertInstanceOf(Notifier::class, $notifier);
            $jobs = new DailyNotifications($this->config()['db'], $notifier, sys_get_temp_dir() . '/sesam-daily-test.txt');
            $jobs->run($monday);
            $jobs->run($monday);

            $this->assertStringContainsString('essai(s) terminés sans abonnement', (string) $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.lost'", [$support]));
            $this->assertStringContainsString('impayés depuis plus d\'une semaine', (string) $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.overdue' ORDER BY id DESC LIMIT 1", [$finance]));
            $this->assertStringContainsString('Demande p20', (string) $this->value("SELECT body FROM notifications WHERE user_id = ? AND category = 'sesam.quote' ORDER BY id DESC LIMIT 1", [$commercial]));
            $this->assertStringContainsString('Boulangerie p20', (string) $this->value("SELECT body FROM notifications WHERE user_id = ? AND category = 'sesam.agency' AND title LIKE '%à relancer%'", [$commercial]));
            $this->assertStringContainsString('devis sans réponse', (string) $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.agency' AND title LIKE '%devis%'", [$commercial]));
            $this->assertStringContainsString('facture(s) agence en retard', (string) $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.invoices'", [$finance]));
            $this->assertFalse($this->value("SELECT id FROM notifications WHERE user_id = ? AND category = 'sesam.invoices'", [$commercial]), 'Le commercial ne gère pas les factures.');
            $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND category = 'sesam.lost'", [$support]), 'Une seule fois par jour.');
        } finally {
            $this->pdo()->prepare('DELETE FROM agency_prospects WHERE id = ?')->execute([$prospect]);
            $this->pdo()->prepare('DELETE FROM contact_requests WHERE id = ?')->execute([$request]);
        }
    }
}
