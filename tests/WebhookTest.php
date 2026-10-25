<?php

declare(strict_types=1);

namespace Sesam\Tests;

use Sesam\Controllers\WebhookController;
use Sesam\Core\Auth\Role;
use Sesam\Core\Request\Request;
use Sesam\Core\Response\ViewRenderer;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Services\PaymentService;
use Sesam\Tests\Support\IntegrationTestCase;

final class WebhookTest extends IntegrationTestCase
{
    private const SECRET = 'p20-secret-de-test';

    public function testSignedSuccessWebhookMarksThePaymentPaidOnce(): void
    {
        $paymentId = $this->payment($this->tenant(Sector::RESERVATION));
        $body = $this->body($paymentId, 'success', 'tx-1');

        $response = $this->call($body, $this->sign($body));
        $this->assertSame(200, $response->status());
        $this->assertSame('{"ok":true}', $response->content());
        $this->assertSame('success', $this->paymentStatus($paymentId));
        $this->assertNotFalse($this->value('SELECT paid_at FROM payments WHERE id = ? AND paid_at IS NOT NULL', [$paymentId]));
        $this->assertSame('tx-1', $this->value('SELECT external_id FROM payments WHERE id = ?', [$paymentId]));

        $replay = $this->call($body, $this->sign($body));
        $this->assertSame(200, $replay->status());
        $this->assertSame(1, $this->webhookTransactions($paymentId));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM payment_webhook_events WHERE event_id = ?', [$this->eventId($body)]));
    }

    public function testBadOrMissingSignatureChangesNothing(): void
    {
        $paymentId = $this->payment($this->tenant(Sector::BOUTIQUE));
        $body = $this->body($paymentId, 'success', 'tx-forged');

        $this->assertSame(401, $this->call($body, hash_hmac('sha256', $body, 'mauvais-secret'))->status());
        $this->assertSame(401, $this->call($body, null)->status());
        $tampered = str_replace('tx-forged', 'tx-other', $body);
        $this->assertSame(401, $this->call($tampered, $this->sign($body))->status());

        $this->assertSame('pending', $this->paymentStatus($paymentId));
        $this->assertSame(0, $this->webhookTransactions($paymentId));
        $this->assertFalse($this->value('SELECT id FROM payment_webhook_events WHERE event_id = ?', [$this->eventId($body)]));
    }

    public function testMalformedPayloadsAreRejected(): void
    {
        $paymentId = $this->payment($this->tenant(Sector::LOYER));

        $notJson = 'pas du json';
        $this->assertSame(400, $this->call($notJson, $this->sign($notJson))->status());
        $noId = json_encode(['status' => 'success'], JSON_THROW_ON_ERROR);
        $this->assertSame(400, $this->call($noId, $this->sign($noId))->status());
        $unknownStatus = $this->body($paymentId, 'refunded', '');
        $this->assertSame(400, $this->call($unknownStatus, $this->sign($unknownStatus))->status());
        $this->assertSame('pending', $this->paymentStatus($paymentId));
    }

    public function testV2WebhookMarksPaidByOrderId(): void
    {
        $paymentId = $this->payment($this->tenant(Sector::BOUTIQUE));
        $orderId = 'ord-' . bin2hex(random_bytes(8));
        $this->pdo()->prepare('UPDATE payments SET external_id = ? WHERE id = ?')->execute([$orderId, $paymentId]);
        $body = json_encode([
            'order_id' => $orderId,
            'amount' => (int) $this->value('SELECT amount_minor FROM payments WHERE id = ?', [$paymentId]),
            'transaction_status' => 'paid',
            'paid_at' => '2026-03-22T10:00:00Z',
        ], JSON_THROW_ON_ERROR);

        $response = $this->call($body, $this->sign($body), 'X-Signature');
        $this->assertSame(200, $response->status());
        $this->assertSame('success', $this->paymentStatus($paymentId));
        $this->assertSame($orderId, $this->value('SELECT external_id FROM payments WHERE id = ?', [$paymentId]));
    }

    public function testUnknownReferenceIsAcknowledgedWithoutSideEffect(): void
    {
        $eventId = self::PREFIX . 'evt-' . bin2hex(random_bytes(4));
        $this->webhookEvents[] = $eventId;
        $body = json_encode(['event_id' => $eventId, 'reference' => 'inconnue-' . $eventId, 'status' => 'success'], JSON_THROW_ON_ERROR);

        $this->assertSame(200, $this->call($body, $this->sign($body))->status());
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM payment_webhook_events WHERE event_id = ?', [$eventId]));
    }

    public function testOutOfOrderWebhooksNeverUndoAPayment(): void
    {
        $tenantId = $this->tenant(Sector::RESERVATION);
        $paid = $this->payment($tenantId);
        $success = $this->body($paid, 'success', 'tx-ok');
        $this->call($success, $this->sign($success));

        foreach (['failed', 'cancelled', 'pending'] as $late) {
            $body = $this->body($paid, $late, '');
            $this->assertSame(200, $this->call($body, $this->sign($body))->status(), $late);
            $this->assertSame('success', $this->paymentStatus($paid), $late);
        }
        $this->assertSame(1, $this->webhookTransactions($paid));

        $failed = $this->payment($tenantId);
        $first = $this->body($failed, 'failed', '');
        $this->call($first, $this->sign($first));
        $stale = $this->body($failed, 'pending', '');
        $this->call($stale, $this->sign($stale));
        $this->assertSame('failed', $this->paymentStatus($failed));
        $recovered = $this->body($failed, 'paid', 'tx-retry');
        $this->call($recovered, $this->sign($recovered));
        $this->assertSame('success', $this->paymentStatus($failed));
    }

