<?php

declare(strict_types=1);

/**
 * Contrôle NabooPay pour abonnements commerces (inscription / renouvellement).
 * Usage : php bin/verify-naboopay-saas.php
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Sesam\Core\Env::load($root . '/.env');

$apiKey = trim(Sesam\Core\Env::get('NABOOPAY_API_KEY', ''));
$live = Sesam\Core\Env::get('NABOOPAY_LIVE', '0') === '1';
$webhookSecret = trim(Sesam\Core\Env::get('NABOOPAY_WEBHOOK_SECRET', ''));
$appUrl = rtrim(trim(Sesam\Core\Env::get('APP_URL', 'http://localhost:8080')), '/');

$ok = true;

echo "=== NabooPay — connectivité API ===\n";
passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/bin/check-naboopay.php'), $apiExit);
if ($apiExit !== 0) {
    $ok = false;
}

echo "\n=== Configuration abonnements SaaS ===\n";
echo 'live_gateway=' . ($live && $apiKey !== '' ? 'yes' : 'no') . "\n";
echo 'webhook_secret=' . ($webhookSecret !== '' ? 'set' : 'MISSING') . "\n";
echo 'app_url=' . $appUrl . "\n";

if (!$live || $apiKey === '') {
    echo "WARN: les commerces utiliseront le bac à sable, pas NabooPay live.\n";
    $ok = false;
}
if ($webhookSecret === '') {
    echo "WARN: POST /webhooks/naboopay renvoie 503 — les renouvellements en ligne ne se confirmeront pas seuls.\n";
    $ok = false;
}

echo "\n=== Parcours attendu (existant dans le code) ===\n";
echo "- Inscription / essai : /commencer puis /abonnement pour le responsable.\n";
echo "- Paiement live : SaasService::startOnlineCheckout → checkout NabooPay → webhook ou retour /abonnement/retour/{id}.\n";
echo "- Enregistrement : SaasService::applyPaidOnlineRenewal (référence idempotente).\n";

echo "\n=== Webhook (sans signature) ===\n";
$webhookUrl = $appUrl . '/webhooks/naboopay';
$code = 0;
if (function_exists('curl_init')) {
    $ch = curl_init($webhookUrl);
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
}
echo 'POST ' . $webhookUrl . ' → HTTP ' . $code . "\n";
if ($webhookSecret !== '' && $code !== 401 && $code !== 403) {
    echo "WARN: sans signature, on attend 401 (ou 403), pas 404/500.\n";
    if ($code === 404 || $code >= 500) {
        $ok = false;
    }
} elseif ($webhookSecret === '' && $code !== 503) {
    echo "WARN: secret vide → HTTP 503 attendu.\n";
}

echo "\nstatus=" . ($ok ? 'ok' : 'check_warnings') . "\n";
exit($ok ? 0 : 1);
