<?php

declare(strict_types=1);

namespace Sesam\Repositories;

final class RoleRepository extends Repository
{
    /**
     * @return list<array{id: int, slug: string, name: string}>
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT id, slug, name FROM roles ORDER BY id ASC');
        $rows = $statement === false ? [] : $statement->fetchAll();
        $roles = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $roles[] = [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
            ];
        }

        return $roles;
    }

    /**
     * @param list<string> $slugs
     * @return list<int>
     */
    public function idsForSlugs(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach (array_values($slugs) as $index => $slug) {
            $key = 'slug' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $slug;
        }
        $statement = $this->pdo->prepare(
            'SELECT id FROM roles WHERE slug IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($params);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $roleIds
     */
    public function replaceUserRoles(int $userId, array $roleIds): void
    {
        $delete = $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $delete->execute(['user_id' => $userId]);
        if ($roleIds === []) {
            return;
        }
        $insert = $this->pdo->prepare(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        foreach ($roleIds as $roleId) {
            $insert->execute([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        }
    }
}
