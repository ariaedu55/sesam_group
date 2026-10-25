<?php

declare(strict_types=1);

namespace Sesam\Repositories;

final class CommandeDeliveryRepository extends Repository
{
    public function upsertForOrder(int $tenantId, int $orderId, string $dueAt, ?string $notes): void
    {
        $existing = $this->findByOrder($orderId, $tenantId);
        $now = gmdate('Y-m-d H:i:s');
        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO commande_deliveries (tenant_id, order_id, due_at, delivered_at, notes, created_at, updated_at)
                 VALUES (:tenant_id, :order_id, :due_at, NULL, :notes, :created_at, :updated_at)'
            );
            $statement->execute([
                'tenant_id' => $tenantId,
                'order_id' => $orderId,
                'due_at' => $dueAt,
                'notes' => $notes !== null && $notes !== '' ? $notes : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE commande_deliveries SET due_at = :due_at, notes = :notes, updated_at = :updated_at
             WHERE order_id = :order_id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'due_at' => $dueAt,
            'notes' => $notes !== null && $notes !== '' ? $notes : null,
            'updated_at' => $now,
            'order_id' => $orderId,
            'tenant_id' => $tenantId,
        ]);
    }

    public function markDelivered(int $orderId, int $tenantId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE commande_deliveries SET delivered_at = :delivered_at, updated_at = :updated_at
             WHERE order_id = :order_id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'delivered_at' => $now,
            'updated_at' => $now,
            'order_id' => $orderId,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @return array{due_at: string, notes: ?string, delivered_at: ?string}|null
     */
    public function findByOrder(int $orderId, int $tenantId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT due_at, notes, delivered_at FROM commande_deliveries
             WHERE order_id = :order_id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['order_id' => $orderId, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'due_at' => (string) $row['due_at'],
            'notes' => $row['notes'] !== null ? (string) $row['notes'] : null,
            'delivered_at' => $row['delivered_at'] !== null ? (string) $row['delivered_at'] : null,
        ];
    }
}
