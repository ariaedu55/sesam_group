<?php

declare(strict_types=1);

namespace Sesam\Core\Money;

/**
 * Pays proposés à l'inscription et à la création d'un commerce. Le pays décide de la devise de l'abonnement :
 *  - Afrique francophone de la zone franc CFA → FCFA ;
 *  - États-Unis et Canada → dollar américain ;
 *  - Europe → euro.
 */
final class Country
{
    public const DEFAULT = 'SN';

    /** @var array<string, array{0: string, 1: string}> code ISO => [nom, devise] */
    public const ALL = [
        // Franc CFA : UEMOA (XOF) et CEMAC (XAF), pays francophones. Les deux francs CFA valent la même chose.
        'SN' => ['Sénégal', Currency::XOF],
        'CI' => ['Côte d\'Ivoire', Currency::XOF],
        'ML' => ['Mali', Currency::XOF],
        'BF' => ['Burkina Faso', Currency::XOF],
        'NE' => ['Niger', Currency::XOF],
        'TG' => ['Togo', Currency::XOF],
        'BJ' => ['Bénin', Currency::XOF],
        'CM' => ['Cameroun', Currency::XOF],
        'GA' => ['Gabon', Currency::XOF],
        'CG' => ['Congo', Currency::XOF],
        'TD' => ['Tchad', Currency::XOF],
        'CF' => ['Centrafrique', Currency::XOF],
        // Dollar américain.
        'US' => ['États-Unis', Currency::USD],
        'CA' => ['Canada', Currency::USD],
        // Euro.
        'FR' => ['France', Currency::EUR],
        'BE' => ['Belgique', Currency::EUR],
        'LU' => ['Luxembourg', Currency::EUR],
        'CH' => ['Suisse', Currency::EUR],
        'MC' => ['Monaco', Currency::EUR],
        'DE' => ['Allemagne', Currency::EUR],
        'ES' => ['Espagne', Currency::EUR],
        'IT' => ['Italie', Currency::EUR],
        'PT' => ['Portugal', Currency::EUR],
        'NL' => ['Pays-Bas', Currency::EUR],
        'IE' => ['Irlande', Currency::EUR],
        'AT' => ['Autriche', Currency::EUR],
        'GR' => ['Grèce', Currency::EUR],
        'FI' => ['Finlande', Currency::EUR],
        'GB' => ['Royaume-Uni', Currency::EUR],
    ];

    public static function exists(string $code): bool
    {
        return isset(self::ALL[strtoupper($code)]);
    }

    public static function name(?string $code): string
    {
        return self::ALL[strtoupper((string) $code)][0] ?? '';
    }

    public static function currency(?string $code): string
    {
        return self::ALL[strtoupper((string) $code)][1] ?? Currency::DEFAULT;
    }

    /**
     * Pays regroupés par devise, pour les listes déroulantes (optgroup).
     *
     * @return array<string, array<string, string>> devise => [code => nom]
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (array_keys(Currency::ALL) as $currency) {
            $groups[$currency] = [];
        }
        foreach (self::ALL as $code => [$name, $currency]) {
            $groups[$currency][$code] = $name;
        }

        return $groups;
    }
}
