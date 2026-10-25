<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Payment\PaymentStatus;
use Sesam\Models\Payment;

final class PaymentRepository extends Repository
{
    public function findById(int $id, int $tenantId): ?Payment
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.tenant_id, p.client_id, p.reference, p.amount_minor, p.currency, p.status,
                    p.gateway, p.mode, p.description, p.external_id, p.paid_at, p.created_at, p.updated_at,
                    c.full_name AS client_name
             FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
             WHERE p.id = :id AND p.tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByReference(string $reference): ?Payment
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.tenant_id, p.client_id, p.reference, p.amount_minor, p.currency, p.status,
                    p.gateway, p.mode, p.description, p.external_id, p.paid_at, p.created_at, p.updated_at,
                    c.full_name AS client_name
             FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
             WHERE p.reference = :reference
             LIMIT 1'
        );
        $statement->execute(['reference' => $reference]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByExternalId(string $externalId): ?Payment
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.tenant_id, p.client_id, p.reference, p.amount_minor, p.currency, p.status,
                    p.gateway, p.mode, p.description, p.external_id, p.paid_at, p.created_at, p.updated_at,
                    c.full_name AS client_name
             FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
             WHERE p.external_id = :external_id
             LIMIT 1'
        );
        $statement->execute(['external_id' => $externalId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Payment>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.tenant_id, p.client_id, p.reference, p.amount_minor, p.currency, p.status,
                    p.gateway, p.mode, p.description, p.external_id, p.paid_at, p.created_at, p.updated_at,
                    c.full_name AS client_name
             FROM payments p
             LEFT JOIN clients c ON c.id = p.client_id AND c.tenant_id = p.tenant_id
             WHERE p.tenant_id = :tenant_id
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrate($row);
            }
        }

        return $items;
    }

    public function countForTenant(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM payments WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    public function countByStatus(int $tenantId, string $status): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM payments WHERE tenant_id = :tenant_id AND status = :status'
        );
        $statement->execute(['tenant_id' => $tenantId, 'status' => $status]);

        return (int) $statement->fetchColumn();
    }

    public function countPaidSince(int $tenantId, string $sinceUtc): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM payments
             WHERE tenant_id = :tenant_id AND status = :status AND paid_at IS NOT NULL AND paid_at >= :since'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'status' => PaymentStatus::SUCCESS,
            'since' => $sinceUtc,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{
     *     tenant_id: int,
     *     client_id: ?int,
     *     reference: string,
     *     amount_minor: int,
     *     currency: string,
     *     status: string,
     *     gateway: string,
     *     mode: string,
     *     description: string,
     *     external_id: ?string
     * } $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO payments (
                tenant_id, client_id, reference, amount_minor, currency, status, gateway, mode,
                description, external_id, paid_at, created_at, updated_at
             ) VALUES (
                :tenant_id, :client_id, :reference, :amount_minor, :currency, :status, :gateway, :mode,
                :description, :external_id, NULL, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'tenant_id' => $data['tenant_id'],
            'client_id' => $data['client_id'],
            'reference' => $data['reference'],
            'amount_minor' => $data['amount_minor'],
            'currency' => $data['currency'],
            'status' => $data['status'],
            'gateway' => $data['gateway'],
            'mode' => $data['mode'],
            'description' => $data['description'],
            'external_id' => $data['external_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(
        int $id,
        int $tenantId,
        string $status,
        ?string $externalId,
        ?string $paidAt,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE payments
             SET status = :status, external_id = COALESCE(:external_id, external_id),
                 paid_at = COALESCE(:paid_at, paid_at), updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'status' => $status,
            'external_id' => $externalId,
            'paid_at' => $paidAt,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Payment
    {
        return new Payment([
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'client_id' => $row['client_id'],
            'reference' => (string) $row['reference'],
            'amount_minor' => (int) $row['amount_minor'],
            'currency' => (string) $row['currency'],
            'status' => (string) $row['status'],
            'gateway' => (string) $row['gateway'],
            'mode' => (string) $row['mode'],
            'description' => (string) $row['description'],
            'external_id' => $row['external_id'],
            'paid_at' => $row['paid_at'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'client_name' => (string) ($row['client_name'] ?? ''),
        ]);
    }
}
