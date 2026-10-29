<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\User;

final class UserRepository extends Repository
{
    private const COLUMNS = 'id, tenant_id, full_name, email, google_sub, password_hash, is_active, last_login_at, created_at, updated_at';

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . '
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . '
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Comptes rattachés à un numéro (format international sans « + », comme à l'inscription) : connexion par téléphone.
     *
     * @return list<User>
     */
    public function findAllByPhone(string $phone): array
    {
        if ($phone === '') {
            return [];
        }
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE phone = :phone ORDER BY is_active DESC, id ASC LIMIT 5');
        $statement->execute(['phone' => $phone]);

        return array_values(array_map(fn (array $row): User => $this->hydrate($row), array_filter($statement->fetchAll(), 'is_array')));
    }

    public function findByGoogleSub(string $sub): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . '
             FROM users
             WHERE google_sub = :google_sub
             LIMIT 1'
        );
        $statement->execute(['google_sub' => $sub]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<User>
     */
    public function search(string $query, int $limit, int $offset, ?int $tenantId = null, bool $sesamOnly = false): array
    {
        $sql = 'SELECT ' . self::COLUMNS . '
                FROM users';
        [$where, $params] = $this->searchClause($query, $tenantId, $sesamOnly);
        $sql .= $where;
        $sql .= ' ORDER BY full_name ASC, id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $users = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $users[] = $this->hydrate($row);
            }
        }

        return $users;
    }

    public function countSearch(string $query, ?int $tenantId = null, bool $sesamOnly = false): int
    {
        $sql = 'SELECT COUNT(*) FROM users';
        [$where, $params] = $this->searchClause($query, $tenantId, $sesamOnly);
        $statement = $this->pdo->prepare($sql . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{full_name: string, email: string, password_hash: string, is_active: int, tenant_id: ?int, google_sub?: ?string} $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO users (tenant_id, full_name, email, google_sub, password_hash, is_active, created_at, updated_at)
             VALUES (:tenant_id, :full_name, :email, :google_sub, :password_hash, :is_active, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $data['tenant_id'] ?? null,
            'full_name' => $data['full_name'],
            'email' => isset($data['email']) && $data['email'] !== '' ? $data['email'] : null,
            'google_sub' => isset($data['google_sub']) && is_string($data['google_sub']) && $data['google_sub'] !== ''
                ? $data['google_sub']
                : null,
            'password_hash' => $data['password_hash'],
            'is_active' => $data['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{full_name: string, email: string, is_active: int, password_hash?: string} $data
     */
    public function update(int $id, array $data): void
    {
        $fields = 'full_name = :full_name, email = :email, is_active = :is_active, updated_at = :updated_at';
        $params = [
            'id' => $id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'is_active' => $data['is_active'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if (isset($data['password_hash'])) {
            $fields .= ', password_hash = :password_hash';
            $params['password_hash'] = $data['password_hash'];
        }
        $statement = $this->pdo->prepare('UPDATE users SET ' . $fields . ' WHERE id = :id');
        $statement->execute($params);
    }

    public function touchLastLogin(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'id' => $id,
            'last_login_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function linkGoogleSub(int $id, string $sub): void
    {
        $statement = $this->pdo->prepare(
            // Un nom de paramètre ne peut servir qu'une fois (requêtes préparées natives) : :google_sub et :current_sub.
            'UPDATE users SET google_sub = :google_sub, updated_at = :updated_at
             WHERE id = :id AND (google_sub IS NULL OR google_sub = :current_sub)'
        );
        $statement->execute([
            'id' => $id,
            'google_sub' => $sub,
            'current_sub' => $sub,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'password_hash' => $passwordHash,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function countSuperAdmins(?int $exceptUserId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE r.slug = :slug AND u.is_active = 1';
        $params = ['slug' => 'super-admin'];
        if ($exceptUserId !== null) {
            $sql .= ' AND u.id <> :except_id';
            $params['except_id'] = $exceptUserId;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function searchClause(string $query, ?int $tenantId, bool $sesamOnly): array
    {
        $where = [];
        $params = [];
        if ($sesamOnly) {
            $where[] = 'tenant_id IS NULL';
        } elseif ($tenantId !== null) {
            $where[] = 'tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }
        if ($query !== '') {
            $where[] = '(full_name LIKE :query_name OR email LIKE :query_email)';
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $params['query_name'] = $like;
            $params['query_email'] = $like;
        }
        $sql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        return [$sql, $params];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        $id = (int) $row['id'];
        $row['roles'] = $this->roleSlugs($id);
        $row['permissions'] = $this->permissionSlugs($id);

        return new User($row);
    }

    /**
     * @return list<string>
     */
    private function roleSlugs(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.slug
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.id ASC'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return list<string>
     */
    private function permissionSlugs(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT p.slug
             FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = :user_id
             ORDER BY p.slug ASC'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }
}
