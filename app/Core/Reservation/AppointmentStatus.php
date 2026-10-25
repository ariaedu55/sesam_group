<?php

declare(strict_types=1);

namespace Sesam\Core\Reservation;

final class AppointmentStatus
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';
    public const NO_SHOW = 'no_show';

    /** Rendez-vous qui occupent un créneau. @return list<string> */
    public static function active(): array
    {
        return [self::PENDING, self::CONFIRMED];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::CONFIRMED, self::COMPLETED, self::NO_SHOW, self::CANCELLED];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::CONFIRMED => 'Confirmé',
            self::CANCELLED => 'Annulé',
            self::COMPLETED => 'Terminé',
            self::NO_SHOW => 'Absent',
            default => 'À confirmer',
        };
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::CONFIRMED => 'info',
            self::COMPLETED => 'success',
            self::NO_SHOW => 'danger',
            self::CANCELLED => 'muted',
            default => 'warning',
        };
    }

    public static function canMoveTo(string $from, string $to): bool
    {
        if ($from === $to || !self::isValid($to)) {
            return false;
        }

        return match ($from) {
            self::PENDING => in_array($to, [self::CONFIRMED, self::CANCELLED, self::COMPLETED, self::NO_SHOW], true),
            self::CONFIRMED => in_array($to, [self::COMPLETED, self::NO_SHOW, self::CANCELLED], true),
            self::CANCELLED, self::NO_SHOW => $to === self::PENDING,
            self::COMPLETED => $to === self::CONFIRMED,
            default => false,
        };
    }
}
