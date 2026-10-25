<?php

declare(strict_types=1);

namespace Sesam\Core\Garde;

final class MovementType
{
    public const IN = 'in';
    public const OUT = 'out';
    public const ADJUST = 'adjust';

    /** @var array<string, string> */
    public const LABELS = [
        self::IN => 'Entrée',
        self::OUT => 'Sortie',
        self::ADJUST => 'Ajustement',
    ];

    /** @var list<string> */
    public const ALL = [self::IN, self::OUT, self::ADJUST];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }
}
