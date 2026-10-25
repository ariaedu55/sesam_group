<?php

declare(strict_types=1);

namespace Sesam\Core\Inscription;

final class EnrollmentStatus
{
    public const ACTIVE = 'active';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    public static function label(string $status): string
    {
        return match ($status) {
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            default => 'Active',
        };
    }
}
