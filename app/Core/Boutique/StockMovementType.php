<?php

declare(strict_types=1);

namespace Sesam\Core\Boutique;

final class StockMovementType
{
    public const IN = 'in';
    public const OUT = 'out';
    public const ADJUST = 'adjust';
    public const SALE = 'sale';
    public const RETURN = 'return';

    /** Mouvements saisis à la main dans la page Stock. */
    public const MANUAL = [self::IN, self::OUT, self::ADJUST];

    public static function label(string $type): string
    {
        return match ($type) {
            self::IN => 'Arrivage',
            self::OUT => 'Sortie',
            self::ADJUST => 'Inventaire',
            self::SALE => 'Vente',
            self::RETURN => 'Retour',
            default => $type,
        };
    }
}
