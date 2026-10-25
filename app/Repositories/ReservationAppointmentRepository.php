<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Models\ReservationAppointment;

final class ReservationAppointmentRepository extends Repository
{
    private const SELECT = 'SELECT a.id, a.client_id, a.service_id, a.staff_id, a.starts_at, a.duration_minutes, a.price_minor, a.status,
                a.source, a.deposit_amount_minor, a.payment_id, a.is_paid, a.notes, a.created_at,
                c.full_name AS client_name, c.phone AS client_phone, s.name AS service_name, st.name AS staff_name
         FROM reservation_appointments a
         INNER JOIN clients c ON c.id = a.client_id AND c.tenant_id = a.tenant_id
         INNER JOIN reservation_services s ON s.id = a.service_id AND s.tenant_id = a.tenant_id
         LEFT JOIN reservation_staff st ON st.id = a.staff_id AND st.tenant_id = a.tenant_id';

    /**
     * @return list<ReservationAppointment>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(self::SELECT . ' WHERE a.tenant_id = :tenant_id ORDER BY a.starts_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->mapAll($statement->fetchAll());
    }

    /**
     * @param array{status?: string, from?: string, to?: string, q?: string, staff_id?: int} $filters
     * @return list<ReservationAppointment>
     */
    public function search(int $tenantId, array $filters, int $limit, int $offset, bool $ascending): array
    {
        [$where, $params] = $this->filter($tenantId, $filters);
        $statement = $this->pdo->prepare(
            self::SELECT . $where . ' ORDER BY a.starts_at ' . ($ascending ? 'ASC' : 'DESC') . ' LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute($params);

        return $this->mapAll($statement->fetchAll());
    }

    /**
     * @param array{status?: string, from?: string, to?: string, q?: string, staff_id?: int} $filters
     */
    public function countSearch(int $tenantId, array $filters): int
    {
        [$where, $params] = $this->filter($tenantId, $filters);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM reservation_appointments a INNER JOIN clients c ON c.id = a.client_id AND c.tenant_id = a.tenant_id' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * Rendez-vous d'une période, dans l'ordre chronologique (agenda, calcul des créneaux).
     *
     * @return list<ReservationAppointment>
     */
    public function listBetween(int $tenantId, string $from, string $to, bool $activeOnly = false): array
    {
        $sql = self::SELECT . ' WHERE a.tenant_id = :tenant_id AND a.starts_at < :to
                  AND DATE_ADD(a.starts_at, INTERVAL a.duration_minutes MINUTE) > :from';
        $params = ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to];
        if ($activeOnly) {
            $sql .= ' AND a.status IN (:s1, :s2)';
            $params['s1'] = AppointmentStatus::PENDING;
            $params['s2'] = AppointmentStatus::CONFIRMED;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY a.starts_at ASC');
        $statement->execute($params);

        return $this->mapAll($statement->fetchAll());
    }

    /**
     * @return list<ReservationAppointment>
     */
    public function listForClient(int $tenantId, int $clientId, int $limit): array
    {
        $statement = $this->pdo->prepare(self::SELECT . ' WHERE a.tenant_id = :tenant_id AND a.client_id = :client_id ORDER BY a.starts_at DESC LIMIT ' . $limit);
        $statement->execute(['tenant_id' => $tenantId, 'client_id' => $clientId]);

        return $this->mapAll($statement->fetchAll());
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(int $tenantId, string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS total FROM reservation_appointments
             WHERE tenant_id = :tenant_id AND starts_at >= :from AND starts_at < :to GROUP BY status'
        );
        $statement->execute(['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]);
        $counts = array_fill_keys(AppointmentStatus::all(), 0);
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $counts[(string) $row['status']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /**
     * Chiffre d'affaires des prestations réalisées (ou confirmées) sur une période.
     */
    public function revenueBetween(int $tenantId, string $from, string $to): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(price_minor), 0) FROM reservation_appointments
             WHERE tenant_id = :tenant_id AND starts_at >= :from AND starts_at < :to AND status IN (:s1, :s2)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'from' => $from,
            'to' => $to,
            's1' => AppointmentStatus::CONFIRMED,
            's2' => AppointmentStatus::COMPLETED,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countPending(int $tenantId, string $after): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = :tenant_id AND status = :status AND starts_at >= :after'
        );
        $statement->execute(['tenant_id' => $tenantId, 'status' => AppointmentStatus::PENDING, 'after' => $after]);

        return (int) $statement->fetchColumn();
    }

    public function countForTenant(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    public function countToday(int $tenantId, string $dayStart, string $dayEnd): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM reservation_appointments
             WHERE tenant_id = :tenant_id AND status IN (:s1, :s2) AND starts_at >= :day_start AND starts_at < :day_end'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            's1' => AppointmentStatus::PENDING,
            's2' => AppointmentStatus::CONFIRMED,
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countUpcoming(int $tenantId, string $after): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = :tenant_id AND status IN (:s1, :s2) AND starts_at >= :after'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            's1' => AppointmentStatus::PENDING,
            's2' => AppointmentStatus::CONFIRMED,
            'after' => $after,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countClientsToday(int $tenantId, string $dayStart, string $dayEnd): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT client_id) FROM reservation_appointments
             WHERE tenant_id = :tenant_id AND status IN (:s1, :s2) AND starts_at >= :day_start AND starts_at < :day_end'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            's1' => AppointmentStatus::PENDING,
            's2' => AppointmentStatus::CONFIRMED,
            'day_start' => $dayStart,
            'day_end' => $dayEnd,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function findById(int $id, int $tenantId): ?ReservationAppointment
    {
        $statement = $this->pdo->prepare(self::SELECT . ' WHERE a.id = :id AND a.tenant_id = :tenant_id LIMIT 1');
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByPaymentId(int $paymentId, int $tenantId): ?ReservationAppointment
    {
        $statement = $this->pdo->prepare(self::SELECT . ' WHERE a.payment_id = :payment_id AND a.tenant_id = :tenant_id LIMIT 1');
        $statement->execute(['payment_id' => $paymentId, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function hasOverlap(
        int $tenantId,
        ?int $staffId,
        string $startsAt,
        int $durationMinutes,
        ?int $excludeId = null,
    ): bool {
        $endsAt = gmdate('Y-m-d H:i:s', strtotime($startsAt) + ($durationMinutes * 60));
        $sql = 'SELECT COUNT(*) FROM reservation_appointments
                WHERE tenant_id = :tenant_id AND status IN (:s1, :s2)
                  AND starts_at < :ends_at
                  AND DATE_ADD(starts_at, INTERVAL duration_minutes MINUTE) > :starts_at';
        $params = [
            'tenant_id' => $tenantId,
            's1' => AppointmentStatus::PENDING,
            's2' => AppointmentStatus::CONFIRMED,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
        if ($staffId !== null) {
            $sql .= ' AND staff_id = :staff_id';
            $params['staff_id'] = $staffId;
        }
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @param array{client_id: int, service_id: int, staff_id: ?int, starts_at: string, duration_minutes: int, status: string, deposit_amount_minor: int, notes: ?string, price_minor?: int, source?: string} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO reservation_appointments (
                tenant_id, client_id, service_id, staff_id, starts_at, duration_minutes, price_minor, status, source,
                deposit_amount_minor, payment_id, notes, cancelled_at, created_at, updated_at
             ) VALUES (
                :tenant_id, :client_id, :service_id, :staff_id, :starts_at, :duration_minutes, :price_minor, :status, :source,
                :deposit_amount_minor, NULL, :notes, NULL, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'client_id' => $data['client_id'],
            'service_id' => $data['service_id'],
            'staff_id' => $data['staff_id'],
            'starts_at' => $data['starts_at'],
            'duration_minutes' => $data['duration_minutes'],
            'price_minor' => $data['price_minor'] ?? 0,
            'status' => $data['status'],
            'source' => $data['source'] ?? 'manual',
            'deposit_amount_minor' => $data['deposit_amount_minor'],
            'notes' => $data['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateSchedule(int $id, int $tenantId, string $startsAt, ?int $staffId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reservation_appointments SET starts_at = :starts_at, staff_id = :staff_id, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'starts_at' => $startsAt,
            'staff_id' => $staffId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function updateStatus(int $id, int $tenantId, string $status): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE reservation_appointments
             SET status = :status,
                 cancelled_at = COALESCE(:cancelled_at, cancelled_at),
                 completed_at = COALESCE(:completed_at, completed_at),
                 updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'status' => $status,
            'cancelled_at' => $status === AppointmentStatus::CANCELLED ? $now : null,
            'completed_at' => $status === AppointmentStatus::COMPLETED ? $now : null,
            'updated_at' => $now,
        ]);
    }

    public function attachPayment(int $id, int $tenantId, int $paymentId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reservation_appointments SET payment_id = :payment_id, updated_at = :updated_at WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId, 'payment_id' => $paymentId, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function setPaid(int $id, int $tenantId, bool $paid): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reservation_appointments SET is_paid = :paid, updated_at = :updated_at WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId, 'paid' => $paid ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * @param array{status?: string, from?: string, to?: string, q?: string, staff_id?: int} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filter(int $tenantId, array $filters): array
    {
        $where = ' WHERE a.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if (($filters['status'] ?? '') !== '' && AppointmentStatus::isValid((string) $filters['status'])) {
            $where .= ' AND a.status = :status';
            $params['status'] = $filters['status'];
        }
        if (($filters['from'] ?? '') !== '') {
            $where .= ' AND a.starts_at >= :from';
            $params['from'] = $filters['from'];
        }
        if (($filters['to'] ?? '') !== '') {
            $where .= ' AND a.starts_at < :to';
            $params['to'] = $filters['to'];
        }
        if ((int) ($filters['staff_id'] ?? 0) > 0) {
            $where .= ' AND a.staff_id = :staff_id';
            $params['staff_id'] = (int) $filters['staff_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $where .= ' AND (c.full_name LIKE :q1 OR c.phone LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = '%' . $filters['q'] . '%';
        }

        return [$where, $params];
    }

    /**
     * @param array<int, mixed> $rows
     * @return list<ReservationAppointment>
     */
    private function mapAll(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrate($row);
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ReservationAppointment
    {
        return new ReservationAppointment($row + ['client_name' => '', 'service_name' => '', 'staff_name' => '']);
    }
}
