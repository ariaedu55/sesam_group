<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Repositories\Repository;

final class DatabaseConnectionTest extends TestCase
{
    public function testConnectsToSesamWithAPreparedStatement(): void
    {
        Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
        /** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
        $config = require dirname(__DIR__) . '/config/app.php';

        $pdo = Connection::make($config['db']);
        $statement = $pdo->prepare('SELECT DATABASE() AS db_name, 1 AS ok');
        $statement->execute();
        $row = $statement->fetch();

        $this->assertIsArray($row);
        $this->assertSame('sesam', $row['db_name']);
        $this->assertSame(1, (int) $row['ok']);

        $repository = new class ($pdo) extends Repository {
            public function driver(): string
            {
                return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            }
        };
        $this->assertSame('mysql', $repository->driver());
    }
}
