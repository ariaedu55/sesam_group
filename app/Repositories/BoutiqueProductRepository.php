<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\BoutiqueProduct;

final class BoutiqueProductRepository extends Repository
{
    private const COLUMNS = 'id, name, description, category, price_minor, compare_price_minor, track_stock, stock_quantity,
        low_stock_threshold, image_path, is_published, created_at, updated_at';

    /**
     * @return list<BoutiqueProduct>
     */
    public function listForTenant(int $tenantId, string $query = '', string $category = '', string $filter = ''): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM boutique_products WHERE tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($query !== '') {
            $sql .= ' AND (name LIKE :q OR category LIKE :q2)';
            $params['q'] = '%' . $query . '%';
            $params['q2'] = '%' . $query . '%';
        }
        if ($category !== '') {
            $sql .= ' AND category = :category';
            $params['category'] = $category;
        }
        $sql .= match ($filter) {
            'rupture' => ' AND track_stock = 1 AND stock_quantity <= 0',
            'faible' => ' AND track_stock = 1 AND stock_quantity > 0 AND stock_quantity <= low_stock_threshold',
            'masques' => ' AND is_published = 0',
            default => '',
        };
        $sql .= ' ORDER BY name ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $this->hydrateList($statement);
    }

    /**
     * @return list<BoutiqueProduct>
     */
    public function listPublished(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM boutique_products
             WHERE tenant_id = :tenant_id AND is_published = 1
             ORDER BY (track_stock = 1 AND stock_quantity <= 0) ASC, category ASC, name ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->hydrateList($statement);
    }

    /**
     * @return list<BoutiqueProduct>
     */
    public function listLowStock(int $tenantId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM boutique_products
             WHERE tenant_id = :tenant_id AND track_stock = 1 AND stock_quantity <= low_stock_threshold
             ORDER BY stock_quantity ASC, name ASC
             LIMIT ' . $limit
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return $this->hydrateList($statement);
    }

    /**
     * @return list<string>
     */
    public function categories(int $tenantId, bool $publishedOnly = false): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT category FROM boutique_products
             WHERE tenant_id = :tenant_id AND category IS NOT NULL AND category <> \'\''
            . ($publishedOnly ? ' AND is_published = 1' : '') . '
             ORDER BY category ASC'
        );
        $statement->execute(['tenant_id' => $tenantId]);

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return array{total: int, published: int, out: int, low: int, stock_value: int}
     */
    public function summary(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_published = 1), 0) AS published,
                    COALESCE(SUM(track_stock = 1 AND stock_quantity <= 0), 0) AS out_count,
                    COALESCE(SUM(track_stock = 1 AND stock_quantity > 0 AND stock_quantity <= low_stock_threshold), 0) AS low_count,
                    COALESCE(SUM(CASE WHEN track_stock = 1 AND stock_quantity > 0 THEN stock_quantity * price_minor ELSE 0 END), 0) AS stock_value
             FROM boutique_products WHERE tenant_id = :tenant_id'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $row = $statement->fetch();
        $row = is_array($row) ? $row : [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'out' => (int) ($row['out_count'] ?? 0),
            'low' => (int) ($row['low_count'] ?? 0),
            'stock_value' => (int) ($row['stock_value'] ?? 0),
        ];
    }

    public function findById(int $id, int $tenantId): ?BoutiqueProduct
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM boutique_products WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Verrouille la ligne jusqu'à la fin de la transaction : deux ventes simultanées ne descendent pas le stock deux fois.
     */
    public function lockById(int $id, int $tenantId): ?BoutiqueProduct
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM boutique_products WHERE id = :id AND tenant_id = :tenant_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array{name: string, description: ?string, category: ?string, price_minor: int, compare_price_minor: ?int, track_stock: int, stock_quantity: int, low_stock_threshold: int, is_published: int} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO boutique_products
                (tenant_id, name, description, category, price_minor, compare_price_minor, track_stock, stock_quantity,
                 low_stock_threshold, is_published, created_at, updated_at)
             VALUES
                (:tenant_id, :name, :description, :category, :price_minor, :compare_price_minor, :track_stock, :stock_quantity,
                 :low_stock_threshold, :is_published, :created_at, :updated_at)'
        );
        $statement->execute($data + [
            'tenant_id' => $tenantId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{name: string, description: ?string, category: ?string, price_minor: int, compare_price_minor: ?int, track_stock: int, low_stock_threshold: int, is_published: int} $data
     */
    public function update(int $id, int $tenantId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE boutique_products
             SET name = :name, description = :description, category = :category, price_minor = :price_minor,
                 compare_price_minor = :compare_price_minor, track_stock = :track_stock,
                 low_stock_threshold = :low_stock_threshold, is_published = :is_published, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute($data + [
            'id' => $id,
            'tenant_id' => $tenantId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function updateStock(int $id, int $tenantId, int $quantity): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE boutique_products SET stock_quantity = :quantity, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'quantity' => $quantity,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function updateImage(int $id, int $tenantId, ?string $path): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE boutique_products SET image_path = :path, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'path' => $path,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function setPublished(int $id, int $tenantId, bool $published): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE boutique_products SET is_published = :published, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'published' => $published ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id, int $tenantId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM boutique_products WHERE id = :id AND tenant_id = :tenant_id');
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): BoutiqueProduct
    {
        return new BoutiqueProduct($row);
    }

    /**
     * @return list<BoutiqueProduct>
     */
    private function hydrateList(\PDOStatement $statement): array
    {
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrate($row);
            }
        }

        return $items;
    }
}
