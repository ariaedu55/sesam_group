<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\User;

$current = is_string($current ?? null) ? $current : '';
$actor = $actor ?? null;
$actorName = $actor instanceof User ? $actor->fullName() : '';
$canManageUsers = (bool) ($canManageUsers ?? false);
$canManageTenants = (bool) ($canManageTenants ?? false);
$canUseAgency = (bool) ($canUseAgency ?? false);
$canViewSettings = (bool) ($canViewSettings ?? false);
$canSeeRevenue = (bool) ($canSeeRevenue ?? false);
$status = is_string($status ?? null) ? $status : '';
$resetLink = is_string($resetLink ?? null) ? $resetLink : '';
$csrf = is_string($csrf ?? null) ? $csrf : '';
$notificationUnread = (int) ($notificationUnread ?? 0);

// Menu en rubriques : le SaaS (commerces abonnés) est séparé de l'agence (sites web et devis) et de la finance.
$nav = [
    ['group' => 'Pilotage'],
    ['id' => 'espace', 'href' => '/espace', 'label' => 'Tableau de bord', 'icon' => 'home', 'tone' => 'blue'],
];
if ($canManageTenants) {
    $nav[] = ['group' => 'Commerces (SaaS)'];
    $nav[] = ['id' => 'commerces', 'href' => '/commerces', 'label' => 'Commerces', 'icon' => 'store', 'tone' => 'electric'];
    $nav[] = ['id' => 'essais', 'href' => '/commerces?abonnement=trial', 'label' => 'Essais en cours', 'icon' => 'clock', 'tone' => 'amber'];
    $nav[] = ['id' => 'tarifs', 'href' => '/tarifs', 'label' => 'Tarifs', 'icon' => 'tag', 'tone' => 'amber'];
}
if ($canUseAgency) {
    $nav[] = ['group' => 'Agence (sites web)'];
    $nav[] = ['id' => 'demandes', 'href' => '/agence/demandes', 'label' => 'Demandes de devis', 'icon' => 'chat', 'tone' => 'coral'];
    $nav[] = ['id' => 'agence', 'href' => '/agence', 'label' => 'Prospects & projets', 'icon' => 'briefcase', 'tone' => 'amber'];
}
if ($canSeeRevenue) {
    $nav[] = ['group' => 'Finance'];
    $nav[] = ['id' => 'revenus', 'href' => '/revenus', 'label' => 'Revenus', 'icon' => 'coin', 'tone' => 'emerald'];
    $nav[] = ['id' => 'statistiques', 'href' => '/finance/statistiques', 'label' => 'Statistiques', 'icon' => 'chart', 'tone' => 'emerald'];
}
$nav[] = ['group' => 'Administration'];
if ($canManageUsers) {
    $nav[] = ['id' => 'utilisateurs', 'href' => '/utilisateurs', 'label' => 'Utilisateurs', 'icon' => 'team', 'tone' => 'teal'];
}
if ($canViewSettings) {
    $nav[] = ['id' => 'parametres', 'href' => '/parametres', 'label' => 'Paramètres', 'icon' => 'gear', 'tone' => 'slate'];
}
$nav[] = ['id' => 'notifications', 'href' => '/notifications', 'label' => 'Notifications', 'icon' => 'bell', 'tone' => 'coral'];
$nav[] = ['id' => 'recherche', 'href' => '/recherche', 'label' => 'Recherche', 'icon' => 'search', 'tone' => 'emerald'];
$nav[] = ['id' => 'profil', 'href' => '/profil', 'label' => 'Mon profil', 'icon' => 'person', 'tone' => 'coral'];
$currentTone = 'blue';
foreach ($nav as $item) {
    if (($item['id'] ?? '') === $current) {
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
    <link rel="stylesheet" href="<?= Html::asset('assets/css/boutique-admin.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/console.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/notifications.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/mobile-app.css') ?>">
</head>
<body class="is-app is-staff">
<div class="app-shell tenant-shell">
    <div class="tenant-board">
        <button class="tenant-rail-scrim" type="button" data-sidebar-scrim hidden aria-label="Fermer le menu"></button>
        <aside class="sidebar tenant-rail" id="staff-nav" data-sidebar>
            <div class="tenant-rail-head">
                <a class="tenant-rail-logo" href="<?= Html::url('/espace') ?>" aria-label="SESAM">
                    <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="" width="36" height="36">
                    <span class="tenant-rail-brand">
                        <span class="tenant-rail-text">SESAM</span>
                        <span class="tenant-rail-commerce tenant-rail-text">Espace interne</span>
                    </span>
                </a>
                <button class="tenant-rail-close" type="button" data-sidebar-close aria-label="Fermer le menu">
                    <?php $name = 'close'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                </button>
            </div>
            <nav class="side-nav tenant-rail-nav" aria-label="Espace SESAM">
                <?php foreach ($nav as $item): ?>
                    <?php if (isset($item['group'])): ?>
                        <p class="side-nav-group tenant-rail-text"><?= Html::e($item['group']) ?></p>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a href="<?= Html::url($item['href']) ?>"<?= $current === $item['id'] ? ' aria-current="page"' : '' ?> title="<?= Html::e($item['label']) ?>" data-tone="<?= Html::e($item['tone']) ?>">
                        <?php $name = $item['icon']; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                        <span class="tenant-rail-text"><?= Html::e($item['label']) ?></span>
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
                <button class="sidebar-toggle tenant-menu-toggle" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="staff-nav" aria-label="Ouvrir le menu">
                    <?php $name = 'menu'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                </button>
                <p class="topbar-title"><?= Html::e($navTitle ?? $title ?? 'SESAM') ?></p>
                <div class="tenant-chrome-tools">
                    <a class="tenant-bell" href="<?= Html::url('/notifications') ?>" aria-label="Notifications<?= $notificationUnread > 0 ? ' (' . $notificationUnread . ' non lues)' : '' ?>">
                        <?php $name = 'bell'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                        <span class="tenant-bell-count" data-notification-count<?= $notificationUnread > 0 ? '' : ' hidden' ?>><?= $notificationUnread ?></span>
                    </a>
                    <a class="topbar-profile tenant-who" href="<?= Html::url('/profil') ?>">
                        <span class="tenant-avatar" aria-hidden="true"><?= Html::e(Html::initials($actorName !== '' ? $actorName : 'S')) ?></span>
                        <span class="tenant-who-copy">
                            <strong><?= Html::e($actorName) ?></strong>
                            <span>Personnel SESAM</span>
                        </span>
                    </a>
                </div>
            </header>
            <main class="content tenant-content" data-tone="<?= Html::e($currentTone) ?>">
                <?php if ($status !== ''): ?>
                    <p class="alert" role="status"><?= Html::e($status) ?></p>
                <?php endif; ?>
                <?php if ($resetLink !== ''): ?>
                    <div class="alert alert-strong">
                        <label for="reset-link">Lien unique</label>
                        <input class="input" id="reset-link" type="text" readonly value="<?= Html::url($resetLink) ?>">
                    </div>
                <?php endif; ?>
<?php // $content est le HTML déjà produit par une vue du projet. Chaque vue échappe ses propres données. ?>
                <?= $content ?>
            </main>
        </div>
    </div>
</div>
<script src="<?= Html::asset('assets/js/sesam.js') ?>" defer></script>
<script src="<?= Html::asset('assets/js/boutique-admin.js') ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/push.php'; ?>
</body>
</html>
