<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\MessageTemplate;

final class MessageTemplateRepository extends Repository
{
    public function findForTenant(?int $tenantId, string $slug, string $channel): ?MessageTemplate
    {
        if ($tenantId !== null) {
            $statement = $this->pdo->prepare(
                'SELECT id, slug, channel, name, subject, body
                 FROM message_templates
                 WHERE tenant_id = :tenant_id AND slug = :slug AND channel = :channel
                 LIMIT 1'
            );
            $statement->execute(['tenant_id' => $tenantId, 'slug' => $slug, 'channel' => $channel]);
            $row = $statement->fetch();
            if (is_array($row)) {
                return $this->hydrate($row);
            }
        }

        $statement = $this->pdo->prepare(
            'SELECT id, slug, channel, name, subject, body
             FROM message_templates
             WHERE tenant_id IS NULL AND slug = :slug AND channel = :channel
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug, 'channel' => $channel]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<MessageTemplate>
     */
    public function listForTenant(?int $tenantId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, slug, channel, name, subject, body
             FROM message_templates
             WHERE tenant_id IS NULL OR tenant_id = :tenant_id
             ORDER BY slug ASC, channel ASC'
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

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): MessageTemplate
    {
        return new MessageTemplate([
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'channel' => (string) $row['channel'],
            'name' => (string) $row['name'],
            'subject' => $row['subject'],
            'body' => (string) $row['body'],
        ]);
    }
}
