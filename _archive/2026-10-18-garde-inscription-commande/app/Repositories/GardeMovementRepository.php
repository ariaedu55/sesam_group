<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\GardeMovement;

final class GardeMovementRepository extends Repository
{
    /**
     * @return list<GardeMovement>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.id, m.product_id, m.type, m.quantity, m.reason, m.created_at,
                    p.name AS product_name, b.lot_code
             FROM garde_movements m
             INNER JOIN garde_products p ON p.id = m.product_id
             INNER JOIN garde_batches b ON b.id = m.batch_id
             WHERE m.tenant_id = :tenant_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    public function countForTenant(int $tenantId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM garde_movements WHERE tenant_id = :tenant_id'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    public function countSince(int $tenantId, string $since): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM garde_movements
             WHERE tenant_id = :tenant_id AND created_at >= :since_at'
        );
        $statement->execute(['tenant_id' => $tenantId, 'since_at' => $since]);

        return (int) $statement->fetchColumn();
    }

    public function insert(
        int $tenantId,
        int $productId,
        int $batchId,
        string $type,
        int $quantity,
        string $reason,
        int $createdBy,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO garde_movements
                (tenant_id, product_id, batch_id, type, quantity, reason, created_by, created_at)
             VALUES
                (:tenant_id, :product_id, :batch_id, :type, :quantity, :reason, :created_by, :created_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'batch_id' => $batchId,
            'type' => $type,
            'quantity' => $quantity,
            'reason' => $reason,
            'created_by' => $createdBy,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): GardeMovement
    {
        return new GardeMovement([
            'id' => (int) $row['id'],
            'product_id' => (int) $row['product_id'],
            'product_name' => (string) $row['product_name'],
            'lot_code' => (string) $row['lot_code'],
            'type' => (string) $row['type'],
            'quantity' => (int) $row['quantity'],
            'reason' => (string) $row['reason'],
            'created_at' => (string) $row['created_at'],
        ]);
    }

    /**
     * @return list<GardeMovement>
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
