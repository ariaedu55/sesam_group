<?php

declare(strict_types=1);

namespace Sesam\Core\Garde;

final class ExpiryStatus
{
    public const EXPIRED = 'expired';
    public const SOON = 'soon';
    public const OK = 'ok';
    public const SOON_DAYS = 30;

    /** @var array<string, string> */
    public const LABELS = [
        self::EXPIRED => 'Expiré',
        self::SOON => 'Bientôt',
        self::OK => 'OK',
    ];

    public static function of(string $expiresOn, string $today = ''): string
    {
        $today = $today !== '' ? $today : gmdate('Y-m-d');
        if ($expiresOn < $today) {
            return self::EXPIRED;
        }
        $limit = gmdate('Y-m-d', strtotime($today . ' +' . self::SOON_DAYS . ' days') ?: time());
        if ($expiresOn <= $limit) {
            return self::SOON;
        }

        return self::OK;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
