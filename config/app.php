<?php

declare(strict_types=1);

use Sesam\Core\Env;

return [
    'name' => 'SESAM',
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => Env::get('APP_DEBUG', '0') === '1',
    'url' => Env::get('APP_URL', 'http://localhost'),
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'name' => Env::get('DB_NAME', ''),
        'user' => Env::get('DB_USER', ''),
        'password' => Env::get('DB_PASSWORD', ''),
    ],
];
