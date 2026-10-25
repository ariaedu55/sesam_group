<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Boutique\OrderStatus;
use Sesam\Core\Payment\PaymentStatus;
use Sesam\Core\Reservation\AppointmentStatus;

final class StatisticsRepository extends Repository
{
    /** @var array<string, string> */
    private const DUE_TABLES = [
        'loyer' => 'loyer_rent_dues',
    ];

    /**
     * @return array{amount: int, count: int}
     */
    public function revenue(int $tenantId, string $from, string $to): array
    {
        $row = $this->row(
            'SELECT COALESCE(SUM(amount_minor), 0) AS amount, COUNT(*) AS total
             FROM payments
             WHERE tenant_id = :tenant_id AND status = :status AND paid_at >= :from AND paid_at < :to',
            ['tenant_id' => $tenantId, 'status' => PaymentStatus::SUCCESS, 'from' => $from, 'to' => $to]
        );

        return ['amount' => (int) $row['amount'], 'count' => (int) $row['total']];
    }

    /**
     * @return array<string, array{amount: int, count: int}>
     */
    public function monthlyRevenue(int $tenantId, string $from, string $to): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DATE_FORMAT(paid_at, \'%Y-%m\') AS month, SUM(amount_minor) AS amount, COUNT(*) AS total
             FROM payments
             WHERE tenant_id = :tenant_id AND status = :status AND paid_at >= :from AND paid_at < :to
             GROUP BY month'
        );
        $statement->execute(['tenant_id' => $tenantId, 'status' => PaymentStatus::SUCCESS, 'from' => $from, 'to' => $to]);
        $months = [];
        foreach ($statement->fetchAll() as $row) {
            $months[(string) $row['month']] = ['amount' => (int) $row['amount'], 'count' => (int) $row['total']];
        }

