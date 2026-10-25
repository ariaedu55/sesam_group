<?php

declare(strict_types=1);

namespace Sesam\Core\Database;

use InvalidArgumentException;
use PDO;

final class Connection
{
    /** @var array<string, PDO> */
    private static array $shared = [];

    /**
     * Une seule connexion par base et par processus : les services d'une requête partagent la même session MySQL.
     *
     * @param array{host: string, port: string, name: string, user: string, password: string} $config
     */
    public static function make(array $config): PDO
    {
        $key = implode('|', [$config['host'], $config['port'], $config['name'], $config['user']]);

        return self::$shared[$key] ??= self::open($config);
    }

    /**
     * @param array{host: string, port: string, name: string, user: string, password: string} $config
     */
    private static function open(array $config): PDO
    {
        $name = $config['name'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Nom de base invalide.');
        }

        $host = $config['host'];
        $port = (int) $config['port'];
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port de base invalide.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
