<?php

declare(strict_types=1);

namespace Sesam\Core\Loyer;

final class LeaseStatus
{
    public const ACTIVE = 'active';
    public const ENDED = 'ended';

    public static function label(string $status): string
    {
        return match ($status) {
            self::ENDED => 'Terminé',
            default => 'Actif',
        };
    }
}
