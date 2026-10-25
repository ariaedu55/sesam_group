<?php

declare(strict_types=1);

namespace Sesam\Repositories;

final class PasswordResetRepository extends Repository
{
    public function insert(int $userId, string $tokenHash, string $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{id: int, user_id: int}|null
     */
    public function findValid(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, user_id
             FROM password_reset_tokens
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at > :now
             LIMIT 1'
        );
        $statement->execute([
            'token_hash' => $tokenHash,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
        ];
    }

    public function markUsed(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = :used_at WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'used_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function expireUnusedForUser(int $userId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE password_reset_tokens
             SET used_at = :used_at
             WHERE user_id = :user_id AND used_at IS NULL'
        );
        $statement->execute([
            'user_id' => $userId,
            'used_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
