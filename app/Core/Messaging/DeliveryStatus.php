<?php

declare(strict_types=1);

namespace Sesam\Core\Messaging;

final class DeliveryStatus
{
    public const PENDING = 'pending';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const MOCK = 'mock';
    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::PENDING, self::SENT, self::FAILED, self::MOCK, self::CANCELLED];
    }
}
