<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\CommandeProduct;

final class CommandeProductRepository extends Repository
{
    /**
     * @return list<CommandeProduct>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, price_minor, deposit_minor, is_active
             FROM commande_products
             WHERE tenant_id = :tenant_id
             ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->hydrateList($statement);
    }

    /**
     * @return list<CommandeProduct>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, price_minor, deposit_minor, is_active
             FROM commande_products
             WHERE tenant_id = :tenant_id AND is_active = 1
             ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->hydrateList($statement);
    }

    public function findById(int $id, int $tenantId): ?CommandeProduct
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, price_minor, deposit_minor, is_active
             FROM commande_products
             WHERE id = :id AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array{name: string, price_minor: int, deposit_minor: int, is_active: int} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO commande_products (tenant_id, name, price_minor, deposit_minor, is_active, created_at, updated_at)
             VALUES (:tenant_id, :name, :price_minor, :deposit_minor, :is_active, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'price_minor' => $data['price_minor'],
            'deposit_minor' => $data['deposit_minor'],
            'is_active' => $data['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CommandeProduct
    {
        return new CommandeProduct([
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'price_minor' => (int) $row['price_minor'],
            'deposit_minor' => (int) $row['deposit_minor'],
            'is_active' => (int) $row['is_active'],
        ]);
    }

    /**
     * @return list<CommandeProduct>
     */
    private function hydrateList(\PDOStatement $statement): array
    {
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrate($row);
            }
        }

        return $items;
    }
}
