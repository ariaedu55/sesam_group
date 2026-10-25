<?php

declare(strict_types=1);

namespace Sesam\Core\Boutique;

/**
 * Trois étapes seulement, pour rester lisible par tout le monde : Nouvelle → Confirmée → Livrée (ou Annulée).
 */
final class OrderStatus
{
    public const NEW = 'new';
    public const CONFIRMED = 'confirmed';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    /** @var list<string> */
    public const ALL = [self::NEW, self::CONFIRMED, self::DELIVERED, self::CANCELLED];

    public static function label(string $status): string
    {
        return match ($status) {
            self::CONFIRMED => 'Confirmée',
            self::DELIVERED => 'Livrée',
            self::CANCELLED => 'Annulée',
            default => 'Nouvelle',
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::CONFIRMED => 'info',
            self::DELIVERED => 'success',
            self::CANCELLED => 'muted',
            default => 'warning',
        };
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    /**
     * Le stock est retiré dès qu'une commande est confirmée ou livrée.
     */
    public static function consumesStock(string $status): bool
    {
        return $status === self::CONFIRMED || $status === self::DELIVERED;
    }

    public static function canMoveTo(string $from, string $to): bool
    {
        if ($from === $to || !self::isValid($to)) {
            return false;
        }

        return match ($from) {
            self::NEW => true,
            self::CONFIRMED => $to === self::DELIVERED || $to === self::CANCELLED,
            self::CANCELLED => $to === self::NEW,
            default => false,
        };
    }
}
