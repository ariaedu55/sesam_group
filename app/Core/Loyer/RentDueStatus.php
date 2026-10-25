<?php

declare(strict_types=1);

namespace Sesam\Core\Loyer;

final class RentDueStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const CANCELLED = 'cancelled';

    public static function label(string $status): string
    {
        return match ($status) {
            self::PAID => 'Payée',
            self::CANCELLED => 'Annulée',
            default => 'En attente',
        };
    }
}
