<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\LoyerProperty;

final class LoyerPropertyRepository extends Repository
{
    /**
     * @return list<LoyerProperty>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, address, city, kind, image_path, updated_at FROM loyer_properties WHERE tenant_id = :tenant_id ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?LoyerProperty
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, address, city, kind, image_path, updated_at FROM loyer_properties WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array{name: string, address: ?string} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO loyer_properties (tenant_id, name, address, created_at, updated_at)
             VALUES (:tenant_id, :name, :address, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'address' => $data['address'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): LoyerProperty
    {
        return new LoyerProperty([
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'address' => (string) ($row['address'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'kind' => (string) ($row['kind'] ?? 'immeuble'),
            'image_path' => isset($row['image_path']) && is_string($row['image_path']) && $row['image_path'] !== '' ? $row['image_path'] : null,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ]);
    }

    /**
     * @return list<LoyerProperty>
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
