<?php

declare(strict_types=1);

namespace Sesam\Core\Boutique;

/**
 * Couleurs proposées pour la page publique de la boutique. Le commerçant choisit un nom, jamais un code couleur.
 */
final class BoutiqueTheme
{
    public const DEFAULT = 'emeraude';

    /** @var array<string, array{label: string, accent: string, soft: string, ink: string}> */
    public const THEMES = [
        'emeraude' => ['label' => 'Émeraude', 'accent' => '#0f9d6b', 'soft' => '#e3f6ee', 'ink' => '#073b2a'],
        'ocean' => ['label' => 'Océan', 'accent' => '#1f6feb', 'soft' => '#e4eeff', 'ink' => '#0b2a5c'],
        'corail' => ['label' => 'Corail', 'accent' => '#e8553e', 'soft' => '#fdebe7', 'ink' => '#5a1b10'],
        'prune' => ['label' => 'Prune', 'accent' => '#8b3fd1', 'soft' => '#f1e7fb', 'ink' => '#33124f'],
        'soleil' => ['label' => 'Soleil', 'accent' => '#d98a00', 'soft' => '#fdf2dc', 'ink' => '#4a2f00'],
        'nuit' => ['label' => 'Nuit', 'accent' => '#1d2433', 'soft' => '#e8eaef', 'ink' => '#0d1119'],
    ];

    public static function isValid(string $theme): bool
    {
        return isset(self::THEMES[$theme]);
    }

    /**
     * @return array{label: string, accent: string, soft: string, ink: string}
     */
    public static function palette(string $theme): array
    {
        return self::THEMES[$theme] ?? self::THEMES[self::DEFAULT];
    }
}
