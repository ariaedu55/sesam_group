<?php

declare(strict_types=1);

use Sesam\Core\Env;

$public = require __DIR__ . '/firebase.public.php';
$disabled = Env::get('GOOGLE_SIGNIN', '1') === '0';
$apiKey = $disabled ? '' : (trim(Env::get('FIREBASE_API_KEY', '')) !== '' ? trim(Env::get('FIREBASE_API_KEY', '')) : (string) $public['FIREBASE_API_KEY']);
$projectId = $disabled ? '' : (trim(Env::get('FIREBASE_PROJECT_ID', '')) !== '' ? trim(Env::get('FIREBASE_PROJECT_ID', '')) : (string) $public['FIREBASE_PROJECT_ID']);
$authDomain = $disabled ? '' : (trim(Env::get('FIREBASE_AUTH_DOMAIN', '')) !== '' ? trim(Env::get('FIREBASE_AUTH_DOMAIN', '')) : (string) $public['FIREBASE_AUTH_DOMAIN']);

return [
    'api_key' => $apiKey,
    'project_id' => $projectId,
    'auth_domain' => $authDomain,
    'web' => [
        'apiKey' => $apiKey,
        'authDomain' => $authDomain,
        'projectId' => $projectId,
        'storageBucket' => trim(Env::get('FIREBASE_STORAGE_BUCKET', '')) !== ''
            ? trim(Env::get('FIREBASE_STORAGE_BUCKET', ''))
            : (string) $public['FIREBASE_STORAGE_BUCKET'],
        'messagingSenderId' => trim(Env::get('FIREBASE_MESSAGING_SENDER_ID', '')) !== ''
            ? trim(Env::get('FIREBASE_MESSAGING_SENDER_ID', ''))
            : (string) $public['FIREBASE_MESSAGING_SENDER_ID'],
        'appId' => trim(Env::get('FIREBASE_APP_ID', '')) !== ''
            ? trim(Env::get('FIREBASE_APP_ID', ''))
            : (string) $public['FIREBASE_APP_ID'],
    ],
];
