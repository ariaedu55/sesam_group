<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\MessageDelivery;

final class MessageDeliveryRepository extends Repository
{
    /**
     * @return list<MessageDelivery>
     */
    public function listForTenant(int $tenantId, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, channel, recipient, template_slug, subject, body, status, error_message, attempts, created_at, sent_at
             FROM message_deliveries
             WHERE tenant_id = :tenant_id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $statement->execute(['tenant_id' => $tenantId]);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $this->hydrate($row);
            }
        }

        return $items;
    }

    public function findById(int $id, int $tenantId): ?MessageDelivery
    {
        $statement = $this->pdo->prepare(
            'SELECT id, channel, recipient, template_slug, subject, body, status, error_message, attempts, created_at, sent_at
             FROM message_deliveries
             WHERE id = :id AND tenant_id = :tenant_id
             LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function countForTenant(int $tenantId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM message_deliveries WHERE tenant_id = :tenant_id');
        $statement->execute(['tenant_id' => $tenantId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array{
     *     tenant_id: int,
     *     channel: string,
     *     recipient: string,
     *     template_slug: string,
     *     subject: ?string,
     *     body: string,
     *     status: string,
     *     error_message: ?string,
     *     attempts: int,
     *     sent_at: ?string
     * } $data
     */
    public function insert(array $data): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO message_deliveries (
                tenant_id, channel, recipient, template_slug, subject, body, status,
                error_message, attempts, created_at, sent_at
             ) VALUES (
                :tenant_id, :channel, :recipient, :template_slug, :subject, :body, :status,
                :error_message, :attempts, :created_at, :sent_at
             )'
        );
        $statement->execute([
            'tenant_id' => $data['tenant_id'],
            'channel' => $data['channel'],
            'recipient' => $data['recipient'],
            'template_slug' => $data['template_slug'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'status' => $data['status'],
            'error_message' => $data['error_message'],
            'attempts' => $data['attempts'],
            'created_at' => $now,
            'sent_at' => $data['sent_at'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): MessageDelivery
    {
        return new MessageDelivery([
            'id' => (int) $row['id'],
            'channel' => (string) $row['channel'],
            'recipient' => (string) $row['recipient'],
            'template_slug' => (string) $row['template_slug'],
            'subject' => $row['subject'],
            'body' => (string) $row['body'],
            'status' => (string) $row['status'],
            'error_message' => $row['error_message'],
            'attempts' => (int) $row['attempts'],
            'created_at' => (string) $row['created_at'],
            'sent_at' => $row['sent_at'],
        ]);
    }
}
