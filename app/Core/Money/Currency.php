<?php

declare(strict_types=1);

namespace Sesam\Core\Money;

/**
 * Devises des abonnements SESAM. Les montants sont toujours des entiers dans la plus petite unité de la devise :
 * francs pour le FCFA (pas de centimes), centimes pour le dollar et l'euro.
 *
 * Pour ajouter une devise : une entrée ici (+ son taux vers le FCFA), puis ses pays dans Country.
 */
final class Currency
{
    public const XOF = 'XOF';
    public const USD = 'USD';
    public const EUR = 'EUR';

    public const DEFAULT = self::XOF;

    /** @var array<string, array{name: string, short: string, symbol: string, decimals: int, region: string}> */
    public const ALL = [
        self::XOF => ['name' => 'Franc CFA', 'short' => 'FCFA', 'symbol' => 'FCFA', 'decimals' => 0, 'region' => 'Afrique francophone (zone franc CFA)'],
        self::USD => ['name' => 'Dollar américain', 'short' => 'USD', 'symbol' => '$', 'decimals' => 2, 'region' => 'États-Unis et Canada'],
        self::EUR => ['name' => 'Euro', 'short' => 'EUR', 'symbol' => '€', 'decimals' => 2, 'region' => 'Europe'],
    ];

    /** Parité fixe officielle : 1 € = 655,957 FCFA. */
    public const EUR_TO_XOF = 655.957;

    /** Taux du dollar par défaut (modifiable dans la console, page Tarifs). */
    public const DEFAULT_USD_TO_XOF = 600;

    public static function exists(string $code): bool
    {
        return isset(self::ALL[$code]);
    }

    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return self::exists($code) ? $code : self::DEFAULT;
    }

    public static function name(string $code): string
    {
        return self::ALL[self::normalize($code)]['name'];
    }

    public static function short(string $code): string
    {
        return self::ALL[self::normalize($code)]['short'];
    }

    public static function decimals(string $code): int
    {
        return self::ALL[self::normalize($code)]['decimals'];
    }

    /**
     * Montant lisible : « 15 000 FCFA », « 29,00 € », « 25,00 $ US ».
     */
    public static function format(int $minor, string $code = self::DEFAULT): string
    {
        $code = self::normalize($code);
        $decimals = self::decimals($code);
        $value = number_format($minor / (10 ** $decimals), $decimals, ',', ' ');

        return match ($code) {
            self::USD => $value . ' $ US',
            self::EUR => $value . ' €',
            default => $value . ' FCFA',
        };
    }

    /** Montant saisi (« 29 », « 29,90 », « 29.90 », « 15 000 ») vers la plus petite unité. Null si illisible. */
    public static function parse(string $input, string $code): ?int
    {
        $clean = str_replace([' ', "\u{00A0}", "\u{202F}", '$', '€', 'FCFA', 'fcfa'], '', trim($input));
        if ($clean === '') {
            return 0;
        }
        $decimals = self::decimals($code);
        if ($decimals === 0) {
            $clean = str_replace(['.', ','], '', $clean);

            return ctype_digit($clean) ? (int) $clean : null;
        }
        $clean = str_replace(',', '.', $clean);
        if (preg_match('/^\d+(\.\d{1,' . $decimals . '})?$/', $clean) !== 1) {
            return null;
        }

        return (int) round(((float) $clean) * (10 ** $decimals));
    }

    /** Valeur à afficher dans un champ de saisie (« 29,90 » pour 2990 centimes). */
    public static function inputValue(int $minor, string $code): string
    {
        $decimals = self::decimals($code);

        return $decimals === 0 ? (string) $minor : number_format($minor / (10 ** $decimals), $decimals, ',', '');
    }

    /**
     * Équivalent en FCFA (tableaux de bord et revenus de la console restent en FCFA).
     */
    public static function toXof(int $minor, string $code, float $usdRate = self::DEFAULT_USD_TO_XOF): int
    {
        $code = self::normalize($code);
        $units = $minor / (10 ** self::decimals($code));

        return (int) round(match ($code) {
            self::EUR => $units * self::EUR_TO_XOF,
            self::USD => $units * $usdRate,
            default => $units,
        });
    }
}
