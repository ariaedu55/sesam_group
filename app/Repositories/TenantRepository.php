<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\Tenant;

final class TenantRepository extends Repository
{
    public function findById(int $id): ?Tenant
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.slug, t.name, t.sector, t.status, t.phone, t.signup_source, t.created_at, t.updated_at, s.display_name
             FROM tenants t
             LEFT JOIN tenant_settings s ON s.tenant_id = t.id
             WHERE t.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? new Tenant($row) : null;
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.slug, t.name, t.sector, t.status, t.phone, t.signup_source, t.created_at, t.updated_at, s.display_name
             FROM tenants t
             LEFT JOIN tenant_settings s ON s.tenant_id = t.id
             WHERE t.slug = :slug
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? new Tenant($row) : null;
    }

    /**
     * @return list<Tenant>
     */
    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT t.id, t.slug, t.name, t.sector, t.status, t.phone, t.signup_source, t.created_at, t.updated_at, s.display_name
             FROM tenants t
             LEFT JOIN tenant_settings s ON s.tenant_id = t.id
             ORDER BY t.name ASC, t.id ASC'
        );
        $rows = $statement === false ? [] : $statement->fetchAll();
        $tenants = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $tenants[] = new Tenant($row);
            }
        }

        return $tenants;
    }

    /**
     * Lien libre : ni le lien actuel d'un autre commerce, ni un ancien lien qui redirige encore vers un autre commerce.
     */
    public function slugAvailable(string $slug, ?int $exceptTenantId = null): bool
    {
        $current = $this->findBySlug($slug);
        if ($current !== null && $current->id() !== $exceptTenantId) {
            return false;
        }
        $owner = $this->tenantIdForOldSlug($slug);

        return $owner === null || $owner === $exceptTenantId;
    }

    public function tenantIdForOldSlug(string $slug): ?int
    {
        try {
            $statement = $this->pdo->prepare('SELECT tenant_id FROM tenant_slug_history WHERE old_slug = ?');
            $statement->execute([$slug]);
            $id = $statement->fetchColumn();
        } catch (\PDOException) {
            return null;
        }

        return $id !== false ? (int) $id : null;
    }

    /**
     * Change le lien public ; l'ancien est gardé pour rediriger les liens déjà partagés.
     */
    public function changeSlug(int $tenantId, string $oldSlug, string $newSlug): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT INTO tenant_slug_history (old_slug, tenant_id, created_at) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), created_at = VALUES(created_at)'
            )->execute([$oldSlug, $tenantId, $now]);
            // Revenir à un de ses anciens liens : il redevient le lien actuel.
            $this->pdo->prepare('DELETE FROM tenant_slug_history WHERE old_slug = ? AND tenant_id = ?')->execute([$newSlug, $tenantId]);
            $this->pdo->prepare('UPDATE tenants SET slug = ?, updated_at = ? WHERE id = ?')->execute([$newSlug, $now, $tenantId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array{slug: string, name: string, sector: string, status: string, display_name: string} $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO tenants (slug, name, sector, status, created_at, updated_at)
             VALUES (:slug, :name, :sector, :status, :created_at, :updated_at)'
        );
        $statement->execute([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'sector' => $data['sector'],
            'status' => $data['status'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $settings = $this->pdo->prepare(
            'INSERT INTO tenant_settings (tenant_id, display_name, created_at, updated_at)
             VALUES (:tenant_id, :display_name, :created_at, :updated_at)'
        );
        $settings->execute([
            'tenant_id' => $id,
            'display_name' => $data['display_name'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    /**
     * @param array{slug: string, name: string, sector: string, status: string, display_name: string} $data
     */
    public function update(int $id, array $data): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE tenants
             SET slug = :slug, name = :name, sector = :sector, status = :status, updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'sector' => $data['sector'],
            'status' => $data['status'],
            'updated_at' => $now,
        ]);
        $settings = $this->pdo->prepare(
            'INSERT INTO tenant_settings (tenant_id, display_name, created_at, updated_at)
             VALUES (:tenant_id, :display_name, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), updated_at = VALUES(updated_at)'
        );
        $settings->execute([
            'tenant_id' => $id,
            'display_name' => $data['display_name'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $statement = $this->pdo->prepare('UPDATE tenants SET status = :status, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'id' => $id,
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function updateDisplayName(int $tenantId, string $displayName): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tenant_settings
             SET display_name = :display_name, updated_at = :updated_at
             WHERE tenant_id = :tenant_id'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'display_name' => $displayName,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
