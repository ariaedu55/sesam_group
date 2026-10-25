<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\CommandeOrderMeasurement;

final class CommandeOrderMeasurementRepository extends Repository
{
    /**
     * @return list<CommandeOrderMeasurement>
     */
    public function listForOrder(int $orderId, int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.id, m.chest_cm, m.waist_cm, m.hips_cm, m.length_cm, m.notes, m.created_at,
                    u.full_name AS author_name
             FROM commande_order_measurements m
             LEFT JOIN users u ON u.id = m.author_user_id
             WHERE m.order_id = :order_id AND m.tenant_id = :tenant_id
             ORDER BY m.created_at DESC'
        );
        $statement->execute(['order_id' => $orderId, 'tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = new CommandeOrderMeasurement([
                'id' => (int) $row['id'],
                'chest_cm' => $row['chest_cm'],
                'waist_cm' => $row['waist_cm'],
                'hips_cm' => $row['hips_cm'],
                'length_cm' => $row['length_cm'],
                'notes' => (string) ($row['notes'] ?? ''),
                'author_name' => (string) ($row['author_name'] ?? ''),
                'created_at' => (string) $row['created_at'],
            ]);
        }

        return $items;
    }

    /**
     * @param array{
     *     author_user_id: ?int,
     *     chest_cm: ?float,
     *     waist_cm: ?float,
     *     hips_cm: ?float,
     *     length_cm: ?float,
     *     notes: ?string
     * } $data
     */
    public function insert(int $tenantId, int $orderId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO commande_order_measurements (
                tenant_id, order_id, author_user_id, chest_cm, waist_cm, hips_cm, length_cm, notes, created_at
             ) VALUES (
                :tenant_id, :order_id, :author_user_id, :chest_cm, :waist_cm, :hips_cm, :length_cm, :notes, :created_at
             )'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'author_user_id' => $data['author_user_id'],
            'chest_cm' => $data['chest_cm'],
            'waist_cm' => $data['waist_cm'],
            'hips_cm' => $data['hips_cm'],
            'length_cm' => $data['length_cm'],
            'notes' => $data['notes'],
            'created_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
