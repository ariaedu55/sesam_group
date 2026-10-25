<?php

declare(strict_types=1);

namespace Sesam\Repositories;

final class AuthAttemptRepository extends Repository
{
    public function record(string $action, string $ip): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO auth_attempts (action, ip, created_at)
             VALUES (:action, :ip, :created_at)'
        );
        $statement->execute([
            'action' => $action,
            'ip' => $ip,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function countRecent(string $action, string $ip, int $minutes): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM auth_attempts
             WHERE action = :action AND ip = :ip AND created_at >= :since'
        );
        $statement->execute([
            'action' => $action,
            'ip' => $ip,
            'since' => gmdate('Y-m-d H:i:s', time() - ($minutes * 60)),
        ]);

        return (int) $statement->fetchColumn();
    }
}
