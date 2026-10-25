<?php

declare(strict_types=1);

namespace Sesam\Services\Inscription;

use Sesam\Core\Auth\Permission;
use Sesam\Core\Database\Connection;
use Sesam\Core\HttpException;
use Sesam\Core\Inscription\EnrollmentStatus;
use Sesam\Core\Inscription\InstallmentStatus;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Core\Validation\ValidationException;
use Sesam\Models\InscriptionEnrollment;
use Sesam\Models\InscriptionFeeType;
use Sesam\Models\InscriptionInstallment;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Repositories\ClientRepository;
use Sesam\Repositories\InscriptionClassRepository;
use Sesam\Repositories\InscriptionEnrollmentRepository;
use Sesam\Repositories\InscriptionFeeTypeRepository;
use Sesam\Repositories\InscriptionInstallmentRepository;
use Sesam\Repositories\PaymentRepository;
use Sesam\Services\PaymentService;
use Sesam\Services\Service;

final class EnrollmentService extends Service
{
    public const PER_PAGE = 20;

    private readonly InscriptionClassRepository $classes;
    private readonly InscriptionFeeTypeRepository $feeTypes;
    private readonly InscriptionEnrollmentRepository $enrollments;
    private readonly InscriptionInstallmentRepository $installments;
    private readonly ClientRepository $clients;
    private readonly PaymentRepository $payments;

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $database
     */
    public function __construct(
        array $database,
        private readonly TenantContext $context,
        private readonly ?PaymentService $paymentService = null,
    ) {
        $pdo = Connection::make($database);
        $this->classes = new InscriptionClassRepository($pdo);
        $this->feeTypes = new InscriptionFeeTypeRepository($pdo);
        $this->enrollments = new InscriptionEnrollmentRepository($pdo);
        $this->installments = new InscriptionInstallmentRepository($pdo);
        $this->clients = new ClientRepository($pdo);
        $this->payments = new PaymentRepository($pdo);
    }

    /**
     * @return list<\Sesam\Models\InscriptionClass>
     */
    public function listClasses(User $actor): array
    {
        $this->assertInscription($actor, Permission::INSCRIPTION_VIEW);

        return $this->classes->listAll($this->tenantId($actor));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createClass(User $actor, array $input): int
    {
        $this->assertInscription($actor, Permission::INSCRIPTION_CLASSES);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(['name' => 'Nom de classe invalide.']);
        }

        return $this->classes->insert($this->tenantId($actor), $name);
    }

