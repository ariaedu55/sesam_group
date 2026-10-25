<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\ClientTag;

final class ClientTagRepository extends Repository
{
    /**
     * @return list<ClientTag>
     */
    public function allForTenant(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, tenant_id, name
             FROM client_tags
             WHERE tenant_id = :tenant_id
             ORDER BY name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $tags = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $tags[] = $this->hydrate($row);
            }
        }

        return $tags;
    }

    /**
     * @return list<ClientTag>
     */
    public function forClient(int $tenantId, int $clientId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.tenant_id, t.name
             FROM client_tags t
             INNER JOIN client_tags_map m ON m.tag_id = t.id AND m.tenant_id = t.tenant_id
             WHERE m.client_id = :client_id AND m.tenant_id = :tenant_id
             ORDER BY t.name ASC'
        );
        $statement->execute(['client_id' => $clientId, 'tenant_id' => $tenantId]);
        $tags = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $tags[] = $this->hydrate($row);
            }
        }

        return $tags;
    }

    public function findByName(int $tenantId, string $name): ?ClientTag
    {
        $statement = $this->pdo->prepare(
            'SELECT id, tenant_id, name
             FROM client_tags
             WHERE tenant_id = :tenant_id AND name = :name
             LIMIT 1'
        );
        $statement->execute(['tenant_id' => $tenantId, 'name' => $name]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function insert(int $tenantId, string $name): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO client_tags (tenant_id, name, created_at)
             VALUES (:tenant_id, :name, :created_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $name,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function replaceClientTags(int $tenantId, int $clientId, array $tagIds): void
    {
        $delete = $this->pdo->prepare(
            'DELETE FROM client_tags_map WHERE client_id = :client_id AND tenant_id = :tenant_id'
        );
        $delete->execute(['client_id' => $clientId, 'tenant_id' => $tenantId]);
        if ($tagIds === []) {
            return;
        }
        $insert = $this->pdo->prepare(
            'INSERT INTO client_tags_map (client_id, tag_id, tenant_id)
             VALUES (:client_id, :tag_id, :tenant_id)'
        );
        foreach ($tagIds as $tagId) {
            $insert->execute([
                'client_id' => $clientId,
                'tag_id' => $tagId,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ClientTag
    {
        return new ClientTag([
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'name' => (string) $row['name'],
        ]);
    }
}
