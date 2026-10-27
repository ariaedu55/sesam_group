<?php

declare(strict_types=1);

/**
 * Applique sur le serveur les réglages prod : Firebase push (FCM), vérifie mail/crons, option seed démo.
 *
 * Usage (sur le VPS, utilisateur jomas ou root avec accès au .env) :
 *   php bin/configure-production.php
 *   php bin/configure-production.php --seed-demo
 */

$root = dirname(__DIR__);
$envPath = $root . '/.env';
$seedDemo = in_array('--seed-demo', $argv ?? [], true);

if (!is_file($envPath)) {
    fwrite(STDERR, "Fichier .env introuvable.\n");
    exit(1);
}

/** @var array<string, string> $firebasePublic */
$firebasePublic = require $root . '/config/firebase.public.php';

$lines = file($envPath, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, ".env illisible.\n");
    exit(1);
}

$map = [];
foreach ($lines as $line) {
    if ($line === '' || str_starts_with(trim($line), '#')) {
        continue;
    }
    $pos = strpos($line, '=');
    if ($pos === false) {
        continue;
    }
    $map[substr($line, 0, $pos)] = substr($line, $pos + 1);
}

foreach ($firebasePublic as $key => $value) {
    $map[$key] = $value;
}

// WhatsApp réel uniquement si URL + token déjà renseignés (ne pas activer à vide).
$waUrl = trim($map['WHATSAPP_API_URL'] ?? '');
$waToken = trim($map['WHATSAPP_API_TOKEN'] ?? '');
$map['NOTIFICATIONS_MODE'] = ($waUrl !== '' && $waToken !== '') ? 'live' : 'mock';

if (trim($map['MAIL_DRIVER'] ?? '') === '') {
    $map['MAIL_DRIVER'] = 'smtp';
}

$out = [];
$written = array_fill_keys(array_keys($map), false);
foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '' || str_starts_with($trim, '#')) {
        $out[] = $line;
        continue;
    }
    $pos = strpos($line, '=');
    if ($pos === false) {
        $out[] = $line;
        continue;
    }
    $key = substr($line, 0, $pos);
    if (isset($map[$key])) {
        $out[] = $key . '=' . $map[$key];
        $written[$key] = true;
    } else {
        $out[] = $line;
    }
}

$missing = [];
foreach ($map as $key => $value) {
    if (!$written[$key]) {
        $missing[$key] = $value;
    }
}

if ($missing !== []) {
    $out[] = '';
    $out[] = '# --- ajouté par bin/configure-production.php ---';
    foreach ($missing as $key => $value) {
        $out[] = $key . '=' . $value;
    }
}

file_put_contents($envPath, implode("\n", $out) . "\n");

echo "PUSH_DRIVER=" . ($map['PUSH_DRIVER'] ?? '') . PHP_EOL;
echo "NOTIFICATIONS_MODE=" . ($map['NOTIFICATIONS_MODE'] ?? '') . PHP_EOL;
echo "MAIL_DRIVER=" . ($map['MAIL_DRIVER'] ?? '') . PHP_EOL;
echo "FIREBASE_PROJECT_ID=" . ($map['FIREBASE_PROJECT_ID'] ?? '') . PHP_EOL;

if (is_file($root . '/storage/firebase/service-account.json')) {
    echo "service-account.json : présent\n";
} else {
    echo "ATTENTION : storage/firebase/service-account.json manquant — FCM serveur inactif.\n";
}

$cronScript = $root . '/bin/install-vps-cron.sh';
if (is_file($cronScript) && function_exists('posix_getuid') && posix_getuid() === 0) {
    passthru('bash ' . escapeshellarg($cronScript), $cronExit);
    echo 'install-vps-cron : exit ' . $cronExit . PHP_EOL;
} elseif (is_file($cronScript)) {
    echo "Crons : exécutez en root : bash bin/install-vps-cron.sh\n";
}

if ($seedDemo) {
    require $root . '/vendor/autoload.php';
    echo "Seed demo.php…\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/database/seeds/demo.php'), $c1);
    echo "Seed demo_full.php…\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/database/seeds/demo_full.php'), $c2);
    echo "Seeds terminés (codes $c1, $c2).\n";
}

echo "OK configure-production\n";
