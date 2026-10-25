<?php

declare(strict_types=1);

namespace Sesam\Core\Crm;

final class ClientStatus
{
    public const ACTIVE = 'active';
    public const ARCHIVED = 'archived';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ACTIVE, self::ARCHIVED];
    }
}