        return $months;
    }

    /**
     * @return array<string, int>
     */
    public function paymentStatusCounts(int $tenantId, string $from, string $to): array
    {
        return $this->pairs(
            'SELECT status AS label, COUNT(*) AS total FROM payments
             WHERE tenant_id = :tenant_id AND created_at >= :from AND created_at < :to
             GROUP BY status',
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]
        );
    }

    /**
     * @return array{amount: int, count: int}
     */
    public function pendingPayments(int $tenantId): array
    {
        $row = $this->row(
            'SELECT COALESCE(SUM(amount_minor), 0) AS amount, COUNT(*) AS total
             FROM payments WHERE tenant_id = :tenant_id AND status = :status',
            ['tenant_id' => $tenantId, 'status' => PaymentStatus::PENDING]
        );

        return ['amount' => (int) $row['amount'], 'count' => (int) $row['total']];
    }

    /**
     * @return array{paying: int, returning: int}
     */
    public function payingClients(int $tenantId, string $from, string $to): array
    {
        $row = $this->row(
            'SELECT COUNT(*) AS paying, COALESCE(SUM(payments_count >= 2), 0) AS returning
             FROM (
                 SELECT client_id, COUNT(*) AS payments_count
                 FROM payments
                 WHERE tenant_id = :tenant_id AND status = :status AND client_id IS NOT NULL
                   AND paid_at >= :from AND paid_at < :to
                 GROUP BY client_id
             ) AS paid',
            ['tenant_id' => $tenantId, 'status' => PaymentStatus::SUCCESS, 'from' => $from, 'to' => $to]
        );

        return ['paying' => (int) $row['paying'], 'returning' => (int) $row['returning']];
    }

    /**
     * @return array{new: int, active: int}
     */
    public function clientCounts(int $tenantId, string $from, string $to): array
    {
        $row = $this->row(
            'SELECT COALESCE(SUM(created_at >= :from AND created_at < :to), 0) AS new_clients,
                    COALESCE(SUM(status = :status), 0) AS active_clients
             FROM clients WHERE tenant_id = :tenant_id',
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to, 'status' => 'active']
        );

        return ['new' => (int) $row['new_clients'], 'active' => (int) $row['active_clients']];
    }

    /**
     * @return array<string, int>
     */
    public function activityByKind(int $tenantId, string $from, string $to): array
    {
        return $this->pairs(
            'SELECT kind AS label, COUNT(*) AS total FROM client_activities
             WHERE tenant_id = :tenant_id AND created_at >= :from AND created_at < :to
             GROUP BY kind',
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]
        );
    }

    /**
     * @return array<string, int>
     */
    public function appointmentStatusCounts(int $tenantId, string $from, string $to): array
    {
        return $this->pairs(
            'SELECT status AS label, COUNT(*) AS total FROM reservation_appointments
             WHERE tenant_id = :tenant_id AND starts_at >= :from AND starts_at < :to
             GROUP BY status',
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]
        );
    }

    /**
     * @return list<array{label: string, count: int, amount: int}>
     */
    public function topServices(int $tenantId, string $from, string $to, int $limit): array
    {
        return $this->ranking(
            'SELECT s.name AS label, COUNT(*) AS total, COALESCE(SUM(s.price_minor), 0) AS amount
             FROM reservation_appointments a
             INNER JOIN reservation_services s ON s.id = a.service_id AND s.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tenant_id AND a.starts_at >= :from AND a.starts_at < :to AND a.status <> :cancelled
             GROUP BY s.id, s.name
             ORDER BY total DESC, amount DESC, s.name ASC
             LIMIT ' . max(1, $limit),
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to, 'cancelled' => AppointmentStatus::CANCELLED]
        );
    }

    /**
     * @return array<string, int>
     */
    public function orderStatusCounts(int $tenantId, string $from, string $to): array
    {
        return $this->pairs(
            'SELECT status AS label, COUNT(*) AS total FROM boutique_orders
             WHERE tenant_id = :tenant_id AND created_at >= :from AND created_at < :to
             GROUP BY status',
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to]
        );
    }

    public function orderValue(int $tenantId, string $from, string $to): int
    {
        $row = $this->row(
            'SELECT COALESCE(SUM(total_minor), 0) AS amount FROM boutique_orders
             WHERE tenant_id = :tenant_id AND created_at >= :from AND created_at < :to AND status IN (:confirmed, :delivered)',
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to, 'confirmed' => OrderStatus::CONFIRMED, 'delivered' => OrderStatus::DELIVERED]
        );

        return (int) $row['amount'];
    }

    /**
     * @return list<array{label: string, count: int, amount: int}>
     */
    public function topProducts(int $tenantId, string $from, string $to, int $limit): array
    {
        return $this->ranking(
            'SELECT i.product_name AS label, SUM(i.quantity) AS total, SUM(i.line_total_minor) AS amount
             FROM boutique_order_items i
             INNER JOIN boutique_orders o ON o.id = i.order_id AND o.tenant_id = i.tenant_id
             WHERE i.tenant_id = :tenant_id AND o.created_at >= :from AND o.created_at < :to AND o.status <> :cancelled
             GROUP BY i.product_name
             ORDER BY total DESC, amount DESC, i.product_name ASC
             LIMIT ' . max(1, $limit),
            ['tenant_id' => $tenantId, 'from' => $from, 'to' => $to, 'cancelled' => OrderStatus::CANCELLED]
        );
    }

    /**
     * @return array{expected: int, collected: int, count: int, paid: int}
     */
    public function dueCollection(string $module, int $tenantId, string $fromDate, string $toDate): array
    {
        $row = $this->row(
            'SELECT COALESCE(SUM(amount_minor), 0) AS expected,
                    COALESCE(SUM(CASE WHEN status = :paid THEN amount_minor ELSE 0 END), 0) AS collected,
                    COUNT(*) AS total,
                    COALESCE(SUM(status = :paid_count), 0) AS paid_total
             FROM ' . $this->dueTable($module) . '
             WHERE tenant_id = :tenant_id AND due_date >= :from AND due_date < :to AND status <> :cancelled',
            [
                'tenant_id' => $tenantId,
                'from' => $fromDate,
                'to' => $toDate,
                'paid' => 'paid',
                'paid_count' => 'paid',
                'cancelled' => 'cancelled',
            ]
        );

        return [
            'expected' => (int) $row['expected'],
            'collected' => (int) $row['collected'],
            'count' => (int) $row['total'],
            'paid' => (int) $row['paid_total'],
        ];
    }

    /**
     * @return array{amount: int, count: int}
     */
    public function overdue(string $module, int $tenantId, string $today): array
    {
        $row = $this->row(
            'SELECT COALESCE(SUM(amount_minor), 0) AS amount, COUNT(*) AS total
             FROM ' . $this->dueTable($module) . '
             WHERE tenant_id = :tenant_id AND status = :pending AND due_date < :today',
            ['tenant_id' => $tenantId, 'pending' => 'pending', 'today' => $today]
        );

        return ['amount' => (int) $row['amount'], 'count' => (int) $row['total']];
    }

    private function dueTable(string $module): string
    {
        $table = self::DUE_TABLES[$module] ?? null;
        if ($table === null) {
            throw new \InvalidArgumentException('Module d\'échéances inconnu.');
        }

        return $table;
    }

    /**
     * @param array<string, int|string> $params
     * @return array<string, mixed>
     */
    private function row(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();

        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string, int|string> $params
     * @return array<string, int>
     */
    private function pairs(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $pairs = [];
        foreach ($statement->fetchAll() as $row) {
            $pairs[(string) $row['label']] = (int) $row['total'];
        }

        return $pairs;
    }

    /**
     * @param array<string, int|string> $params
     * @return list<array{label: string, count: int, amount: int}>
     */
    private function ranking(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[] = ['label' => (string) $row['label'], 'count' => (int) $row['total'], 'amount' => (int) $row['amount']];
        }

        return $rows;
    }
}
