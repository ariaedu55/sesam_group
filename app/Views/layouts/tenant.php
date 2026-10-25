<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;
use Sesam\Models\User;

$current = is_string($current ?? null) ? $current : '';
$actor = $actor ?? null;
$actorName = $actor instanceof User ? $actor->fullName() : '';
$canManageUsers = (bool) ($canManageUsers ?? false);
$canViewSettings = (bool) ($canViewSettings ?? false);
$showTeamNav = (bool) ($showTeamNav ?? $canManageUsers);
$showClientsNav = (bool) ($showClientsNav ?? false);
$showPaymentsNav = (bool) ($showPaymentsNav ?? false);
$showStatsNav = (bool) ($showStatsNav ?? false);
$showReservationNav = (bool) ($showReservationNav ?? false);
$showBoutiqueNav = (bool) ($showBoutiqueNav ?? false);
$showLoyerNav = (bool) ($showLoyerNav ?? false);
$showImmoSettingsNav = (bool) ($showImmoSettingsNav ?? false);
$tenant = $tenant instanceof Tenant ? $tenant : null;
$tenantDisplayName = is_string($tenantDisplayName ?? null) && $tenantDisplayName !== ''
    ? $tenantDisplayName
    : ($tenant?->displayName() ?? '');
$tenantSectorLabel = is_string($tenantSectorLabel ?? null) && $tenantSectorLabel !== ''
    ? $tenantSectorLabel
    : ($tenant?->sectorLabel() ?? '');
$notificationUnread = (int) ($notificationUnread ?? 0);
$status = is_string($status ?? null) ? $status : '';
$resetLink = is_string($resetLink ?? null) ? $resetLink : '';
$csrf = is_string($csrf ?? null) ? $csrf : '';
$searchQuery = is_string($searchQuery ?? null) ? $searchQuery : '';
$subscriptionAccess = is_array($subscriptionAccess ?? null) ? $subscriptionAccess : [];

