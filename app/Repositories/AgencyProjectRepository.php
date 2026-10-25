<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\AgencyInvoice;
use Sesam\Models\AgencyProject;

final class AgencyProjectRepository extends Repository
{
    private const PROJECT_SELECT = 'SELECT pr.id, pr.prospect_id, pr.quote_id, pr.title, pr.offer, pr.amount, pr.status, pr.start_month,
            pr.started_on, pr.delivered_on, pr.site_url, pr.created_at, pr.updated_at, p.full_name, p.business_name,
            (SELECT COALESCE(SUM(i.amount), 0) FROM agency_invoices i WHERE i.project_id = pr.id) AS invoiced_amount,
            (SELECT COALESCE(SUM(i.amount), 0) FROM agency_invoices i WHERE i.project_id = pr.id AND i.status = \'paid\') AS paid_amount
        FROM agency_projects pr
        INNER JOIN agency_prospects p ON p.id = pr.prospect_id';

    private const INVOICE_SELECT = 'SELECT i.id, i.project_id, i.reference, i.label, i.amount, i.status, i.due_on, i.paid_on, i.payment_method,
            i.created_at, pr.title AS project_title
        FROM agency_invoices i
        INNER JOIN agency_projects pr ON pr.id = i.project_id';

    /**
     * @return list<AgencyProject>
     */
    public function list(string $status): array
    {
        $params = [];
        $where = '';
        if ($status !== '') {
            $where = ' WHERE pr.status = :status';
            $params['status'] = $status;
        }
        $statement = $this->pdo->prepare(self::PROJECT_SELECT . $where . ' ORDER BY FIELD(pr.status, \'in_progress\', \'planned\', \'delivered\'), pr.updated_at DESC LIMIT 200');
        $statement->execute($params);

        return $this->projects($statement->fetchAll());
    }

    /**
     * @return list<AgencyProject>
     */
    public function forProspect(int $prospectId): array
    {
        $statement = $this->pdo->prepare(self::PROJECT_SELECT . ' WHERE pr.prospect_id = :prospect_id ORDER BY pr.id DESC');
        $statement->execute(['prospect_id' => $prospectId]);

        return $this->projects($statement->fetchAll());
    }

    /**
     * @return list<AgencyProject>
     */
    public function startedIn(string $month): array
    {
        $statement = $this->pdo->prepare(self::PROJECT_SELECT . ' WHERE pr.start_month = :month ORDER BY pr.started_on ASC, pr.id ASC');
        $statement->execute(['month' => $month]);

        return $this->projects($statement->fetchAll());
    }

    public function find(int $id): ?AgencyProject
    {
        $statement = $this->pdo->prepare(self::PROJECT_SELECT . ' WHERE pr.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? new AgencyProject($row) : null;
    }

    /**
     * @param array{prospect_id: int, quote_id: int, title: string, offer: string, amount: int} $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO agency_projects (prospect_id, quote_id, title, offer, amount, status, created_at, updated_at)
             VALUES (:prospect_id, :quote_id, :title, :offer, :amount, :status, :created_at, :updated_at)'
        );
        $statement->execute($data + ['status' => 'planned', 'created_at' => $now, 'updated_at' => $now]);

        return (int) $this->pdo->lastInsertId();
    }

    public function countStartedIn(string $month): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM agency_projects WHERE start_month = :month FOR UPDATE');
        $statement->execute(['month' => $month]);

        return (int) $statement->fetchColumn();
    }

    public function start(int $id, string $month, string $date): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE agency_projects SET status = 'in_progress', start_month = :month, started_on = :started_on, updated_at = :updated_at WHERE id = :id"
        );
        $statement->execute(['id' => $id, 'month' => $month, 'started_on' => $date, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function deliver(int $id, string $date, string $siteUrl): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE agency_projects SET status = 'delivered', delivered_on = :delivered_on, site_url = :site_url, updated_at = :updated_at WHERE id = :id"
        );
        $statement->execute(['id' => $id, 'delivered_on' => $date, 'site_url' => $siteUrl, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * @return array{in_progress: int, planned: int, delivered: int}
     */
    public function statusCounts(): array
    {
        $counts = ['in_progress' => 0, 'planned' => 0, 'delivered' => 0];
        $statement = $this->pdo->query('SELECT status, COUNT(*) AS n FROM agency_projects GROUP BY status');
        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            if (is_array($row) && array_key_exists((string) $row['status'], $counts)) {
                $counts[(string) $row['status']] = (int) $row['n'];
            }
        }

        return $counts;
    }

    /**
     * @return list<AgencyInvoice>
     */
    public function invoices(?int $projectId, string $status, string $today): array
    {
        $where = [];
        $params = [];
        if ($projectId !== null) {
            $where[] = 'i.project_id = :project_id';
            $params['project_id'] = $projectId;
        }
        if ($status === 'overdue') {
            $where[] = "i.status = 'issued' AND i.due_on < :today";
            $params['today'] = $today;
        } elseif ($status !== '') {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        $statement = $this->pdo->prepare(
            self::INVOICE_SELECT . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY i.status = \'paid\' ASC, i.due_on ASC, i.id DESC LIMIT 300'
        );
        $statement->execute($params);
        $list = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $list[] = new AgencyInvoice($row);
            }
        }

        return $list;
    }

