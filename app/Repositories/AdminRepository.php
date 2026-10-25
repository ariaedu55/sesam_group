<?php

declare(strict_types=1);

namespace Sesam\Repositories;

/**
 * Chiffres de la console SESAM : abonnements, encaissements, agence, demandes du site, usage des commerces.
 */
final class AdminRepository extends Repository
{
    /**
     * Revenu mensuel récurrent : somme des mensualités des abonnements actifs et à jour.
     *
     * @return array{total: int, count: int, by_sector: array<string, array{amount: int, count: int}>}
     */
    public function mrr(string $today): array
    {
        $statement = $this->pdo->prepare(
            "SELECT t.sector, COUNT(*) AS n, COALESCE(SUM(s.monthly_amount_minor), 0) AS amount
             FROM tenant_subscriptions s INNER JOIN tenants t ON t.id = s.tenant_id
             WHERE s.status = 'active' AND (s.period_end IS NULL OR s.period_end >= :today)
             GROUP BY t.sector"
        );
        $statement->execute(['today' => $today]);
        $total = 0;
        $count = 0;
        $bySector = [];
        foreach ($statement->fetchAll() as $row) {
            $bySector[(string) $row['sector']] = ['amount' => (int) $row['amount'], 'count' => (int) $row['n']];
            $total += (int) $row['amount'];
            $count += (int) $row['n'];
        }

        return ['total' => $total, 'count' => $count, 'by_sector' => $bySector];
    }

    /**
     * Encaissements par mois (« YYYY-MM ») : abonnements SaaS et factures agence.
     *
     * @return array<string, array{saas: int, agency: int}>
     */
    public function revenueByMonth(string $from, string $to): array
    {
        $months = [];
        $saas = $this->pdo->prepare(
            "SELECT DATE_FORMAT(paid_on, '%Y-%m') AS m, SUM(amount) AS total FROM tenant_subscription_payments
             WHERE paid_on >= :from AND paid_on < :to GROUP BY m"
        );
        $saas->execute(['from' => $from, 'to' => $to]);
        foreach ($saas->fetchAll() as $row) {
            $months[(string) $row['m']]['saas'] = (int) $row['total'];
        }
        $agency = $this->pdo->prepare(
            "SELECT DATE_FORMAT(paid_on, '%Y-%m') AS m, SUM(amount) AS total FROM agency_invoices
             WHERE status = 'paid' AND paid_on >= :from AND paid_on < :to GROUP BY m"
        );
        $agency->execute(['from' => $from, 'to' => $to]);
        foreach ($agency->fetchAll() as $row) {
            $months[(string) $row['m']]['agency'] = (int) $row['total'];
        }
        foreach ($months as $key => $values) {
            $months[$key] = ['saas' => (int) ($values['saas'] ?? 0), 'agency' => (int) ($values['agency'] ?? 0)];
        }

        return $months;
    }

    /**
     * Tous les encaissements d'une période, les plus récents d'abord.
     *
     * @return list<array{source: string, date: string, amount: int, label: string, method: string, reference: string, link: string}>
     */
    public function payments(string $from, string $to, int $limit = 500): array
    {
        $statement = $this->pdo->prepare(
            "SELECT 'saas' AS source, p.paid_on AS date, p.amount, CONCAT('Abonnement — ', COALESCE(ts.display_name, t.name), ' (', p.months, ' mois)') AS label,
                    p.method, p.reference, CONCAT('/commerces/', t.id) AS link
             FROM tenant_subscription_payments p
             INNER JOIN tenants t ON t.id = p.tenant_id
             LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
             WHERE p.paid_on >= :from1 AND p.paid_on < :to1
             UNION ALL
             SELECT 'agency', i.paid_on, i.amount, CONCAT('Agence — ', i.label, ' (', i.reference, ')'), COALESCE(i.payment_method, ''), i.reference,
                    CONCAT('/agence/projets/', i.project_id)
             FROM agency_invoices i
             WHERE i.status = 'paid' AND i.paid_on >= :from2 AND i.paid_on < :to2
             ORDER BY date DESC
             LIMIT " . max(1, $limit)
        );
        $statement->execute(['from1' => $from, 'to1' => $to, 'from2' => $from, 'to2' => $to]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[] = [
                'source' => (string) $row['source'],
                'date' => (string) $row['date'],
                'amount' => (int) $row['amount'],
                'label' => (string) $row['label'],
                'method' => (string) $row['method'],
                'reference' => (string) $row['reference'],
                'link' => (string) $row['link'],
            ];
        }

        return $rows;
    }

