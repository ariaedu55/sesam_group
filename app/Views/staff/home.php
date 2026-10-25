<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;
use Sesam\Models\User;

$actor = $actor ?? null;
$name = $actor instanceof User ? $actor->fullName() : '';
$roles = $actor instanceof User ? $actor->roles() : [];
$canManageUsers = (bool) ($canManageUsers ?? false);
$canManageTenants = (bool) ($canManageTenants ?? false);
$canUseAgency = (bool) ($canUseAgency ?? false);
$canViewSettings = (bool) ($canViewSettings ?? false);
$tenant = $tenant instanceof Tenant ? $tenant : null;

?>
<section class="staff-home">
    <?php if ($tenant !== null): ?>
        <p class="eyebrow"><?= Html::e($tenant->displayName()) ?></p>
        <h1>Bonjour <?= Html::e($name) ?></h1>
        <p class="muted">Vous êtes dans l'espace de <?= Html::e($tenant->displayName()) ?> (<?= Html::e($tenant->sectorLabel()) ?>). Le tableau de bord des modules viendra plus tard.</p>
    <?php else: ?>
        <p class="eyebrow">Personnel SESAM</p>
        <h1>Bonjour <?= Html::e($name) ?></h1>
        <p class="muted">Vous êtes connecté à l'espace interne.
            <?php if ($canManageTenants): ?>La console des commerces regroupe les comptes, leurs accès et leurs abonnements.<?php endif; ?>
            <?php if ($canUseAgency): ?>L'agence suit les prospects, les devis, les projets web et leurs factures.<?php endif; ?>
            <?php if (!$canManageTenants && !$canUseAgency): ?>Votre rôle n'a pas encore d'écran dédié.<?php endif; ?>
        </p>
    <?php endif; ?>
    <?php if ($roles !== []): ?>
        <p>
            <?php foreach ($roles as $role): ?>
                <span class="badge"><?= Html::e(str_replace('-', ' ', $role)) ?></span>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <p class="row">
        <?php if ($canManageUsers): ?>
            <a class="btn btn-primary" href="<?= Html::url('/utilisateurs') ?>">Gérer les utilisateurs</a>
        <?php endif; ?>
        <?php if ($canManageTenants): ?>
            <a class="btn <?= $canManageUsers ? 'btn-secondary' : 'btn-primary' ?>" href="<?= Html::url('/commerces') ?>">Gérer les commerces</a>
        <?php endif; ?>
        <?php if ($canUseAgency): ?>
            <a class="btn <?= $canManageUsers || $canManageTenants ? 'btn-secondary' : 'btn-primary' ?>" href="<?= Html::url('/agence') ?>">Ouvrir l'agence</a>
        <?php endif; ?>
        <?php if ($canViewSettings): ?>
            <a class="btn <?= $canManageUsers ? 'btn-secondary' : 'btn-primary' ?>" href="<?= Html::url('/parametres') ?>">Paramètres</a>
        <?php endif; ?>
    </p>
</section>
