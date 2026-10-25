<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\ReservationPrestation;

final class ReservationPrestationRepository extends Repository
{
    private const COLUMNS = 'id, name, description, image_path, category, duration_minutes, price_minor, deposit_minor, is_active, updated_at';

    /**
     * @return list<ReservationPrestation>
     */
    public function listActive(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM reservation_services
             WHERE tenant_id = :tenant_id AND is_active = 1
             ORDER BY COALESCE(category, \'\') ASC, name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->hydrateAll($statement);
    }

    /**
     * @return list<ReservationPrestation>
     */
    public function listAll(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM reservation_services
             WHERE tenant_id = :tenant_id
             ORDER BY is_active DESC, COALESCE(category, \'\') ASC, name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->hydrateAll($statement);
    }

    /**
     * @return list<string>
     */
    public function categories(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT category FROM reservation_services
             WHERE tenant_id = :tenant_id AND category IS NOT NULL AND category <> \'\' ORDER BY category ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function findById(int $id, int $tenantId): ?ReservationPrestation
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM reservation_services WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? new ReservationPrestation($row) : null;
    }

    /**
     * @param array{category_id?: ?int, name: string, description?: ?string, category?: ?string, duration_minutes: int, price_minor: int, deposit_minor: int, is_active: int} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO reservation_services
                (tenant_id, category_id, name, description, category, duration_minutes, price_minor, deposit_minor, is_active, created_at, updated_at)
             VALUES
                (:tenant_id, NULL, :name, :description, :category, :duration_minutes, :price_minor, :deposit_minor, :is_active, :created_at, :updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'duration_minutes' => $data['duration_minutes'],
            'price_minor' => $data['price_minor'],
            'deposit_minor' => $data['deposit_minor'],
            'is_active' => $data['is_active'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{name: string, description: ?string, category: ?string, duration_minutes: int, price_minor: int, deposit_minor: int, is_active: int} $data
     */
    public function update(int $id, int $tenantId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reservation_services
             SET name = :name, description = :description, category = :category, duration_minutes = :duration_minutes,
                 price_minor = :price_minor, deposit_minor = :deposit_minor, is_active = :is_active, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute($data + ['id' => $id, 'tenant_id' => $tenantId, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function updateImage(int $id, int $tenantId, ?string $path): void
    {
        $statement = $this->pdo->prepare('UPDATE reservation_services SET image_path = :path, updated_at = :updated_at WHERE id = :id AND tenant_id = :tenant_id');
        $statement->execute(['path' => $path, 'updated_at' => gmdate('Y-m-d H:i:s'), 'id' => $id, 'tenant_id' => $tenantId]);
    }

    public function setActive(int $id, int $tenantId, bool $active): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE reservation_services SET is_active = :active, updated_at = :updated_at WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId, 'active' => $active ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    /**
     * @return list<ReservationPrestation>
     */
    private function hydrateAll(\PDOStatement $statement): array
    {
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new ReservationPrestation($row);
            }
        }

        return $items;
    }
}
