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
 * Sesam Immo : biens et logements, bail avec nouveau locataire, échéancier, paiements partiels, quittance,
 * relances automatiques et manuelles, alertes, interventions, réglages et fin de bail.
 */
final class ImmoModuleTest extends IntegrationTestCase
{
    public function testOtherSectorsCannotOpenImmo(): void
    {
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $this->tenant(Sector::BOUTIQUE));
        foreach (['/immo/loyers', '/immo/biens', '/immo/baux', '/immo/locataires', '/immo/parametres'] as $path) {
            $this->assertSame(403, $this->get($kernel, $path)->status(), $path);
        }
        // Les anciennes adresses du module Loyer mènent aux nouvelles.
        $legacy = $this->get($kernel, '/loyer/echeances');
        $this->assertSame(301, $legacy->status());
        $this->assertSame('/immo/loyers', $legacy->headers()['Location'] ?? '');
    }

    public function testFullRentalCycleWithPartialPaymentsReceiptsAndEndOfLease(): void
    {
        $tenantId = $this->tenant(Sector::LOYER);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        // Bien puis logement.
        $property = $this->submit($kernel, '/immo/biens', ['name' => 'Immeuble p20', 'kind' => 'immeuble', 'address' => 'Mermoz', 'city' => 'Dakar'], '/immo/biens/nouveau');
        $this->assertSame(302, $property->status());
        $propertyId = (int) $this->value('SELECT id FROM loyer_properties WHERE tenant_id = ?', [$tenantId]);
        $this->assertSame(422, $this->submit($kernel, '/immo/biens/' . $propertyId . '/logements', ['label' => '', 'kind' => 'appartement'], '/immo/biens/' . $propertyId)->status());
        $this->submit($kernel, '/immo/biens/' . $propertyId . '/logements', [
            'label' => 'A1', 'kind' => 'appartement', 'rooms' => '3', 'surface_m2' => '70',
            'default_rent_minor' => '100 000', 'default_charges_minor' => '10000', 'default_deposit_minor' => '200000',
        ], '/immo/biens/' . $propertyId);
        $unitId = (int) $this->value('SELECT id FROM loyer_units WHERE property_id = ?', [$propertyId]);
        $this->assertSame(110000, (int) $this->value('SELECT default_rent_minor + default_charges_minor FROM loyer_units WHERE id = ?', [$unitId]));

        // Bail avec un nouveau locataire saisi sur place, entrée il y a deux mois.
        $start = gmdate('Y-m-01', (int) strtotime('first day of -2 months'));
        $missing = $this->submit($kernel, '/immo/baux', ['unit_id' => (string) $unitId, 'full_name' => 'Ousmane p20', 'phone' => '', 'rent_minor' => '100000', 'starts_on' => $start, 'due_day' => '5', 'duration_months' => '12'], '/immo/baux/nouveau');
        $this->assertSame(422, $missing->status());
        $lease = $this->submit($kernel, '/immo/baux', [
            'unit_id' => (string) $unitId, 'full_name' => 'Ousmane p20', 'phone' => '77 123 45 67',
            'rent_minor' => '100000', 'charges_minor' => '10000', 'deposit_minor' => '200000',
            'starts_on' => $start, 'due_day' => '5', 'duration_months' => '12', 'notice_months' => '1',
        ], '/immo/baux/nouveau');
        $this->assertSame(302, $lease->status());
        $leaseId = (int) $this->value('SELECT id FROM loyer_leases WHERE tenant_id = ?', [$tenantId]);
        $this->assertStringStartsWith('BAIL-', (string) $this->value('SELECT reference FROM loyer_leases WHERE id = ?', [$leaseId]));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM clients WHERE tenant_id = ? AND full_name = 'Ousmane p20'", [$tenantId]));
        // Échéancier : du mois d'entrée jusqu'au mois prochain, loyer + charges.
        $this->assertSame(4, (int) $this->value('SELECT COUNT(*) FROM loyer_rent_dues WHERE lease_id = ?', [$leaseId]));
        $this->assertSame(110000, (int) $this->value('SELECT amount_minor FROM loyer_rent_dues WHERE lease_id = ? LIMIT 1', [$leaseId]));
        // Le logement n'est plus proposé pour un autre bail.
        $this->assertStringNotContainsString('value="' . $unitId . '"', $this->get($kernel, '/immo/baux/nouveau')->content());

        // Accueil : impayés, caution non versée, occupation.
        $home = html_entity_decode($this->get($kernel, '/espace')->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('loyer(s) en retard', $home);
        $this->assertStringContainsString('caution(s) non versée(s)', $home);
        $this->assertStringContainsString('100 %', $home);

        // Paiement partiel puis solde sur le loyer le plus ancien.
        $dueId = (int) $this->value('SELECT id FROM loyer_rent_dues WHERE lease_id = ? ORDER BY due_date LIMIT 1', [$leaseId]);
        $duePage = '/immo/loyers/' . $dueId;
        $this->assertSame(422, $this->submit($kernel, $duePage . '/paiement', ['amount' => '200000', 'method' => 'especes', 'paid_on' => gmdate('Y-m-d')], $duePage)->status());
        $this->assertSame(422, $this->submit($kernel, $duePage . '/paiement', ['amount' => '1000', 'method' => 'especes', 'paid_on' => gmdate('Y-m-d')], $duePage)->status());
        $this->assertSame(302, $this->submit($kernel, $duePage . '/paiement', ['amount' => '60 000', 'method' => 'wave', 'paid_on' => gmdate('Y-m-d'), 'reference' => 'TX1'], $duePage)->status());
        $this->assertSame('partial', $this->value('SELECT status FROM loyer_rent_dues WHERE id = ?', [$dueId]));
        $this->assertSame(60000, (int) $this->value('SELECT paid_minor FROM loyer_rent_dues WHERE id = ?', [$dueId]));
        $partials = html_entity_decode($this->fetch($kernel, '/immo/loyers', ['vue' => 'partiels'])->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Partiel · en retard', $partials);
        $this->assertStringContainsString('60 000 FCFA payés sur 110 000 FCFA', $partials);
        $firstPayment = (int) $this->value('SELECT id FROM immo_payments WHERE due_id = ? ORDER BY id LIMIT 1', [$dueId]);
        $receipt = html_entity_decode($this->get($kernel, '/immo/quittances/' . $firstPayment)->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Reçu de paiement partiel', $receipt);
        $this->assertStringContainsString('50 000 FCFA', $receipt);

        $this->assertSame(302, $this->submit($kernel, $duePage . '/paiement', ['amount' => '50000', 'method' => 'especes', 'paid_on' => gmdate('Y-m-d')], $duePage)->status());
        $this->assertSame('paid', $this->value('SELECT status FROM loyer_rent_dues WHERE id = ?', [$dueId]));
        $lastPayment = (int) $this->value('SELECT id FROM immo_payments WHERE due_id = ? ORDER BY id DESC LIMIT 1', [$dueId]);
        $quittance = html_entity_decode($this->get($kernel, '/immo/quittances/' . $lastPayment)->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Quittance de loyer', $quittance);
        $this->assertStringContainsString('110 000 FCFA', $quittance);
        $this->assertSame(422, $this->submit($kernel, $duePage . '/paiement', ['amount' => '1000', 'method' => 'especes', 'paid_on' => gmdate('Y-m-d')], $duePage)->status());

        // Partiels désactivés dans les réglages : il faut encaisser le reste en entier.
        $settings = $this->settings(['allow_partial' => '']);
        $this->assertSame(302, $this->submit($kernel, '/immo/parametres', $settings, '/immo/parametres')->status());
        $secondDue = (int) $this->value("SELECT id FROM loyer_rent_dues WHERE lease_id = ? AND status = 'pending' ORDER BY due_date LIMIT 1", [$leaseId]);
        $refused = $this->submit($kernel, '/immo/loyers/' . $secondDue . '/paiement', ['amount' => '50000', 'method' => 'especes', 'paid_on' => gmdate('Y-m-d')], '/immo/loyers/' . $secondDue);
        $this->assertSame(422, $refused->status());
        $this->assertStringContainsString('désactivés', html_entity_decode($refused->content(), ENT_QUOTES, 'UTF-8'));

        // Relance manuelle (message modifiable) puis journal. WhatsApp est simulé : le locataire qui a un e-mail
        // reçoit la relance par e-mail (secours).
        $this->pdo()->prepare('UPDATE clients c JOIN loyer_leases l ON l.client_id = c.id SET c.email = ? WHERE l.id = ?')->execute(['locataire-p20@example.com', $leaseId]);
        $this->assertSame(302, $this->submit($kernel, '/immo/loyers/' . $secondDue . '/relance', ['message' => 'Bonjour, merci de régler le loyer.'], '/immo/loyers/' . $secondDue)->status());
        $this->assertSame('manual', $this->value('SELECT kind FROM immo_reminders WHERE due_id = ?', [$secondDue]));
        $this->assertSame('email', $this->value('SELECT channel FROM immo_reminders WHERE due_id = ?', [$secondDue]));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM message_deliveries WHERE channel = 'email' AND recipient = ? AND body LIKE '%merci de régler le loyer%'", ['locataire-p20@example.com']));
        $this->assertStringContainsString('Relance manuelle', html_entity_decode($this->get($kernel, '/immo/relances')->content(), ENT_QUOTES, 'UTF-8'));

        // Caution versée, puis fin de bail : loyers futurs annulés, logement libre.
        $this->submit($kernel, '/immo/baux/' . $leaseId . '/caution', ['deposit_paid_on' => gmdate('Y-m-d')], '/immo/baux/' . $leaseId);
        $this->assertNotNull($this->value('SELECT deposit_paid_on FROM loyer_leases WHERE id = ?', [$leaseId]));
        $this->assertSame(302, $this->submit($kernel, '/immo/baux/' . $leaseId . '/terminer', ['ended_on' => gmdate('Y-m-d'), 'end_reason' => 'Déménagement'], '/immo/baux/' . $leaseId)->status());
        $this->assertSame('ended', $this->value('SELECT status FROM loyer_leases WHERE id = ?', [$leaseId]));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM loyer_rent_dues WHERE lease_id = ? AND status = 'cancelled'", [$leaseId]));
        $this->assertStringContainsString('value="' . $unitId . '"', $this->get($kernel, '/immo/baux/nouveau')->content());
    }

    public function testAutomaticRemindersFollowSettingsAndAreSentOncePerDay(): void
    {
        $tenantId = $this->tenant(Sector::LOYER);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $this->submit($kernel, '/immo/parametres', $this->settings(['remind_before_days' => '3', 'remind_after_days' => '7']), '/immo/parametres');
        $leaseId = $this->lease($tenantId, gmdate('Y-m-d', (int) strtotime('-2 months')));
        // Échéances ciblées : une dans 3 jours (rappel avant), une il y a 7 jours (retard), une hors règle.
        foreach (['+3 days', '-7 days', '-20 days'] as $offset) {
            $this->insert('loyer_rent_dues', [
                'tenant_id' => $tenantId, 'lease_id' => $leaseId, 'due_date' => gmdate('Y-m-d', (int) strtotime($offset)),
                'amount_minor' => 80000, 'rent_part_minor' => 80000, 'status' => 'pending',
            ]);
        }

        $run = $this->submit($kernel, '/immo/relances/lancer', [], '/immo/relances');
        $this->assertSame(302, $run->status());
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM immo_reminders WHERE tenant_id = ? AND kind = 'before'", [$tenantId]));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM immo_reminders WHERE tenant_id = ? AND kind = 'late'", [$tenantId]));
        $this->assertSame(2, (int) $this->value('SELECT COUNT(*) FROM immo_reminders WHERE tenant_id = ?', [$tenantId]));
        // Relancer le même jour n'envoie rien de plus.
        $this->submit($kernel, '/immo/relances/lancer', [], '/immo/relances');
        $this->assertSame(2, (int) $this->value('SELECT COUNT(*) FROM immo_reminders WHERE tenant_id = ?', [$tenantId]));
        // Message invalide : la somme doit y figurer.
        $this->assertSame(422, $this->submit($kernel, '/immo/parametres', $this->settings(['reminder_message' => 'Bonjour {locataire}']), '/immo/parametres')->status());
    }

    public function testTenantFilesInterventionsAndStaffPermissions(): void
    {
        $tenantId = $this->tenant(Sector::LOYER);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $created = $this->submit($kernel, '/immo/locataires', [
            'full_name' => 'Awa p20', 'phone' => '770001122', 'id_type' => 'cni', 'id_number' => '123',
            'profession' => 'Infirmière', 'guarantor_name' => 'Moussa', 'guarantor_phone' => '770001133',
        ], '/immo/locataires/nouveau');
        $this->assertSame(302, $created->status());
        $clientId = (int) $this->value("SELECT id FROM clients WHERE tenant_id = ? AND full_name = 'Awa p20'", [$tenantId]);
        $file = html_entity_decode($this->get($kernel, '/immo/locataires/' . $clientId)->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Infirmière', $file);
        $this->assertStringContainsString('Moussa', $file);

        $leaseId = $this->lease($tenantId, gmdate('Y-m-01'));
        $unitId = (int) $this->value('SELECT unit_id FROM loyer_leases WHERE id = ?', [$leaseId]);
        $this->assertSame(302, $this->submit($kernel, '/immo/interventions', ['unit_id' => (string) $unitId, 'title' => 'Fuite p20', 'priority' => 'urgente'], '/immo/interventions')->status());
        $interventionId = (int) $this->value('SELECT id FROM immo_interventions WHERE tenant_id = ?', [$tenantId]);
        $this->assertStringContainsString('intervention(s) urgente(s)', html_entity_decode($this->get($kernel, '/espace')->content(), ENT_QUOTES, 'UTF-8'));
        $this->submit($kernel, '/immo/interventions/' . $interventionId, ['status' => 'terminee', 'cost_minor' => '25000'], '/immo/interventions');
        $this->assertSame('terminee', $this->value('SELECT status FROM immo_interventions WHERE id = ?', [$interventionId]));
        $this->assertSame(25000, (int) $this->value('SELECT cost_minor FROM immo_interventions WHERE id = ?', [$interventionId]));
        $this->logout($kernel);

        // Un employé encaisse et relance, mais ne touche ni aux biens, ni aux baux, ni aux réglages.
        $this->loginAs($kernel, [Role::STAFF], $tenantId);
        $this->assertSame(200, $this->get($kernel, '/immo/loyers')->status());
        $this->assertSame(403, $this->get($kernel, '/immo/biens/nouveau')->status());
        $this->assertSame(403, $this->get($kernel, '/immo/baux/nouveau')->status());
        $this->assertSame(403, $this->get($kernel, '/immo/parametres')->status());
    }

    public function testPropertyPhotoIsStoredAndServedOnPublicPortal(): void
    {
        $tenantId = $this->tenant(Sector::LOYER);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $form = $this->get($kernel, '/immo/biens/nouveau');
        $this->assertSame(200, $form->status());
        $this->assertStringContainsString('name="image"', $form->content());
        $this->assertStringContainsString('enctype="multipart/form-data"', $form->content());

        $png = $this->pngFile(640, 400);
        $saved = $this->submitWithFiles($kernel, '/immo/biens', [
            'name' => 'Villa photo p20',
            'kind' => 'villa',
            'address' => 'Almadies',
            'city' => 'Dakar',
        ], [
            'image' => [
                'name' => 'facade.png',
                'type' => 'image/png',
                'tmp_name' => $png,
                'error' => UPLOAD_ERR_OK,
                'size' => (int) filesize($png),
            ],
        ], '/immo/biens/nouveau');
        $this->assertSame(302, $saved->status());
        $propertyId = (int) $this->value('SELECT id FROM loyer_properties WHERE tenant_id = ? AND name = ?', [$tenantId, 'Villa photo p20']);
        $this->assertGreaterThan(0, $propertyId);
        $imagePath = (string) $this->value('SELECT image_path FROM loyer_properties WHERE id = ?', [$propertyId]);
        $this->assertMatchesRegularExpression('#^immo/' . $tenantId . '/[a-f0-9]{32}\.webp$#', $imagePath);
        $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $imagePath);
        $this->assertFileExists($absolute);

        $this->insert('loyer_units', [
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'label' => 'Villa entière',
            'default_rent_minor' => 450000,
            'is_active' => 1,
        ]);
        $slug = (string) $this->value('SELECT slug FROM tenants WHERE id = ?', [$tenantId]);
        $this->logout($kernel);

        $portal = $this->get($kernel, '/t/' . $slug);
        $this->assertSame(200, $portal->status());
        $this->assertStringContainsString('im-hero', $portal->content());
        $this->assertStringContainsString('Villa photo p20', $portal->content());
        $this->assertStringContainsString('/t/' . $slug . '/bien/' . $propertyId . '/image', $portal->content());

        $image = $this->get($kernel, '/t/' . $slug . '/bien/' . $propertyId . '/image');
        $this->assertSame(200, $image->status());
        $this->assertSame('image/webp', $image->headers()['Content-Type'] ?? '');
        $this->assertNotSame('', $image->content());

        $otherId = $this->tenant(Sector::LOYER);
        $otherSlug = (string) $this->value('SELECT slug FROM tenants WHERE id = ?', [$otherId]);
        $this->assertSame(404, $this->get($kernel, '/t/' . $otherSlug . '/bien/' . $propertyId . '/image')->status());

        if (is_file($absolute)) {
            unlink($absolute);
        }
        if (is_file($png)) {
            unlink($png);
        }
    }

    private function pngFile(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertInstanceOf(\GdImage::class, $image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 15, 159, 110));
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sesam-immo-' . bin2hex(random_bytes(4)) . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * @param array<string, string> $query
     */
    private function fetch(Kernel $kernel, string $path, array $query): Response
    {
        return $kernel->handle(new Request('GET', $path, $query));
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function settings(array $overrides): array
    {
        return $overrides + [
            'display_name' => 'Agence p20', 'whatsapp_phone' => '77 000 00 00', 'default_due_day' => '5', 'grace_days' => '3',
            'remind_before_days' => '3', 'remind_on_due' => '1', 'remind_after_days' => '3,7', 'auto_reminders' => '1',
            'reminder_message' => 'Bonjour {locataire}, reste {reste} pour {mois}.', 'allow_partial' => '1', 'min_partial_minor' => '5000',
            'lease_end_alert_days' => '60',
        ];
    }

    private function lease(int $tenantId, string $startsOn): int
    {
        $propertyId = $this->insert('loyer_properties', ['tenant_id' => $tenantId, 'name' => 'Résidence p20 ' . bin2hex(random_bytes(2))]);
        $unitId = $this->insert('loyer_units', ['tenant_id' => $tenantId, 'property_id' => $propertyId, 'label' => 'B' . random_int(1, 99), 'default_rent_minor' => 80000, 'is_active' => 1]);

        return $this->insert('loyer_leases', [
            'tenant_id' => $tenantId, 'unit_id' => $unitId, 'client_id' => $this->client($tenantId, 'Locataire p20', '770009988'),
            'rent_minor' => 80000, 'due_day' => 5, 'starts_on' => $startsOn, 'status' => 'active',
        ]);
    }
}
