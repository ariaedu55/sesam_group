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
 * Comptes maquette marketing (production ou local) : 3 commerces Réservation / Boutique / Immo, données riches.
 * Usage : php database/seeds/marketing_demo.php
 */
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require __DIR__ . '/demo_seed_lib.php';
require __DIR__ . '/demo_full.php';

Env::load($root . '/.env');
/** @var array{db: array{host: string, port: string, name: string, user: string, password: string}, url: string} $config */
$config = require $root . '/config/app.php';

const MARKETING_PASSWORD = 'SesamMaquette-2026!';

/** @var list<array{slug: string, name: string, display: string, sector: string, owner: string, email: string, portal_tagline?: string}> $shops */
$shops = [
    [
        'slug' => 'maquette-salon-teranga',
        'name' => 'Salon Teranga',
        'display' => 'Salon Teranga',
        'sector' => Sector::RESERVATION,
        'owner' => 'Awa Ndiaye',
        'email' => 'maquette.salon@sesam-tech.com',
        'portal_tagline' => 'Coiffure afro & soins — réservez en ligne à Mermoz',
    ],
    [
        'slug' => 'maquette-boutique-wax',
        'name' => 'Maison Wax & Co',
        'display' => 'Maison Wax & Co',
        'sector' => Sector::BOUTIQUE,
        'owner' => 'Moussa Diop',
        'email' => 'maquette.boutique@sesam-tech.com',
        'portal_tagline' => 'Mode wax et accessoires — commandez sur WhatsApp',
    ],
    [
        'slug' => 'maquette-residence-keur',
        'name' => 'Résidence Keur',
        'display' => 'Résidence Keur — Gestion locative',
        'sector' => Sector::LOYER,
        'owner' => 'Fatou Sarr',
        'email' => 'maquette.immo@sesam-tech.com',
    ],
];

$pdo = Connection::make($config['db']);
$users = new UserRepository($pdo);
$roles = new RoleRepository($pdo);
$tenants = new TenantRepository($pdo);
$hash = password_hash(MARKETING_PASSWORD, PASSWORD_DEFAULT);
$appUrl = rtrim((string) (getenv('APP_URL') ?: $config['url'] ?? 'https://sesam-tech.com'), '/');

$tenantIds = [];
$credentialsLines = [
    'SESAM — comptes maquette marketing',
    'Généré le ' . gmdate('Y-m-d H:i:s') . ' UTC',
    'Connexion : ' . $appUrl . '/connexion',
    'Mot de passe commun : ' . MARKETING_PASSWORD,
    '',
    'Données fictives (numéros 77 00x, e-mails @exemple.sn). Usage démo / vidéos / présentation commerciale.',
    str_repeat('-', 72),
    '',
];

foreach ($shops as $shop) {
    $tenant = $tenants->findBySlug($shop['slug']);
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
            'name' => $shop['name'],
            'sector' => $shop['sector'],
            'status' => 'active',
            'display_name' => $shop['display'],
        ]);
        echo "Commerce mis à jour : {$shop['slug']} (#{$id})\n";
    }
    ensureUser($users, $roles, $shop['email'], $shop['owner'], $id, [Role::TENANT_OWNER], $hash);
    seedSubscription($pdo, $id, $shop['sector']);
    if ($shop['sector'] === Sector::BOUTIQUE) {
        seedBoutique($pdo, $id, demoCatalogProducts());
    }
    if ($shop['sector'] === Sector::RESERVATION) {
        seedSalon($pdo, $id);
    }
    $tenantIds[$shop['sector']] = $id;
    marketingPatchBranding($pdo, $shop, $id);
}

if (isset($tenantIds[Sector::RESERVATION])) {
    demoReservation($pdo, $tenantIds[Sector::RESERVATION]);
}
if (isset($tenantIds[Sector::BOUTIQUE])) {
    demoBoutique($pdo, $tenantIds[Sector::BOUTIQUE]);
}
if (isset($tenantIds[Sector::LOYER])) {
    demoLoyer($pdo, $tenantIds[Sector::LOYER]);
    demoImmo($pdo, $tenantIds[Sector::LOYER]);
    marketingPatchImmoDisplay($pdo, $tenantIds[Sector::LOYER], 'Résidence Keur — Gestion locative', 'gestion@maquette-keur.sesam-tech.com');
}
foreach ($tenantIds as $tenantId) {
    demoNotifications($pdo, $tenantId);
}

$moduleLabels = [
    Sector::RESERVATION => 'Réservation (salon)',
    Sector::BOUTIQUE => 'Boutique',
    Sector::LOYER => 'Immo / loyers',
];

foreach ($shops as $shop) {
    $id = $tenantIds[$shop['sector']] ?? null;
    if ($id === null) {
        continue;
    }
    $credentialsLines[] = $moduleLabels[$shop['sector']] ?? $shop['sector'];
    $credentialsLines[] = '  Commerce : ' . $shop['display'];
    $credentialsLines[] = '  E-mail   : ' . $shop['email'];
    $credentialsLines[] = '  Espace   : ' . $appUrl . '/espace';
    $credentialsLines[] = '  Portail  : ' . $appUrl . '/t/' . $shop['slug'];
    $credentialsLines[] = '';
}

$credentialsPath = $root . '/docs/comptes-demo-marketing.txt';
if (!is_dir(dirname($credentialsPath))) {
    mkdir(dirname($credentialsPath), 0755, true);
}
file_put_contents($credentialsPath, implode("\n", $credentialsLines) . "\n");
echo "Identifiants enregistrés : {$credentialsPath}\n";
echo 'Mot de passe : ' . MARKETING_PASSWORD . "\n";

/**
 * @param array{slug: string, name: string, display: string, sector: string, owner: string, email: string, portal_tagline?: string} $shop
 */
function marketingPatchBranding(PDO $pdo, array $shop, int $tenantId): void
{
    if ($shop['sector'] === Sector::RESERVATION && isset($shop['portal_tagline'])) {
        $pdo->prepare(
            'UPDATE reservation_settings SET tagline = ?, description = ?, updated_at = UTC_TIMESTAMP() WHERE tenant_id = ?'
        )->execute([
            $shop['portal_tagline'],
            'Compte maquette SESAM — salon fictif pour démonstrations commerciales.',
            $tenantId,
        ]);
    }
    if ($shop['sector'] === Sector::BOUTIQUE && isset($shop['portal_tagline'])) {
        $pdo->prepare(
            'UPDATE boutique_settings SET tagline = ?, description = ?, updated_at = UTC_TIMESTAMP() WHERE tenant_id = ?'
        )->execute([
            $shop['portal_tagline'],
            'Compte maquette SESAM — boutique fictive pour démonstrations commerciales.',
            $tenantId,
        ]);
    }
}

function marketingPatchImmoDisplay(PDO $pdo, int $tenantId, string $displayName, string $email): void
{
    $pdo->prepare(
        'UPDATE immo_settings SET display_name = ?, email = ?, updated_at = UTC_TIMESTAMP() WHERE tenant_id = ?'
    )->execute([$displayName, $email, $tenantId]);
}
