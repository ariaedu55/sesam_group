<?php

declare(strict_types=1);

namespace Sesam\Core\Boutique;

final class Money
{
    public static function format(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}
