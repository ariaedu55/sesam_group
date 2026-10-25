<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\CommandeOrderItem;

final class CommandeOrderItemRepository extends Repository
{
    /**
     * @return list<CommandeOrderItem>
     */
    public function listForOrder(int $orderId, int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, product_name, quantity, unit_price_minor, line_total_minor
             FROM commande_order_items
             WHERE order_id = :order_id AND tenant_id = :tenant_id
             ORDER BY id ASC'
        );
        $statement->execute(['order_id' => $orderId, 'tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = new CommandeOrderItem([
                'id' => (int) $row['id'],
                'product_name' => (string) $row['product_name'],
                'quantity' => (int) $row['quantity'],
                'unit_price_minor' => (int) $row['unit_price_minor'],
                'line_total_minor' => (int) $row['line_total_minor'],
            ]);
        }

        return $items;
    }

    /**
     * @param list<array{product_id: int, product_name: string, quantity: int, unit_price_minor: int, line_total_minor: int}> $lines
     */
    public function insertLines(int $tenantId, int $orderId, array $lines): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO commande_order_items (
                tenant_id, order_id, product_id, product_name, quantity, unit_price_minor, line_total_minor
             ) VALUES (
                :tenant_id, :order_id, :product_id, :product_name, :quantity, :unit_price_minor, :line_total_minor
             )'
        );
        foreach ($lines as $line) {
            $statement->execute([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'quantity' => $line['quantity'],
                'unit_price_minor' => $line['unit_price_minor'],
                'line_total_minor' => $line['line_total_minor'],
            ]);
        }
    }
}
