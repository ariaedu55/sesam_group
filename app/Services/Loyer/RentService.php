<?php

declare(strict_types=1);

namespace Sesam\Services\Loyer;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Loyer\LeaseStatus;
use Sesam\Core\Loyer\RentDueStatus;
use Sesam\Core\Messaging\MessageChannel;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\LoyerLease;
use Sesam\Models\LoyerProperty;
use Sesam\Models\LoyerRentDue;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\LoyerLeaseRepository;
use Sesam\Repositories\LoyerPropertyRepository;
use Sesam\Repositories\LoyerRentDueRepository;
use Sesam\Repositories\LoyerUnitRepository;
use Sesam\Repositories\PaymentRepository;
use Sesam\Services\MessagingService;
use Sesam\Services\PaymentService;
use Sesam\Services\Service;

final class RentService extends Service
{
    public const PER_PAGE = 20;
    private const SCHEDULE_MONTHS = 12;

    private readonly LoyerPropertyRepository $properties;
    private readonly LoyerUnitRepository $units;
    private readonly LoyerLeaseRepository $leases;
    private readonly LoyerRentDueRepository $dues;
    private readonly ClientRepository $clients;
    private readonly PaymentRepository $payments;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ?PaymentService $paymentService = null,
        private readonly ?MessagingService $messaging = null,
    ) {
        $pdo = Connection::make($database);
        $this->properties = new LoyerPropertyRepository($pdo);
        $this->units = new LoyerUnitRepository($pdo);
        $this->leases = new LoyerLeaseRepository($pdo);
        $this->dues = new LoyerRentDueRepository($pdo);
        $this->clients = new ClientRepository($pdo);
        $this->payments = new PaymentRepository($pdo);
    }

    /**
     * @return list<LoyerProperty>
     */
    public function listProperties(User $actor): array
    {
        $this->assertLoyer($actor, Permission::LOYER_VIEW);

        return $this->properties->listAll($this->tenantId($actor));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createProperty(User $actor, array $input): int
    {
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_PROPERTIES);
        $name = trim((string) ($input['name'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(['name' => 'Nom de bien invalide.']);
        }
        if (mb_strlen($address) > 255) {
            throw new ValidationException(['address' => 'Adresse trop longue.']);
        }

        return $this->properties->insert($tenantId, [
            'name' => $name,
            'address' => $address !== '' ? $address : null,
        ]);
    }

    public function findProperty(User $actor, int $id): LoyerProperty
    {
        $this->assertLoyer($actor, Permission::LOYER_VIEW);
        $property = $this->properties->findById($id, $this->tenantId($actor));
        if ($property === null) {
            throw new HttpException(404, 'Bien introuvable.');
        }

        return $property;
    }

    /**
     * @return list<\Sesam\Models\LoyerUnit>
     */
    public function listUnits(User $actor, int $propertyId): array
    {
        $this->findProperty($actor, $propertyId);

        return $this->units->listForProperty($propertyId, $this->tenantId($actor));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createUnit(User $actor, int $propertyId, array $input): void
    {
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_PROPERTIES);
        $this->findProperty($actor, $propertyId);
        $label = trim((string) ($input['label'] ?? ''));
        $rent = (int) ($input['default_rent_minor'] ?? 0);
        if ($label === '' || mb_strlen($label) > 80) {
            throw new ValidationException(['label' => 'Libellé d\'unité invalide.']);
        }
        if ($rent < 0) {
            throw new ValidationException(['default_rent_minor' => 'Loyer invalide.']);
        }
        $this->units->insert($tenantId, $propertyId, [
            'label' => $label,
            'default_rent_minor' => $rent,
            'is_active' => 1,
        ]);
    }

    /**
     * @return array{leases: list<LoyerLease>, total: int, page: int, pages: int}
     */
    public function paginateLeases(User $actor, int $page): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_VIEW);
        $page = max(1, $page);
        $total = $this->leases->countForTenant($tenantId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return [
            'leases' => $this->leases->listForTenant($tenantId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public function findLease(User $actor, int $id): LoyerLease
    {
        $lease = $this->leases->findById($id, $this->tenantId($actor));
        $this->assertLoyer($actor, Permission::LOYER_VIEW);
        if ($lease === null) {
            throw new HttpException(404, 'Contrat introuvable.');
        }

        return $lease;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createLease(User $actor, array $input): int
    {
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_LEASES);
        $unitId = (int) ($input['unit_id'] ?? 0);
        $clientId = (int) ($input['client_id'] ?? 0);
        $rent = (int) ($input['rent_minor'] ?? 0);
        $dueDay = (int) ($input['due_day'] ?? 5);
        $startsOn = trim((string) ($input['starts_on'] ?? ''));
        $unit = $this->units->findById($unitId, $tenantId);
        if ($unit === null || !$unit->isActive()) {
            throw new ValidationException(['unit_id' => 'Unité introuvable.']);
        }
        if ($this->clients->findById($clientId, $tenantId) === null) {
            throw new ValidationException(['client_id' => 'Locataire introuvable.']);
        }
        if ($rent < 100) {
            throw new ValidationException(['rent_minor' => 'Montant de loyer minimum 100 FCFA.']);
        }
        if ($dueDay < 1 || $dueDay > 28) {
            throw new ValidationException(['due_day' => 'Jour d\'échéance entre 1 et 28.']);
        }
        $startsOn = $this->parseDate($startsOn, 'starts_on');
        $leaseId = $this->leases->insert($tenantId, [
            'unit_id' => $unitId,
            'client_id' => $clientId,
            'rent_minor' => $rent,
            'due_day' => $dueDay,
            'starts_on' => $startsOn,
            'status' => LeaseStatus::ACTIVE,
        ]);
        $this->dues->insertBatch($tenantId, $this->buildSchedule($leaseId, $rent, $dueDay, $startsOn));

        return $leaseId;
    }

    /**
     * @return array{
     *     dues: list<LoyerRentDue>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     filterLate: bool
     * }
     */
    public function paginateDues(User $actor, int $page, ?string $filterLate): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_VIEW);
        $today = gmdate('Y-m-d');
        $late = $filterLate === '1' ? '1' : null;
        $page = max(1, $page);
        $total = $this->dues->countForTenant($tenantId, $late, $today);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $dues = $this->dues->listForTenant($tenantId, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $late, $today);
        foreach ($dues as $index => $due) {
            $dues[$index] = $this->syncDuePayment($due, $tenantId);
        }

        return [
            'dues' => $dues,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'filterLate' => $late === '1',
        ];
    }

    public function findDue(User $actor, int $id): LoyerRentDue
    {
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_VIEW);
        $due = $this->dues->findById($id, $tenantId);
        if ($due === null) {
            throw new HttpException(404, 'Échéance introuvable.');
        }

        return $this->syncDuePayment($due, $tenantId);
    }

    public function initiateDuePayment(User $actor, int $dueId): string
    {
        if ($this->paymentService === null) {
            throw new HttpException(503, 'Paiements non disponibles.');
        }
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_DUES);
        $due = $this->findDue($actor, $dueId);
        if ($due->status() === RentDueStatus::PAID) {
            throw new ValidationException(['status' => 'Échéance déjà payée.']);
        }
        if ($due->paymentId() !== null) {
            $payment = $this->payments->findById($due->paymentId(), $tenantId);
            if ($payment !== null && $payment->status() === PaymentStatus::PENDING) {
                throw new ValidationException(['payment' => 'Un paiement est déjà en cours.']);
            }
        }
        $lease = $this->findLease($actor, $due->leaseId());
        $result = $this->paymentService->initiate($actor, [
            'amount_minor' => $due->amountMinor(),
            'description' => 'Loyer ' . $due->dueDate() . ' — ' . $lease->propertyName() . ' ' . $lease->unitLabel(),
            'client_id' => $lease->clientId(),
        ]);
        $this->dues->attachPayment($dueId, $tenantId, $result['payment']->id());

        return $result['checkout_url'];
    }

    public function sendDueReminder(User $actor, int $dueId): void
    {
        if ($this->messaging === null) {
            throw new HttpException(503, 'Messagerie non disponible.');
        }
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_DUES);
        $due = $this->findDue($actor, $dueId);
        if ($due->status() === RentDueStatus::PAID) {
            throw new ValidationException(['status' => 'Échéance déjà payée.']);
        }
        $lease = $this->findLease($actor, $due->leaseId());
        $client = $this->clients->findById($lease->clientId(), $tenantId);
        $recipient = $client?->phone() ?? $client?->email() ?? '';
        if ($recipient === '') {
            throw new ValidationException(['recipient' => 'Aucun téléphone ou e-mail pour le locataire.']);
        }
        $this->messaging->send($actor, [
            'channel' => $client?->phone() !== null && $client->phone() !== '' ? MessageChannel::WHATSAPP : MessageChannel::EMAIL,
            'recipient' => $recipient,
            'template_slug' => 'test_message',
            'name' => $client?->fullName() ?? 'Locataire',
            'amount' => (string) $due->amountMinor(),
            'client' => $lease->propertyName() . ' — échéance ' . $due->dueDate(),
        ]);
    }

    /**
     * @return array{products: never, clients: list<\Sesam\Models\Client>, units: list<\Sesam\Models\LoyerUnit>}
     */
    public function leaseFormCatalog(User $actor): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertLoyer($actor, Permission::LOYER_LEASES);

        return [
            'clients' => $this->clients->search($tenantId, '', null, null, 200, 0),
            'units' => $this->units->listActive($tenantId),
        ];
    }

    /**
     * @return array{rent_due_today: int, rent_late: int, leases_active: int}
     */
    public function dashboardCounts(int $tenantId): array
    {
        $today = gmdate('Y-m-d');

        return [
            'rent_due_today' => $this->dues->countDueToday($tenantId, $today),
            'rent_late' => $this->dues->countLate($tenantId, $today),
            'leases_active' => $this->leases->countActive($tenantId),
        ];
    }

    /**
     * @return list<array{lease_id: int, due_date: string, amount_minor: int}>
     */
    private function buildSchedule(int $leaseId, int $amountMinor, int $dueDay, string $startsOn): array
    {
        $rows = [];
        $anchor = strtotime($startsOn . ' UTC');
        if ($anchor === false) {
            return $rows;
        }
        for ($monthOffset = 0; $monthOffset < self::SCHEDULE_MONTHS; ++$monthOffset) {
            $monthStart = strtotime('+' . $monthOffset . ' months', $anchor);
            if ($monthStart === false) {
                continue;
            }
            $yearMonth = gmdate('Y-m', $monthStart);
            $daysInMonth = (int) gmdate('t', strtotime($yearMonth . '-01 UTC'));
            $day = min($dueDay, $daysInMonth);
            $rows[] = [
                'lease_id' => $leaseId,
                'due_date' => $yearMonth . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'amount_minor' => $amountMinor,
            ];
        }

        return $rows;
    }

    private function syncDuePayment(LoyerRentDue $due, int $tenantId): LoyerRentDue
    {
        if ($due->paymentId() === null || $due->status() === RentDueStatus::PAID) {
            return $due;
        }
        $payment = $this->payments->findById($due->paymentId(), $tenantId);
        if ($payment === null) {
            return $due;
        }
        $this->dues->syncPaidFromPayment($due->id(), $tenantId, $payment->id(), $payment->status());

        return $this->dues->findById($due->id(), $tenantId) ?? $due;
    }

    private function parseDate(string $value, string $field): string
    {
        if ($value === '') {
            throw new ValidationException([$field => 'Date requise.']);
        }
        $timestamp = strtotime($value . ' UTC');
        if ($timestamp === false) {
            throw new ValidationException([$field => 'Date invalide.']);
        }

        return gmdate('Y-m-d', $timestamp);
    }

    /**
     * @return list<array{property: LoyerProperty, units: list<\Sesam\Models\LoyerUnit>}>
     */
    public function publicCatalog(): array
    {
        $tenantId = $this->requirePublicSector();
        $items = [];
        foreach ($this->properties->listAll($tenantId) as $property) {
            $units = [];
            foreach ($this->units->listForProperty($property->id(), $tenantId) as $unit) {
                if ($unit->isActive()) {
                    $units[] = $unit;
                }
            }
            $items[] = [
                'property' => $property,
                'units' => $units,
            ];
        }

        return $items;
    }

    private function requirePublicSector(): int
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::LOYER) {
            throw new HttpException(404, 'Page introuvable.');
        }

        return $tenant->id();
    }

    private function tenantId(User $actor): int
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

    private function assertLoyer(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::LOYER) {
            throw new HttpException(403, 'Ce module est réservé aux commerces secteur Loyer.');
        }
    }
}
