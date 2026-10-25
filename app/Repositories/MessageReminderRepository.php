<?php

declare(strict_types=1);

namespace Sesam\Repositories;

final class MessageReminderRepository extends Repository
{
    public function insert(
        int $tenantId,
        string $channel,
        string $recipient,
        string $templateSlug,
        string $variablesJson,
        string $sendAt,
        ?string $reference = null,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO message_reminders (
                tenant_id, channel, recipient, template_slug, reference, variables_json, send_at, status, created_at
             ) VALUES (
                :tenant_id, :channel, :recipient, :template_slug, :reference, :variables_json, :send_at, :status, :created_at
             )'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'channel' => $channel,
            'recipient' => $recipient,
            'template_slug' => $templateSlug,
            'reference' => $reference,
            'variables_json' => $variablesJson,
            'send_at' => $sendAt,
            'status' => 'pending',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return list<array{
     *     id: int,
     *     tenant_id: int,
     *     channel: string,
     *     recipient: string,
     *     template_slug: string,
     *     variables_json: string
     * }>
     */
    public function duePending(int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, tenant_id, channel, recipient, template_slug, variables_json
             FROM message_reminders
             WHERE status = :status AND send_at <= :now
             ORDER BY send_at ASC, id ASC
             LIMIT ' . $limit
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute(['status' => 'pending', 'now' => $now]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'id' => (int) $row['id'],
                'tenant_id' => (int) $row['tenant_id'],
                'channel' => (string) $row['channel'],
                'recipient' => (string) $row['recipient'],
                'template_slug' => (string) $row['template_slug'],
                'variables_json' => (string) $row['variables_json'],
            ];
        }

        return $items;
    }

    /**
     * Réserve un rappel avant de l'envoyer : si deux envois tournent en même temps (tâche planifiée et visite),
     * un seul obtient le rappel. Renvoie false s'il a déjà été pris ou annulé.
     */
    public function claim(int $id): bool
    {
        $statement = $this->pdo->prepare("UPDATE message_reminders SET status = 'processing' WHERE id = :id AND status = 'pending'");
        $statement->execute(['id' => $id]);

        return $statement->rowCount() === 1;
    }

    /** Annule les rappels pas encore envoyés d'un objet (rendez-vous annulé ou déplacé). */
    public function cancelPending(int $tenantId, string $reference): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE message_reminders SET status = 'cancelled', processed_at = :processed_at
             WHERE tenant_id = :tenant_id AND reference = :reference AND status = 'pending'"
        );
        $statement->execute(['processed_at' => gmdate('Y-m-d H:i:s'), 'tenant_id' => $tenantId, 'reference' => $reference]);

        return $statement->rowCount();
    }

    public function markProcessed(int $id, string $status, ?string $error): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE message_reminders
             SET status = :status, last_error = :error, processed_at = :processed_at
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
            'error' => $error,
            'processed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
