<?php

declare(strict_types=1);

namespace Sesam\Core\Logger;

use RuntimeException;

final class Logger
{
    public function __construct(private readonly string $file)
    {
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Journal indisponible.');
        }

        $line = gmdate('c') . ' ' . $level . ' ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
        if (file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Journal indisponible.');
        }
    }
}
