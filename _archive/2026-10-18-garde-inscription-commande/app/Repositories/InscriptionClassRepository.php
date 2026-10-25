<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\InscriptionClass;

final class InscriptionClassRepository extends Repository
{
    /**
     * @return list<InscriptionClass>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, is_active FROM inscription_classes WHERE tenant_id = :tenant_id ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    /**
     * @return list<InscriptionClass>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, is_active FROM inscription_classes
             WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?InscriptionClass
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, is_active FROM inscription_classes WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function insert(int $tenantId, string $name): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO inscription_classes (tenant_id, name, is_active, created_at, updated_at)
             VALUES (:tenant_id, :name, 1, :created_at, :updated_at)'
        );
        $statement->execute(['tenant_id' => $tenantId, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): InscriptionClass
    {
        return new InscriptionClass([
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'is_active' => (int) $row['is_active'],
        ]);
    }

    /**
     * @return list<InscriptionClass>
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
