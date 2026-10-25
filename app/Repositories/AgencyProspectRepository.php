<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\AgencyProspect;
use Sesam\Services\Agency\AgencyCatalog;

final class AgencyProspectRepository extends Repository
{
    private const COLUMNS = 'id, full_name, business_name, email, phone, activity, source, contact_request_id, stage, next_action_on, created_at, updated_at';

    /**
     * @return list<AgencyProspect>
     */
    public function search(string $query, string $stage, bool $clientsOnly): array
    {
        $where = [];
        $params = [];
        if ($query !== '') {
            $where[] = '(full_name LIKE :q_name OR business_name LIKE :q_business OR email LIKE :q_email OR phone LIKE :q_phone)';
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $params += ['q_name' => $like, 'q_business' => $like, 'q_email' => $like, 'q_phone' => $like];
        }
        if ($stage !== '') {
            $where[] = 'stage = :stage';
            $params['stage'] = $stage;
        }
        if ($clientsOnly) {
            $where[] = "stage IN ('" . implode("', '", AgencyCatalog::CLIENT_STAGES) . "')";
        }
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM agency_prospects'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY updated_at DESC, id DESC LIMIT 200'
        );
        $statement->execute($params);

        return $this->mapList($statement->fetchAll());
    }

    public function find(int $id): ?AgencyProspect
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM agency_prospects WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? new AgencyProspect($row) : null;
    }

    /**
     * @param array{full_name: string, business_name: string, email: string, phone: string, activity: string, source: string, contact_request_id: ?int, stage: string, next_action_on: ?string, created_by: int} $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO agency_prospects (full_name, business_name, email, phone, activity, source, contact_request_id, stage, next_action_on, created_by, created_at, updated_at)
             VALUES (:full_name, :business_name, :email, :phone, :activity, :source, :contact_request_id, :stage, :next_action_on, :created_by, :created_at, :updated_at)'
        );
        $statement->execute($data + ['created_at' => $now, 'updated_at' => $now]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{full_name: string, business_name: string, email: string, phone: string, activity: string, next_action_on: ?string} $data
     */
    public function update(int $id, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE agency_prospects
             SET full_name = :full_name, business_name = :business_name, email = :email, phone = :phone,
                 activity = :activity, next_action_on = :next_action_on, updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute($data + ['id' => $id, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function updateStage(int $id, string $stage): void
    {
        $statement = $this->pdo->prepare('UPDATE agency_prospects SET stage = :stage, updated_at = :updated_at WHERE id = :id');
        $statement->execute(['id' => $id, 'stage' => $stage, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * @return array<string, int>
     */
    public function stageCounts(): array
    {
        $statement = $this->pdo->query('SELECT stage, COUNT(*) AS n FROM agency_prospects GROUP BY stage');
        $counts = [];
        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            if (is_array($row)) {
                $counts[(string) $row['stage']] = (int) $row['n'];
            }
        }

        return $counts;
    }

    public function countDueActions(string $today): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM agency_prospects
             WHERE next_action_on IS NOT NULL AND next_action_on <= :today AND stage NOT IN ('livre', 'perdu')"
        );
        $statement->execute(['today' => $today]);

        return (int) $statement->fetchColumn();
    }

    public function addEvent(int $prospectId, string $kind, string $body, int $actorId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO agency_prospect_events (prospect_id, kind, body, actor_user_id, created_at)
             VALUES (:prospect_id, :kind, :body, :actor_user_id, :created_at)'
        );
        $statement->execute([
            'prospect_id' => $prospectId,
            'kind' => $kind,
            'body' => mb_substr($body, 0, 500),
            'actor_user_id' => $actorId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(int $prospectId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.kind, e.body, e.created_at, u.full_name AS actor_name
             FROM agency_prospect_events e
             LEFT JOIN users u ON u.id = e.actor_user_id
             WHERE e.prospect_id = :prospect_id
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT 50'
        );
        $statement->execute(['prospect_id' => $prospectId]);

        return array_values(array_filter($statement->fetchAll(), 'is_array'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingRequests(int $limit = 20): array
    {
        $statement = $this->pdo->query(
            'SELECT r.id, r.full_name, r.email, r.phone, r.activity, r.message, r.created_at
             FROM contact_requests r
             LEFT JOIN agency_prospects p ON p.contact_request_id = r.id
             WHERE p.id IS NULL
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ' . max(1, min(100, $limit))
        );

        return $statement === false ? [] : array_values(array_filter($statement->fetchAll(), 'is_array'));
    }

    /**
     * Demandes de devis site web, avec le détail du projet. « nouvelles » : pas encore suivies ; « suivies » : devenues prospects.
     *
     * @return list<array<string, mixed>>
     */
    public function requests(string $view, int $limit = 200): array
    {
        $where = match ($view) {
            'suivies' => 'WHERE p.id IS NOT NULL',
            'toutes' => '',
            default => 'WHERE p.id IS NULL',
        };
        $statement = $this->pdo->query(
            'SELECT r.*, p.id AS prospect_id, p.stage AS prospect_stage
             FROM contact_requests r
             LEFT JOIN agency_prospects p ON p.contact_request_id = r.id
             ' . $where . '
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ' . max(1, min(500, $limit))
        );

        return $statement === false ? [] : array_values(array_filter($statement->fetchAll(), 'is_array'));
    }

    public function countPendingRequests(): int
    {
        $statement = $this->pdo->query(
            'SELECT COUNT(*) FROM contact_requests r
             LEFT JOIN agency_prospects p ON p.contact_request_id = r.id
             WHERE p.id IS NULL'
        );

        return $statement === false ? 0 : (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRequest(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.id, r.full_name, r.email, r.phone, r.activity, r.message, p.id AS prospect_id
             FROM contact_requests r
             LEFT JOIN agency_prospects p ON p.contact_request_id = r.id
             WHERE r.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<int, mixed> $rows
     * @return list<AgencyProspect>
     */
    private function mapList(array $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $list[] = new AgencyProspect($row);
            }
        }

        return $list;
    }
}
