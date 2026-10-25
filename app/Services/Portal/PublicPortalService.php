<?php

declare(strict_types=1);

namespace Sesam\Services\Portal;

use Sesam\Core\Crm\ClientStatus;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Messaging\DeliveryStatus;
use Sesam\Core\Session\Session;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\Payment;
use Sesam\Models\Tenant;
use Sesam\Repositories\AuthAttemptRepository;
use Sesam\Repositories\ClientActivityRepository;
use Sesam\Repositories\ClientRepository;
use Sesam\Services\Loyer\RentService;
use Sesam\Services\MessagingService;
use Sesam\Services\PaymentService;
use Sesam\Services\Reservation\BookingService;
use Sesam\Services\Service;

final class PublicPortalService extends Service
{
    public const RATE_MAX = 12;
    public const RATE_WINDOW = 15;
    private const CONFIRM_KEY = 'portal_confirm';
    private const CONFIRM_TTL = 1800;

    private readonly ClientRepository $clients;
    private readonly ClientActivityRepository $activities;
    private readonly AuthAttemptRepository $attempts;
    private readonly \PDO $pdo;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly Session $session,
        private readonly BookingService $booking,
        private readonly RentService $rent,
        private readonly PaymentService $payments,
        private readonly MessagingService $messaging,
    ) {
        $pdo = Connection::make($database);
        $this->pdo = $pdo;
        $this->clients = new ClientRepository($pdo);
        $this->activities = new ClientActivityRepository($pdo);
        $this->attempts = new AuthAttemptRepository($pdo);
    }

    public function requireTenant(string $slug): Tenant
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->slug() !== $slug || !$tenant->isActive()) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $tenant;
    }

    /**
     * @return array<string, mixed>
     */
    public function homePayload(Tenant $tenant): array
    {
        $sector = $tenant->sector();

        return [
            'tenant' => $tenant,
            'sector' => $sector,
            'lead' => $this->lead($sector),
            'cta' => $this->cta($sector),
            'catalog' => $this->catalog($sector),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: string}
     */
    public function submit(Tenant $tenant, array $input, string $ip): array
    {
        $this->assertRateLimit($ip);
        $this->attempts->record('portal', $ip);
        $sector = $tenant->sector();
        if (!in_array($sector, [Sector::RESERVATION, Sector::LOYER], true)) {
            throw new HttpException(404, 'Page introuvable.');
        }

        // Une demande refusée (créneau pris, champ métier invalide) ne doit laisser aucune fiche invitée dans le CRM.
        $this->pdo->beginTransaction();
        try {
            $clientId = $this->createGuest($tenant->id(), $input, $sector);
            $result = match ($sector) {
                Sector::RESERVATION => $this->submitReservation($tenant, $clientId, $input),
                Sector::LOYER => $this->submitInquiry($tenant, $clientId, $input),
                default => throw new HttpException(404, 'Page introuvable.'),
            };
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $result;
    }

    public function confirmation(Tenant $tenant, string $token): array
    {
        $stored = $this->session->get(self::CONFIRM_KEY);
        if (!is_array($stored)) {
            throw new HttpException(404, 'Page introuvable.');
        }
        $savedToken = is_string($stored['token'] ?? null) ? $stored['token'] : '';
        $tenantId = (int) ($stored['tenant_id'] ?? 0);
        $created = (int) ($stored['created_at'] ?? 0);
        if ($savedToken === '' || !hash_equals($savedToken, $token) || $tenantId !== $tenant->id()) {
            throw new HttpException(404, 'Page introuvable.');
        }
        if ($created < time() - self::CONFIRM_TTL) {
            $this->session->forget(self::CONFIRM_KEY);
            throw new HttpException(404, 'Page introuvable.');
        }

        return [
            'title' => is_string($stored['title'] ?? null) ? $stored['title'] : 'Demande enregistrée',
            'summary' => is_string($stored['summary'] ?? null) ? $stored['summary'] : '',
            'whatsapp_status' => is_string($stored['whatsapp_status'] ?? null) ? $stored['whatsapp_status'] : DeliveryStatus::FAILED,
            'whatsapp_mock' => (bool) ($stored['whatsapp_mock'] ?? true),
        ];
    }

    public function publicPayment(Tenant $tenant, int $paymentId): Payment
    {
        unset($tenant);
        $payment = $this->payments->publicPayment($paymentId);
        if ($payment->mode() !== 'sandbox') {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $payment;
    }

    /**
     * @return array{redirect: string}
     */
    public function sandboxConfirm(Tenant $tenant, int $paymentId): array
    {
        $this->payments->sandboxConfirmPublic($paymentId);
        $summary = $this->afterPaymentSuccess($tenant, $paymentId);
        $token = $this->storeConfirmation($tenant, $summary['title'], $summary['summary'], $summary['phone'], $summary['name'], true, $summary['email']);

        return ['redirect' => '/t/' . $tenant->slug() . '/confirmation?k=' . rawurlencode($token)];
    }

    /**
     * @return array{redirect: string}
     */
    public function sandboxCancel(Tenant $tenant, int $paymentId): array
    {
        $this->payments->sandboxCancelPublic($paymentId);
        $token = $this->storeConfirmation(
            $tenant,
            'Paiement annulé',
            'Le paiement a été annulé. Votre demande reste enregistrée côté commerce.',
            '',
            '',
            false,
        );

        return ['redirect' => '/t/' . $tenant->slug() . '/confirmation?k=' . rawurlencode($token)];
    }

    /**
     * Retour navigateur après NabooPay live : confirmation serveur via GET transaction.
     *
     * @return array{redirect: string}
     */
    public function returnFromProvider(Tenant $tenant, int $paymentId): array
    {
        $payment = $this->payments->syncFromProvider($tenant->id(), $paymentId);
        if ($payment->status() === \Sesam\Core\Payment\PaymentStatus::SUCCESS) {
            $summary = $this->afterPaymentSuccess($tenant, $paymentId);
            $token = $this->storeConfirmation($tenant, $summary['title'], $summary['summary'], $summary['phone'], $summary['name'], true, $summary['email']);

            return ['redirect' => '/t/' . $tenant->slug() . '/confirmation?k=' . rawurlencode($token)];
        }
        $token = $this->storeConfirmation(
            $tenant,
            'Paiement en attente',
            'Si vous avez payé, la confirmation arrivera sous peu. Sinon, vous pouvez réessayer depuis le commerce.',
            '',
            '',
            false,
        );

        return ['redirect' => '/t/' . $tenant->slug() . '/confirmation?k=' . rawurlencode($token)];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(string $sector): array
    {
        return match ($sector) {
            Sector::RESERVATION => $this->booking->publicCatalog(),
            Sector::LOYER => ['listings' => $this->rent->publicCatalog()],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: string}
     */
    private function submitReservation(Tenant $tenant, int $clientId, array $input): array
    {
        $appointmentId = $this->booking->publicCreate($input + ['client_id' => $clientId]);
        $appointment = $this->booking->publicFind($appointmentId);
        $summary = $appointment->serviceName() . ' — ' . $this->booking->human($appointment->startsAt());
        if ($appointment->depositAmountMinor() < 100) {
            $confirmed = $this->booking->publicAutoConfirm($appointmentId);
            $title = $confirmed ? 'Rendez-vous confirmé' : 'Demande de rendez-vous envoyée';
            $summary .= $confirmed ? '' : ' — le salon confirme très vite.';
            $token = $this->storeConfirmation($tenant, $title, $summary, (string) ($input['phone'] ?? ''), (string) ($input['full_name'] ?? ''), true, (string) ($input['email'] ?? ''));

            return ['redirect' => '/t/' . $tenant->slug() . '/confirmation?k=' . rawurlencode($token)];
        }
        $checkout = $this->payments->initiatePublic([
            'amount_minor' => $appointment->depositAmountMinor(),
            'description' => 'Acompte RDV — ' . $appointment->serviceName(),
            'client_id' => $clientId,
        ], '/t/' . $tenant->slug() . '/paiement/{id}/bac-a-sable');
        $this->booking->publicAttachPayment($appointmentId, $checkout['payment']->id());

        return ['redirect' => $this->relativeCheckout($checkout['checkout_url'])];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{redirect: string}
     */
    private function submitInquiry(Tenant $tenant, int $clientId, array $input): array
    {
        $message = trim((string) ($input['message'] ?? ''));
        $unitLabel = trim((string) ($input['unit_label'] ?? ''));
        $summary = $unitLabel !== '' ? 'Demande locative — ' . $unitLabel : 'Demande locative';
        if ($message !== '') {
            $summary .= ' : ' . mb_substr($message, 0, 180);
        }
        $this->activities->insert($tenant->id(), $clientId, null, 'portal', $summary);
        $token = $this->storeConfirmation($tenant, 'Message envoyé', $summary, (string) ($input['phone'] ?? ''), (string) ($input['full_name'] ?? ''), true, (string) ($input['email'] ?? ''));

        return ['redirect' => '/t/' . $tenant->slug() . '/confirmation?k=' . rawurlencode($token)];
    }

    /**
     * @return array{title: string, summary: string, phone: string, name: string, email: string}
     */
    private function afterPaymentSuccess(Tenant $tenant, int $paymentId): array
    {
        $payment = $this->payments->publicPayment($paymentId);
        $phone = '';
        $name = '';
        $email = '';
        $client = $payment->clientId() !== null ? $this->clients->findById($payment->clientId(), $tenant->id()) : null;
        if ($client !== null) {
            $phone = (string) ($client->phone() ?? '');
            $name = $client->fullName();
            $email = (string) ($client->email() ?? '');
        }

        if ($tenant->sector() === Sector::RESERVATION) {
            $appointment = $this->booking->publicFindByPayment($paymentId);
            if ($appointment !== null) {
                $this->booking->publicConfirm($appointment->id());

                return [
                    'title' => 'Rendez-vous confirmé',
                    'summary' => $appointment->serviceName() . ' — ' . $this->booking->human($appointment->startsAt()),
                    'phone' => $phone,
                    'name' => $name,
                    'email' => $email,
                ];
            }
        }

        return [
            'title' => 'Paiement enregistré',
            'summary' => $payment->description(),
            'phone' => $phone,
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createGuest(int $tenantId, array $input, string $sector): int
    {
        $name = trim((string) ($input['full_name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['full_name'] = 'Indiquez votre nom (120 caractères max.).';
        }
        if ($phone === '' || mb_strlen($phone) > 40) {
            $errors['phone'] = 'Indiquez un téléphone (40 caractères max.).';
        }
        if ($email !== '') {
            if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'E-mail invalide.';
            }
        }
        if ($sector === Sector::LOYER) {
            $message = trim((string) ($input['message'] ?? ''));
            if (mb_strlen($message) > 500) {
                $errors['message'] = 'Message trop long.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $clientId = $this->clients->insert($tenantId, [
            'full_name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'status' => ClientStatus::ACTIVE,
        ]);
        $this->activities->insert($tenantId, $clientId, null, 'portal', 'Arrivée via le portail public');

        return $clientId;
    }

    private function storeConfirmation(
        Tenant $tenant,
        string $title,
        string $summary,
        string $phone,
        string $name,
        bool $notify = true,
        string $email = '',
    ): string {
        $whatsapp = ['status' => DeliveryStatus::FAILED, 'mock' => $this->messaging->isMockMode()];
        if ($notify && ($phone !== '' || $email !== '')) {
            $body = $tenant->displayName() . ' — ' . $title . '. ' . $summary;
            $whatsapp = $this->messaging->notifyClient($phone, $email, $name !== '' ? $name : 'Client', $title . ' — ' . $tenant->displayName(), $body);
        }
        $token = bin2hex(random_bytes(16));
        $this->session->put(self::CONFIRM_KEY, [
            'token' => $token,
            'tenant_id' => $tenant->id(),
            'title' => $title,
            'summary' => $summary,
            'whatsapp_status' => $whatsapp['status'],
            'whatsapp_mock' => $whatsapp['mock'],
            'created_at' => time(),
        ]);

        return $token;
    }

    private function assertRateLimit(string $ip): void
    {
        if ($this->attempts->countRecent('portal', $ip, self::RATE_WINDOW) >= self::RATE_MAX) {
            throw new HttpException(429, 'Trop de tentatives. Réessayez dans quelques minutes.');
        }
    }

    private function relativeCheckout(string $checkoutUrl): string
    {
        if (preg_match('#^https?://#i', $checkoutUrl) === 1) {
            return $checkoutUrl;
        }
        $path = parse_url($checkoutUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            throw new HttpException(500, 'Une erreur est survenue.');
        }

        return $path;
    }

    private function lead(string $sector): string
    {
        return match ($sector) {
            Sector::RESERVATION => 'Choisissez une prestation, un créneau, puis confirmez. Pas de compte à créer.',
            Sector::LOYER => 'Parcourez les biens disponibles et laissez vos coordonnées. Aucun loyer n’est encaissé ici.',
            default => '',
        };
    }

    private function cta(string $sector): string
    {
        return match ($sector) {
            Sector::RESERVATION => 'Réserver',
            Sector::LOYER => 'Envoyer ma demande',
            default => '',
        };
    }
}
