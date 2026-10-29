<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Models\Payment;
use Sesam\Services\SaasService;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Essai gratuit d'un mois activable / désactivable depuis la console SESAM.
 * Désactivé : le compte est créé « en attente », l'espace s'ouvre au paiement du premier mois
 * (mensualité + frais de mise en place définis dans les tarifs).
 */
final class TrialSettingTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        // Réglage global : on remet toujours l'essai gratuit (valeur par défaut) après chaque test.
        $this->pdo()->prepare("UPDATE platform_settings SET setting_value = '1' WHERE setting_key = 'trial_enabled'")->execute();
        $this->pdo()->prepare("UPDATE saas_prices SET setup_fee = 0 WHERE sector = 'boutique'")->execute();
        parent::tearDown();
    }

    public function testOnlySesamManagersCanSwitchTheTrial(): void
    {
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $this->tenant(Sector::BOUTIQUE));
        $this->assertSame(403, $this->submit($kernel, '/tarifs/essai', ['trial_enabled' => '0'], '/profil')->status());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::SUPER_ADMIN], null);
        $page = html_entity_decode($kernel->handle(new Request('GET', '/tarifs'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Essai gratuit d\'un mois à l\'inscription', $page);
        $this->assertStringContainsString('Désactiver l\'essai gratuit', $page);

        $this->submit($kernel, '/tarifs/essai', ['trial_enabled' => '0'], '/tarifs');
        $this->assertSame('0', $this->value("SELECT setting_value FROM platform_settings WHERE setting_key = 'trial_enabled'"));
        $this->assertStringContainsString('Activer l\'essai gratuit', html_entity_decode($kernel->handle(new Request('GET', '/tarifs'))->content(), ENT_QUOTES, 'UTF-8'));

        $this->submit($kernel, '/tarifs/essai', ['trial_enabled' => '1'], '/tarifs');
        $this->assertSame('1', $this->value("SELECT setting_value FROM platform_settings WHERE setting_key = 'trial_enabled'"));
    }

    public function testCommencerPageFollowsTheSetting(): void
    {
        $kernel = $this->kernel();
        $withTrial = html_entity_decode($kernel->handle(new Request('GET', '/commencer'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('1 mois offert', $withTrial);
        $this->assertStringContainsString('data-cm-modal="signup"', $withTrial);
        $this->assertStringContainsString('data-cm-modal="wait"', $withTrial);
        $this->assertStringContainsString('data-cm-modal="help"', $withTrial);
        $this->assertStringContainsString('Créer mon espace gratuitement', $withTrial);

        $this->pdo()->prepare("UPDATE platform_settings SET setting_value = '0' WHERE setting_key = 'trial_enabled'")->execute();
        $paid = html_entity_decode($kernel->handle(new Request('GET', '/commencer'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('1 mois offert', $paid);
        $this->assertStringContainsString('Activation immédiate après paiement', $paid);
        $this->assertMatchesRegularExpression('/\d[\d\s ]*FCFA \/ mois/u', $paid, 'Les tarifs de la console sont affichés.');
    }

    public function testWithoutTrialTheSpaceOpensAfterTheFirstPayment(): void
    {
        $kernel = $this->kernel();
        $this->pdo()->prepare("UPDATE platform_settings SET setting_value = '0' WHERE setting_key = 'trial_enabled'")->execute();
        $this->pdo()->prepare("UPDATE saas_prices SET setup_fee = 5000 WHERE sector = 'boutique'")->execute();
        $monthly = (int) $this->value("SELECT monthly_amount FROM saas_prices WHERE sector = 'boutique'");

        $email = 'p20-paid-' . bin2hex(random_bytes(3)) . '@example.com';
        $signup = $this->submit($kernel, '/commencer', [
            'sector' => Sector::BOUTIQUE, 'full_name' => 'Fatou p20', 'business_name' => 'Boutique payante p20', 'phone' => '771234567',
            'email' => $email, 'password' => 'SignupPass-2026', 'password_confirm' => 'SignupPass-2026', 'terms' => '1',
        ], '/commencer');
        $tenantId = (int) $this->value('SELECT tenant_id FROM users WHERE email = ?', [$email]);
        $this->track($tenantId, $email);
        $this->assertSame('/abonnement', $signup->headers()['Location'] ?? '', 'Sans essai, on arrive directement sur le paiement.');
        $this->assertSame('pending', $this->value('SELECT status FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));

        // L'espace reste fermé : tout renvoie vers la page Abonnement, qui explique l'activation.
        $espace = $kernel->handle(new Request('GET', '/espace'));
        $this->assertSame('/abonnement', $espace->headers()['Location'] ?? '');
        $page = html_entity_decode($kernel->handle(new Request('GET', '/abonnement'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Activez votre espace', $page);
        $this->assertStringContainsString('frais de mise en place', $page);

        // Le premier paiement inclut les frais de mise en place, une seule fois.
        $saas = new SaasService($this->config()['db']);
        $owner = (new \Sesam\Repositories\UserRepository($this->pdo()))->findByEmail($email);
        $this->assertNotNull($owner);
        $checkout = $saas->checkout($owner, 1, 'wave');
        $this->assertSame($monthly + 5000, $checkout['amount']);
        $this->assertSame(5000, $checkout['setupFee']);

        // Paiement confirmé (webhook NabooPay) : l'abonnement devient actif, la mensualité retenue exclut les frais.
        $payment = new Payment(['id' => 0, 'tenant_id' => $tenantId, 'reference' => 'P20-ACT-' . bin2hex(random_bytes(3)), 'amount_minor' => $monthly + 5000, 'status' => 'success']);
        $saas->applyPaidOnlineRenewal($payment, ['purpose' => 'subscription', 'months' => 1, 'method' => 'wave', 'actor_user_id' => 0, 'setup_fee' => 5000]);
        $this->assertSame('active', $this->value('SELECT status FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame($monthly, (int) $this->value('SELECT monthly_amount_minor FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(0, $saas->setupFeeDue($tenantId), 'Les frais ne sont dus qu\'une fois.');
        $this->assertSame(200, $kernel->handle(new Request('GET', '/espace'))->status(), 'L\'espace est ouvert après paiement.');
    }
}
