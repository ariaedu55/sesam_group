<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Models\Notification;

/**
 * Notifications de la cloche. Un commerce voit les siennes (tenant_id) ; le personnel SESAM, celles
 * sans commerce (tenant_id NULL). user_id NULL : notification pour tous les comptes de l'espace.
 */
final class NotificationRepository extends Repository
{
    private const COLUMNS = 'id, tenant_id, user_id, category, level, title, body, link, push_status, read_at, created_at';

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function scope(?int $tenantId, int $userId): array
    {
        return $tenantId === null
            ? ['tenant_id IS NULL AND user_id = ?', [$userId]]
            : ['tenant_id = ? AND (user_id IS NULL OR user_id = ?)', [$tenantId, $userId]];
    }

    /**
     * @return list<Notification>
     */
    public function forUser(?int $tenantId, int $userId, int $limit): array
    {
        [$where, $params] = $this->scope($tenantId, $userId);
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM notifications WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(200, $limit)));
        $statement->execute($params);
        $items = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = new Notification($row);
            }
        }

        return $items;
    }

    public function countUnread(?int $tenantId, int $userId): int
    {
        [$where, $params] = $this->scope($tenantId, $userId);
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE ' . $where . ' AND read_at IS NULL');
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function findForUser(int $id, ?int $tenantId, int $userId): ?Notification
    {
        [$where, $params] = $this->scope($tenantId, $userId);
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM notifications WHERE id = ? AND ' . $where . ' LIMIT 1');
        $statement->execute(array_merge([$id], $params));
        $row = $statement->fetch();

        return is_array($row) ? new Notification($row) : null;
    }

    public function insert(?int $tenantId, ?int $userId, string $title, string $body, string $category = 'general', ?string $link = null): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notifications (tenant_id, user_id, category, title, body, link, read_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NULL, UTC_TIMESTAMP())'
        );
        $statement->execute([$tenantId, $userId, $category, mb_substr($title, 0, 120), mb_substr($body, 0, 500), $link]);

        return (int) $this->pdo->lastInsertId();
    }

    public function markRead(int $id, ?int $tenantId, int $userId): void
    {
        [$where, $params] = $this->scope($tenantId, $userId);
        $statement = $this->pdo->prepare('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE id = ? AND ' . $where . ' AND read_at IS NULL');
        $statement->execute(array_merge([$id], $params));
    }

    public function markAllRead(?int $tenantId, int $userId): void
    {
        [$where, $params] = $this->scope($tenantId, $userId);
        $statement = $this->pdo->prepare('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE ' . $where . ' AND read_at IS NULL');
        $statement->execute($params);
    }
}
