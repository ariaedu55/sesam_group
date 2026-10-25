<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\ReservationTeamMember;

final class ReservationTeamRepository extends Repository
{
    private const COLUMNS = 'id, name, role_label, phone, is_active';

    /**
     * @return list<ReservationTeamMember>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM reservation_staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->withServices($tenantId, $statement->fetchAll());
    }

    /**
     * @return list<ReservationTeamMember>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM reservation_staff WHERE tenant_id = :tenant_id ORDER BY is_active DESC, name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->withServices($tenantId, $statement->fetchAll());
    }

    public function findById(int $id, int $tenantId): ?ReservationTeamMember
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM reservation_staff WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? ($this->withServices($tenantId, [$row])[0] ?? null) : null;
    }

    public function insert(int $tenantId, string $name, ?string $role = null, ?string $phone = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO reservation_staff (tenant_id, name, role_label, phone, is_active, created_at, updated_at)
             VALUES (:tenant_id, :name, :role_label, :phone, 1, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'role_label' => $role,
            'phone' => $phone,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, string $name, ?string $role, ?string $phone, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reservation_staff SET name = :name, role_label = :role_label, phone = :phone, is_active = :active, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'name' => $name,
            'role_label' => $role,
            'phone' => $phone,
            'active' => $active ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param list<int> $serviceIds
     */
    public function replaceServices(int $tenantId, int $staffId, array $serviceIds): void
    {
        $this->pdo->prepare('DELETE FROM reservation_staff_services WHERE tenant_id = :tenant_id AND staff_id = :staff_id')
            ->execute(['tenant_id' => $tenantId, 'staff_id' => $staffId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO reservation_staff_services (tenant_id, staff_id, service_id) VALUES (:tenant_id, :staff_id, :service_id)'
        );
        foreach (array_unique($serviceIds) as $serviceId) {
            $insert->execute(['tenant_id' => $tenantId, 'staff_id' => $staffId, 'service_id' => $serviceId]);
        }
    }

    /**
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     */
    public function availability(int $tenantId, int $staffId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT weekday, starts_at, ends_at FROM reservation_staff_availability
             WHERE tenant_id = :tenant_id AND staff_id = :staff_id ORDER BY weekday ASC, starts_at ASC'
        );
        $statement->execute(['tenant_id' => $tenantId, 'staff_id' => $staffId]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = [
                    'weekday' => (int) $row['weekday'],
                    'starts_at' => (string) $row['starts_at'],
                    'ends_at' => (string) $row['ends_at'],
                ];
            }
        }

        return $rows;
    }

    public function replaceAvailability(int $tenantId, int $staffId, array $slots): void
    {
        $delete = $this->pdo->prepare(
            'DELETE FROM reservation_staff_availability WHERE tenant_id = :tenant_id AND staff_id = :staff_id'
        );
        $delete->execute(['tenant_id' => $tenantId, 'staff_id' => $staffId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO reservation_staff_availability (tenant_id, staff_id, weekday, starts_at, ends_at)
             VALUES (:tenant_id, :staff_id, :weekday, :starts_at, :ends_at)'
        );
        foreach ($slots as $slot) {
            $insert->execute([
                'tenant_id' => $tenantId,
                'staff_id' => $staffId,
                'weekday' => $slot['weekday'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
            ]);
        }
    }

    /**
     * @param array<int, mixed> $rows
     * @return list<ReservationTeamMember>
     */
    private function withServices(int $tenantId, array $rows): array
    {
        $statement = $this->pdo->prepare('SELECT staff_id, service_id FROM reservation_staff_services WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);
        $links = [];
        foreach ($statement->fetchAll() as $link) {
            if (is_array($link)) {
                $links[(int) $link['staff_id']][] = (int) $link['service_id'];
            }
        }
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $row['service_ids'] = $links[(int) $row['id']] ?? [];
                $items[] = new ReservationTeamMember($row);
            }
        }

        return $items;
    }
}
