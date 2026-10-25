<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\LoyerUnit;

final class LoyerUnitRepository extends Repository
{
    /**
     * @return list<LoyerUnit>
     */
    public function listForProperty(int $propertyId, int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, property_id, label, default_rent_minor, is_active
             FROM loyer_units
             WHERE property_id = :property_id AND tenant_id = :tenant_id
             ORDER BY label ASC'
        );
        $statement->execute(['property_id' => $propertyId, 'tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = new LoyerUnit([
                'id' => (int) $row['id'],
                'property_id' => (int) $row['property_id'],
                'label' => (string) $row['label'],
                'default_rent_minor' => (int) $row['default_rent_minor'],
                'is_active' => (int) $row['is_active'],
            ]);
        }

        return $items;
    }

    /**
     * @return list<LoyerUnit>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.property_id, u.label, u.default_rent_minor, u.is_active
             FROM loyer_units u
             INNER JOIN loyer_properties p ON p.id = u.property_id AND p.tenant_id = u.tenant_id
             WHERE u.tenant_id = :tenant_id AND u.is_active = 1
             ORDER BY p.name ASC, u.label ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = new LoyerUnit([
                'id' => (int) $row['id'],
                'property_id' => (int) $row['property_id'],
                'label' => (string) $row['label'],
                'default_rent_minor' => (int) $row['default_rent_minor'],
                'is_active' => (int) $row['is_active'],
            ]);
        }

        return $items;
    }

    public function findById(int $id, int $tenantId): ?LoyerUnit
    {
        $statement = $this->pdo->prepare(
            'SELECT id, property_id, label, default_rent_minor, is_active
             FROM loyer_units WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        return new LoyerUnit([
            'id' => (int) $row['id'],
            'property_id' => (int) $row['property_id'],
            'label' => (string) $row['label'],
            'default_rent_minor' => (int) $row['default_rent_minor'],
            'is_active' => (int) $row['is_active'],
        ]);
    }

    /**
     * @param array{label: string, default_rent_minor: int, is_active: int} $data
     */
    public function insert(int $tenantId, int $propertyId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO loyer_units (tenant_id, property_id, label, default_rent_minor, is_active, created_at, updated_at)
             VALUES (:tenant_id, :property_id, :label, :default_rent_minor, :is_active, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'label' => $data['label'],
            'default_rent_minor' => $data['default_rent_minor'],
            'is_active' => $data['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
