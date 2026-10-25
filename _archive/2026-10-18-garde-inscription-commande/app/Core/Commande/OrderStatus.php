<?php

declare(strict_types=1);

namespace Sesam\Core\Commande;

final class OrderStatus
{
    public const NEW = 'new';
    public const MEASUREMENTS = 'measurements';
    public const CONFECTION = 'confection';
    public const FITTING = 'fitting';
    public const COMPLETED = 'completed';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function workflow(): array
    {
        return [
            self::NEW,
            self::MEASUREMENTS,
            self::CONFECTION,
            self::FITTING,
            self::COMPLETED,
            self::DELIVERED,
        ];
    }

    public static function next(string $status): ?string
    {
        $flow = self::workflow();
        $index = array_search($status, $flow, true);
        if ($index === false || !isset($flow[$index + 1])) {
            return null;
        }

        return $flow[$index + 1];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::MEASUREMENTS => 'Mesures',
            self::CONFECTION => 'Confection',
            self::FITTING => 'Essayage',
            self::COMPLETED => 'Terminée',
            self::DELIVERED => 'Livrée',
            self::CANCELLED => 'Annulée',
            default => 'Nouvelle',
        };
    }
}
