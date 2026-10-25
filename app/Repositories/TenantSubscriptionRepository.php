<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\Tenant;
use Sesam\Models\TenantSubscription;

final class TenantSubscriptionRepository extends Repository
{
    public function find(int $tenantId): ?TenantSubscription
    {
        $statement = $this->pdo->prepare(
            'SELECT tenant_id, status, monthly_amount_minor, period_start, period_end, created_at, updated_at
             FROM tenant_subscriptions
             WHERE tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? new TenantSubscription($row) : null;
    }

    public function save(int $tenantId, string $status, ?int $monthlyAmount, ?string $periodStart, ?string $periodEnd): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO tenant_subscriptions (tenant_id, status, monthly_amount_minor, period_start, period_end, created_at, updated_at)
             VALUES (:tenant_id, :status, :amount, :period_start, :period_end, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE status = VALUES(status), monthly_amount_minor = VALUES(monthly_amount_minor),
                 period_start = VALUES(period_start), period_end = VALUES(period_end), updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'status' => $status,
            'amount' => $monthlyAmount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addEvent(int $tenantId, string $action, ?string $fromStatus, string $toStatus, ?string $periodEnd, string $note, ?int $actorId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tenant_subscription_events (tenant_id, action, from_status, to_status, period_end, note, actor_user_id, created_at)
             VALUES (:tenant_id, :action, :from_status, :to_status, :period_end, :note, :actor_user_id, :created_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'period_end' => $periodEnd,
            'note' => $note,
            'actor_user_id' => $actorId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(int $tenantId, int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.action, e.from_status, e.to_status, e.period_end, e.note, e.created_at, u.full_name AS actor_name
             FROM tenant_subscription_events e
             LEFT JOIN users u ON u.id = e.actor_user_id
             WHERE e.tenant_id = :tenant_id
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT ' . max(1, min(100, $limit))
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return array_values(array_filter($statement->fetchAll(), 'is_array'));
    }

    /**
     * @param array{q: string, sector: string, status: string, subscription: string} $filters
     * @return list<Tenant>
     */
    public function console(array $filters, string $today): array
    {
        $where = [];
        $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(t.name LIKE :q_name OR s.display_name LIKE :q_display OR t.slug LIKE :q_slug)';
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $params['q_name'] = $like;
            $params['q_display'] = $like;
            $params['q_slug'] = $like;
        }
        if ($filters['sector'] !== '') {
            $where[] = 't.sector = :sector';
            $params['sector'] = $filters['sector'];
        }
        if ($filters['status'] !== '') {
            $where[] = 't.status = :status';
            $params['status'] = $filters['status'];
        }
        switch ($filters['subscription']) {
            case 'none':
                $where[] = 'sub.tenant_id IS NULL';
                break;
            case 'suspended':
                $where[] = "sub.status = 'suspended'";
                break;
            case 'overdue':
                $where[] = "sub.status = 'active' AND sub.period_end < :today";
                $params['today'] = $today;
                break;
            case 'active':
                $where[] = "sub.status = 'active' AND (sub.period_end IS NULL OR sub.period_end >= :today)";
                $params['today'] = $today;
                break;
            case 'trial':
                $where[] = "sub.status = 'trial' AND sub.period_end >= :today";
                $params['today'] = $today;
                break;
            case 'trial_over':
                $where[] = "sub.status = 'trial' AND sub.period_end < :today";
                $params['today'] = $today;
                break;
        }
        if (($filters['source'] ?? '') === 'site') {
            $where[] = "t.signup_source = 'site'";
        }
        $sql = 'SELECT t.id, t.slug, t.name, t.sector, t.status, t.created_at, t.updated_at, s.display_name, t.signup_source, t.phone,
                       sub.status AS subscription_status, sub.period_end AS subscription_period_end,
                       (SELECT u.email FROM users u
                        INNER JOIN user_roles ur ON ur.user_id = u.id
                        INNER JOIN roles r ON r.id = ur.role_id AND r.slug = \'tenant-owner\'
                        WHERE u.tenant_id = t.id
                        ORDER BY u.id ASC
                        LIMIT 1) AS owner_email
                FROM tenants t
                LEFT JOIN tenant_settings s ON s.tenant_id = t.id
                LEFT JOIN tenant_subscriptions sub ON sub.tenant_id = t.id'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY t.name ASC, t.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $tenants = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $tenants[] = new Tenant($row);
            }
        }

        return $tenants;
    }

    /**
     * @return array{total: int, active: int, suspended: int, sectors: array<string, int>, subscriptions: array{none: int, active: int, overdue: int, suspended: int, soon: int}}
     */
    public function overview(string $today, string $soonLimit): array
    {
        $totals = ['total' => 0, 'active' => 0, 'suspended' => 0, 'sectors' => []];
        $statement = $this->pdo->query('SELECT sector, status, COUNT(*) AS n FROM tenants GROUP BY sector, status');
        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $count = (int) $row['n'];
            $totals['total'] += $count;
            if ($row['status'] === 'active') {
                $totals['active'] += $count;
            } elseif ($row['status'] === 'suspended') {
                $totals['suspended'] += $count;
            }
            $sector = (string) $row['sector'];
            $totals['sectors'][$sector] = ($totals['sectors'][$sector] ?? 0) + $count;
        }

        $subscriptions = $this->pdo->prepare(
            "SELECT
                SUM(sub.tenant_id IS NULL) AS none_count,
                SUM(sub.status = 'active' AND (sub.period_end IS NULL OR sub.period_end >= :today_a)) AS active_count,
                SUM(sub.status = 'active' AND sub.period_end < :today_b) AS overdue_count,
                SUM(sub.status = 'suspended') AS suspended_count,
                SUM(sub.status = 'active' AND sub.period_end >= :today_c AND sub.period_end <= :soon) AS soon_count,
                SUM(sub.status = 'trial' AND sub.period_end >= :today_d) AS trial_count,
                SUM(sub.status = 'trial' AND sub.period_end < :today_e) AS trial_over_count,
                SUM(t.signup_source = 'site') AS self_signup_count
             FROM tenants t
             LEFT JOIN tenant_subscriptions sub ON sub.tenant_id = t.id"
        );
        $subscriptions->execute(['today_a' => $today, 'today_b' => $today, 'today_c' => $today, 'today_d' => $today, 'today_e' => $today, 'soon' => $soonLimit]);
        $row = $subscriptions->fetch();
        $row = is_array($row) ? $row : [];

        return $totals + [
            'subscriptions' => [
                'none' => (int) ($row['none_count'] ?? 0),
                'active' => (int) ($row['active_count'] ?? 0),
                'overdue' => (int) ($row['overdue_count'] ?? 0),
                'suspended' => (int) ($row['suspended_count'] ?? 0),
                'soon' => (int) ($row['soon_count'] ?? 0),
                'trial' => (int) ($row['trial_count'] ?? 0),
                'trial_over' => (int) ($row['trial_over_count'] ?? 0),
                'self_signup' => (int) ($row['self_signup_count'] ?? 0),
            ],
        ];
    }

    /**
     * @return list<array{id: int, full_name: string, email: string, is_active: bool}>
     */
    public function owners(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT u.id, u.full_name, u.email, u.is_active
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id AND r.slug = 'tenant-owner'
             WHERE u.tenant_id = :tenant_id
             ORDER BY u.id ASC"
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $owners = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $owners[] = [
                    'id' => (int) $row['id'],
                    'full_name' => (string) $row['full_name'],
                    'email' => (string) $row['email'],
                    'is_active' => (int) $row['is_active'] === 1,
                ];
            }
        }

        return $owners;
    }

    public function countUsers(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }
}
