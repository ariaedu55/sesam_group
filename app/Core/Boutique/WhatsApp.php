<?php

declare(strict_types=1);

namespace Sesam\Core\Boutique;

final class WhatsApp
{
    private const DEFAULT_COUNTRY = '221';

    /**
     * Ramène un numéro saisi librement (« 77 123 45 67 », « +221 77… », « 00221… ») au format international sans « + ».
     * Un numéro sénégalais à 9 chiffres reçoit l'indicatif 221.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 9) {
            $digits = self::DEFAULT_COUNTRY . $digits;
        }

        return $digits;
    }

    public static function isValid(string $normalized): bool
    {
        return preg_match('/^[1-9]\d{7,14}$/', $normalized) === 1;
    }

    public static function link(string $normalized, string $message = ''): string
    {
        $url = 'https://wa.me/' . $normalized;

        return $message === '' ? $url : $url . '?text=' . rawurlencode($message);
    }

    public static function display(string $normalized): string
    {
        if ($normalized === '') {
            return '';
        }
        if (str_starts_with($normalized, self::DEFAULT_COUNTRY) && strlen($normalized) === 12) {
            $local = substr($normalized, 3);

            return '+221 ' . substr($local, 0, 2) . ' ' . substr($local, 2, 3) . ' ' . substr($local, 5, 2) . ' ' . substr($local, 7, 2);
        }

        return '+' . $normalized;
    }
}
