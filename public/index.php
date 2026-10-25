<?php

declare(strict_types=1);

use Sesam\Core\Bootstrap;
use Sesam\Core\Request\Request;

ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (PHP_SAPI === 'cli-server') {
    $requested = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requested = rawurldecode(is_string($requested) ? $requested : '/');
    $file = __DIR__ . $requested;
    if ($requested !== '/' && is_file($file)) {
        return false;
    }
}

$root = dirname(__DIR__);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>SESAM</title></head><body><p>Dépendances absentes. Exécutez composer install.</p></body></html>';
    exit;
}

require $autoload;

if (extension_loaded('zlib')) {
    ini_set('zlib.output_compression', '1');
}

$kernel = Bootstrap::kernel($root);
$response = $kernel->handle(Request::capture());
$response->send();
