<?php

declare(strict_types=1);

namespace Sesam\Core\Payment;

final class PaymentStatus
{
    public const PENDING = 'pending';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::SUCCESS, self::FAILED, self::CANCELLED];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::SUCCESS => 'Payé',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
            default => 'En attente',
        };
    }
}