    public function testWebhookSuccessSettlesTheLinkedRentDue(): void
    {
        $tenantId = $this->tenant(Sector::LOYER);
        $clientId = $this->client($tenantId, 'Locataire p20');
        $propertyId = $this->insert('loyer_properties', ['tenant_id' => $tenantId, 'name' => 'Immeuble p20']);
        $unitId = $this->insert('loyer_units', ['tenant_id' => $tenantId, 'property_id' => $propertyId, 'label' => 'B2', 'default_rent_minor' => 75000, 'is_active' => 1]);
        $leaseId = $this->insert('loyer_leases', [
            'tenant_id' => $tenantId, 'unit_id' => $unitId, 'client_id' => $clientId, 'rent_minor' => 75000,
            'due_day' => 5, 'starts_on' => gmdate('Y-m-01'), 'status' => 'active',
        ]);
        $paymentId = $this->payment($tenantId, 'pending', 75000);
        $dueId = $this->insert('loyer_rent_dues', [
            'tenant_id' => $tenantId, 'lease_id' => $leaseId, 'due_date' => gmdate('Y-m-05'),
            'amount_minor' => 75000, 'status' => 'pending', 'payment_id' => $paymentId,
        ]);

        $body = $this->body($paymentId, 'completed', 'tx-loyer');
        $this->assertSame(200, $this->call($body, $this->sign($body))->status());

        $kernel = $this->kernel();
        $this->loginAs($kernel, [Role::TENANT_OWNER], $tenantId);
        $page = $this->get($kernel, '/immo/loyers/' . $dueId);
        $this->assertSame(200, $page->status());
        $this->assertSame('paid', $this->value('SELECT status FROM loyer_rent_dues WHERE id = ?', [$dueId]));
        $this->assertSame('en-ligne', $this->value('SELECT method FROM immo_payments WHERE due_id = ?', [$dueId]));

        $again = $this->submit($kernel, '/immo/loyers/' . $dueId . '/lien');
        $this->assertSame('paid', $this->value('SELECT status FROM loyer_rent_dues WHERE id = ?', [$dueId]));
        $this->assertSame(1, (int) $this->value('SELECT COUNT(*) FROM payments WHERE tenant_id = ?', [$tenantId]), 'Aucun second paiement après encaissement : ' . $again->status());
    }

    public function testWebhookRouteSkipsCsrfAndAnswersJson(): void
    {
        $response = $this->kernel()->handle(new Request('POST', '/webhooks/naboopay', [], [], [], [], false, '127.0.0.1', '{}'));

        $this->assertNotSame(419, $response->status());
        $this->assertStringStartsWith('application/json', $response->headers()['Content-Type'] ?? '');
    }

    private function call(string $body, ?string $signature, string $headerName = 'X-Naboo-Signature'): \Sesam\Core\Response\Response
    {
        $config = [
            'gateway' => 'naboopay',
            'naboopay' => [
                'api_key' => '',
                'webhook_secret' => self::SECRET,
                'api_base' => 'https://api.naboopay.com',
                'signature_header' => $headerName,
                'live' => false,
            ],
        ];
        $service = new PaymentService($this->config()['db'], new TenantContext(), $config, 'http://localhost:8080');
        $controller = new WebhookController(new ViewRenderer(dirname(__DIR__) . '/app/Views'), $service, $config);
        $headers = $signature === null ? [] : [$headerName => $signature];

        return $controller->nabooPay(new Request('POST', '/webhooks/naboopay', [], [], $headers, [], true, '203.0.113.9', $body));
    }

    private function body(int $paymentId, string $status, string $transactionId): string
    {
        $eventId = self::PREFIX . 'evt-' . bin2hex(random_bytes(6));
        $this->webhookEvents[] = $eventId;

        return json_encode([
            'event_id' => $eventId,
            'reference' => $this->value('SELECT reference FROM payments WHERE id = ?', [$paymentId]),
            'status' => $status,
            'transaction_id' => $transactionId,
        ], JSON_THROW_ON_ERROR);
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::SECRET);
    }

    private function eventId(string $body): string
    {
        return (string) json_decode($body, true, 512, JSON_THROW_ON_ERROR)['event_id'];
    }

    private function paymentStatus(int $paymentId): string
    {
        return (string) $this->value('SELECT status FROM payments WHERE id = ?', [$paymentId]);
    }

    private function webhookTransactions(int $paymentId): int
    {
        return (int) $this->value("SELECT COUNT(*) FROM payment_transactions WHERE payment_id = ? AND kind = 'webhook_received'", [$paymentId]);
    }
}
