<?php

declare(strict_types=1);

namespace Sesam\Repositories;

/**
 * Réglages de la plateforme SESAM (table platform_settings), modifiables depuis la console.
 * Si la table n'existe pas encore (migration non appliquée), la valeur par défaut est renvoyée.
 */
final class PlatformSettingsRepository extends Repository
{
    public function get(string $key, string $default = ''): string
    {
        try {
            $statement = $this->pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ?');
            $statement->execute([$key]);
            $value = $statement->fetchColumn();
        } catch (\PDOException) {
            return $default;
        }

        return is_string($value) ? $value : $default;
    }

    public function set(string $key, string $value, ?int $userId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_by, updated_at)
             VALUES (:setting_key, :setting_value, :updated_by, :updated_at)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_by' => $userId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{value: string, updated_at: ?string, updated_by_name: ?string}
     */
    public function details(string $key, string $default = ''): array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT s.setting_value, s.updated_at, u.full_name FROM platform_settings s
                 LEFT JOIN users u ON u.id = s.updated_by WHERE s.setting_key = ?'
            );
            $statement->execute([$key]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            $row = false;
        }

        return is_array($row)
            ? ['value' => (string) $row['setting_value'], 'updated_at' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null, 'updated_by_name' => $row['full_name'] !== null ? (string) $row['full_name'] : null]
            : ['value' => $default, 'updated_at' => null, 'updated_by_name' => null];
    }
}
