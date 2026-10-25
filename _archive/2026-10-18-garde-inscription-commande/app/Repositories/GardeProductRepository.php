<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\GardeProduct;

final class GardeProductRepository extends Repository
{
    /**
     * @return list<GardeProduct>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.unit, p.min_quantity, p.image_path, p.is_active,
                    COALESCE(SUM(b.quantity), 0) AS stock_quantity
             FROM garde_products p
             LEFT JOIN garde_batches b ON b.product_id = p.id AND b.tenant_id = p.tenant_id
             WHERE p.tenant_id = :tenant_id
             GROUP BY p.id, p.name, p.unit, p.min_quantity, p.image_path, p.is_active
             ORDER BY p.name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    /**
     * @return list<GardeProduct>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.unit, p.min_quantity, p.image_path, p.is_active,
                    COALESCE(SUM(b.quantity), 0) AS stock_quantity
             FROM garde_products p
             LEFT JOIN garde_batches b ON b.product_id = p.id AND b.tenant_id = p.tenant_id
             WHERE p.tenant_id = :tenant_id AND p.is_active = 1
             GROUP BY p.id, p.name, p.unit, p.min_quantity, p.image_path, p.is_active
             ORDER BY p.name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?GardeProduct
    {
        $statement = $this->pdo->prepare(
            'SELECT p.id, p.name, p.unit, p.min_quantity, p.image_path, p.is_active,
                    COALESCE(SUM(b.quantity), 0) AS stock_quantity
             FROM garde_products p
             LEFT JOIN garde_batches b ON b.product_id = p.id AND b.tenant_id = p.tenant_id
             WHERE p.id = :id AND p.tenant_id = :tenant_id
             GROUP BY p.id, p.name, p.unit, p.min_quantity, p.image_path, p.is_active
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function insert(int $tenantId, string $name, string $unit, int $minQuantity, ?string $imagePath): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO garde_products (tenant_id, name, unit, min_quantity, image_path, is_active, created_at, updated_at)
             VALUES (:tenant_id, :name, :unit, :min_quantity, :image_path, 1, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'unit' => $unit,
            'min_quantity' => $minQuantity,
            'image_path' => $imagePath,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateImagePath(int $id, int $tenantId, ?string $imagePath): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE garde_products
             SET image_path = :image_path, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'image_path' => $imagePath,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): GardeProduct
    {
        return new GardeProduct([
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'unit' => (string) $row['unit'],
            'min_quantity' => (int) $row['min_quantity'],
            'image_path' => (string) ($row['image_path'] ?? ''),
            'stock_quantity' => (int) $row['stock_quantity'],
            'is_active' => (int) $row['is_active'],
        ]);
    }

    /**
     * @return list<GardeProduct>
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
