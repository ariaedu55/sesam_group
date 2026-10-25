<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\ClientActivity;

final class ClientActivityRepository extends Repository
{
    public function insert(
        int $tenantId,
        int $clientId,
        ?int $actorUserId,
        string $kind,
        string $summary,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO client_activities (tenant_id, client_id, actor_user_id, kind, summary, created_at)
             VALUES (:tenant_id, :client_id, :actor_user_id, :kind, :summary, :created_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'actor_user_id' => $actorUserId,
            'kind' => $kind,
            'summary' => $summary,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<ClientActivity>
     */
    public function recentForTenant(int $tenantId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.client_id, a.kind, a.summary, a.created_at, u.full_name AS actor_name
             FROM client_activities a
             LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE a.tenant_id = :tenant_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new ClientActivity([
                    'id' => (int) $row['id'],
                    'client_id' => (int) $row['client_id'],
                    'kind' => (string) $row['kind'],
                    'summary' => (string) $row['summary'],
                    'actor_name' => (string) ($row['actor_name'] ?? ''),
                    'created_at' => (string) $row['created_at'],
                ]);
            }
        }

        return $items;
    }

    /**
     * @return list<ClientActivity>
     */
    public function forClient(int $tenantId, int $clientId, int $limit = 50): array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.client_id, a.kind, a.summary, a.created_at, u.full_name AS actor_name
             FROM client_activities a
             LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE a.tenant_id = :tenant_id AND a.client_id = :client_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . $limit
        );
        $statement->execute(['tenant_id' => $tenantId, 'client_id' => $clientId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new ClientActivity([
                    'id' => (int) $row['id'],
                    'client_id' => (int) $row['client_id'],
                    'kind' => (string) $row['kind'],
                    'summary' => (string) $row['summary'],
                    'actor_name' => (string) ($row['actor_name'] ?? ''),
                    'created_at' => (string) $row['created_at'],
                ]);
            }
        }

        return $items;
    }
}
