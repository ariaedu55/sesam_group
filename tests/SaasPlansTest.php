<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Payment;
use Sesam\Repositories\UserRepository;
use Sesam\Services\SaasService;
use Sesam\Tests\Support\IntegrationTestCase;

/**
 * Tarifs de la console SESAM : appliqués à chaque souscription et renouvellement,
 * formules 3 mois / 6 mois / 1 an avec réduction en pourcentage, propres à chaque service.
 */
final class SaasPlansTest extends IntegrationTestCase
{
    /** @var array<string, mixed> */
    private array $savedPrice = [];

    protected function setUp(): void
    {
        parent::setUp();
        $row = $this->pdo()->query("SELECT * FROM saas_prices WHERE sector = 'boutique'")->fetch(\PDO::FETCH_ASSOC);
        $this->savedPrice = is_array($row) ? $row : [];
    }

    protected function tearDown(): void
    {
        // Tarifs globaux : on remet exactement ceux d'avant le test.
        if ($this->savedPrice !== []) {
            $set = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys(array_diff_key($this->savedPrice, ['sector' => 1]))));
            $this->pdo()->prepare('UPDATE saas_prices SET ' . $set . ' WHERE sector = :sector')->execute($this->savedPrice);
        }
        parent::tearDown();
    }

    private function setBoutiquePrice(int $monthly, array $plans = []): void
    {
        $sql = 'UPDATE saas_prices SET monthly_amount = ?, setup_fee = 0';
        $params = [$monthly];
        foreach ([3, 6, 12] as $months) {
            $sql .= ', plan_' . $months . '_enabled = ?, plan_' . $months . '_discount = ?';
            $params[] = ($plans[$months]['enabled'] ?? true) ? 1 : 0;
            $params[] = $plans[$months]['discount'] ?? 0;
        }
        $this->pdo()->prepare($sql . " WHERE sector = 'boutique'")->execute($params);
    }

    /** Commerce déjà abonné à un ancien tarif (mémorisé dans son abonnement). */
    private function subscribedOwner(int $oldMonthly, string $status = 'active'): array
    {
        $tenantId = $this->tenant(Sector::BOUTIQUE);
        $this->insert('tenant_subscriptions', [
            'tenant_id' => $tenantId, 'status' => $status, 'monthly_amount_minor' => $oldMonthly,
            'period_start' => gmdate('Y-m-d', strtotime('-25 days')), 'period_end' => gmdate('Y-m-d', strtotime('+5 days')),
        ]);
        $email = $this->user([Role::TENANT_OWNER], $tenantId);
        $owner = (new UserRepository($this->pdo()))->findByEmail($email);
        $this->assertNotNull($owner);

        return [$tenantId, $owner, $email];
    }

    public function testNewConsolePriceAppliesToRenewalOfAnExistingSubscription(): void
    {
        [$tenantId, $owner] = $this->subscribedOwner(5000);
        $this->setBoutiquePrice(12000);
        $saas = new SaasService($this->config()['db']);

        $this->assertSame(12000, $saas->page($owner)['price'], 'La page Abonnement affiche le tarif de la console, pas l\'ancien.');
        $this->assertSame(12000, $saas->checkout($owner, 1, 'wave')['amount']);
        $this->assertSame(36000, $saas->checkout($owner, 3, 'wave')['amount']);

        // Paiement NabooPay confirmé : l'abonnement retient le nouveau tarif.
        $payment = new Payment(['id' => 0, 'tenant_id' => $tenantId, 'reference' => 'PLAN-' . bin2hex(random_bytes(3)), 'amount_minor' => 12000, 'status' => 'success']);
        $saas->applyPaidOnlineRenewal($payment, ['purpose' => 'subscription', 'months' => 1, 'method' => 'wave', 'actor_user_id' => 0]);
        $this->assertSame(12000, (int) $this->value('SELECT monthly_amount_minor FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
    }

    public function testDiscountedPlansAreOfferedAndCharged(): void
    {
        [$tenantId, $owner, $email] = $this->subscribedOwner(10000);
        $this->setBoutiquePrice(10000, [3 => ['discount' => 5], 6 => ['discount' => 10], 12 => ['enabled' => false, 'discount' => 20]]);
        $saas = new SaasService($this->config()['db']);

        $plans = $saas->plans($tenantId);
        $this->assertSame([1, 3, 6], array_keys($plans), 'La formule 1 an désactivée n\'est pas proposée.');
        $this->assertSame(28500, $plans[3]['amount']);
        $this->assertSame(54000, $plans[6]['amount']);
        $this->assertSame(6000, $plans[6]['saving']);
        $this->assertSame(9000, $plans[6]['per_month']);

        try {
            $saas->checkout($owner, 12, 'wave');
            $this->fail('Une formule désactivée ne peut pas être payée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('months', $exception->errors());
        }

        // Parcours complet dans l'espace du commerce (paiement simulé).
        $kernel = $this->kernel();
        $kernel->handle($this->post('/connexion', ['_csrf' => $this->csrf($kernel, '/connexion'), 'email' => $email, 'password' => self::PASSWORD]));
        $page = html_entity_decode($this->get($kernel, '/abonnement')->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('6 mois · -10 %', $page);
        $this->assertStringContainsString('Vous économisez 6 000 FCFA', $page);
        $this->assertStringNotContainsString('1 an', $page);
        $this->assertStringContainsString('Renouveler mon abonnement', $page, 'Un abonnement en cours se renouvelle depuis le bandeau.');
        $this->assertStringContainsString('Échéance', $page);
        $this->assertStringContainsString('assets/images/paiement/wave.png', $page);
        $this->assertStringContainsString('assets/images/paiement/orange-money.png', $page);
        $this->assertStringContainsString('Le plus avantageux', $page);

        $checkout = html_entity_decode($this->submit($kernel, '/abonnement/payer', ['months' => '6', 'method' => 'wave'], '/abonnement')->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('54 000 FCFA', $checkout);
        $this->assertStringContainsString('-10 %', $checkout);

        $this->submit($kernel, '/abonnement/payer/confirmer', ['months' => '6', 'method' => 'wave', 'decision' => 'pay'], '/abonnement');
        $this->assertSame(54000, (int) $this->value('SELECT amount FROM tenant_subscription_payments WHERE tenant_id = ?', [$tenantId]));
        $this->assertSame(9000, (int) $this->value('SELECT monthly_amount_minor FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]));
    }

    public function testConsoleSavesPlansPerServiceAndRejectsBadDiscounts(): void
    {
        [$tenantId] = $this->subscribedOwner(4000, 'trial');
        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::SUPER_ADMIN], null);
        $form = [];
        foreach (['reservation', 'boutique', 'loyer'] as $sector) {
            $row = $this->pdo()->query("SELECT * FROM saas_prices WHERE sector = '" . $sector . "'")->fetch(\PDO::FETCH_ASSOC);
            $form[$sector . '_monthly'] = (string) $row['monthly_amount'];
            $form[$sector . '_setup'] = (string) $row['setup_fee'];
            foreach ([3, 6, 12] as $months) {
                if ((int) $row['plan_' . $months . '_enabled'] === 1) {
                    $form[$sector . '_plan_' . $months . '_enabled'] = '1';
                }
                $form[$sector . '_plan_' . $months . '_discount'] = (string) $row['plan_' . $months . '_discount'];
            }
        }
        $page = html_entity_decode($kernel->handle(new Request('GET', '/tarifs'))->content(), ENT_QUOTES, 'UTF-8');
        $this->assertStringContainsString('boutique_plan_12_discount', $page);

        $bad = $form;
        $bad['boutique_plan_3_discount'] = '95';
        $this->assertSame(422, $this->submit($kernel, '/tarifs', $bad, '/tarifs')->status());

        $form['boutique_monthly'] = '11000';
        $form['boutique_plan_3_enabled'] = '1';
        $form['boutique_plan_3_discount'] = '7';
        unset($form['boutique_plan_6_enabled']);
        $form['boutique_plan_12_enabled'] = '1';
        $form['boutique_plan_12_discount'] = '20 %';
        $this->assertSame('/tarifs', $this->submit($kernel, '/tarifs', $form, '/tarifs')->headers()['Location'] ?? '');

        $row = $this->pdo()->query("SELECT * FROM saas_prices WHERE sector = 'boutique'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame([11000, 1, 7, 0, 1, 20], [(int) $row['monthly_amount'], (int) $row['plan_3_enabled'], (int) $row['plan_3_discount'], (int) $row['plan_6_enabled'], (int) $row['plan_12_enabled'], (int) $row['plan_12_discount']]);
        $this->assertSame(11000, (int) $this->value('SELECT monthly_amount_minor FROM tenant_subscriptions WHERE tenant_id = ?', [$tenantId]), 'Un commerce en essai suit le nouveau tarif.');

        $saas = new SaasService($this->config()['db']);
        $this->assertSame([1, 3, 12], array_keys($saas->plans($tenantId)));
        $this->assertSame(105600, $saas->plans($tenantId)[12]['amount']);
    }
}