    /**
     * @return list<InscriptionFeeType>
     */
    public function listFeeTypes(User $actor): array
    {
        $this->assertInscription($actor, Permission::INSCRIPTION_VIEW);

        return $this->feeTypes->listAll($this->tenantId($actor));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createFeeType(User $actor, array $input): int
    {
        $this->assertInscription($actor, Permission::INSCRIPTION_FEES);
        $name = trim((string) ($input['name'] ?? ''));
        $total = (int) ($input['total_minor'] ?? 0);
        $count = (int) ($input['installment_count'] ?? 1);
        if ($name === '' || mb_strlen($name) > 120) {
            throw new ValidationException(['name' => 'Nom de frais invalide.']);
        }
        if ($total < 100) {
            throw new ValidationException(['total_minor' => 'Montant minimum 100 FCFA.']);
        }
        if ($count < 1 || $count > 12) {
            throw new ValidationException(['installment_count' => 'Nombre de tranches entre 1 et 12.']);
        }

        return $this->feeTypes->insert($this->tenantId($actor), [
            'name' => $name,
            'total_minor' => $total,
            'installment_count' => $count,
        ]);
    }

    /**
     * @return array{
     *     enrollments: list<InscriptionEnrollment>,
     *     total: int,
     *     page: int,
     *     pages: int
     * }
     */
    public function paginateEnrollments(User $actor, int $page): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertInscription($actor, Permission::INSCRIPTION_VIEW);
        $page = max(1, $page);
        $total = $this->enrollments->countForTenant($tenantId);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return [
            'enrollments' => $this->enrollments->listForTenant($tenantId, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return array{
     *     enrollment: InscriptionEnrollment,
     *     installments: list<InscriptionInstallment>,
     *     total_minor: int,
     *     paid_minor: int,
     *     pending_minor: int
     * }
     */
    public function enrollmentDetail(User $actor, int $id): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertInscription($actor, Permission::INSCRIPTION_VIEW);
        $enrollment = $this->findEnrollment($actor, $id);
        $installments = [];
        $total = 0;
        $paid = 0;
        foreach ($this->installments->listForEnrollment($id, $tenantId) as $row) {
            $installments[] = $this->syncInstallmentPayment($row, $tenantId);
        }
        foreach ($installments as $inst) {
            $total += $inst->amountMinor();
            if ($inst->status() === InstallmentStatus::PAID) {
                $paid += $inst->amountMinor();
            }
        }

        return [
            'enrollment' => $enrollment,
            'installments' => $installments,
            'total_minor' => $total,
            'paid_minor' => $paid,
            'pending_minor' => $total - $paid,
        ];
    }

    public function findEnrollment(User $actor, int $id): InscriptionEnrollment
    {
        $enrollment = $this->enrollments->findById($id, $this->tenantId($actor));
        $this->assertInscription($actor, Permission::INSCRIPTION_VIEW);
        if ($enrollment === null) {
            throw new HttpException(404, 'Dossier introuvable.');
        }

        return $enrollment;
    }

    /**
     * @return array{
     *     classes: list<\Sesam\Models\InscriptionClass>,
     *     feeTypes: list<InscriptionFeeType>,
     *     clients: list<\Sesam\Models\Client>
     * }
     */
    public function enrollmentFormCatalog(User $actor): array
    {
        $tenantId = $this->tenantId($actor);
        $this->assertInscription($actor, Permission::INSCRIPTION_ENROLLMENTS);

        return [
            'classes' => $this->classes->listActive($tenantId),
            'feeTypes' => $this->feeTypes->listActive($tenantId),
            'clients' => $this->clients->search($tenantId, '', null, null, 200, 0),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createEnrollment(User $actor, array $input): int
    {
        $tenantId = $this->tenantId($actor);
        $this->assertInscription($actor, Permission::INSCRIPTION_ENROLLMENTS);
        $classId = (int) ($input['class_id'] ?? 0);
        $guardianId = (int) ($input['guardian_client_id'] ?? 0);
        $studentName = trim((string) ($input['student_name'] ?? ''));
        $firstDue = $this->parseDate(trim((string) ($input['first_due_date'] ?? '')), 'first_due_date');
        /** @var list<int|string> $rawFeeIds */
        $rawFeeIds = is_array($input['fee_type_ids'] ?? null) ? $input['fee_type_ids'] : [];
        $feeTypeIds = [];
        foreach ($rawFeeIds as $raw) {
            if (ctype_digit((string) $raw)) {
                $feeTypeIds[] = (int) $raw;
            }
        }
        $feeTypeIds = array_values(array_unique($feeTypeIds));

        if ($this->classes->findById($classId, $tenantId) === null) {
            throw new ValidationException(['class_id' => 'Classe introuvable.']);
        }
        if ($this->clients->findById($guardianId, $tenantId) === null) {
            throw new ValidationException(['guardian_client_id' => 'Responsable introuvable.']);
        }
        if ($studentName === '' || mb_strlen($studentName) > 120) {
            throw new ValidationException(['student_name' => 'Nom de l\'élève invalide.']);
        }
        if ($feeTypeIds === []) {
            throw new ValidationException(['fee_type_ids' => 'Sélectionnez au moins un type de frais.']);
        }

        $feeTypes = [];
        foreach ($feeTypeIds as $feeId) {
            $fee = $this->feeTypes->findById($feeId, $tenantId);
            if ($fee === null || !$fee->isActive()) {
                throw new ValidationException(['fee_type_ids' => 'Type de frais invalide.']);
            }
            $feeTypes[] = $fee;
        }

        $enrollmentId = $this->enrollments->insert($tenantId, [
            'class_id' => $classId,
            'student_name' => $studentName,
            'guardian_client_id' => $guardianId,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $batch = [];
        foreach ($feeTypes as $fee) {
            $batch = array_merge($batch, $this->buildInstallmentRows($enrollmentId, $fee, $firstDue));
        }
        $this->installments->insertBatch($tenantId, $batch);

        return $enrollmentId;
    }

    public function initiateInstallmentPayment(User $actor, int $enrollmentId, int $installmentId): string
    {
        if ($this->paymentService === null) {
            throw new HttpException(503, 'Paiements non disponibles.');
        }
        $tenantId = $this->tenantId($actor);
        $this->assertInscription($actor, Permission::INSCRIPTION_ENROLLMENTS);
        $enrollment = $this->findEnrollment($actor, $enrollmentId);
        $installment = $this->findInstallment($actor, $enrollmentId, $installmentId);
        $installment = $this->syncInstallmentPayment($installment, $tenantId);
        if ($installment->status() === InstallmentStatus::PAID) {
            throw new ValidationException(['status' => 'Tranche déjà payée.']);
        }
        if ($installment->paymentId() !== null) {
            $payment = $this->payments->findById($installment->paymentId(), $tenantId);
            if ($payment !== null && $payment->status() === PaymentStatus::PENDING) {
                throw new ValidationException(['payment' => 'Un paiement est déjà en cours.']);
            }
        }
        $result = $this->paymentService->initiate($actor, [
            'amount_minor' => $installment->amountMinor(),
            'description' => 'Frais scolarité — ' . $enrollment->studentName() . ' — ' . $installment->label(),
            'client_id' => $enrollment->guardianClientId(),
        ]);
        $this->installments->attachPayment($installmentId, $tenantId, $result['payment']->id());

        return $result['checkout_url'];
    }

    public function findInstallment(User $actor, int $enrollmentId, int $installmentId): InscriptionInstallment
    {
        $tenantId = $this->tenantId($actor);
        $this->assertInscription($actor, Permission::INSCRIPTION_VIEW);
        $installment = $this->installments->findById($installmentId, $tenantId);
        if ($installment === null || $installment->enrollmentId() !== $enrollmentId) {
            throw new HttpException(404, 'Tranche introuvable.');
        }

        return $installment;
    }

    /**
     * @return list<array{
     *     enrollment_id: int,
     *     fee_type_id: int,
     *     label: string,
     *     amount_minor: int,
     *     due_date: string
     * }>
     */
    private function buildInstallmentRows(int $enrollmentId, InscriptionFeeType $fee, string $firstDueDate): array
    {
        $count = max(1, min(12, $fee->installmentCount()));
        $total = $fee->totalMinor();
        $base = intdiv($total, $count);
        $remainder = $total % $count;
        $anchor = strtotime($firstDueDate . ' UTC');
        if ($anchor === false) {
            return [];
        }
        $rows = [];
        for ($index = 0; $index < $count; ++$index) {
            $amount = $base;
            if ($index === $count - 1) {
                $amount += $remainder;
            }
            $dueTimestamp = strtotime('+' . $index . ' months', $anchor);
            if ($dueTimestamp === false) {
                continue;
            }
            $rows[] = [
                'enrollment_id' => $enrollmentId,
                'fee_type_id' => $fee->id(),
                'label' => $fee->name() . ' — tranche ' . ($index + 1) . '/' . $count,
                'amount_minor' => $amount,
                'due_date' => gmdate('Y-m-d', $dueTimestamp),
            ];
        }

        return $rows;
    }

    private function syncInstallmentPayment(InscriptionInstallment $installment, int $tenantId): InscriptionInstallment
    {
        if ($installment->paymentId() === null || $installment->status() === InstallmentStatus::PAID) {
            return $installment;
        }
        $payment = $this->payments->findById($installment->paymentId(), $tenantId);
        if ($payment === null) {
            return $installment;
        }
        $this->installments->syncPaidFromPayment(
            $installment->id(),
            $tenantId,
            $payment->id(),
            $payment->status(),
        );

        return $this->installments->findById($installment->id(), $tenantId) ?? $installment;
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
     * @return array{classes: list<\Sesam\Models\InscriptionClass>, feeTypes: list<InscriptionFeeType>}
     */
    public function publicCatalog(): array
    {
        $tenantId = $this->requirePublicSector();

        return [
            'classes' => $this->classes->listActive($tenantId),
            'feeTypes' => $this->feeTypes->listActive($tenantId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function publicCreate(array $input): int
    {
        $tenantId = $this->requirePublicSector();
        $classId = (int) ($input['class_id'] ?? 0);
        $guardianId = (int) ($input['client_id'] ?? 0);
        $studentName = trim((string) ($input['student_name'] ?? ''));
        $firstDue = $this->parseDate(trim((string) ($input['first_due_date'] ?? gmdate('Y-m-d'))), 'first_due_date');
        /** @var list<int|string> $rawFeeIds */
        $rawFeeIds = is_array($input['fee_type_ids'] ?? null) ? $input['fee_type_ids'] : [];
        $feeTypeIds = [];
        foreach ($rawFeeIds as $raw) {
            if (ctype_digit((string) $raw)) {
                $feeTypeIds[] = (int) $raw;
            }
        }
        $feeTypeIds = array_values(array_unique($feeTypeIds));
        if ($feeTypeIds === []) {
            foreach ($this->feeTypes->listActive($tenantId) as $activeFee) {
                $feeTypeIds[] = $activeFee->id();
            }
        }

        $class = $this->classes->findById($classId, $tenantId);
        if ($class === null || !$class->isActive()) {
            throw new ValidationException(['class_id' => 'Classe introuvable.']);
        }
        if ($this->clients->findById($guardianId, $tenantId) === null) {
            throw new ValidationException(['full_name' => 'Vos informations sont incomplètes.']);
        }
        if ($studentName === '' || mb_strlen($studentName) > 120) {
            throw new ValidationException(['student_name' => 'Nom de l\'élève invalide.']);
        }
        if ($feeTypeIds === []) {
            throw new ValidationException(['fee_type_ids' => 'Aucun type de frais n\'est ouvert pour l\'instant.']);
        }

        $feeTypes = [];
        foreach ($feeTypeIds as $feeId) {
            $fee = $this->feeTypes->findById($feeId, $tenantId);
            if ($fee === null || !$fee->isActive()) {
                throw new ValidationException(['fee_type_ids' => 'Type de frais invalide.']);
            }
            $feeTypes[] = $fee;
        }

        $enrollmentId = $this->enrollments->insert($tenantId, [
            'class_id' => $classId,
            'student_name' => $studentName,
            'guardian_client_id' => $guardianId,
            'status' => EnrollmentStatus::ACTIVE,
        ]);

        $batch = [];
        foreach ($feeTypes as $fee) {
            $batch = array_merge($batch, $this->buildInstallmentRows($enrollmentId, $fee, $firstDue));
        }
        $this->installments->insertBatch($tenantId, $batch);

        return $enrollmentId;
    }

    public function publicFind(int $id): InscriptionEnrollment
    {
        $enrollment = $this->enrollments->findById($id, $this->requirePublicSector());
        if ($enrollment === null) {
            throw new HttpException(404, 'Dossier introuvable.');
        }

        return $enrollment;
    }

    public function publicFirstInstallment(int $enrollmentId): ?InscriptionInstallment
    {
        $rows = $this->installments->listForEnrollment($enrollmentId, $this->requirePublicSector());

        return $rows[0] ?? null;
    }

    public function publicFindInstallmentByPayment(int $paymentId): ?InscriptionInstallment
    {
        return $this->installments->findByPaymentId($paymentId, $this->requirePublicSector());
    }

    public function publicAttachPayment(int $installmentId, int $paymentId): void
    {
        $this->installments->attachPayment($installmentId, $this->requirePublicSector(), $paymentId);
    }

    private function requirePublicSector(): int
    {
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::INSCRIPTION) {
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

    private function assertInscription(User $actor, string $permission): void
    {
        if (!$actor->can($permission)) {
            throw new HttpException(403, 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.');
        }
        $tenant = $this->context->tenant();
        if (!$tenant instanceof Tenant || $tenant->sector() !== Sector::INSCRIPTION) {
            throw new HttpException(403, 'Ce module est réservé aux commerces secteur Inscription.');
        }
    }
}
