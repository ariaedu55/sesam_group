<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Commande\OrderStatus;
use Sesam\Models\CommandeOrder;

final class CommandeOrderRepository extends Repository
{
    public function countForTenant(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM commande_orders WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    public function countToday(int $tenantId, string $dayStart, string $dayEnd): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM commande_orders
             WHERE tenant_id = :tenant_id AND created_at >= :start AND created_at < :end'
        );
        $statement->execute(['tenant_id' => $tenantId, 'start' => $dayStart, 'end' => $dayEnd]);

        return (int) $statement->fetchColumn();
    }

    public function countInProgress(int $tenantId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM commande_orders
             WHERE tenant_id = :tenant_id AND status IN (:s1, :s2, :s3)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            's1' => OrderStatus::MEASUREMENTS,
            's2' => OrderStatus::CONFECTION,
            's3' => OrderStatus::FITTING,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function countDeliveriesDue(int $tenantId, string $dayStart, string $dayEnd): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM commande_orders
             WHERE tenant_id = :tenant_id
               AND delivery_due_at IS NOT NULL
               AND delivery_due_at >= :start AND delivery_due_at < :end
               AND status NOT IN (:delivered, :cancelled)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'start' => $dayStart,
            'end' => $dayEnd,
            'delivered' => OrderStatus::DELIVERED,
            'cancelled' => OrderStatus::CANCELLED,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<CommandeOrder>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.client_id, o.order_number, o.status, o.total_minor, o.deposit_amount_minor,
                    o.payment_id, o.fabric_notes, o.instructions, o.photo_url, o.delivery_due_at, o.delivered_at,
                    o.created_at, c.full_name AS client_name
             FROM commande_orders o
             INNER JOIN clients c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
             WHERE o.tenant_id = :tenant_id
             ORDER BY o.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('tenant_id', $tenantId, \PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        $statement->execute();

        return $this->hydrateList($statement);
    }

    public function findById(int $id, int $tenantId): ?CommandeOrder
    {
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.client_id, o.order_number, o.status, o.total_minor, o.deposit_amount_minor,
                    o.payment_id, o.fabric_notes, o.instructions, o.photo_url, o.delivery_due_at, o.delivered_at,
                    o.created_at, c.full_name AS client_name
             FROM commande_orders o
             INNER JOIN clients c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
             WHERE o.id = :id AND o.tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByPaymentId(int $paymentId, int $tenantId): ?CommandeOrder
    {
        $statement = $this->pdo->prepare(
            'SELECT o.id, o.client_id, o.order_number, o.status, o.total_minor, o.deposit_amount_minor,
                    o.payment_id, o.fabric_notes, o.instructions, o.photo_url, o.delivery_due_at, o.delivered_at,
                    o.created_at, c.full_name AS client_name
             FROM commande_orders o
             INNER JOIN clients c ON c.id = o.client_id AND c.tenant_id = o.tenant_id
             WHERE o.payment_id = :payment_id AND o.tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['payment_id' => $paymentId, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array{
     *     client_id: int,
     *     order_number: string,
     *     status: string,
     *     total_minor: int,
     *     deposit_amount_minor: int
     * } $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO commande_orders (
                tenant_id, client_id, order_number, status, total_minor, deposit_amount_minor,
                payment_id, fabric_notes, instructions, photo_url, delivery_due_at, delivered_at,
                created_at, updated_at
             ) VALUES (
                :tenant_id, :client_id, :order_number, :status, :total_minor, :deposit_amount_minor,
                NULL, NULL, NULL, NULL, NULL, NULL, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'client_id' => $data['client_id'],
            'order_number' => $data['order_number'],
            'status' => $data['status'],
            'total_minor' => $data['total_minor'],
            'deposit_amount_minor' => $data['deposit_amount_minor'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, int $tenantId, string $status): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE commande_orders SET status = :status, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'status' => $status,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function markDelivered(int $id, int $tenantId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE commande_orders
             SET status = :status, delivered_at = :delivered_at, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'status' => OrderStatus::DELIVERED,
            'delivered_at' => $now,
            'updated_at' => $now,
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function updateDetails(int $id, int $tenantId, string $fabric, string $instructions, ?string $photoUrl): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE commande_orders
             SET fabric_notes = :fabric, instructions = :instructions, photo_url = :photo_url, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'fabric' => $fabric !== '' ? $fabric : null,
            'instructions' => $instructions !== '' ? $instructions : null,
            'photo_url' => $photoUrl !== null && $photoUrl !== '' ? $photoUrl : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function updateDeliveryDue(int $id, int $tenantId, string $dueAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE commande_orders SET delivery_due_at = :due_at, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'due_at' => $dueAt,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function attachPayment(int $id, int $tenantId, int $paymentId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE commande_orders SET payment_id = :payment_id, updated_at = :updated_at
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'payment_id' => $paymentId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CommandeOrder
    {
        return new CommandeOrder([
            'id' => (int) $row['id'],
            'client_id' => (int) $row['client_id'],
            'order_number' => (string) $row['order_number'],
            'status' => (string) $row['status'],
            'total_minor' => (int) $row['total_minor'],
            'deposit_amount_minor' => (int) $row['deposit_amount_minor'],
            'payment_id' => $row['payment_id'],
            'fabric_notes' => (string) ($row['fabric_notes'] ?? ''),
            'instructions' => (string) ($row['instructions'] ?? ''),
            'photo_url' => (string) ($row['photo_url'] ?? ''),
            'delivery_due_at' => $row['delivery_due_at'],
            'delivered_at' => $row['delivered_at'],
            'created_at' => (string) $row['created_at'],
            'client_name' => (string) ($row['client_name'] ?? ''),
        ]);
    }

    /**
     * @return list<CommandeOrder>
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
