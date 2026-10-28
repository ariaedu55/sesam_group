<?php

declare(strict_types=1);

use Sesam\Core\Auth\Role;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;

/**
 * Compte Super Admin principal SESAM (console, commerces, abonnements).
 * Usage : php database/seeds/sesam_principal_admin.php
 */
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require __DIR__ . '/demo_seed_lib.php';

Env::load($root . '/.env');
/** @var array{db: array{host: string, port: string, name: string, user: string, password: string}, url: string} $config */
$config = require $root . '/config/app.php';

const SESAM_PRINCIPAL_EMAIL = 'sesam@sesam-tech.com';
const SESAM_PRINCIPAL_EMAIL_PREVIOUS = 'admin@sesam-tech.com';
const SESAM_PRINCIPAL_NAME = 'Administrateur SESAM';
const SESAM_PRINCIPAL_PASSWORD = 'SesamAdmin-2026!';

$pdo = Connection::make($config['db']);
$users = new UserRepository($pdo);
$roles = new RoleRepository($pdo);
$hash = password_hash(SESAM_PRINCIPAL_PASSWORD, PASSWORD_DEFAULT);

$previous = $users->findByEmail(SESAM_PRINCIPAL_EMAIL_PREVIOUS);
$target = $users->findByEmail(SESAM_PRINCIPAL_EMAIL);
if ($previous !== null && $target === null) {
    $users->update($previous->id(), [
        'full_name' => SESAM_PRINCIPAL_NAME,
        'email' => SESAM_PRINCIPAL_EMAIL,
        'is_active' => 1,
        'password_hash' => $hash,
    ]);
}

ensureUser($users, $roles, SESAM_PRINCIPAL_EMAIL, SESAM_PRINCIPAL_NAME, null, [Role::SUPER_ADMIN], $hash);
echo 'Super Admin principal : ' . SESAM_PRINCIPAL_EMAIL . PHP_EOL;

$appUrl = rtrim((string) (getenv('APP_URL') ?: $config['url'] ?? 'https://sesam-tech.com'), '/');
$credentialsPath = $root . '/docs/comptes-demo-marketing.txt';
$block = implode("\n", [
    '',
    str_repeat('-', 72),
    '',
    'Personnel SESAM — administrateur principal',
    '  Nom      : ' . SESAM_PRINCIPAL_NAME,
    '  E-mail   : ' . SESAM_PRINCIPAL_EMAIL,
    '  Mot de passe : ' . SESAM_PRINCIPAL_PASSWORD,
    '  Connexion : ' . $appUrl . '/connexion',
    '  Console  : ' . $appUrl . '/espace (tableau de bord SESAM, commerces, revenus)',
    '',
]);

if (is_file($credentialsPath)) {
    $existing = file_get_contents($credentialsPath);
    if ($existing === false) {
        file_put_contents($credentialsPath, 'SESAM — accès démo et administration' . $block);
        echo "Fichier identifiants créé : {$credentialsPath}\n";
    } else {
        $updated = str_replace(SESAM_PRINCIPAL_EMAIL_PREVIOUS, SESAM_PRINCIPAL_EMAIL, $existing);
        if (!str_contains($updated, SESAM_PRINCIPAL_EMAIL)) {
            $updated = rtrim($updated) . $block;
        }
        if ($updated !== $existing) {
            file_put_contents($credentialsPath, $updated);
            echo "Identifiants mis à jour dans {$credentialsPath}\n";
        } else {
            echo "Fichier identifiants déjà à jour.\n";
        }
    }
} else {
    file_put_contents($credentialsPath, 'SESAM — accès démo et administration' . $block);
    echo "Fichier identifiants créé : {$credentialsPath}\n";
}
