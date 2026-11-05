<?php

declare(strict_types=1);

namespace Sesam\Core\Tenant;

/**
 * Lien public d'un commerce (/t/{slug}) : minuscules, chiffres et tirets, accents retirés.
 */
final class Slug
{
    public const MIN = 3;
    public const MAX = 60;

    public static function from(string $text): string
    {
        // Accents retirés sans dépendre d'iconv (qui écrit « 'e » pour « é » sous Windows).
        $plain = strtr($text, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'ñ' => 'n',
            'À' => 'a', 'Â' => 'a', 'Ç' => 'c', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Î' => 'i', 'Ô' => 'o', 'Ù' => 'u', 'Û' => 'u', 'œ' => 'oe', 'Œ' => 'oe', 'æ' => 'ae']);

        return trim(substr(strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $plain), '-')), 0, self::MAX), '-');
    }
}
