<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Services\DailyNotifications;
use Sesam\Services\Notifier;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Notifications : appareils, préférences, événements des commerces et de la console SESAM, rappels quotidiens.
 * L'envoi push est en mode journal pendant les tests (PUSH_DRIVER_OVERRIDE=log) : rien ne part chez Google.
 */
final class NotificationsTest extends IntegrationTestCase
{
    public function testDeviceRegistrationPreferencesAndTestPush(): void
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE);
        $kernel = $this->kernel();
        $email = $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $userId = $this->userId($email);
        $notifier = Notifier::current();
        $this->assertInstanceOf(Notifier::class, $notifier);
        $this->assertFalse($notifier->isLive(), 'Les tests ne doivent jamais envoyer de vrai push.');

        // Service worker servi depuis la racine, avec la configuration publique Firebase.
        $sw = $this->get($kernel, '/firebase-messaging-sw.js');
        $this->assertSame(200, $sw->status());
        $this->assertStringContainsString('application/javascript', $sw->headers()['Content-Type'] ?? '');
        $this->assertStringContainsString('onBackgroundMessage', $sw->content());
        $this->assertStringContainsString('"projectId":"sesam-e1c82"', $sw->content());

        $page = $this->get($kernel, '/espace');
        $this->assertStringContainsString('data-push', $page->content());
        $this->assertStringContainsString('firebase-messaging-compat.js', $page->content());

        $this->assertSame(422, $this->submit($kernel, '/notifications/appareils', ['token' => 'mauvais jeton <script>'], '/espace')->status());
        $token = 'p20-token:' . bin2hex(random_bytes(20));
        $this->assertSame(200, $this->submit($kernel, '/notifications/appareils', ['token' => $token], '/espace')->status());
        $this->submit($kernel, '/notifications/appareils', ['token' => $token], '/espace');
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM push_devices WHERE user_id = ?', [$userId]));

        $this->submit($kernel, '/notifications/test', [], '/notifications/reglages');
        $this->assertSame('sent', $this->value("SELECT push_status FROM notifications WHERE user_id = ? AND category = 'general'", [$userId]));
        $sent = $notifier->sent();
        $this->assertSame($token, $sent[array_key_last($sent)]['token'] ?? null);

        // Commandes : rien sur l'appareil, mais toujours dans la cloche.
        $this->submit($kernel, '/notifications/reglages', ['in_app_boutique_order' => '1', 'push_boutique_order' => '', 'in_app_general' => '1', 'push_general' => '1'], '/notifications/reglages');
        $before = count($notifier->sent());
        $notifier->toTenant($tenantId, 'boutique.order', 'Nouvelle commande test', 'Awa · 2 articles', '/boutique/commandes');
        $this->assertCount($before, $notifier->sent());
        $this->assertSame('off', $this->value("SELECT push_status FROM notifications WHERE user_id = ? AND category = 'boutique.order'", [$userId]));

        $list = html_entity_decode($this->get($kernel, '/notifications')->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Nouvelle commande test', $list);
        $this->assertStringContainsString('Nouvelles commandes', $list);
        $this->submit($kernel, '/notifications/tout-lu', [], '/notifications');
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]));

        $this->submit($kernel, '/notifications/appareils/oublier', ['token' => $token], '/espace');
        $this->assertSame(0, (int) $this->value('SELECT COUNT(*) FROM push_devices WHERE user_id = ?', [$userId]));
    }

    public function testBusinessEventsNotifyTheRightPeople(): void
    {
        $kernel = $this->kernel();
        $shop = $this->tenant(Sector::BOUTIQUE);
        $owner = $this->userId($this->user([Role::TENANT_OWNER], $shop));
        $other = $this->userId($this->user([Role::TENANT_OWNER], $this->tenant(Sector::BOUTIQUE)));
        $this->insert('boutique_settings', ['tenant_id' => $shop, 'whatsapp_phone' => '221770000000', 'is_open' => 1]);
        $productId = $this->insert('boutique_products', ['tenant_id' => $shop, 'name' => 'Robe p20', 'price_minor' => 10000, 'track_stock' => 1, 'stock_quantity' => 3, 'low_stock_threshold' => 2, 'is_published' => 1]);

        // Commande depuis la boutique en ligne → le commerçant est prévenu, pas l'autre commerce.
        $slug = $this->slug($shop);
        $order = $kernel->handle($this->post('/t/' . $slug . '/commander', [
            '_csrf' => $this->csrf($kernel, '/t/' . $slug),
            'items' => [(string) $productId => '2'],
            'customer_name' => 'Khady p20',
            'customer_phone' => '770001234',
        ]));
        $this->assertSame(303, $order->status());
        $this->assertStringStartsWith('Nouvelle commande', (string) $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'boutique.order'", [$owner]));
        $this->assertFalse($this->value("SELECT id FROM notifications WHERE user_id = ? AND category = 'boutique.order'", [$other]));

        // Confirmation : le stock passe sous le seuil → alerte stock bas.
        $this->loginAs($kernel, [Role::MANAGER], $shop);
        $orderId = (int) $this->value('SELECT id FROM boutique_orders WHERE tenant_id = ?', [$shop]);
        $this->submit($kernel, '/boutique/commandes/' . $orderId . '/statut', ['status' => 'confirmed'], '/boutique/commandes/' . $orderId);
        $this->assertSame(1, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$productId]));
        $this->assertSame('Stock bas : Robe p20', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'boutique.stock'", [$owner]));
        $this->logout($kernel);

        // Inscription depuis le site → le personnel SESAM est prévenu.
        $staff = $this->userId($this->user([Role::SUPER_ADMIN], null));
        $email = 'p20-notif-' . bin2hex(random_bytes(3)) . '@example.com';
        $this->submit($kernel, '/commencer', [
            'sector' => Sector::LOYER, 'full_name' => 'Fatou p20', 'business_name' => 'Agence p20', 'phone' => '771234567',
            'email' => $email, 'password' => 'SignupPass-2026', 'password_confirm' => 'SignupPass-2026', 'terms' => '1',
        ], '/commencer');
        $this->track((int) $this->value('SELECT tenant_id FROM users WHERE email = ?', [$email]), $email);
        $this->assertSame('Nouvelle inscription : Agence p20', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.signup' ORDER BY id DESC LIMIT 1", [$staff]));
        $this->assertNull($this->value("SELECT tenant_id FROM notifications WHERE user_id = ? AND category = 'sesam.signup' ORDER BY id DESC LIMIT 1", [$staff]));
    }

    public function testShopNotificationsCoverStockOrdersAndSubscription(): void
    {
        $kernel = $this->kernel();
        $shop = $this->tenant(Sector::BOUTIQUE);
        $owner = $this->userId($this->user([Role::TENANT_OWNER], $shop));
        $this->insert('boutique_settings', ['tenant_id' => $shop, 'whatsapp_phone' => '221770000000', 'is_open' => 1]);
        $robe = $this->insert('boutique_products', ['tenant_id' => $shop, 'name' => 'Robe p20', 'price_minor' => 10000, 'track_stock' => 1, 'stock_quantity' => 3, 'low_stock_threshold' => 1, 'is_published' => 1]);
        $sac = $this->insert('boutique_products', ['tenant_id' => $shop, 'name' => 'Sac p20', 'price_minor' => 5000, 'track_stock' => 1, 'stock_quantity' => 10, 'low_stock_threshold' => 2, 'is_published' => 1]);

        // Commande en ligne qui videra le stock : la notification le signale.
        $slug = $this->slug($shop);
        $kernel->handle($this->post('/t/' . $slug . '/commander', [
            '_csrf' => $this->csrf($kernel, '/t/' . $slug),
            'items' => [(string) $robe => '3'],
            'customer_name' => 'Khady p20',
            'customer_phone' => '770001234',
        ]));
        $this->assertStringContainsString('Robe p20 (épuisé après cette commande)', (string) $this->value("SELECT body FROM notifications WHERE user_id = ? AND category = 'boutique.order'", [$owner]));
        $this->assertSame('warning', $this->value("SELECT level FROM notifications WHERE user_id = ? AND category = 'boutique.order'", [$owner]));

        // Sortie manuelle de stock sous le seuil → alerte ; une entrée n'en déclenche pas.
        $this->loginAs($kernel, [Role::MANAGER], $shop);
        $this->submit($kernel, '/boutique/stock', ['product_id' => (string) $sac, 'type' => 'out', 'quantity' => '8'], '/boutique/stock');
        $this->assertSame(2, (int) $this->value('SELECT stock_quantity FROM boutique_products WHERE id = ?', [$sac]));
        $this->assertSame('Stock bas : Sac p20', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'boutique.stock'", [$owner]));
        $this->submit($kernel, '/boutique/stock', ['product_id' => (string) $sac, 'type' => 'in', 'quantity' => '5'], '/boutique/stock');
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND category = 'boutique.stock'", [$owner]));
        $this->logout($kernel);

        // Rappel du matin : la commande d'hier est toujours en attente. Le lundi, point du stock.
        $this->pdo()->prepare('UPDATE boutique_orders SET created_at = ? WHERE tenant_id = ?')->execute([gmdate('Y-m-d H:i:s', strtotime('-1 day')), $shop]);
        $this->pdo()->prepare('UPDATE boutique_products SET stock_quantity = 0 WHERE id = ?')->execute([$robe]);
        $notifier = Notifier::current();
        $this->assertInstanceOf(Notifier::class, $notifier);
        $jobs = new DailyNotifications($this->config()['db'], $notifier, sys_get_temp_dir() . '/sesam-daily-test.txt');
        $monday = gmdate('Y-m-d', strtotime('next monday'));
        $jobs->run($monday);
        $this->assertSame('1 commande(s) à traiter', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'boutique.pending'", [$owner]));
        $this->assertSame('Point stock de la semaine : 1 épuisé(s)', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'boutique.stock' ORDER BY id DESC LIMIT 1", [$owner]));
        $jobs->run($monday);
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND category = 'boutique.pending'", [$owner]));

        // Abonnement payé en ligne : le commerce reçoit la confirmation.
        $this->insert('tenant_subscriptions', ['tenant_id' => $shop, 'status' => 'trial', 'monthly_amount_minor' => 15000, 'period_start' => gmdate('Y-m-d', strtotime('-10 days')), 'period_end' => gmdate('Y-m-d', strtotime('+20 days'))]);
        $this->loginAs($kernel, [Role::TENANT_OWNER], $shop);
        $this->submit($kernel, '/abonnement/payer/confirmer', ['months' => '1', 'method' => 'wave', 'decision' => 'pay'], '/abonnement');
        $this->assertSame('Abonnement renouvelé ✓', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'subscription' ORDER BY id DESC LIMIT 1", [$owner]));
    }

    public function testDailyRemindersAreSentOnce(): void
    {
        $salon = $this->tenant(Sector::RESERVATION);
        $owner = $this->userId($this->user([Role::TENANT_OWNER], $salon));
        $serviceId = $this->insert('reservation_services', ['tenant_id' => $salon, 'name' => 'Coupe p20', 'duration_minutes' => 30, 'price_minor' => 5000, 'deposit_minor' => 0, 'is_active' => 1]);
        $this->insert('reservation_appointments', [
            'tenant_id' => $salon, 'client_id' => $this->client($salon), 'service_id' => $serviceId, 'starts_at' => gmdate('Y-m-d') . ' 15:00:00',
            'duration_minutes' => 30, 'price_minor' => 5000, 'status' => 'pending', 'source' => 'online', 'deposit_amount_minor' => 0,
        ]);
        $trial = $this->tenant(Sector::BOUTIQUE);
        $trialOwner = $this->userId($this->user([Role::TENANT_OWNER], $trial));
        $this->insert('tenant_subscriptions', ['tenant_id' => $trial, 'status' => 'trial', 'period_start' => gmdate('Y-m-d', strtotime('-27 days')), 'period_end' => gmdate('Y-m-d', strtotime('+3 days'))]);
        $staff = $this->userId($this->user([Role::ADMIN], null));

        $this->kernel();
        $notifier = Notifier::current();
        $this->assertInstanceOf(Notifier::class, $notifier);
        $jobs = new DailyNotifications($this->config()['db'], $notifier, sys_get_temp_dir() . '/sesam-daily-test.txt');
        $first = $jobs->run();
        $this->assertGreaterThanOrEqual(1, $first['reservation.today']);
        $this->assertSame('1 rendez-vous aujourd\'hui', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'reservation.today'", [$owner]));
        $this->assertSame('Essai gratuit : plus que 3 jour(s)', $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'subscription'", [$trialOwner]));
        $this->assertStringContainsString('essai(s) se terminent', (string) $this->value("SELECT title FROM notifications WHERE user_id = ? AND category = 'sesam.trial' ORDER BY id DESC LIMIT 1", [$staff]));

        $jobs->run();
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND category = 'reservation.today'", [$owner]));
        $this->assertSame(1, (int) $this->value("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND category = 'subscription'", [$trialOwner]));
    }
}
