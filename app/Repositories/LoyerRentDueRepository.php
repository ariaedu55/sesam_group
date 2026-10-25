<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Loyer\RentDueStatus;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Models\LoyerRentDue;

final class LoyerRentDueRepository extends Repository
{
    public function countDueToday(int $tenantId, string $today): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM loyer_rent_dues
             WHERE tenant_id = :tenant_id AND due_date = :today AND status = :status'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'today' => $today,
            'status' => RentDueStatus::PENDING,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countLate(int $tenantId, string $today): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM loyer_rent_dues
             WHERE tenant_id = :tenant_id AND due_date < :today AND status = :status'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'today' => $today,
            'status' => RentDueStatus::PENDING,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countForTenant(int $tenantId, ?string $filterLate, string $today): int
    {
        if ($filterLate === '1') {
            $statement = $this->pdo->prepare(
                'SELECT COUNT(*) FROM loyer_rent_dues
                 WHERE tenant_id = :tenant_id AND due_date < :today AND status = :status'
            );
            $statement->execute([
                'tenant_id' => $tenantId,
                'today' => $today,
                'status' => RentDueStatus::PENDING,
            ]);

            return (int) $statement->fetchColumn();
        }

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM loyer_rent_dues WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<LoyerRentDue>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset, ?string $filterLate, string $today): array
    {
        $sql = 'SELECT d.id, d.lease_id, d.due_date, d.amount_minor, d.status, d.payment_id,
                       c.full_name AS client_name, u.label AS unit_label, p.name AS property_name
                FROM loyer_rent_dues d
                INNER JOIN loyer_leases l ON l.id = d.lease_id AND l.tenant_id = d.tenant_id
                INNER JOIN clients c ON c.id = l.client_id AND c.tenant_id = d.tenant_id
                INNER JOIN loyer_units u ON u.id = l.unit_id AND u.tenant_id = d.tenant_id
                INNER JOIN loyer_properties p ON p.id = u.property_id AND p.tenant_id = d.tenant_id
                WHERE d.tenant_id = :tenant_id';
        if ($filterLate === '1') {
            $sql .= ' AND d.due_date < :today AND d.status = :pending';
        }
        $sql .= ' ORDER BY d.due_date ASC LIMIT :limit OFFSET :offset';

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue('tenant_id', $tenantId, \PDO::PARAM_INT);
        if ($filterLate === '1') {
            $statement->bindValue('today', $today);
            $statement->bindValue('pending', RentDueStatus::PENDING);
        }
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?LoyerRentDue
    {
        $statement = $this->pdo->prepare(
            'SELECT d.id, d.lease_id, d.due_date, d.amount_minor, d.status, d.payment_id,
                    c.full_name AS client_name, u.label AS unit_label, p.name AS property_name
             FROM loyer_rent_dues d
             INNER JOIN loyer_leases l ON l.id = d.lease_id AND l.tenant_id = d.tenant_id
             INNER JOIN clients c ON c.id = l.client_id AND c.tenant_id = d.tenant_id
             INNER JOIN loyer_units u ON u.id = l.unit_id AND u.tenant_id = d.tenant_id
             INNER JOIN loyer_properties p ON p.id = u.property_id AND p.tenant_id = d.tenant_id
             WHERE d.id = :id AND d.tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param list<array{lease_id: int, due_date: string, amount_minor: int}> $rows
     */
    public function insertBatch(int $tenantId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO loyer_rent_dues (
                tenant_id, lease_id, due_date, amount_minor, status, payment_id, paid_at, created_at, updated_at
             ) VALUES (
                :tenant_id, :lease_id, :due_date, :amount_minor, :status, NULL, NULL, :created_at, :updated_at
             )'
        );
        foreach ($rows as $row) {
            $statement->execute([
                'tenant_id' => $tenantId,
                'lease_id' => $row['lease_id'],
                'due_date' => $row['due_date'],
                'amount_minor' => $row['amount_minor'],
                'status' => RentDueStatus::PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function attachPayment(int $id, int $tenantId, int $paymentId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE loyer_rent_dues SET payment_id = :payment_id, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'payment_id' => $paymentId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function markPaid(int $id, int $tenantId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE loyer_rent_dues SET status = :status, paid_at = :paid_at, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'status' => RentDueStatus::PAID,
            'paid_at' => $now,
            'updated_at' => $now,
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function syncPaidFromPayment(int $dueId, int $tenantId, int $paymentId, string $paymentStatus): void
    {
        if ($paymentStatus !== PaymentStatus::SUCCESS) {
            return;
        }
        $statement = $this->pdo->prepare(
            'UPDATE loyer_rent_dues SET status = :status, paid_at = COALESCE(paid_at, :paid_at), updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id AND payment_id = :payment_id AND status = :pending'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'status' => RentDueStatus::PAID,
            'paid_at' => $now,
            'updated_at' => $now,
            'id' => $dueId,
            'tenant_id' => $tenantId,
            'payment_id' => $paymentId,
            'pending' => RentDueStatus::PENDING,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): LoyerRentDue
    {
        return new LoyerRentDue([
            'id' => (int) $row['id'],
            'lease_id' => (int) $row['lease_id'],
            'due_date' => (string) $row['due_date'],
            'amount_minor' => (int) $row['amount_minor'],
            'status' => (string) $row['status'],
            'payment_id' => $row['payment_id'],
            'client_name' => (string) ($row['client_name'] ?? ''),
            'unit_label' => (string) ($row['unit_label'] ?? ''),
            'property_name' => (string) ($row['property_name'] ?? ''),
        ]);
    }

    /**
     * @return list<LoyerRentDue>
     */
    private function mapList(\PDOStatement $statement): array
    {
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $this->map($row);
            }
        }

        return $items;
    }
}
