<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Boutique\OrderStatus;
use Sesam\Models\BoutiqueOrder;
use Sesam\Models\BoutiqueOrderItem;

final class BoutiqueOrderRepository extends Repository
{
    private const COLUMNS = 'o.id, o.client_id, o.order_number, o.status, o.source, o.customer_name, o.customer_phone,
        o.customer_address, o.note, o.total_minor, o.is_paid, o.stock_applied, o.confirmed_at, o.delivered_at,
        o.created_at, o.updated_at,
        (SELECT COALESCE(SUM(i.quantity), 0) FROM boutique_order_items i WHERE i.order_id = o.id) AS item_count';

    /**
     * @return list<BoutiqueOrder>
     */
    public function listForTenant(int $tenantId, string $status, string $query, int $limit, int $offset): array
    {
        [$where, $params] = $this->filter($tenantId, $status, $query);
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM boutique_orders o' . $where . '
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute($params);

        return $this->hydrateList($statement);
    }

    public function countForTenant(int $tenantId, string $status, string $query): int
    {
        [$where, $params] = $this->filter($tenantId, $status, $query);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM boutique_orders o' . $where);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS total FROM boutique_orders WHERE tenant_id = :tenant_id GROUP BY status'
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $counts = array_fill_keys(OrderStatus::ALL, 0);
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $counts[(string) $row['status']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public function countSince(int $tenantId, string $since): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM boutique_orders WHERE tenant_id = :tenant_id AND created_at >= :since'
        );
        $statement->execute(['tenant_id' => $tenantId, 'since' => $since]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Chiffre d'affaires des commandes confirmées ou livrées depuis une date.
     */
    public function revenueSince(int $tenantId, string $since): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(SUM(total_minor), 0) FROM boutique_orders
             WHERE tenant_id = :tenant_id AND status IN (:confirmed, :delivered) AND created_at >= :since'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'confirmed' => OrderStatus::CONFIRMED,
            'delivered' => OrderStatus::DELIVERED,
            'since' => $since,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<BoutiqueOrder>
     */
    public function listForClient(int $tenantId, int $clientId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM boutique_orders o
             WHERE o.tenant_id = :tenant_id AND o.client_id = :client_id
             ORDER BY o.created_at DESC LIMIT ' . $limit
        );
        $statement->execute(['tenant_id' => $tenantId, 'client_id' => $clientId]);

        return $this->hydrateList($statement);
    }

    public function findById(int $id, int $tenantId): ?BoutiqueOrder
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM boutique_orders o WHERE o.id = :id AND o.tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? new BoutiqueOrder($row) : null;
    }

    public function lockById(int $id, int $tenantId): ?BoutiqueOrder
    {
        $statement = $this->pdo->prepare(
            'SELECT o.* FROM boutique_orders o WHERE o.id = :id AND o.tenant_id = :tenant_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? new BoutiqueOrder($row) : null;
    }

    /**
     * @param array{client_id: ?int, order_number: string, status: string, source: string, customer_name: string, customer_phone: ?string, customer_address: ?string, note: ?string, total_minor: int} $data
     */
    public function insert(int $tenantId, array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO boutique_orders
                (tenant_id, client_id, order_number, status, source, customer_name, customer_phone, customer_address,
                 note, total_minor, created_at, updated_at)
             VALUES
                (:tenant_id, :client_id, :order_number, :status, :source, :customer_name, :customer_phone, :customer_address,
                 :note, :total_minor, :created_at, :updated_at)'
        );
        $statement->execute($data + [
            'tenant_id' => $tenantId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param list<array{product_id: int, product_name: string, quantity: int, unit_price_minor: int, line_total_minor: int}> $lines
     */
    public function insertItems(int $tenantId, int $orderId, array $lines): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO boutique_order_items
                (tenant_id, order_id, product_id, product_name, quantity, unit_price_minor, line_total_minor)
             VALUES (:tenant_id, :order_id, :product_id, :product_name, :quantity, :unit_price_minor, :line_total_minor)'
        );
        foreach ($lines as $line) {
            $statement->execute($line + ['tenant_id' => $tenantId, 'order_id' => $orderId]);
        }
    }

    /**
     * @return list<BoutiqueOrderItem>
     */
    public function items(int $orderId, int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT product_id, product_name, quantity, unit_price_minor, line_total_minor
             FROM boutique_order_items WHERE order_id = :order_id AND tenant_id = :tenant_id ORDER BY id ASC'
        );
        $statement->execute(['order_id' => $orderId, 'tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new BoutiqueOrderItem($row);
            }
        }

        return $items;
    }

    public function updateStatus(int $id, int $tenantId, string $status, bool $stockApplied): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE boutique_orders
             SET status = :status, stock_applied = :stock_applied, updated_at = :now,
                 confirmed_at = CASE WHEN :status_c IN (:confirmed, :delivered) AND confirmed_at IS NULL THEN :now_c ELSE confirmed_at END,
                 delivered_at = CASE WHEN :status_d = :delivered_d THEN :now_d ELSE delivered_at END
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'status' => $status,
            'status_c' => $status,
            'status_d' => $status,
            'confirmed' => OrderStatus::CONFIRMED,
            'delivered' => OrderStatus::DELIVERED,
            'delivered_d' => OrderStatus::DELIVERED,
            'stock_applied' => $stockApplied ? 1 : 0,
            'now' => $now,
            'now_c' => $now,
            'now_d' => $now,
        ]);
    }

    public function setPaid(int $id, int $tenantId, bool $paid): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE boutique_orders SET is_paid = :paid, updated_at = :updated_at WHERE id = :id AND tenant_id = :tenant_id'
        );
        $statement->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'paid' => $paid ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function filter(int $tenantId, string $status, string $query): array
    {
        $where = ' WHERE o.tenant_id = :tenant_id';
        $params = ['tenant_id' => $tenantId];
        if ($status !== '' && OrderStatus::isValid($status)) {
            $where .= ' AND o.status = :status';
            $params['status'] = $status;
        }
        if ($query !== '') {
            $where .= ' AND (o.order_number LIKE :q1 OR o.customer_name LIKE :q2 OR o.customer_phone LIKE :q3)';
            $params['q1'] = '%' . $query . '%';
            $params['q2'] = '%' . $query . '%';
            $params['q3'] = '%' . $query . '%';
        }

        return [$where, $params];
    }

    /**
     * @return list<BoutiqueOrder>
     */
    private function hydrateList(\PDOStatement $statement): array
    {
        $orders = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $orders[] = new BoutiqueOrder($row);
            }
        }

        return $orders;
    }
}
