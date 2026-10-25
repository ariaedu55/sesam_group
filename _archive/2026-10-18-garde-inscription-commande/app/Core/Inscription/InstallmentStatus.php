<?php

declare(strict_types=1);

namespace Sesam\Core\Inscription;

final class InstallmentStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';

    public static function label(string $status): string
    {
        return $status === self::PAID ? 'Payée' : 'En attente';
    }
}
