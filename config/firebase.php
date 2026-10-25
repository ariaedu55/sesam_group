<?php

declare(strict_types=1);

use Sesam\Core\Env;

/*
 * Notifications push via Firebase Cloud Messaging (API HTTP v1).
 * - web : configuration publique utilisée par le navigateur pour obtenir un jeton d'appareil.
 * - credentials : clé du compte de service (secrète), lue par le serveur pour signer ses demandes d'accès.
 * - driver : « fcm » envoie vraiment, « log » journalise seulement (tests, environnements sans clé).
 *   PUSH_DRIVER_OVERRIDE, posé par phpunit.xml, l'emporte sur le .env pour que les tests n'envoient jamais rien.
 */
$root = dirname(__DIR__);
$credentials = Env::get('FIREBASE_CREDENTIALS', 'storage/firebase/service-account.json');
if ($credentials !== '' && !preg_match('#^([A-Za-z]:)?[\\\\/]#', $credentials)) {
    $credentials = $root . DIRECTORY_SEPARATOR . $credentials;
}
$driver = strtolower(trim(Env::get('PUSH_DRIVER_OVERRIDE', Env::get('PUSH_DRIVER', 'log'))));

return [
    'driver' => in_array($driver, ['fcm', 'log'], true) ? $driver : 'log',
    'credentials' => $credentials,
    'project_id' => Env::get('FIREBASE_PROJECT_ID', ''),
    'vapid_key' => Env::get('FIREBASE_VAPID_KEY', ''),
    'web' => [
        'apiKey' => Env::get('FIREBASE_API_KEY', ''),
        'authDomain' => Env::get('FIREBASE_AUTH_DOMAIN', ''),
        'projectId' => Env::get('FIREBASE_PROJECT_ID', ''),
        'storageBucket' => Env::get('FIREBASE_STORAGE_BUCKET', ''),
        'messagingSenderId' => Env::get('FIREBASE_MESSAGING_SENDER_ID', ''),
        'appId' => Env::get('FIREBASE_APP_ID', ''),
    ],
    'cache_dir' => $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache',
    // Rappels du jour lancés par la première visite connectée (quand aucune tâche planifiée ne tourne). Jamais pendant les tests.
    'daily_auto' => Env::get('PUSH_DRIVER_OVERRIDE', '') === '',
    // Certificats racine (liste Mozilla) : le PHP de WAMP n'en fournit pas pour cURL.
    'ca_bundle' => $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . 'cacert.pem',
];
