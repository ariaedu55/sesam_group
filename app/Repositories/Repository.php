<?php

declare(strict_types=1);

namespace Sesam\Repositories;

use PDO;

abstract class Repository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }
}
