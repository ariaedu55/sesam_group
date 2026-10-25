<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\InscriptionFeeType;

final class InscriptionFeeTypeRepository extends Repository
{
    /**
     * @return list<InscriptionFeeType>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, total_minor, installment_count, is_active
             FROM inscription_fee_types WHERE tenant_id = :tenant_id ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    /**
     * @return list<InscriptionFeeType>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, total_minor, installment_count, is_active
             FROM inscription_fee_types WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?InscriptionFeeType
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, total_minor, installment_count, is_active
             FROM inscription_fee_types WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array{name: string, total_minor: int, installment_count: int} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO inscription_fee_types (
                tenant_id, name, total_minor, installment_count, is_active, created_at, updated_at
             ) VALUES (
                :tenant_id, :name, :total_minor, :installment_count, 1, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'total_minor' => $data['total_minor'],
            'installment_count' => $data['installment_count'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): InscriptionFeeType
    {
        return new InscriptionFeeType([
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'total_minor' => (int) $row['total_minor'],
            'installment_count' => (int) $row['installment_count'],
            'is_active' => (int) $row['is_active'],
        ]);
    }

    /**
     * @return list<InscriptionFeeType>
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
