<?php

declare(strict_types=1);

namespace Sesam\Services;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Logger\Logger;
use Sesam\Core\Payment\Gateway\NabooPayGateway;
use Sesam\Core\Payment\PaymentGatewayFactory;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Payment;
use Sesam\Models\User;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\PaymentRepository;
use Sesam\Repositories\PaymentTransactionRepository;
use Sesam\Repositories\PaymentWebhookRepository;

final class PaymentService extends Service
{
    public const PER_PAGE = 20;

    private readonly PaymentRepository $payments;
    private readonly PaymentTransactionRepository $transactions;
    private readonly PaymentWebhookRepository $webhooks;
    private readonly ClientRepository $clients;
    private readonly PaymentGatewayFactory $gateways;
    private ?SaasService $subscriptionSettler = null;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     * @param array{
     *     gateway: string,
     *     naboopay: array{api_key: string, webhook_secret: string, api_base: string, signature_header: string}
     * } $paymentConfig
     */
    private readonly \PDO $pdo;

    public function __construct(
        array $database,
        private readonly TenantContext $context,
        array $paymentConfig,
        private readonly string $appUrl,
        private readonly ?Logger $logger = null,
    ) {
        $pdo = Connection::make($database);
        $this->pdo = $pdo;
        $this->payments = new PaymentRepository($pdo);
        $this->transactions = new PaymentTransactionRepository($pdo);
        $this->webhooks = new PaymentWebhookRepository($pdo);
        $this->clients = new ClientRepository($pdo);
        $this->gateways = new PaymentGatewayFactory($paymentConfig, $appUrl);
    }

    public function useSubscriptionSettler(SaasService $saas): void
    {
        $this->subscriptionSettler = $saas;
    }

    public function isLiveGateway(): bool
    {
        return $this->gateways->make()->isLive();
    }

