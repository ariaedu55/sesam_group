<?php

declare(strict_types=1);

namespace Sesam\Helpers;

final class Html
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = mb_substr((string) ($parts[0] ?? 'S'), 0, 1, 'UTF-8');
        $second = mb_substr((string) ($parts[1] ?? ''), 0, 1, 'UTF-8');

        return mb_strtoupper($first . $second, 'UTF-8');
    }

    /**
     * Le suffixe ?v= change à chaque modification du fichier : le cache navigateur peut être long sans servir d'ancienne version.
     */
    public static function asset(string $path): string
    {
        $url = self::encodePath(self::scriptDirectory()) . '/' . ltrim(self::encodePath($path), '/');
        $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        $version = is_file($file) ? filemtime($file) : false;

        return self::e($version === false ? $url : $url . '?v=' . $version);
    }

    /**
     * <picture> avec une source WebP quand assets/images/<nom>.webp existe ; le JPEG reste le repli.
     */
    public static function picture(string $file, string $alt, int $width, int $height, bool $lazy = true): string
    {
        $image = 'assets/images/' . $file;
        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image) ?? $image;
        $source = $webp !== $image && is_file(dirname(__DIR__, 2) . '/public/' . $webp)
            ? '<source srcset="' . self::asset($webp) . '" type="image/webp">'
            : '';

        return '<picture>' . $source . '<img src="' . self::asset($image) . '" alt="' . self::e($alt) . '" width="' . $width . '" height="' . $height . '"'
            . ($lazy ? ' loading="lazy"' : '') . ' decoding="async"></picture>';
    }

    /**
     * Le chemin est encodé segment par segment ; la requête (?…) et l'ancre (#…) sont gardées telles quelles.
     */
    public static function url(string $path): string
    {
        $tail = '';
        $cut = strcspn($path, '?#');
        if ($cut < strlen($path)) {
            $tail = substr($path, $cut);
            $path = substr($path, 0, $cut);
        }
        $base = preg_replace('#/public$#', '', self::scriptDirectory()) ?? '';
        $suffix = ltrim(self::encodePath($path), '/');
        $prefix = self::encodePath($base);

        if ($suffix === '') {
            return self::e(($prefix === '' ? '/' : $prefix . '/') . $tail);
        }

        return self::e(($prefix === '' ? '' : $prefix) . '/' . $suffix . $tail);
    }

    private static function scriptDirectory(): string
    {
        $script = str_replace('\\', '/', rawurldecode($_SERVER['SCRIPT_NAME'] ?? ''));
        $directory = dirname($script);
        if ($directory === '/' || $directory === '.' || $directory === '\\') {
            return '';
        }

        return $directory;
    }

    private static function encodePath(string $path): string
    {
        $leading = str_starts_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }
            $segments[] = rawurlencode($segment);
        }

        return ($leading ? '/' : '') . implode('/', $segments);
    }
}
