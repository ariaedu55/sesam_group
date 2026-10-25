<?php

declare(strict_types=1);

namespace Sesam\Core\Tenant;

final class Sector
{
    public const RESERVATION = 'reservation';
    public const BOUTIQUE = 'boutique';
    public const LOYER = 'loyer';

    /** @var array<string, string> */
    public const LABELS = [
        self::RESERVATION => 'Réservation',
        self::BOUTIQUE => 'Boutique',
        self::LOYER => 'Immo',
    ];

    /** @var list<string> */
    public const ALL = [
        self::RESERVATION,
        self::BOUTIQUE,
        self::LOYER,
    ];

    public static function label(string $sector): string
    {
        return self::LABELS[$sector] ?? $sector;
    }
}
