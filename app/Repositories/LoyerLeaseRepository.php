<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Loyer\LeaseStatus;
use Sesam\Models\LoyerLease;

final class LoyerLeaseRepository extends Repository
{
    public function countActive(int $tenantId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM loyer_leases WHERE tenant_id = :tenant_id AND status = :status'
        );
        $statement->execute(['tenant_id' => $tenantId, 'status' => LeaseStatus::ACTIVE]);

        return (int) $statement->fetchColumn();
    }

    public function countForTenant(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM loyer_leases WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<LoyerLease>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT l.id, l.unit_id, l.client_id, l.rent_minor, l.due_day, l.starts_on, l.status,
                    c.full_name AS client_name, u.label AS unit_label, p.name AS property_name
             FROM loyer_leases l
             INNER JOIN clients c ON c.id = l.client_id AND c.tenant_id = l.tenant_id
             INNER JOIN loyer_units u ON u.id = l.unit_id AND u.tenant_id = l.tenant_id
             INNER JOIN loyer_properties p ON p.id = u.property_id AND p.tenant_id = l.tenant_id
             WHERE l.tenant_id = :tenant_id
             ORDER BY l.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('tenant_id', $tenantId, \PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?LoyerLease
    {
        $statement = $this->pdo->prepare(
            'SELECT l.id, l.unit_id, l.client_id, l.rent_minor, l.due_day, l.starts_on, l.status,
                    c.full_name AS client_name, u.label AS unit_label, p.name AS property_name
             FROM loyer_leases l
             INNER JOIN clients c ON c.id = l.client_id AND c.tenant_id = l.tenant_id
             INNER JOIN loyer_units u ON u.id = l.unit_id AND u.tenant_id = l.tenant_id
             INNER JOIN loyer_properties p ON p.id = u.property_id AND p.tenant_id = l.tenant_id
             WHERE l.id = :id AND l.tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param array{
     *     unit_id: int,
     *     client_id: int,
     *     rent_minor: int,
     *     due_day: int,
     *     starts_on: string,
     *     status: string
     * } $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO loyer_leases (
                tenant_id, unit_id, client_id, rent_minor, due_day, starts_on, ends_on, status, created_at, updated_at
             ) VALUES (
                :tenant_id, :unit_id, :client_id, :rent_minor, :due_day, :starts_on, NULL, :status, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'unit_id' => $data['unit_id'],
            'client_id' => $data['client_id'],
            'rent_minor' => $data['rent_minor'],
            'due_day' => $data['due_day'],
            'starts_on' => $data['starts_on'],
            'status' => $data['status'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): LoyerLease
    {
        return new LoyerLease([
            'id' => (int) $row['id'],
            'unit_id' => (int) $row['unit_id'],
            'client_id' => (int) $row['client_id'],
            'rent_minor' => (int) $row['rent_minor'],
            'due_day' => (int) $row['due_day'],
            'starts_on' => (string) $row['starts_on'],
            'status' => (string) $row['status'],
            'client_name' => (string) ($row['client_name'] ?? ''),
            'unit_label' => (string) ($row['unit_label'] ?? ''),
            'property_name' => (string) ($row['property_name'] ?? ''),
        ]);
    }

    /**
     * @return list<LoyerLease>
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
