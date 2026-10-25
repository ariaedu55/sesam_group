<?php

declare(strict_types=1);

namespace Sesam\Repositories;

final class PaymentWebhookRepository extends Repository
{
    public function exists(string $provider, string $eventId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM payment_webhook_events WHERE provider = :provider AND event_id = :event_id LIMIT 1'
        );
        $statement->execute(['provider' => $provider, 'event_id' => $eventId]);

        return (bool) $statement->fetchColumn();
    }

    public function insert(string $provider, string $eventId, string $payloadHash): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO payment_webhook_events (provider, event_id, payload_hash, processed_at, created_at)
             VALUES (:provider, :event_id, :payload_hash, :processed_at, :created_at)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'provider' => $provider,
            'event_id' => $eventId,
            'payload_hash' => $payloadHash,
            'processed_at' => $now,
            'created_at' => $now,
        ]);
    }
}
