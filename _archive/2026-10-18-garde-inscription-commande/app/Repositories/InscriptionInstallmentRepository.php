<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Inscription\InstallmentStatus;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Models\InscriptionInstallment;

final class InscriptionInstallmentRepository extends Repository
{
    public function countPending(int $tenantId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inscription_installments
             WHERE tenant_id = :tenant_id AND status = :status'
        );
        $statement->execute(['tenant_id' => $tenantId, 'status' => InstallmentStatus::PENDING]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<InscriptionInstallment>
     */
    public function listForEnrollment(int $enrollmentId, int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, enrollment_id, label, amount_minor, due_date, status, payment_id, paid_at
             FROM inscription_installments
             WHERE enrollment_id = :enrollment_id AND tenant_id = :tenant_id
             ORDER BY due_date ASC, id ASC'
        );
        $statement->execute(['enrollment_id' => $enrollmentId, 'tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = $this->map($row);
        }

        return $items;
    }

    public function findById(int $id, int $tenantId): ?InscriptionInstallment
    {
        $statement = $this->pdo->prepare(
            'SELECT id, enrollment_id, label, amount_minor, due_date, status, payment_id, paid_at
             FROM inscription_installments WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function findByPaymentId(int $paymentId, int $tenantId): ?InscriptionInstallment
    {
        $statement = $this->pdo->prepare(
            'SELECT id, enrollment_id, label, amount_minor, due_date, status, payment_id, paid_at
             FROM inscription_installments WHERE payment_id = :payment_id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['payment_id' => $paymentId, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param list<array{
     *     enrollment_id: int,
     *     fee_type_id: int,
     *     label: string,
     *     amount_minor: int,
     *     due_date: string
     * }> $rows
     */
    public function insertBatch(int $tenantId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO inscription_installments (
                tenant_id, enrollment_id, fee_type_id, label, amount_minor, due_date, status,
                payment_id, paid_at, created_at, updated_at
             ) VALUES (
                :tenant_id, :enrollment_id, :fee_type_id, :label, :amount_minor, :due_date, :status,
                NULL, NULL, :created_at, :updated_at
             )'
        );
        foreach ($rows as $row) {
            $statement->execute([
                'tenant_id' => $tenantId,
                'enrollment_id' => $row['enrollment_id'],
                'fee_type_id' => $row['fee_type_id'],
                'label' => $row['label'],
                'amount_minor' => $row['amount_minor'],
                'due_date' => $row['due_date'],
                'status' => InstallmentStatus::PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function attachPayment(int $id, int $tenantId, int $paymentId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE inscription_installments SET payment_id = :payment_id, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'payment_id' => $paymentId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function syncPaidFromPayment(int $id, int $tenantId, int $paymentId, string $paymentStatus): void
    {
        if ($paymentStatus !== PaymentStatus::SUCCESS) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE inscription_installments
             SET status = :status, paid_at = COALESCE(paid_at, :paid_at), updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id AND payment_id = :payment_id AND status = :pending'
        );
        $statement->execute([
            'status' => InstallmentStatus::PAID,
            'paid_at' => $now,
            'updated_at' => $now,
            'id' => $id,
            'tenant_id' => $tenantId,
            'payment_id' => $paymentId,
            'pending' => InstallmentStatus::PENDING,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): InscriptionInstallment
    {
        return new InscriptionInstallment([
            'id' => (int) $row['id'],
            'enrollment_id' => (int) $row['enrollment_id'],
            'label' => (string) $row['label'],
            'amount_minor' => (int) $row['amount_minor'],
            'due_date' => (string) $row['due_date'],
            'status' => (string) $row['status'],
            'payment_id' => $row['payment_id'],
            'paid_at' => $row['paid_at'],
        ]);
    }
}
