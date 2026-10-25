<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\GardeShift;

final class GardeShiftRepository extends Repository
{
    /**
     * @return list<GardeShift>
     */
    public function listBetween(int $tenantId, string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id, s.staff_id, s.shift_date, s.note, st.name AS staff_name
             FROM garde_shifts s
             INNER JOIN garde_staff st ON st.id = s.staff_id
             WHERE s.tenant_id = :tenant_id AND s.shift_date BETWEEN :from_date AND :to_date
             ORDER BY s.shift_date ASC, st.name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId, 'from_date' => $from, 'to_date' => $to]);

        return $this->mapList($statement);
    }

    public function findById(int $id, int $tenantId): ?GardeShift
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id, s.staff_id, s.shift_date, s.note, st.name AS staff_name
             FROM garde_shifts s
             INNER JOIN garde_staff st ON st.id = s.staff_id
             WHERE s.id = :id AND s.tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function existsOnDay(int $tenantId, int $staffId, string $date): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT id FROM garde_shifts
             WHERE tenant_id = :tenant_id AND staff_id = :staff_id AND shift_date = :shift_date LIMIT 1'
        );
        $statement->execute(['tenant_id' => $tenantId, 'staff_id' => $staffId, 'shift_date' => $date]);

        return $statement->fetch() !== false;
    }

    public function countOnDate(int $tenantId, string $date): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM garde_shifts WHERE tenant_id = :tenant_id AND shift_date = :shift_date'
        );
        $statement->execute(['tenant_id' => $tenantId, 'shift_date' => $date]);

        return (int) $statement->fetchColumn();
    }

    public function countDistinctStaffOnDate(int $tenantId, string $date): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT staff_id) FROM garde_shifts
             WHERE tenant_id = :tenant_id AND shift_date = :shift_date'
        );
        $statement->execute(['tenant_id' => $tenantId, 'shift_date' => $date]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<string>
     */
    public function datesInRange(int $tenantId, string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT shift_date FROM garde_shifts
             WHERE tenant_id = :tenant_id AND shift_date BETWEEN :from_date AND :to_date'
        );
        $statement->execute(['tenant_id' => $tenantId, 'from_date' => $from, 'to_date' => $to]);
        $dates = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $dates[] = (string) $row['shift_date'];
            }
        }

        return $dates;
    }

    public function insert(int $tenantId, int $staffId, string $date, string $note): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO garde_shifts (tenant_id, staff_id, shift_date, note, created_at, updated_at)
             VALUES (:tenant_id, :staff_id, :shift_date, :note, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'staff_id' => $staffId,
            'shift_date' => $date,
            'note' => $note,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $tenantId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM garde_shifts WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): GardeShift
    {
        return new GardeShift([
            'id' => (int) $row['id'],
            'staff_id' => (int) $row['staff_id'],
            'staff_name' => (string) $row['staff_name'],
            'shift_date' => (string) $row['shift_date'],
            'note' => (string) $row['note'],
        ]);
    }

    /**
     * @return list<GardeShift>
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
