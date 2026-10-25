<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use Sesam\Core\Boutique\BoutiqueTheme;
use Sesam\Models\BoutiqueSettings;

final class BoutiqueSettingsRepository extends Repository
{
    public function find(int $tenantId): BoutiqueSettings
    {
        $statement = $this->pdo->prepare('SELECT * FROM boutique_settings WHERE tenant_id = :tenant_id LIMIT 1');
        $statement->execute(['tenant_id' => $tenantId]);
        $row = $statement->fetch();

        return new BoutiqueSettings(is_array($row) ? $row : ['theme' => BoutiqueTheme::DEFAULT, 'is_open' => 1]);
    }

    /**
     * @param array{whatsapp_phone: ?string, tagline: ?string, description: ?string, address: ?string, opening_hours: ?string, delivery_info: ?string, instagram_url: ?string, facebook_url: ?string, tiktok_url: ?string, theme: string, is_open: int, show_stock: int} $data
     */
    public function save(int $tenantId, array $data): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO boutique_settings
                (tenant_id, whatsapp_phone, tagline, description, address, opening_hours, delivery_info,
                 instagram_url, facebook_url, tiktok_url, theme, is_open, show_stock, created_at, updated_at)
             VALUES
                (:tenant_id, :whatsapp_phone, :tagline, :description, :address, :opening_hours, :delivery_info,
                 :instagram_url, :facebook_url, :tiktok_url, :theme, :is_open, :show_stock, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                whatsapp_phone = VALUES(whatsapp_phone), tagline = VALUES(tagline), description = VALUES(description),
                address = VALUES(address), opening_hours = VALUES(opening_hours), delivery_info = VALUES(delivery_info),
                instagram_url = VALUES(instagram_url), facebook_url = VALUES(facebook_url), tiktok_url = VALUES(tiktok_url),
                theme = VALUES(theme), is_open = VALUES(is_open), show_stock = VALUES(show_stock), updated_at = VALUES(updated_at)'
        );
        $statement->execute($data + [
            'tenant_id' => $tenantId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param 'logo_path'|'cover_path' $column
     */
    public function updateImage(int $tenantId, string $column, ?string $path): void
    {
        if ($column !== 'logo_path' && $column !== 'cover_path') {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO boutique_settings (tenant_id, ' . $column . ', created_at, updated_at)
             VALUES (:tenant_id, :path, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE ' . $column . ' = VALUES(' . $column . '), updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'path' => $path,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
