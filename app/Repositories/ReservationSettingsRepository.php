<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\ReservationSettings;

/**
 * Réglages du salon : présentation publique, règles de réservation, horaires d'ouverture, fermetures et absences.
 */
final class ReservationSettingsRepository extends Repository
{
    public function find(int $tenantId): ReservationSettings
    {
        $statement = $this->pdo->prepare('SELECT * FROM reservation_settings WHERE tenant_id = :tenant_id LIMIT 1');
        $statement->execute(['tenant_id' => $tenantId]);
        $row = $statement->fetch();
        $data = is_array($row) ? $row : [];
        $data['hours'] = $this->hours($tenantId);

        return new ReservationSettings($data);
    }

    /**
     * @param array<string, int|string|null> $data
     */
    public function save(int $tenantId, array $data): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $columns = array_keys($data);
        $statement = $this->pdo->prepare(
            'INSERT INTO reservation_settings (tenant_id, ' . implode(', ', $columns) . ', created_at, updated_at)
             VALUES (:tenant_id, :' . implode(', :', $columns) . ', :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(static fn (string $c): string => $c . ' = VALUES(' . $c . ')', $columns))
            . ', updated_at = VALUES(updated_at)'
        );
        $statement->execute($data + ['tenant_id' => $tenantId, 'created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @return array<int, list<array{0: string, 1: string}>>
     */
    public function hours(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT weekday, opens_at, closes_at FROM reservation_hours
             WHERE tenant_id = :tenant_id ORDER BY weekday ASC, opens_at ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $hours = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $hours[(int) $row['weekday']][] = [(string) $row['opens_at'], (string) $row['closes_at']];
            }
        }

        return $hours;
    }

    /**
     * @param list<array{weekday: int, opens_at: string, closes_at: string}> $rows
     */
    public function replaceHours(int $tenantId, array $rows): void
    {
        $this->pdo->prepare('DELETE FROM reservation_hours WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $tenantId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO reservation_hours (tenant_id, weekday, opens_at, closes_at) VALUES (:tenant_id, :weekday, :opens_at, :closes_at)'
        );
        foreach ($rows as $row) {
            $insert->execute($row + ['tenant_id' => $tenantId]);
        }
    }

    /**
     * @return list<array{id: int, staff_id: ?int, staff_name: string, starts_on: string, ends_on: string, reason: string}>
     */
    public function closures(int $tenantId, ?string $from = null, ?int $staffId = null, bool $salonOnly = false): array
    {
        $sql = 'SELECT c.id, c.staff_id, COALESCE(s.name, \'\') AS staff_name, c.starts_on, c.ends_on, COALESCE(c.reason, \'\') AS reason
                FROM reservation_closures c
                LEFT JOIN reservation_staff s ON s.id = c.staff_id AND s.tenant_id = c.tenant_id
                WHERE c.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($from !== null) {
            $sql .= ' AND c.ends_on >= :from';
            $params['from'] = $from;
        }
        if ($salonOnly) {
            $sql .= ' AND c.staff_id IS NULL';
        } elseif ($staffId !== null) {
            $sql .= ' AND c.staff_id = :staff_id';
            $params['staff_id'] = $staffId;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY c.starts_on ASC');
        $statement->execute($params);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = [
                    'id' => (int) $row['id'],
                    'staff_id' => $row['staff_id'] === null ? null : (int) $row['staff_id'],
                    'staff_name' => (string) $row['staff_name'],
                    'starts_on' => (string) $row['starts_on'],
                    'ends_on' => (string) $row['ends_on'],
                    'reason' => (string) $row['reason'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Fermetures et absences qui touchent une journée : clé 0 pour le salon, sinon l'identifiant de la personne.
     *
     * @return array<int, true>
     */
    public function closedOn(int $tenantId, string $date): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(staff_id, 0) AS who FROM reservation_closures
             WHERE tenant_id = :tenant_id AND starts_on <= :day AND ends_on >= :day2'
        );
        $statement->execute(['tenant_id' => $tenantId, 'day' => $date, 'day2' => $date]);
        $closed = [];
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $who) {
            $closed[(int) $who] = true;
        }

        return $closed;
    }

    public function insertClosure(int $tenantId, ?int $staffId, string $startsOn, string $endsOn, ?string $reason): void
    {
        $this->pdo->prepare(
            'INSERT INTO reservation_closures (tenant_id, staff_id, starts_on, ends_on, reason, created_at)
             VALUES (:tenant_id, :staff_id, :starts_on, :ends_on, :reason, :created_at)'
        )->execute([
            'tenant_id' => $tenantId,
            'staff_id' => $staffId,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'reason' => $reason,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function deleteClosure(int $tenantId, int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM reservation_closures WHERE id = :id AND tenant_id = :tenant_id');
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return $statement->rowCount() > 0;
    }
}
