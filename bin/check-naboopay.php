<?php

declare(strict_types=1);

/**
 * Vérifie la connectivité NabooPay (sans afficher de secret).
 * Usage : php bin/check-naboopay.php
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Sesam\Core\Env::load($root . '/.env');

$apiKey = trim(Sesam\Core\Env::get('NABOOPAY_API_KEY', ''));
$live = Sesam\Core\Env::get('NABOOPAY_LIVE', '0') === '1';
$base = rtrim(trim(Sesam\Core\Env::get('NABOOPAY_API_BASE', 'https://api.naboopay.com')), '/');
$webhookSecret = trim(Sesam\Core\Env::get('NABOOPAY_WEBHOOK_SECRET', ''));

echo 'mode=' . ($live && $apiKey !== '' ? 'live' : 'sandbox') . PHP_EOL;
echo 'api_base=' . $base . PHP_EOL;
echo 'api_key=' . ($apiKey !== '' ? 'set(' . strlen($apiKey) . ' chars)' : 'empty') . PHP_EOL;
echo 'webhook_secret=' . ($webhookSecret !== '' ? 'set' : 'empty') . PHP_EOL;

if ($apiKey === '') {
    echo 'status=skip (no API key — bac à sable local)' . PHP_EOL;
    exit(0);
}

$url = $base . '/api/v2/accounts';
$headers = "Authorization: Bearer {$apiKey}\r\nAccept: application/json\r\n";
$body = false;
$code = 0;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $err !== '') {
            $body = false;
        }
    }
}
if ($body === false) {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => $headers,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
}
if ($body === false) {
    echo 'status=error network (curl and stream failed)' . PHP_EOL;
    exit(1);
}

echo 'http=' . $code . PHP_EOL;
if ($code >= 200 && $code < 300) {
    echo 'status=ok NabooPay API reachable' . PHP_EOL;
    exit(0);
}

echo 'status=fail body_prefix=' . substr(preg_replace('/\s+/', ' ', (string) $body) ?? '', 0, 120) . PHP_EOL;
exit(1);
