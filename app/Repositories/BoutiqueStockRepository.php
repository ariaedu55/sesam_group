<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\BoutiqueStockMovement;

final class BoutiqueStockRepository extends Repository
{
    /**
     * @param array{product_id: int, type: string, quantity: int, stock_after: int, note: ?string, order_id: ?int, user_id: ?int} $data
     */
    public function insert(int $tenantId, array $data): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO boutique_stock_movements
                (tenant_id, product_id, type, quantity, stock_after, note, order_id, user_id, created_at)
             VALUES (:tenant_id, :product_id, :type, :quantity, :stock_after, :note, :order_id, :user_id, :created_at)'
        );
        $statement->execute($data + [
            'tenant_id' => $tenantId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<BoutiqueStockMovement>
     */
    public function listForTenant(int $tenantId, ?int $productId, int $limit): array
    {
        $sql = 'SELECT m.id, m.product_id, p.name AS product_name, m.type, m.quantity, m.stock_after, m.note, m.order_id, m.created_at
                FROM boutique_stock_movements m
                INNER JOIN boutique_products p ON p.id = m.product_id AND p.tenant_id = m.tenant_id
                WHERE m.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($productId !== null) {
            $sql .= ' AND m.product_id = :product_id';
            $params['product_id'] = $productId;
        }
        $statement = $this->pdo->prepare($sql . ' ORDER BY m.created_at DESC, m.id DESC LIMIT ' . $limit);
        $statement->execute($params);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new BoutiqueStockMovement($row);
            }
        }

        return $items;
    }
}
