<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\GardeBatch;

final class GardeBatchRepository extends Repository
{
    /**
     * @return list<GardeBatch>
     */
    public function listForProduct(int $productId, int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT b.id, b.product_id, b.lot_code, b.quantity, b.expires_on, p.name AS product_name
             FROM garde_batches b
             INNER JOIN garde_products p ON p.id = b.product_id
             WHERE b.product_id = :product_id AND b.tenant_id = :tenant_id
             ORDER BY b.expires_on ASC, b.lot_code ASC'
        );
        $statement->execute(['product_id' => $productId, 'tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    /**
     * @return list<GardeBatch>
     */
    public function listWithStock(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT b.id, b.product_id, b.lot_code, b.quantity, b.expires_on, p.name AS product_name
             FROM garde_batches b
             INNER JOIN garde_products p ON p.id = b.product_id
             WHERE b.tenant_id = :tenant_id AND b.quantity > 0
             ORDER BY b.expires_on ASC, p.name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?GardeBatch
    {
        $statement = $this->pdo->prepare(
            'SELECT b.id, b.product_id, b.lot_code, b.quantity, b.expires_on, p.name AS product_name
             FROM garde_batches b
             INNER JOIN garde_products p ON p.id = b.product_id
             WHERE b.id = :id AND b.tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function findByLot(int $tenantId, int $productId, string $lotCode): ?GardeBatch
    {
        $statement = $this->pdo->prepare(
            'SELECT b.id, b.product_id, b.lot_code, b.quantity, b.expires_on, p.name AS product_name
             FROM garde_batches b
             INNER JOIN garde_products p ON p.id = b.product_id
             WHERE b.tenant_id = :tenant_id AND b.product_id = :product_id AND b.lot_code = :lot_code
             LIMIT 1'
        );
        $statement->execute(['tenant_id' => $tenantId, 'product_id' => $productId, 'lot_code' => $lotCode]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function countAlerts(int $tenantId, string $today, string $soonLimit): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM garde_batches
             WHERE tenant_id = :tenant_id AND quantity > 0 AND expires_on <= :soon_limit'
        );
        $statement->execute(['tenant_id' => $tenantId, 'soon_limit' => $soonLimit]);

        return (int) $statement->fetchColumn();
    }

    public function insert(int $tenantId, int $productId, string $lotCode, int $quantity, string $expiresOn): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO garde_batches (tenant_id, product_id, lot_code, quantity, expires_on, created_at, updated_at)
             VALUES (:tenant_id, :product_id, :lot_code, :quantity, :expires_on, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'lot_code' => $lotCode,
            'quantity' => $quantity,
            'expires_on' => $expiresOn,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function setQuantity(int $id, int $tenantId, int $quantity): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE garde_batches SET quantity = :quantity, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'quantity' => $quantity,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): GardeBatch
    {
        return new GardeBatch([
            'id' => (int) $row['id'],
            'product_id' => (int) $row['product_id'],
            'product_name' => (string) $row['product_name'],
            'lot_code' => (string) $row['lot_code'],
            'quantity' => (int) $row['quantity'],
            'expires_on' => (string) $row['expires_on'],
        ]);
    }

    /**
     * @return list<GardeBatch>
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
