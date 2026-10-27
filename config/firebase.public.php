<?php

declare(strict_types=1);

/**
 * Configuration Firebase web publique (FCM côté navigateur).
 * Ces valeurs ne sont pas secrètes ; la clé serveur reste dans storage/firebase/service-account.json.
 */
return [
    'PUSH_DRIVER' => 'fcm',
    'FIREBASE_CREDENTIALS' => 'storage/firebase/service-account.json',
    'FIREBASE_API_KEY' => 'AIzaSyDzv-Wf9pBn8FXnoR2zVd7k8-ix3O63KpE',
    'FIREBASE_AUTH_DOMAIN' => 'sesam-e1c82.firebaseapp.com',
    'FIREBASE_PROJECT_ID' => 'sesam-e1c82',
    'FIREBASE_STORAGE_BUCKET' => 'sesam-e1c82.firebasestorage.app',
    'FIREBASE_MESSAGING_SENDER_ID' => '368392116712',
    'FIREBASE_APP_ID' => '1:368392116712:web:c7e6e03cdca5ea2bb4ec5d',
    'FIREBASE_VAPID_KEY' => 'BJJD8SZDEg2T5JAlMXPFMEUA0ttqlscBCdiz1E9Vx1jmg_aXzO8Q9yDoHzQSxULdHEFFdqWL6Miek2wpG53hadM',
];
