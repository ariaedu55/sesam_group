<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * SaaS en libre-service : inscription « Commencer » avec essai d'un mois et connexion directe,
 * fin d'essai (seule la page Abonnement reste ouverte), réabonnement simulé, suivi dans la console SESAM.
 */
final class SaasSignupTest extends IntegrationTestCase
{
    public function testSignupCreatesTheSpaceWithATrialAndLogsIn(): void
    {
        $kernel = $this->kernel();
        $email = 'p20-signup-' . bin2hex(random_bytes(3)) . '@example.com';
        $form = $kernel->handle(new Request('GET', '/commencer', ['service' => 'boutique']));
        $this->assertSame(200, $form->status());
        $this->assertStringContainsString('value="boutique" checked', $form->content());

        $invalid = $this->submit($kernel, '/commencer', ['sector' => 'garde', 'full_name' => '', 'phone' => '12', 'email' => 'x', 'password' => 'court', 'password_confirm' => 'court'], '/commencer');
        $this->assertSame(422, $invalid->status());
        $html = html_entity_decode($invalid->content(), ENT_QUOTES, 'UTF-8');
        foreach (['Choisissez votre service.', 'Numéro invalide', 'E-mail invalide.', '12 caractères minimum.', 'Acceptez les conditions'] as $message) {
            $this->assertStringContainsString($message, $html);
        }

        $signup = $this->submit($kernel, '/commencer', [
            'sector' => Sector::BOUTIQUE, 'full_name' => 'Awa p20', 'business_name' => 'Boutique Éclat p20', 'phone' => '77 111 22 33',
            'email' => $email, 'password' => 'SignupPass-2026', 'password_confirm' => 'SignupPass-2026', 'terms' => '1',
        ], '/commencer');
        $this->assertSame(302, $signup->status());
        $this->assertSame('/espace', $signup->headers()['Location'] ?? '');
        $tenantId = (int) $this->value('SELECT tenant_id FROM users WHERE email = ?', [$email]);
        $this->track($tenantId, $email);
        $this->assertSame('boutique-eclat-p20', $this->value('SELECT slug FROM tenants WHERE id = ?', [$tenantId]));
        $this->assertSame('site', $this->value('SELECT signup_source FROM tenants WHERE id = ?', [$tenantId]));
        $this->assertSame('221771112233', $this->value('SELECT whatsapp_phone FROM boutique_settings WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame('trial', $this->value('SELECT status FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(gmdate('Y-m-d', strtotime('+30 days')), $this->value('SELECT period_end FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));

        // Connecté directement dans l'espace Boutique, avec le bandeau d'essai.
        $home = html_entity_decode($this->get($kernel, '/espace')->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Essai gratuit : 30 jour(s) restant(s).', $home);
        $this->assertStringContainsString('/boutique/produits', $home);
        $this->assertSame(302, $this->get($kernel, '/commencer')->status());
        $this->logout($kernel);

        // Le même e-mail ne peut pas s'inscrire deux fois.
        $again = $this->submit($kernel, '/commencer', [
            'sector' => Sector::LOYER, 'full_name' => 'Awa p20', 'phone' => '771112233', 'email' => $email,
            'password' => 'SignupPass-2026', 'password_confirm' => 'SignupPass-2026', 'terms' => '1',
        ], '/commencer');
        $this->assertSame(422, $again->status());

        // La console SESAM voit le nouveau commerce, filtré par origine.
        $this->loginAs($kernel, [Role::SUPER_ADMIN], null);
        $console = $kernel->handle(new Request('GET', '/commerces', ['source' => 'site', 'abonnement' => 'trial']));
        $this->assertStringContainsString('Boutique Éclat p20', html_entity_decode($console->content(), ENT_QUOTES, 'UTF-8'));
    }

    public function testTrialEndBlocksTheSpaceUntilTheOwnerRenews(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $this->insert('tenant_subscriptions', [
            'tenant_id' => $tenantId, 'status' => 'trial', 'monthly_amount_minor' => 15000,
            'period_start' => gmdate('Y-m-d', strtotime('-31 days')), 'period_end' => gmdate('Y-m-d', strtotime('-1 day')),
        ]);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);

        $blocked = $this->get($kernel, '/espace');
        $this->assertSame(302, $blocked->status());
        $this->assertSame('/abonnement', $blocked->headers()['Location'] ?? '');
        $this->assertSame(302, $this->get($kernel, '/reservation/agenda')->status());
        $page = html_entity_decode($this->get($kernel, '/abonnement')->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('Votre essai gratuit est terminé', $page);
        $this->assertStringContainsString('Continuer vers le paiement', $page);

        $checkout = $this->submit($kernel, '/abonnement/payer', ['months' => '3', 'method' => 'wave'], '/abonnement');
        $this->assertSame(200, $checkout->status());
        $this->assertStringContainsString('45 000 FCFA', html_entity_decode($checkout->content(), ENT_QUOTES, 'UTF-8'));
        $this->assertSame('trial', $this->value('SELECT status FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]), 'Rien ne change avant la confirmation.');

        $paid = $this->submit($kernel, '/abonnement/payer/confirmer', ['months' => '3', 'method' => 'wave', 'decision' => 'pay'], '/abonnement');
        $this->assertSame(302, $paid->status());
        $this->assertSame('active', $this->value('SELECT status FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $end = (new \DateTimeImmutable('today'))->modify('first day of +3 months');
        $this->assertSame($end->setDate((int) $end->format('Y'), (int) $end->format('n'), min((int) gmdate('j'), (int) $end->format('t')))->modify('-1 day')->format('Y-m-d'), $this->value('SELECT period_end FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame('en-ligne', $this->value('SELECT channel FROM tenant_subscription_payments WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(45000, (int) $this->value('SELECT amount FROM tenant_subscription_payments WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(200, $this->get($kernel, '/espace')->status());
        $this->logout($kernel);

        // Un employé voit l'abonnement mais ne peut pas le payer.
        $this->loginAs($kernel, [Role::STAFF], $tenantId);
        $this->assertStringContainsString('Seul le responsable', html_entity_decode($this->get($kernel, '/abonnement')->content(), ENT_QUOTES, 'UTF-8'));
        $this->assertSame(403, $this->submit($kernel, '/abonnement/payer', ['months' => '1', 'method' => 'wave'], '/abonnement')->status());
    }

    public function testConsoleCanOpenAndExtendATrial(): void
    {
        $tenantId = $this->tenant(Sector::LOYER);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::SUPER_ADMIN], null);
        $path = '/commerces/' . $tenantId;
        $this->submit($kernel, $path . '/essai', ['trial_days' => '15'], $path);
        $this->assertSame(gmdate('Y-m-d', strtotime('+15 days')), $this->value('SELECT period_end FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $this->submit($kernel, $path . '/essai', ['trial_days' => '30'], $path);
        $this->assertSame(gmdate('Y-m-d', strtotime('+45 days')), $this->value('SELECT period_end FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(422, $this->submit($kernel, $path . '/essai', ['trial_days' => '90'], $path)->status());
        $this->assertStringContainsString('Essai gratuit jusqu', html_entity_decode($this->get($kernel, $path)->content(), ENT_QUOTES, 'UTF-8'));
        $stats = $this->get($kernel, '/finance/statistiques');
        $this->assertSame(200, $stats->status());
        $this->assertStringContainsString('Essais convertis', html_entity_decode($stats->content(), ENT_QUOTES, 'UTF-8'));
    }
}
