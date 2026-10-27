<?php

declare(strict_types=1);

use Sesam\Core\Auth\Role;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Tenant\Sector;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\TenantRepository;
use Sesam\Repositories\UserRepository;

/**
 * Comptes de démonstration locaux. Relancer ce script réapplique le même mot de passe.
 * Usage : php database/seeds/demo.php
 */
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require __DIR__ . '/demo_seed_lib.php';

Env::load($root . '/.env');
/** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
$config = require $root . '/config/app.php';

const DEMO_PASSWORD = 'SesamDemo-2026!';

$staff = [
    ['email' => 'demo.superadmin@sesam.local', 'name' => 'Démo Super Admin', 'role' => Role::SUPER_ADMIN],
    ['email' => 'demo.admin@sesam.local', 'name' => 'Démo Admin', 'role' => Role::ADMIN],
    ['email' => 'demo.commercial@sesam.local', 'name' => 'Démo Commercial', 'role' => Role::COMMERCIAL],
    ['email' => 'demo.finance@sesam.local', 'name' => 'Démo Finance', 'role' => Role::FINANCE],
    ['email' => 'demo.support@sesam.local', 'name' => 'Démo Support', 'role' => Role::SUPPORT],
];

$shops = [
    ['slug' => 'demo-reservation', 'name' => 'Salon Démo', 'display' => 'Horizon Démo', 'sector' => Sector::RESERVATION, 'owner' => 'Awa Démo', 'email' => 'demo.reservation@sesam.local'],
    ['slug' => 'demo-boutique', 'name' => 'Boutique Démo', 'display' => 'Awa Couture', 'sector' => Sector::BOUTIQUE, 'owner' => 'Awa Démo', 'email' => 'demo.boutique@sesam.local', 'previous_slug' => 'demo-commande', 'previous_email' => 'demo.commande@sesam.local'],
    ['slug' => 'demo-loyer', 'name' => 'Immeuble Démo', 'display' => 'Immeuble Démo', 'sector' => Sector::LOYER, 'owner' => 'Fatou Démo', 'email' => 'demo.loyer@sesam.local'],
];

$pdo = Connection::make($config['db']);
$users = new UserRepository($pdo);
$roles = new RoleRepository($pdo);
$tenants = new TenantRepository($pdo);
$hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

foreach ($staff as $account) {
    ensureUser($users, $roles, $account['email'], $account['name'], null, [$account['role']], $hash);
    echo "Personnel : {$account['email']} ({$account['role']})\n";
}

$demoTenants = [];
foreach ($shops as $shop) {
    $tenant = $tenants->findBySlug($shop['slug']);
    if ($tenant === null && isset($shop['previous_slug'])) {
        // L'ancien commerce de démonstration « Commande » devient la boutique de démonstration.
        $tenant = $tenants->findBySlug($shop['previous_slug']);
    }
    if (isset($shop['previous_email']) && $users->findByEmail($shop['email']) === null) {
        $previous = $users->findByEmail($shop['previous_email']);
        if ($previous !== null) {
            $users->update($previous->id(), [
                'full_name' => $shop['owner'],
                'email' => $shop['email'],
                'is_active' => 1,
                'password_hash' => $hash,
            ]);
        }
    }
    if ($tenant === null) {
        $id = $tenants->insert([
            'slug' => $shop['slug'],
            'name' => $shop['name'],
            'sector' => $shop['sector'],
            'status' => 'active',
            'display_name' => $shop['display'],
        ]);
        echo "Commerce créé : {$shop['slug']} (#{$id})\n";
    } else {
        $id = $tenant->id();
        $tenants->update($id, [
            'slug' => $shop['slug'],
            'name' => $shop['name'],
            'sector' => $shop['sector'],
            'status' => 'active',
            'display_name' => $shop['display'],
        ]);
        echo "Commerce déjà présent : {$shop['slug']} (#{$id})\n";
    }
    ensureUser($users, $roles, $shop['email'], $shop['owner'], $id, [Role::TENANT_OWNER], $hash);
    echo "Responsable : {$shop['email']}\n";
    if ($shop['sector'] === Sector::BOUTIQUE) {
        seedBoutique($pdo, $id, demoCatalogProducts());
    }
    if ($shop['sector'] === Sector::RESERVATION) {
        seedSalon($pdo, $id);
    }
    seedSubscription($pdo, $id, $shop['sector']);
    $demoTenants[$shop['sector']] = $id;
}

require __DIR__ . '/demo_full.php';
seedFullDemo($pdo, $demoTenants, $users, $roles, $hash);

echo 'Mot de passe commun : ' . DEMO_PASSWORD . "\n";
