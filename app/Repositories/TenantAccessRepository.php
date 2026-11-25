<?php

declare(strict_types=1);

namespace Sesam\Repositories;

/**
 * Activités supplémentaires d'un compte : un même e-mail ou téléphone ouvre plusieurs activités SESAM.
 * L'activité principale reste users.tenant_id ; cette table ne contient que les autres.
 */
final class TenantAccessRepository extends Repository
{
    /**
     * Toutes les activités du compte, l'activité principale en premier.
     *
     * @return list<int>
     */
    public function tenantIdsFor(int $userId, ?int $homeTenantId): array
    {
        $ids = $homeTenantId !== null ? [$homeTenantId] : [];
        $statement = $this->pdo->prepare('SELECT tenant_id FROM user_tenant_access WHERE user_id = ? ORDER BY created_at, tenant_id');
        $statement->execute([$userId]);
        foreach ($statement->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $id = (int) $id;
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function has(int $userId, int $tenantId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM user_tenant_access WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $statement->execute([$userId, $tenantId]);

        return $statement->fetchColumn() !== false;
    }

    public function grant(int $userId, int $tenantId): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO user_tenant_access (user_id, tenant_id, created_at) VALUES (?, ?, ?)')
            ->execute([$userId, $tenantId, gmdate('Y-m-d H:i:s')]);
    }

    /**
     * Remplace les business supplémentaires du compte (le business principal reste users.tenant_id).
     *
     * @param list<int> $tenantIds
     */
    public function replaceExtra(int $userId, int $homeTenantId, array $tenantIds): void
    {
        $this->pdo->prepare('DELETE FROM user_tenant_access WHERE user_id = ?')->execute([$userId]);
        foreach (array_unique($tenantIds) as $tenantId) {
            if ($tenantId !== $homeTenantId) {
                $this->grant($userId, $tenantId);
            }
        }
    }

    /**
     * Responsable du business (rôle « tenant-owner »), rattaché à lui en principal ou en supplémentaire.
     */
    public function ownerIdOf(int $tenantId): ?int
    {
        $statement = $this->pdo->prepare(
            "SELECT u.id FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id AND r.slug = 'tenant-owner'
             WHERE u.tenant_id = ? OR u.id IN (SELECT a.user_id FROM user_tenant_access a WHERE a.tenant_id = ?)
             ORDER BY (u.tenant_id = ?) DESC, u.id
             LIMIT 1"
        );
        $statement->execute([$tenantId, $tenantId, $tenantId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Tous les business du groupe auquel appartient ce business : ceux de son responsable.
     *
     * @return list<int>
     */
    public function groupTenantIds(int $tenantId): array
    {
        $ownerId = $this->ownerIdOf($tenantId);
        if ($ownerId === null) {
            return [$tenantId];
        }
        $statement = $this->pdo->prepare('SELECT tenant_id FROM users WHERE id = ?');
        $statement->execute([$ownerId]);
        $home = $statement->fetchColumn();

        return $this->tenantIdsFor($ownerId, $home === false || $home === null ? null : (int) $home);
    }

    /**
     * Comptes qui ouvrent cette activité en plus de leur activité principale.
     *
     * @return list<int>
     */
    public function userIdsFor(int $tenantId): array
    {
        $statement = $this->pdo->prepare('SELECT user_id FROM user_tenant_access WHERE tenant_id = ? ORDER BY created_at');
        $statement->execute([$tenantId]);

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }
}
