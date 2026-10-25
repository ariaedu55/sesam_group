<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Crm\ClientStatus;
use Sesam\Models\Client;

final class ClientRepository extends Repository
{
    public function findById(int $id, int $tenantId): ?Client
    {
        $statement = $this->pdo->prepare(
            'SELECT id, tenant_id, full_name, email, phone, status, created_at, updated_at
             FROM clients
             WHERE id = :id AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByPhone(string $phone, int $tenantId): ?Client
    {
        $statement = $this->pdo->prepare(
            'SELECT id, tenant_id, full_name, email, phone, status, created_at, updated_at
             FROM clients
             WHERE phone = :phone AND tenant_id = :tenant_id
             ORDER BY id ASC
             LIMIT 1'
        );
        $statement->execute(['phone' => $phone, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Même numéro quelle que soit l'écriture (« +221 77 123 45 67 », « 771234567 ») : on compare les 9 derniers chiffres.
     */
    public function findByPhoneDigits(string $phone, int $tenantId): ?Client
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 7) {
            return null;
        }
        $statement = $this->pdo->prepare(
            "SELECT id, tenant_id, full_name, email, phone, status, created_at, updated_at
             FROM clients
             WHERE tenant_id = :tenant_id AND phone IS NOT NULL
               AND RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 9) = :digits
             ORDER BY (status = 'active') DESC, id ASC
             LIMIT 1"
        );
        $statement->execute(['tenant_id' => $tenantId, 'digits' => substr($digits, -9)]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Suggestions pendant la saisie : nom qui contient le texte (ceux qui commencent par lui d'abord) ou numéro qui contient les chiffres.
     *
     * @return list<Client>
     */
    public function suggest(int $tenantId, string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $digits = (string) preg_replace('/\D+/', '', $query);
        $sql = "SELECT id, tenant_id, full_name, email, phone, status, created_at, updated_at
                FROM clients
                WHERE tenant_id = :tenant_id AND status <> 'archived'
                  AND (full_name LIKE :contains" . (strlen($digits) >= 3 ? " OR REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', '') LIKE :digits" : '') . ")
                ORDER BY (full_name LIKE :starts) DESC, full_name ASC, id ASC
                LIMIT " . max(1, min(20, $limit));
        $params = [
            'tenant_id' => $tenantId,
            'contains' => '%' . addcslashes($query, '%_\\') . '%',
            'starts' => addcslashes($query, '%_\\') . '%',
        ];
        if (strlen($digits) >= 3) {
            $params['digits'] = '%' . $digits . '%';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $clients = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $clients[] = $this->hydrate($row);
            }
        }

        return $clients;
    }

    /**
     * @return list<Client>
     */
    public function search(
        int $tenantId,
        string $query,
        ?string $status,
        ?int $tagId,
        int $limit,
        int $offset,
    ): array {
        [$where, $params] = $this->filterClause($tenantId, $query, $status, $tagId);
        $sql = 'SELECT c.id, c.tenant_id, c.full_name, c.email, c.phone, c.status, c.created_at, c.updated_at
                FROM clients c' . $where . '
                ORDER BY c.full_name ASC, c.id ASC
                LIMIT ' . $limit . ' OFFSET ' . $offset;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $clients = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $clients[] = $this->hydrate($row);
            }
        }

        return $clients;
    }

    public function countSearch(int $tenantId, string $query, ?string $status, ?int $tagId): int
    {
        [$where, $params] = $this->filterClause($tenantId, $query, $status, $tagId);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM clients c' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<Client>
     */
    public function listForExport(int $tenantId, string $query, ?string $status, ?int $tagId): array
    {
        [$where, $params] = $this->filterClause($tenantId, $query, $status, $tagId);
        $sql = 'SELECT c.id, c.tenant_id, c.full_name, c.email, c.phone, c.status, c.created_at, c.updated_at
                FROM clients c' . $where . '
                ORDER BY c.full_name ASC, c.id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $clients = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $clients[] = $this->hydrate($row);
            }
        }

        return $clients;
    }

    /**
     * @param array{full_name: string, email: ?string, phone: ?string, status: string} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO clients (tenant_id, full_name, email, phone, status, created_at, updated_at)
             VALUES (:tenant_id, :full_name, :email, :phone, :status, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{full_name: string, email: ?string, phone: ?string, status: string} $data
     */
    public function update(int $id, int $tenantId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE clients
             SET full_name = :full_name, email = :email, phone = :phone, status = :status, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'status' => $data['status'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filterClause(int $tenantId, string $query, ?string $status, ?int $tagId): array
    {
        $where = ' WHERE c.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($query !== '') {
            $where .= ' AND (c.full_name LIKE :query_name OR c.email LIKE :query_email OR c.phone LIKE :query_phone)';
            $like = '%' . $query . '%';
            $params['query_name'] = $like;
            $params['query_email'] = $like;
            $params['query_phone'] = $like;
        }
        if ($status !== null && $status !== '' && in_array($status, ClientStatus::all(), true)) {
            $where .= ' AND c.status = :status';
            $params['status'] = $status;
        }
        if ($tagId !== null && $tagId > 0) {
            $where .= ' AND EXISTS (
                SELECT 1 FROM client_tags_map m
                WHERE m.client_id = c.id AND m.tenant_id = c.tenant_id AND m.tag_id = :tag_id
            )';
            $params['tag_id'] = $tagId;
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Client
    {
        return new Client([
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'full_name' => (string) $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ]);
    }
}
