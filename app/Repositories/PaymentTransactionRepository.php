<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\PaymentTransaction;

final class PaymentTransactionRepository extends Repository
{
    /**
     * @return list<PaymentTransaction>
     */
    public function forPayment(int $paymentId, int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT kind, status, payload, created_at
             FROM payment_transactions
             WHERE payment_id = :payment_id AND tenant_id = :tenant_id
             ORDER BY created_at ASC, id ASC'
        );
        $statement->execute(['payment_id' => $paymentId, 'tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new PaymentTransaction([
                    'kind' => (string) $row['kind'],
                    'status' => (string) $row['status'],
                    'payload' => (string) ($row['payload'] ?? ''),
                    'created_at' => (string) $row['created_at'],
                ]);
            }
        }

        return $items;
    }

    public function insert(
        int $paymentId,
        int $tenantId,
        string $kind,
        string $status,
        ?string $externalId,
        ?string $payload,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO payment_transactions (payment_id, tenant_id, kind, status, external_id, payload, created_at)
             VALUES (:payment_id, :tenant_id, :kind, :status, :external_id, :payload, :created_at)'
        );
        $statement->execute([
            'payment_id' => $paymentId,
            'tenant_id' => $tenantId,
            'kind' => $kind,
            'status' => $status,
            'external_id' => $externalId,
            'payload' => $payload,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
