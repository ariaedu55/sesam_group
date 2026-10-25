<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\GardeStaff;

final class GardeStaffRepository extends Repository
{
    /**
     * @return list<GardeStaff>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, role_label, is_active FROM garde_staff WHERE tenant_id = :tenant_id ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    /**
     * @return list<GardeStaff>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, role_label, is_active FROM garde_staff
             WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?GardeStaff
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, role_label, is_active FROM garde_staff
             WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function insert(int $tenantId, string $name, string $roleLabel): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO garde_staff (tenant_id, name, role_label, is_active, created_at, updated_at)
             VALUES (:tenant_id, :name, :role_label, 1, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'role_label' => $roleLabel,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): GardeStaff
    {
        return new GardeStaff([
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'role_label' => (string) $row['role_label'],
            'is_active' => (int) $row['is_active'],
        ]);
    }

    /**
     * @return list<GardeStaff>
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