    /**
     * Abonnements échus ou qui arrivent à échéance avant une date.
     *
     * @return list<array{id: int, name: string, sector: string, period_end: string, amount: int, overdue: bool, email: string}>
     */
    public function dueSubscriptions(string $today, string $until): array
    {
        $statement = $this->pdo->prepare(
            "SELECT t.id, COALESCE(ts.display_name, t.name) AS name, t.sector, s.period_end, COALESCE(s.monthly_amount_minor, 0) AS amount,
                    (SELECT u.email FROM users u WHERE u.tenant_id = t.id AND u.is_active = 1 ORDER BY u.id ASC LIMIT 1) AS email
             FROM tenant_subscriptions s
             INNER JOIN tenants t ON t.id = s.tenant_id
             LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
             WHERE s.status = 'active' AND s.period_end IS NOT NULL AND s.period_end <= :until
             ORDER BY s.period_end ASC"
        );
        $statement->execute(['until' => $until]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'sector' => (string) $row['sector'],
                'period_end' => (string) $row['period_end'],
                'amount' => (int) $row['amount'],
                'overdue' => (string) $row['period_end'] < $today,
                'email' => (string) ($row['email'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array{count: int, amount: int, overdue_count: int, overdue_amount: int}
     */
    public function unpaidInvoices(string $today): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total,
                    COALESCE(SUM(due_on < :today), 0) AS late_n, COALESCE(SUM(CASE WHEN due_on < :today2 THEN amount ELSE 0 END), 0) AS late_total
             FROM agency_invoices WHERE status = 'issued'"
        );
        $statement->execute(['today' => $today, 'today2' => $today]);
        $row = $statement->fetch() ?: [];

        return [
            'count' => (int) ($row['n'] ?? 0),
            'amount' => (int) ($row['total'] ?? 0),
            'overdue_count' => (int) ($row['late_n'] ?? 0),
            'overdue_amount' => (int) ($row['late_total'] ?? 0),
        ];
    }

    /**
     * @return array{open_prospects: int, quotes_pending: int, quotes_pending_amount: int, projects_running: int, requests_new: int}
     */
    public function agencyPulse(string $since): array
    {
        $value = function (string $sql, array $params = []): int {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return (int) $statement->fetchColumn();
        };

        return [
            'open_prospects' => $value("SELECT COUNT(*) FROM agency_prospects WHERE stage NOT IN ('gagne', 'projet-en-cours', 'livre', 'perdu')"),
            'quotes_pending' => $value("SELECT COUNT(*) FROM agency_quotes WHERE status = 'sent'"),
            'quotes_pending_amount' => $value("SELECT COALESCE(SUM(amount), 0) FROM agency_quotes WHERE status = 'sent'"),
            'projects_running' => $value("SELECT COUNT(*) FROM agency_projects WHERE status IN ('planned', 'in_progress')"),
            'requests_new' => $value(
                'SELECT COUNT(*) FROM contact_requests r WHERE r.created_at >= :since
                 AND NOT EXISTS (SELECT 1 FROM agency_prospects p WHERE p.contact_request_id = r.id)',
                ['since' => $since]
            ),
        ];
    }

    /**
     * @return list<array{id: int, full_name: string, activity: string, phone: string, created_at: string}>
     */
    public function newRequests(int $limit): array
    {
        $statement = $this->pdo->query(
            'SELECT r.id, r.full_name, r.activity, r.phone, r.created_at FROM contact_requests r
             WHERE NOT EXISTS (SELECT 1 FROM agency_prospects p WHERE p.contact_request_id = r.id)
             ORDER BY r.created_at DESC LIMIT ' . max(1, $limit)
        );

        return $statement === false ? [] : array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'full_name' => (string) $r['full_name'],
            'activity' => (string) $r['activity'],
            'phone' => (string) $r['phone'],
            'created_at' => (string) $r['created_at'],
        ], $statement->fetchAll());
    }

