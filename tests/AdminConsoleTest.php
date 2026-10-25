<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Console SESAM : tableau de bord, encaissement d'abonnement, notes internes, revenus, export et tarifs.
 */
final class AdminConsoleTest extends IntegrationTestCase
{
    public function testPaymentExtendsAccessReactivatesTenantAndFeedsRevenue(): void
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE, 'suspended');
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::SUPER_ADMIN], null);
        $path = '/commerces/' . $tenantId;
        $today = gmdate('Y-m-d');

        $page = $this->get($kernel, $path);
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('Enregistrer le paiement', $page->content());

        $invalid = $this->submit($kernel, $path . '/encaissement', ['amount' => '', 'months' => '20', 'paid_on' => '2999-01-01', 'method' => 'bitcoin'], $path);
        $this->assertSame(422, $invalid->status());
        $this->assertStringContainsString('Indiquez le montant', html_entity_decode($invalid->content(), ENT_QUOTES, 'UTF-8'));

        $ok = $this->submit($kernel, $path . '/encaissement', ['amount' => '30 000', 'months' => '3', 'paid_on' => $today, 'method' => 'wave', 'reference' => 'TX-p20'], $path);
        $this->assertSame(302, $ok->status());
        $this->assertSame('active', $this->value('SELECT status FROM tenants WHERE id = ?', [$tenantId]));
        $this->assertSame('active', $this->value('SELECT status FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $expectedEnd = (new \DateTimeImmutable($today))->modify('+3 months')->modify('-1 day')->format('Y-m-d');
        $this->assertSame($expectedEnd, $this->value('SELECT period_end FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(30000, (int) $this->value('SELECT amount FROM tenant_subscription_payments WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(10000, (int) $this->value('SELECT monthly_amount_minor FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));

        // Un second paiement prolonge à partir de la fin de période en cours.
        $this->submit($kernel, $path . '/encaissement', ['amount' => '10000', 'months' => '1', 'paid_on' => $today, 'method' => 'especes'], $path);
        $next = (new \DateTimeImmutable($expectedEnd))->modify('+1 day');
        $this->assertSame($next->modify('+1 month')->modify('-1 day')->format('Y-m-d'), $this->value('SELECT period_end FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));

        $revenue = $kernel->handle(new Request('GET', '/revenus', ['annee' => gmdate('Y'), 'mois' => gmdate('Y-m')]));
        $this->assertSame(200, $revenue->status());
        $this->assertStringContainsString('TX-p20', $revenue->content());

        $csv = $kernel->handle(new Request('GET', '/revenus/export', ['annee' => gmdate('Y'), 'mois' => gmdate('Y-m')]));
        $this->assertSame(200, $csv->status());
        $this->assertStringContainsString('text/csv', $csv->headers()['Content-Type'] ?? '');
        $this->assertStringContainsString('"TX-p20"', $csv->content());

        $dashboard = $this->get($kernel, '/espace');
        $this->assertStringContainsString('Revenu mensuel récurrent', html_entity_decode($dashboard->content(), ENT_QUOTES, 'UTF-8'));
    }

    public function testNotesAreKeptOnTheTenantFile(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::ADMIN], null);
        $path = '/commerces/' . $tenantId;

        $this->assertSame(422, $this->submit($kernel, $path . '/notes', ['note_body' => '  '], $path)->status());
        $saved = $this->submit($kernel, $path . '/notes', ['note_body' => 'Appel p20 : souhaite former sa coiffeuse.'], $path);
        $this->assertSame(302, $saved->status());
        $this->assertStringContainsString('Appel p20', html_entity_decode($this->get($kernel, $path)->content(), ENT_QUOTES, 'UTF-8'));
    }

    public function testRevenueAndPricesFollowPermissions(): void
    {
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::COMMERCIAL], null);
        $this->assertSame(403, $this->get($kernel, '/revenus')->status());
        $this->assertSame(403, $this->get($kernel, '/tarifs')->status());
        $this->assertSame(200, $this->get($kernel, '/espace')->status());
        $this->logout($kernel);

        $this->loginAs($kernel, [Role::FINANCE], null);
        $this->assertSame(200, $this->get($kernel, '/revenus')->status());
        $this->logout($kernel);

        $tenantId = $this->tenant(Sector::LOYER);
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $this->assertSame(403, $this->get($kernel, '/revenus')->status());
        $this->assertSame(403, $this->submit($kernel, '/commerces/' . $tenantId . '/encaissement', ['amount' => '1000', 'months' => '1', 'paid_on' => gmdate('Y-m-d'), 'method' => 'wave'], '/espace')->status());
    }

    public function testPricesCanBeUpdated(): void
    {
        $before = $this->pdo()->query('SELECT sector, monthly_amount, setup_fee FROM saas_prices')->fetchAll();
        try {
            $kernel = $this->kernel();
            $this->loginAs($kernel, [Role::SUPER_ADMIN], null);
            $this->assertSame(200, $this->get($kernel, '/tarifs')->status());
            $saved = $this->submit($kernel, '/tarifs', [
                'reservation_monthly' => '17 500', 'reservation_setup' => '0',
                'boutique_monthly' => '9000', 'boutique_setup' => '5000',
                'loyer_monthly' => '20000', 'loyer_setup' => '0',
            ], '/tarifs');
            $this->assertSame(302, $saved->status());
            $this->assertSame(17500, (int) $this->value('SELECT monthly_amount FROM saas_prices WHERE sector = ?', ['reservation']));
            $this->assertSame(5000, (int) $this->value('SELECT setup_fee FROM saas_prices WHERE sector = ?', ['boutique']));
        } finally {
            $restore = $this->pdo()->prepare('UPDATE saas_prices SET monthly_amount = ?, setup_fee = ? WHERE sector = ?');
            foreach ($before as $row) {
                $restore->execute([$row['monthly_amount'], $row['setup_fee'], $row['sector']]);
            }
        }
    }
}