    public function findInvoice(int $id): ?AgencyInvoice
    {
        $statement = $this->pdo->prepare(self::INVOICE_SELECT . ' WHERE i.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? new AgencyInvoice($row) : null;
    }

    public function invoicedTotal(int $projectId): int
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM agency_invoices WHERE project_id = :project_id FOR UPDATE');
        $statement->execute(['project_id' => $projectId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{project_id: int, label: string, amount: int, due_on: string} $data
     */
    public function insertInvoice(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO agency_invoices (project_id, reference, label, amount, status, due_on, created_at, updated_at)
             VALUES (:project_id, :reference, :label, :amount, :status, :due_on, :created_at, :updated_at)'
        );
        $statement->execute($data + [
            'reference' => 'tmp-' . bin2hex(random_bytes(6)),
            'status' => 'issued',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE agency_invoices SET reference = :reference WHERE id = :id')->execute([
            'id' => $id,
            'reference' => sprintf('FA-%s-%04d', gmdate('Y'), $id),
        ]);

        return $id;
    }

    public function markPaid(int $id, string $date, string $method): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE agency_invoices SET status = 'paid', paid_on = :paid_on, payment_method = :method, updated_at = :updated_at
             WHERE id = :id AND status = 'issued'"
        );
        $statement->execute(['id' => $id, 'paid_on' => $date, 'method' => $method, 'updated_at' => gmdate('Y-m-d H:i:s')]);

        return $statement->rowCount() === 1;
    }

    /**
     * @return array{outstanding: int, overdue: int, overdue_count: int, paid_month: int}
     */
    public function invoiceTotals(string $today, string $month): array
    {
        $statement = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'issued' THEN amount END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN status = 'issued' AND due_on < :today_a THEN amount END), 0) AS overdue,
                COALESCE(SUM(status = 'issued' AND due_on < :today_b), 0) AS overdue_count,
                COALESCE(SUM(CASE WHEN status = 'paid' AND DATE_FORMAT(paid_on, '%Y-%m') = :month THEN amount END), 0) AS paid_month
             FROM agency_invoices"
        );
        $statement->execute(['today_a' => $today, 'today_b' => $today, 'month' => $month]);
        $row = $statement->fetch();
        $row = is_array($row) ? $row : [];

        return [
            'outstanding' => (int) ($row['outstanding'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'overdue_count' => (int) ($row['overdue_count'] ?? 0),
            'paid_month' => (int) ($row['paid_month'] ?? 0),
        ];
    }

    /**
     * @param array<int, mixed> $rows
     * @return list<AgencyProject>
     */
    private function projects(array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $list[] = new AgencyProject($row);
            }
        }

        return $list;
    }
}
