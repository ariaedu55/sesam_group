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