    /**
     * @return array{payments: list<Payment>, total: int, page: int, pages: int}
     */
    public function paginate(User $actor, int $page): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::PAYMENTS_VIEW);
        $page = max(1, $page);
        $total = $this->payments->countForTenant($tenantId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return [
            'payments' => $this->payments->listForTenant($tenantId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{payment: Payment, transactions: list<\Sesam\Models\PaymentTransaction>}
     */
    public function detail(User $actor, int $id): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::PAYMENTS_VIEW);
        $payment = $this->findOrDeny($tenantId, $id);

        return [
            'payment' => $payment,
            'transactions' => $this->transactions->forPayment($id, $tenantId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payment: Payment, checkout_url: string}
     */
    public function initiate(User $actor, array $input): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::PAYMENTS_CREATE);
        $amount = (int) ($input['amount_minor'] ?? 0);
        $description = trim((string) ($input['description'] ?? ''));
        $clientId = $input['client_id'] ?? null;
        $clientId = is_numeric($clientId) ? (int) $clientId : null;
        $errors = [];
        if ($amount < 100) {
            $errors['amount_minor'] = 'Montant minimum : 100 FCFA.';
        } elseif ($amount > 50000000) {
            $errors['amount_minor'] = 'Montant trop élevé.';
        }
        if ($description === '') {
            $errors['description'] = 'Indiquez un libellé.';
        } elseif (mb_strlen($description) > 255) {
            $errors['description'] = '255 caractères maximum.';
        }
        if ($clientId !== null && $this->clients->findById($clientId, $tenantId) === null) {
            $errors['client_id'] = 'Client introuvable.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->openCheckout($tenantId, $amount, $description, $clientId, '/paiements/{id}/bac-a-sable');
    }

    /**
     * @param array<string, mixed> $input
     * @return array{payment: Payment, checkout_url: string}
     */
    public function initiatePublic(array $input, string $checkoutPath): array
    {
        $tenantId = $this->context->requireId();
        $amount = (int) ($input['amount_minor'] ?? 0);
        $description = trim((string) ($input['description'] ?? ''));
        $clientId = $input['client_id'] ?? null;
        $clientId = is_numeric($clientId) ? (int) $clientId : null;
        $errors = [];
        if ($amount < 100) {
            $errors['amount_minor'] = 'Montant minimum : 100 FCFA.';
        } elseif ($amount > 50000000) {
            $errors['amount_minor'] = 'Montant trop élevé.';
        }
        if ($description === '') {
            $errors['description'] = 'Indiquez un libellé.';
        } elseif (mb_strlen($description) > 255) {
            $errors['description'] = '255 caractères maximum.';
        }
        if ($clientId !== null && $this->clients->findById($clientId, $tenantId) === null) {
            $errors['client_id'] = 'Client introuvable.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->openCheckout($tenantId, $amount, $description, $clientId, $checkoutPath);
    }

    /**
     * Abonnement SaaS : crée le paiement et renvoie l'URL NabooPay (live) ou le bac à sable.
     *
     * @param array{purpose: string, months: int, method: string, actor_user_id: int} $meta
     * @param list<string> $methods
     * @return array{payment: Payment, checkout_url: string}
     */
    public function initiateSubscription(int $tenantId, int $amount, string $description, array $meta, array $methods): array
    {
        if ($amount < 100) {
            throw new ValidationException(['months' => 'Montant minimum : 100 FCFA.']);
        }

        return $this->openCheckout(
            $tenantId,
            $amount,
            $description,
            null,
            '/abonnement/retour/{id}',
            $meta,
            $methods,
        );
    }

    public function syncFromProvider(int $tenantId, int $paymentId): Payment
    {
        $payment = $this->findOrDeny($tenantId, $paymentId);
        if ($payment->status() === PaymentStatus::SUCCESS || $payment->mode() !== 'live') {
            return $payment;
        }
        $orderId = $payment->externalId();
        if ($orderId === null || $orderId === '') {
            return $payment;
        }
        $gateway = $this->gateways->make();
        if (!$gateway instanceof NabooPayGateway) {
            return $payment;
        }
        try {
            $remote = $gateway->fetchTransaction($orderId);
        } catch (HttpException $exception) {
            $this->logger?->info('Sync NabooPay échouée paiement=' . $paymentId . ' : ' . $exception->getMessage());

            return $payment;
        }
        $status = trim((string) ($remote['transaction_status'] ?? ''));
        $mapped = $this->mapProviderStatus($status);
        if ($mapped === null || !$this->webhookMayMove($payment->status(), $mapped)) {
            return $payment;
        }
        if (isset($remote['amount']) && (int) $remote['amount'] !== $payment->amountMinor()) {
            $this->logger?->info('Sync NabooPay montant divergent paiement=' . $paymentId);

            return $payment;
        }
        $this->markStatus($payment, $mapped, $orderId, 'provider_sync', json_encode($remote, JSON_THROW_ON_ERROR));

        return $this->findOrDeny($tenantId, $paymentId);
    }

    public function publicPayment(int $paymentId): Payment
    {
        return $this->findOrDeny($this->context->requireId(), $paymentId);
    }

    public function sandboxConfirmPublic(int $paymentId): void
    {
        $tenantId = $this->context->requireId();
        $payment = $this->findOrDeny($tenantId, $paymentId);
        if ($payment->mode() !== 'sandbox') {
            throw new HttpException(403, 'Cette page est réservée au bac à sable NabooPay.');
        }
        if ($payment->status() !== PaymentStatus::PENDING) {
            return;
        }
        $this->markStatus($payment, PaymentStatus::SUCCESS, 'sandbox_tx_' . bin2hex(random_bytes(8)), 'sandbox_confirm');
    }

    public function sandboxCancelPublic(int $paymentId): void
    {
        $tenantId = $this->context->requireId();
        $payment = $this->findOrDeny($tenantId, $paymentId);
        if ($payment->mode() !== 'sandbox') {
            throw new HttpException(403, 'Cette page est réservée au bac à sable NabooPay.');
        }
        if ($payment->status() !== PaymentStatus::PENDING) {
            return;
        }
        $this->markStatus($payment, PaymentStatus::CANCELLED, null, 'sandbox_cancel');
    }

    public function sandboxConfirm(User $actor, int $paymentId): void
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::PAYMENTS_CREATE);
        $payment = $this->findOrDeny($tenantId, $paymentId);
        if ($payment->mode() !== 'sandbox') {
            throw new HttpException(403, 'Cette page est réservée au bac à sable NabooPay.');
        }
        if ($payment->status() !== PaymentStatus::PENDING) {
            return;
        }
        $this->markStatus($payment, PaymentStatus::SUCCESS, 'sandbox_tx_' . bin2hex(random_bytes(8)), 'sandbox_confirm');
    }

    public function sandboxCancel(User $actor, int $paymentId): void
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::PAYMENTS_CREATE);
        $payment = $this->findOrDeny($tenantId, $paymentId);
        if ($payment->mode() !== 'sandbox') {
            throw new HttpException(403, 'Cette page est réservée au bac à sable NabooPay.');
        }
        if ($payment->status() !== PaymentStatus::PENDING) {
            return;
        }
        $this->markStatus($payment, PaymentStatus::CANCELLED, null, 'sandbox_cancel');
    }

    /**
     * @param array{naboopay: array{webhook_secret: string, signature_header: string}} $config
     */
    public function handleNabooWebhook(string $rawBody, ?string $signature, array $config): void
    {
        $secret = trim($config['naboopay']['webhook_secret']);
        if ($secret === '') {
            $this->logger?->info('Webhook NabooPay reçu sans NABOOPAY_WEBHOOK_SECRET configuré.');
            throw new HttpException(503, 'Webhook NabooPay non configuré.');
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        $provided = trim((string) $signature);
        if ($provided === '' || !hash_equals($expected, $provided)) {
            $this->logger?->info('Signature webhook NabooPay invalide.');
            throw new HttpException(401, 'Signature invalide.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new HttpException(400, 'Payload JSON invalide.');
        }

        $orderId = trim((string) ($payload['order_id'] ?? ''));
        $reference = trim((string) ($payload['reference'] ?? ''));
        $status = trim((string) ($payload['transaction_status'] ?? $payload['status'] ?? ''));
        $eventId = trim((string) ($payload['event_id'] ?? ''));
        if ($eventId === '') {
            if ($orderId === '' && $reference === '') {
                throw new HttpException(400, 'Champs order_id ou reference requis.');
            }
            $eventId = hash(
                'sha256',
                $orderId . '|' . $reference . '|' . $status . '|' . (string) ($payload['paid_at'] ?? '') . '|' . (string) ($payload['updated_at'] ?? '')
            );
        }
        if ($this->webhooks->exists('naboopay', $eventId)) {
            return;
        }

        $payment = null;
        if ($orderId !== '') {
            $payment = $this->payments->findByExternalId($orderId);
        }
        if ($payment === null && $reference !== '') {
            $payment = $this->payments->findByReference($reference);
        }
        if ($payment === null) {
            $this->logger?->info('Webhook NabooPay : paiement inconnu order_id=' . $orderId . ' reference=' . $reference);
            $this->webhooks->insert('naboopay', $eventId, hash('sha256', $rawBody));

            return;
        }

        $mapped = $this->mapProviderStatus($status);
        if ($mapped === null) {
            throw new HttpException(400, 'Statut NabooPay non reconnu.');
        }
        if (!$this->webhookMayMove($payment->status(), $mapped)) {
            $this->logger?->info('Webhook NabooPay ignoré : ' . $payment->status() . ' → ' . $mapped . ' paiement=' . $payment->id());
            $this->webhooks->insert('naboopay', $eventId, hash('sha256', $rawBody));

            return;
        }
        if (isset($payload['amount']) && (int) $payload['amount'] !== $payment->amountMinor()) {
            $this->logger?->info('Webhook NabooPay montant divergent paiement=' . $payment->id());
            throw new HttpException(400, 'Montant NabooPay divergent.');
        }

        $externalId = $orderId !== '' ? $orderId : trim((string) ($payload['transaction_id'] ?? ''));
        $this->markStatus(
            $payment,
            $mapped,
            $externalId !== '' ? $externalId : null,
            'webhook_received',
            $rawBody,
        );
        $this->webhooks->insert('naboopay', $eventId, hash('sha256', $rawBody));
    }

    public function countPending(int $tenantId): int
    {
        return $this->payments->countByStatus($tenantId, PaymentStatus::PENDING);
    }

    public function countReceivedToday(int $tenantId): int
    {
        return $this->payments->countPaidSince($tenantId, gmdate('Y-m-d') . ' 00:00:00');
    }

    /**
     * @return list<\Sesam\Models\Client>
     */
    public function clientsForSelect(User $actor): array
    {
        $tenantId = $this->requireTenant($actor);
        $this->assertCan($actor, Permission::PAYMENTS_CREATE);

        return $this->clients->search($tenantId, '', null, null, 100, 0);
    }

    private function markStatus(
        Payment $payment,
        string $status,
        ?string $externalId,
        string $kind,
        ?string $payload = null,
    ): void {
        if ($payment->status() === PaymentStatus::SUCCESS && $status === PaymentStatus::SUCCESS) {
            return;
        }
        $paidAt = $status === PaymentStatus::SUCCESS ? gmdate('Y-m-d H:i:s') : null;
        $this->payments->updateStatus($payment->id(), $payment->tenantId(), $status, $externalId, $paidAt);
        $this->transactions->insert(
            $payment->id(),
            $payment->tenantId(),
            $kind,
            $status,
            $externalId,
            $payload,
        );
        if ($status === PaymentStatus::SUCCESS) {
            $this->settleLinkedRecords($payment, (string) $paidAt);
        }
    }

    /**
     * Un paiement encaissé est reporté sur l'échéance de loyer qu'il règle, sans attendre l'ouverture de la page Immo.
     * (L'acompte d'un rendez-vous s'affiche avec le statut de son paiement ; le rendez-vous n'est pas soldé pour autant.)
     */
    private function settleLinkedRecords(Payment $payment, string $paidAt): void
    {
        $subscriptionMeta = $this->subscriptionMeta($payment);
        if ($subscriptionMeta !== null) {
            $this->subscriptionSettler?->applyPaidOnlineRenewal($payment, $subscriptionMeta);
            $this->notifyPaid($payment);

            return;
        }
        // Loyer Immo : le paiement devient un encaissement (complet ou partiel) de l'échéance liée.
        (new \Sesam\Core\Immo\ImmoLedger($this->pdo))->applyOnlinePayment($payment->tenantId(), $payment->id(), $payment->amountMinor(), $paidAt);
        $this->notifyPaid($payment);
    }

    /**
     * @return array{purpose: string, months: int, method: string, actor_user_id: int}|null
     */
    private function subscriptionMeta(Payment $payment): ?array
    {
        foreach ($this->transactions->forPayment($payment->id(), $payment->tenantId()) as $transaction) {
            if ($transaction->kind() !== 'checkout_created') {
                continue;
            }
            $payload = json_decode($transaction->payload(), true);
            if (!is_array($payload) || ($payload['purpose'] ?? '') !== 'subscription') {
                continue;
            }
            $months = (int) ($payload['months'] ?? 0);
            $method = (string) ($payload['method'] ?? '');
            $actorUserId = (int) ($payload['actor_user_id'] ?? 0);
            if ($months < 1 || $method === '') {
                return null;
            }

            return [
                'purpose' => 'subscription',
                'months' => $months,
                'method' => $method,
                'actor_user_id' => $actorUserId,
            ];
        }

        return null;
    }

    /**
     * Prévient le commerce : loyer payé (Immo), acompte payé (Réservation), ou paiement en ligne reçu.
     */
    private function notifyPaid(Payment $payment): void
    {
        $amount = number_format($payment->amountMinor(), 0, ',', ' ') . ' FCFA';
        $who = $payment->clientName() !== '' ? $payment->clientName() . ' · ' : '';
        $due = $this->pdo->prepare('SELECT id FROM loyer_rent_dues WHERE payment_id = ? AND tenant_id = ?');
        $due->execute([$payment->id(), $payment->tenantId()]);
        $dueId = $due->fetchColumn();
        if ($dueId !== false) {
            Notifier::tenant($payment->tenantId(), 'immo.payment', 'Loyer payé en ligne : ' . $amount, $who . $payment->description(), '/immo/loyers/' . (int) $dueId, 'success');

            return;
        }
        $appointment = $this->pdo->prepare('SELECT id FROM reservation_appointments WHERE payment_id = ? AND tenant_id = ?');
        $appointment->execute([$payment->id(), $payment->tenantId()]);
        $appointmentId = $appointment->fetchColumn();
        if ($appointmentId !== false) {
            Notifier::tenant($payment->tenantId(), 'reservation.deposit', 'Acompte payé : ' . $amount, $who . $payment->description(), '/reservation/rendez-vous/' . (int) $appointmentId, 'success');

            return;
        }
        if ($this->subscriptionMeta($payment) !== null) {
            Notifier::tenant(
                $payment->tenantId(),
                'subscription',
                'Abonnement renouvelé ✓',
                $payment->description() . ' · ' . $amount,
                '/abonnement',
                'success',
            );

            return;
        }
        Notifier::tenant($payment->tenantId(), 'payment.received', 'Paiement reçu : ' . $amount, $who . $payment->description(), '/paiements/' . $payment->id(), 'success');
    }

    /**
     * Les webhooks peuvent arriver dans le désordre : un paiement encaissé reste encaissé,
     * et un statut terminé ne repasse jamais « en attente ».
     */
    private function webhookMayMove(string $current, string $next): bool
    {
        if ($current === PaymentStatus::SUCCESS) {
            return false;
        }

        return $next !== PaymentStatus::PENDING || $current === PaymentStatus::PENDING;
    }

    private function mapProviderStatus(string $status): ?string
    {
        return match (strtolower($status)) {
            'success', 'paid', 'paid_and_blocked', 'completed' => PaymentStatus::SUCCESS,
            'failed', 'error' => PaymentStatus::FAILED,
            'cancelled', 'canceled' => PaymentStatus::CANCELLED,
            'pending' => PaymentStatus::PENDING,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $methods
     * @return array{payment: Payment, checkout_url: string}
     */
    private function openCheckout(
        int $tenantId,
        int $amount,
        string $description,
        ?int $clientId,
        string $checkoutPath,
        array $meta = [],
        array $methods = [],
    ): array {
        $gateway = $this->gateways->make();
        $reference = bin2hex(random_bytes(16));
        $mode = $gateway->isLive() ? 'live' : 'sandbox';
        if ($mode === 'live') {
            $checkoutPath = $this->liveReturnPath($checkoutPath);
        }
        $paymentId = $this->payments->insert([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'reference' => $reference,
            'amount_minor' => $amount,
            'currency' => 'XOF',
            'status' => PaymentStatus::PENDING,
            'gateway' => $gateway->slug(),
            'mode' => $mode,
            'description' => $description,
            'external_id' => null,
        ]);
        $payment = $this->findOrDeny($tenantId, $paymentId);
        $returnUrl = $this->absoluteCheckout($paymentId, $checkoutPath);
        $checkout = $gateway->createCheckout($payment, $returnUrl, [
            'methods' => $methods !== [] ? $methods : ['wave', 'orange_money'],
            'success_url' => $returnUrl,
            'error_url' => $returnUrl,
        ]);
        if ($checkout['external_id'] !== null && $checkout['external_id'] !== '') {
            $this->payments->updateStatus($paymentId, $tenantId, PaymentStatus::PENDING, $checkout['external_id'], null);
            $payment = $this->findOrDeny($tenantId, $paymentId);
        }
        $this->transactions->insert(
            $paymentId,
            $tenantId,
            'checkout_created',
            PaymentStatus::PENDING,
            $checkout['external_id'],
            json_encode(array_merge([
                'checkout_url' => $checkout['checkout_url'],
                'mode' => $checkout['mode'],
            ], $meta), JSON_THROW_ON_ERROR),
        );

        return [
            'payment' => $payment,
            'checkout_url' => $checkout['checkout_url'],
        ];
    }

    private function liveReturnPath(string $checkoutPath): string
    {
        if (str_contains($checkoutPath, '/bac-a-sable')) {
            return str_replace('/bac-a-sable', '/retour', $checkoutPath);
        }

        return $checkoutPath;
    }

    private function absoluteCheckout(int $paymentId, string $checkoutPath): string
    {
        $path = str_replace('{id}', (string) $paymentId, $checkoutPath);
        if (
            preg_match('#^/paiements/[1-9][0-9]*/(bac-a-sable|retour)$#', $path) !== 1
            && preg_match('#^/t/[a-z0-9-]+/paiement/[1-9][0-9]*/(bac-a-sable|retour)$#', $path) !== 1
            && preg_match('#^/abonnement/retour/[1-9][0-9]*$#', $path) !== 1
        ) {
            throw new HttpException(500, 'Une erreur est survenue.');
        }

        return rtrim($this->appUrl, '/') . $path;
    }

    private function findOrDeny(int $tenantId, int $id): Payment
    {
        $payment = $this->payments->findById($id, $tenantId);
        if ($payment === null) {
            $this->logger?->info('tenant isolation payment tenant=' . $tenantId . ' id=' . $id);
            throw new HttpException(404, 'Paiement introuvable.');
        }

        return $payment;
    }

    private function requireTenant(User $actor): int
    {
        if ($actor->tenantId() === null) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenantId = $this->context->requireId();
        if ($tenantId !== $actor->tenantId()) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }

        return $tenantId;
    }

    private function assertCan(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
    }
}
