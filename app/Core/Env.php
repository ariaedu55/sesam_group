<?php

declare(strict_types=1);

namespace Sesam\Core;

use RuntimeException;

final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('Fichier .env introuvable.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Fichier .env illisible.');
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));
            if ($key === '') {
                continue;
            }

            // Ne pas écraser une variable déjà fournie (ex. phpunit.xml force="true").
            if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return (string) $value;
    }
}
