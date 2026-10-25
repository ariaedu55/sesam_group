<?php

declare(strict_types=1);

namespace Sesam\Core\Messaging;

final class MessageChannel
{
    public const INTERNAL = 'internal';
    public const EMAIL = 'email';
    public const WHATSAPP = 'whatsapp';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::INTERNAL, self::EMAIL, self::WHATSAPP];
    }
}