$nav = [
    ['id' => 'espace', 'href' => '/espace', 'label' => $showBoutiqueNav || $showReservationNav || $showLoyerNav ? 'Accueil' : 'Tableau de bord', 'icon' => 'home', 'tone' => 'blue'],
];
if ($showReservationNav) {
    // Un salon suit sa journée : l'agenda d'abord, puis les rendez-vous, les prestations et l'équipe.
    $nav[] = ['id' => 'reservation-agenda', 'href' => '/reservation/agenda', 'label' => 'Agenda', 'icon' => 'calendar', 'tone' => 'electric'];
    $nav[] = ['id' => 'reservation-rdv', 'href' => '/reservation/rendez-vous', 'label' => 'Rendez-vous', 'icon' => 'clock', 'tone' => 'coral'];
    $nav[] = ['id' => 'reservation-prestations', 'href' => '/reservation/prestations', 'label' => 'Prestations', 'icon' => 'spark', 'tone' => 'amber'];
    $nav[] = ['id' => 'reservation-equipe', 'href' => '/reservation/equipe', 'label' => 'Équipe', 'icon' => 'team', 'tone' => 'teal'];
}
if ($showBoutiqueNav) {
    // Une boutique suit l'ordre d'une journée de commerçant : commandes, produits, stock, clients.
    $nav[] = ['id' => 'boutique-commandes', 'href' => '/boutique/commandes', 'label' => 'Commandes', 'icon' => 'bag', 'tone' => 'electric'];
    $nav[] = ['id' => 'boutique-produits', 'href' => '/boutique/produits', 'label' => 'Produits', 'icon' => 'tag', 'tone' => 'amber'];
    $nav[] = ['id' => 'boutique-stock', 'href' => '/boutique/stock', 'label' => 'Stock', 'icon' => 'box', 'tone' => 'coral'];
}
if ($showClientsNav) {
    $nav[] = ['id' => 'clients', 'href' => '/clients', 'label' => 'Clients', 'icon' => 'users', 'tone' => 'teal'];
}
if ($showPaymentsNav) {
    $nav[] = ['id' => 'paiements', 'href' => '/paiements', 'label' => 'Paiements', 'icon' => 'card', 'tone' => 'emerald'];
}
if ($showStatsNav) {
    $nav[] = ['id' => 'statistiques', 'href' => '/statistiques', 'label' => 'Statistiques', 'icon' => 'chart', 'tone' => 'amber'];
}
if ($showLoyerNav) {
    // Immo suit le travail d'un gestionnaire : encaisser, connaître ses locataires, ses biens, ses baux.
    $nav[] = ['id' => 'immo-loyers', 'href' => '/immo/loyers', 'label' => 'Loyers', 'icon' => 'card', 'tone' => 'emerald'];
    $nav[] = ['id' => 'immo-locataires', 'href' => '/immo/locataires', 'label' => 'Locataires', 'icon' => 'users', 'tone' => 'teal'];
    $nav[] = ['id' => 'immo-biens', 'href' => '/immo/biens', 'label' => 'Biens', 'icon' => 'building', 'tone' => 'electric'];
    $nav[] = ['id' => 'immo-baux', 'href' => '/immo/baux', 'label' => 'Contrats de bail', 'icon' => 'file', 'tone' => 'amber'];
    $nav[] = ['id' => 'immo-interventions', 'href' => '/immo/interventions', 'label' => 'Interventions', 'icon' => 'briefcase', 'tone' => 'coral'];
    $nav[] = ['id' => 'immo-relances', 'href' => '/immo/relances', 'label' => 'Relances', 'icon' => 'bell', 'tone' => 'coral'];
}
if ($showReservationNav) {
    $nav[] = ['id' => 'reservation-salon', 'href' => '/reservation/salon', 'label' => 'Mon salon', 'icon' => 'store', 'tone' => 'emerald'];
}
if ($showBoutiqueNav) {
    $nav[] = ['id' => 'boutique-parametres', 'href' => '/boutique/parametres', 'label' => 'Ma boutique', 'icon' => 'store', 'tone' => 'emerald'];
}
$nav[] = ['id' => 'notifications', 'href' => '/notifications', 'label' => 'Notifications', 'icon' => 'bell', 'tone' => 'coral'];
if ($showTeamNav) {
    $nav[] = ['id' => 'utilisateurs', 'href' => '/utilisateurs', 'label' => $showReservationNav ? 'Comptes' : 'Équipe', 'icon' => 'team', 'tone' => 'teal'];
}
if ($showImmoSettingsNav) {
    $nav[] = ['id' => 'immo-parametres', 'href' => '/immo/parametres', 'label' => 'Réglages', 'icon' => 'gear', 'tone' => 'slate'];
}
if ($canViewSettings && !$showBoutiqueNav && !$showReservationNav && !$showLoyerNav) {
    $nav[] = ['id' => 'parametres', 'href' => '/parametres', 'label' => 'Paramètres', 'icon' => 'gear', 'tone' => 'slate'];
}
$nav[] = ['id' => 'abonnement', 'href' => '/abonnement', 'label' => 'Abonnement', 'icon' => 'coin', 'tone' => 'amber'];
$nav[] = ['id' => 'profil', 'href' => '/profil', 'label' => 'Mon profil', 'icon' => 'person', 'tone' => 'blue'];
$currentTone = 'blue';
foreach ($nav as $item) {
    if ($item['id'] === $current) {
        $currentTone = $item['tone'];
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? 'SESAM') ?></title>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/sesam.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/app-glass.css') ?>">
    <?php // Composants communs du back-office (cartes, listes, reçus de paiement) : chargés pour tous les modules. ?>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/boutique-admin.css') ?>">
    <?php if ($showReservationNav): ?>
        <link rel="stylesheet" href="<?= Html::asset('assets/css/reservation-admin.css') ?>">
    <?php endif; ?>
    <?php if ($showLoyerNav): ?>
        <link rel="stylesheet" href="<?= Html::asset('assets/css/immo.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/notifications.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/mobile-app.css') ?>">
</head>
<body class="is-app is-tenant">
<div class="app-shell tenant-shell">
    <div class="tenant-board">
        <button class="tenant-rail-scrim" type="button" data-sidebar-scrim hidden aria-label="Fermer le menu"></button>
        <aside class="sidebar tenant-rail" id="tenant-nav" data-sidebar>
            <div class="tenant-rail-head">
                <a class="tenant-rail-logo" href="<?= Html::url('/espace') ?>" aria-label="SESAM">
                    <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="" width="36" height="36">
                    <span class="tenant-rail-brand">
                        <span class="tenant-rail-text">SESAM</span>
                        <?php if ($tenantDisplayName !== ''): ?>
                            <span class="tenant-rail-commerce tenant-rail-text"><?= Html::e($tenantDisplayName) ?></span>
                        <?php endif; ?>
                    </span>
                </a>
                <button class="tenant-rail-close" type="button" data-sidebar-close aria-label="Fermer le menu">
                    <?php $name = 'close'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                </button>
            </div>
            <nav class="side-nav tenant-rail-nav" aria-label="Tableau de bord">
                <?php foreach ($nav as $item): ?>
                    <a href="<?= Html::url($item['href']) ?>"<?= $current === $item['id'] ? ' aria-current="page"' : '' ?> title="<?= Html::e($item['label']) ?>" data-tone="<?= Html::e($item['tone']) ?>">
                        <?php $name = $item['icon']; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                        <span class="tenant-rail-text"><?= Html::e($item['label']) ?></span>
                        <?php if ($item['id'] === 'notifications' && $notificationUnread > 0): ?>
                            <span class="badge tenant-rail-badge"><?= Html::e((string) $notificationUnread) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <form class="staff-logout tenant-rail-logout" method="post" action="<?= Html::url('/deconnexion') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf) ?>">
                <button class="tenant-rail-logout-btn" type="submit" title="Se déconnecter">
                    <?php $name = 'logout'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                    <span class="tenant-rail-text">Se déconnecter</span>
                </button>
            </form>
        </aside>
        <div class="app-main tenant-workspace">
            <header class="topbar tenant-chrome">
                <button class="sidebar-toggle tenant-menu-toggle" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="tenant-nav" aria-label="Ouvrir le menu">
                    <?php $name = 'menu'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                </button>
                <form class="tenant-search" method="get" action="<?= Html::url('/recherche') ?>" role="search">
                    <span class="tenant-search-scope">Tout</span>
                    <label class="sr-only" for="tenant-search-q">Rechercher</label>
                    <input class="input" id="tenant-search-q" name="q" type="search" maxlength="120" placeholder="Rechercher…" value="<?= Html::e($searchQuery) ?>">
                    <button class="tenant-search-submit" type="submit" aria-label="Lancer la recherche">
                        <?php $name = 'search'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                    </button>
                </form>
                <div class="tenant-chrome-tools">
                    <a class="tenant-bell" href="<?= Html::url('/notifications') ?>" aria-label="Notifications<?= $notificationUnread > 0 ? ' (' . $notificationUnread . ' non lues)' : '' ?>">
                        <?php $name = 'bell'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                        <span class="tenant-bell-count" data-notification-count<?= $notificationUnread > 0 ? '' : ' hidden' ?>><?= $notificationUnread ?></span>
                    </a>
                    <a class="topbar-profile tenant-who" href="<?= Html::url('/profil') ?>">
                        <span class="tenant-avatar" aria-hidden="true"><?= Html::e(Html::initials($actorName !== '' ? $actorName : 'S')) ?></span>
                        <span class="tenant-who-copy">
                            <strong><?= Html::e($actorName) ?></strong>
                        </span>
                    </a>
                </div>
            </header>
            <main class="content tenant-content" data-tone="<?= Html::e($currentTone) ?>">
                <?php if (($subscriptionAccess['banner'] ?? '') !== '' && $current !== 'abonnement'): ?>
                    <p class="sub-banner is-<?= Html::e((string) $subscriptionAccess['tone']) ?>" role="status"><span><?= Html::e((string) $subscriptionAccess['banner']) ?></span> <a href="<?= Html::url('/abonnement') ?>"><?= ($subscriptionAccess['state'] ?? '') === 'trial' ? 'Choisir mon abonnement' : 'Se réabonner' ?></a></p>
                <?php endif; ?>
                <?php if ($status !== ''): ?>
                    <p class="alert" role="status"><?= Html::e($status) ?></p>
                <?php endif; ?>
                <?php if ($resetLink !== ''): ?>
                    <div class="alert alert-strong">
                        <label for="reset-link">Lien unique</label>
                        <input class="input" id="reset-link" type="text" readonly value="<?= Html::url($resetLink) ?>">
                    </div>
                <?php endif; ?>
                <?= $content ?>
            </main>
        </div>
    </div>
</div>
<script src="<?= Html::asset('assets/js/sesam.js') ?>" defer></script>
<?php if ($showBoutiqueNav || $showReservationNav): ?>
    <script src="<?= Html::asset('assets/js/boutique-admin.js') ?>" defer></script>
<?php endif; ?>
<?php if ($showReservationNav): ?>
    <script src="<?= Html::asset('assets/js/reservation-admin.js') ?>" defer></script>
<?php endif; ?>
<?php if ($showLoyerNav): ?>
    <script src="<?= Html::asset('assets/js/immo-admin.js') ?>" defer></script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/partials/push.php'; ?>
</body>
</html>
