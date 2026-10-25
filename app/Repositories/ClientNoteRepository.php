<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\ClientNote;

final class ClientNoteRepository extends Repository
{
    /**
     * @return list<ClientNote>
     */
    public function forClient(int $tenantId, int $clientId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT n.id, n.body, n.created_at, u.full_name AS author_name
             FROM client_notes n
             INNER JOIN users u ON u.id = n.author_user_id
             WHERE n.client_id = :client_id AND n.tenant_id = :tenant_id
             ORDER BY n.created_at DESC, n.id DESC'
        );
        $statement->execute(['client_id' => $clientId, 'tenant_id' => $tenantId]);
        $notes = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $notes[] = new ClientNote([
                    'id' => (int) $row['id'],
                    'body' => (string) $row['body'],
                    'author_name' => (string) $row['author_name'],
                    'created_at' => (string) $row['created_at'],
                ]);
            }
        }

        return $notes;
    }

    public function insert(int $tenantId, int $clientId, int $authorUserId, string $body): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO client_notes (tenant_id, client_id, author_user_id, body, created_at)
             VALUES (:tenant_id, :client_id, :author_user_id, :body, :created_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'client_id' => $clientId,
            'author_user_id' => $authorUserId,
            'body' => $body,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