    /**
     * @return list<array{id: int, name: string, sector: string, status: string, created_at: string}>
     */
    public function recentTenants(int $limit): array
    {
        $statement = $this->pdo->query(
            'SELECT t.id, COALESCE(ts.display_name, t.name) AS name, t.sector, t.status, t.created_at
             FROM tenants t LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
             ORDER BY t.created_at DESC, t.id DESC LIMIT ' . max(1, $limit)
        );

        return $statement === false ? [] : array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'sector' => (string) $r['sector'],
            'status' => (string) $r['status'],
            'created_at' => (string) $r['created_at'],
        ], $statement->fetchAll());
    }

    public function countTenantsSince(string $since): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM tenants WHERE created_at >= :since');
        $statement->execute(['since' => $since]);

        return (int) $statement->fetchColumn();
    }

    // ---------------------------------------------------------------- Encaissements d'un commerce

    /**
     * @param array{amount: int, months: int, paid_on: string, method: string, reference: string, period_end: ?string, note: string, actor_user_id: int} $data
     */
    public function insertPayment(int $tenantId, array $data): void
    {
        $this->pdo->prepare(
            'INSERT INTO tenant_subscription_payments (tenant_id, amount, months, paid_on, method, reference, period_end, note, actor_user_id, created_at)
             VALUES (:tenant_id, :amount, :months, :paid_on, :method, :reference, :period_end, :note, :actor_user_id, :created_at)'
        )->execute($data + ['tenant_id' => $tenantId, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * @return list<array{amount: int, months: int, paid_on: string, method: string, reference: string, period_end: string, note: string, actor: string}>
     */
    public function tenantPayments(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT p.amount, p.months, p.paid_on, p.method, p.reference, COALESCE(p.period_end, \'\') AS period_end, p.note,
                    COALESCE(u.full_name, \'\') AS actor
             FROM tenant_subscription_payments p LEFT JOIN users u ON u.id = p.actor_user_id
             WHERE p.tenant_id = :tenant_id ORDER BY p.paid_on DESC, p.id DESC LIMIT 36'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return array_map(static fn (array $r): array => [
            'amount' => (int) $r['amount'],
            'months' => (int) $r['months'],
            'paid_on' => (string) $r['paid_on'],
            'method' => (string) $r['method'],
            'reference' => (string) $r['reference'],
            'period_end' => (string) $r['period_end'],
            'note' => (string) $r['note'],
            'actor' => (string) $r['actor'],
        ], $statement->fetchAll());
    }

    public function tenantPaidTotal(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM tenant_subscription_payments WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    public function insertNote(int $tenantId, string $body, int $actorId): void
    {
        $this->pdo->prepare(
            'INSERT INTO tenant_notes (tenant_id, body, actor_user_id, created_at) VALUES (:tenant_id, :body, :actor, :created_at)'
        )->execute(['tenant_id' => $tenantId, 'body' => $body, 'actor' => $actorId, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * @return list<array{body: string, actor: string, created_at: string}>
     */
    public function tenantNotes(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT n.body, COALESCE(u.full_name, \'\') AS actor, n.created_at FROM tenant_notes n LEFT JOIN users u ON u.id = n.actor_user_id
             WHERE n.tenant_id = :tenant_id ORDER BY n.created_at DESC, n.id DESC LIMIT 30'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return array_map(static fn (array $r): array => [
            'body' => (string) $r['body'],
            'actor' => (string) $r['actor'],
            'created_at' => (string) $r['created_at'],
        ], $statement->fetchAll());
    }

    /**
     * Usage d'un commerce : de quoi savoir s'il se sert vraiment de l'outil.
     *
     * @return array{clients: int, activity_30d: int, activity_label: string, last_login: string}
     */
    public function usage(int $tenantId, string $sector, string $since): array
    {
        $value = function (string $sql, array $params): int {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return (int) $statement->fetchColumn();
        };
        [$sql, $label] = match ($sector) {
            'boutique' => ['SELECT COUNT(*) FROM boutique_orders WHERE tenant_id = :t AND created_at >= :s', 'commandes'],
            'loyer' => ['SELECT COUNT(*) FROM loyer_rent_dues WHERE tenant_id = :t AND due_date >= :s', 'échéances'],
            default => ['SELECT COUNT(*) FROM reservation_appointments WHERE tenant_id = :t AND created_at >= :s', 'rendez-vous'],
        };
        $login = $this->pdo->prepare('SELECT MAX(last_login_at) FROM users WHERE tenant_id = :t');
        $login->execute(['t' => $tenantId]);

        return [
            'clients' => $value('SELECT COUNT(*) FROM clients WHERE tenant_id = :t', ['t' => $tenantId]),
            'activity_30d' => $value($sql, ['t' => $tenantId, 's' => $since]),
            'activity_label' => $label,
            'last_login' => (string) ($login->fetchColumn() ?: ''),
        ];
    }

    // ---------------------------------------------------------------- Tarifs

    /**
     * @return array<string, array{monthly_amount: int, setup_fee: int}>
     */
    public function prices(): array
    {
        $statement = $this->pdo->query('SELECT sector, monthly_amount, setup_fee FROM saas_prices');
        $prices = [];
        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            $prices[(string) $row['sector']] = ['monthly_amount' => (int) $row['monthly_amount'], 'setup_fee' => (int) $row['setup_fee']];
        }

        return $prices;
    }

    public function savePrice(string $sector, int $monthly, int $setup): void
    {
        $this->pdo->prepare(
            'INSERT INTO saas_prices (sector, monthly_amount, setup_fee, updated_at) VALUES (:sector, :monthly, :setup, :updated_at)
             ON DUPLICATE KEY UPDATE monthly_amount = VALUES(monthly_amount), setup_fee = VALUES(setup_fee), updated_at = VALUES(updated_at)'
        )->execute(['sector' => $sector, 'monthly' => $monthly, 'setup' => $setup, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * Indicateurs de la page Statistiques (SaaS, essais, agence) sur une période.
     *
     * @return array<string, mixed>
     */
    public function statistics(string $from, string $to, string $today): array
    {
        $value = function (string $sql, array $params): mixed {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return $statement->fetchColumn();
        };
        $rows = function (string $sql, array $params): array {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            return array_values(array_filter($statement->fetchAll(), 'is_array'));
        };

        $signups = [];
        foreach ($rows("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, signup_source AS src, COUNT(*) AS n FROM tenants WHERE created_at >= ? AND created_at < ? GROUP BY m, src", [$from, $to]) as $row) {
            $signups[(string) $row['m']][(string) $row['src']] = (int) $row['n'];
        }
        // Essais ouverts sur la période, puis convertis (au moins un paiement d'abonnement ensuite).
        $trials = (int) $value("SELECT COUNT(DISTINCT tenant_id) FROM tenant_subscription_events WHERE action = 'trial' AND created_at >= ? AND created_at < ?", [$from, $to]);
        $converted = (int) $value(
            "SELECT COUNT(DISTINCT e.tenant_id) FROM tenant_subscription_events e
             WHERE e.action = 'trial' AND e.created_at >= ? AND e.created_at < ?
               AND EXISTS (SELECT 1 FROM tenant_subscription_payments p WHERE p.tenant_id = e.tenant_id AND p.created_at >= e.created_at)",
            [$from, $to]
        );

        return [
            'signups' => $signups,
            'trials' => $trials,
            'converted' => $converted,
            'trial_running' => (int) $value("SELECT COUNT(*) FROM tenant_subscriptions WHERE status = 'trial' AND period_end >= ?", [$today]),
            'trial_ending' => $rows(
                "SELECT t.id, COALESCE(ts.display_name, t.name) AS name, t.sector, s.period_end, t.phone
                 FROM tenant_subscriptions s JOIN tenants t ON t.id = s.tenant_id LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
                 WHERE s.status = 'trial' AND s.period_end BETWEEN ? AND ? ORDER BY s.period_end LIMIT 20",
                [gmdate('Y-m-d', (int) strtotime($today . ' -10 days')), gmdate('Y-m-d', (int) strtotime($today . ' +7 days'))]
            ),
            'lost' => (int) $value("SELECT COUNT(*) FROM tenant_subscriptions WHERE status = 'suspended' OR (status IN ('active', 'trial') AND period_end < ?)", [gmdate('Y-m-d', (int) strtotime($today . ' -30 days'))]),
            'by_sector' => $rows(
                'SELECT t.sector, COALESCE(SUM(p.amount), 0) AS amount, COUNT(DISTINCT p.tenant_id) AS payers
                 FROM tenant_subscription_payments p JOIN tenants t ON t.id = p.tenant_id
                 WHERE p.paid_on >= ? AND p.paid_on < ? GROUP BY t.sector',
                [$from, $to]
            ),
            'online_share' => $rows(
                'SELECT channel, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS amount FROM tenant_subscription_payments WHERE paid_on >= ? AND paid_on < ? GROUP BY channel',
                [$from, $to]
            ),
            'top' => $rows(
                'SELECT t.id, COALESCE(ts.display_name, t.name) AS name, t.sector, SUM(p.amount) AS amount
                 FROM tenant_subscription_payments p JOIN tenants t ON t.id = p.tenant_id LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
                 WHERE p.paid_on >= ? AND p.paid_on < ? GROUP BY t.id, name, t.sector ORDER BY amount DESC LIMIT 5',
                [$from, $to]
            ),
            'quotes' => $rows('SELECT status, COUNT(*) AS n, COALESCE(SUM(amount), 0) AS amount FROM agency_quotes WHERE created_at >= ? AND created_at < ? GROUP BY status', [$from, $to]),
            'requests' => (int) $value('SELECT COUNT(*) FROM contact_requests WHERE created_at >= ? AND created_at < ?', [$from, $to]),
            'projects_delivered' => (int) $value("SELECT COUNT(*) FROM agency_projects WHERE status = 'delivered' AND delivered_on >= ? AND delivered_on < ?", [$from, $to]),
        ];
    }
}
