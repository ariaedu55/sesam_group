<?php

declare(strict_types=1);

use Sesam\Core\Auth\Permission;
use Sesam\Helpers\Html;
use Sesam\Models\User;

$current = is_string($current ?? null) ? $current : '';
$actor = ($actor ?? null) instanceof User ? $actor : null;
$canSend = $actor !== null && $actor->can(Permission::NOTIFICATIONS_SEND);
$canHistory = $actor !== null && $actor->can(Permission::NOTIFICATIONS_HISTORY);

?>
<nav class="row notification-subnav" aria-label="Notifications">
    <a class="btn btn-ghost<?= $current === 'notifications' ? ' is-active' : '' ?>" href="<?= Html::url('/notifications') ?>">Boîte de réception</a>
    <?php if ($canHistory): ?>
        <a class="btn btn-ghost<?= $current === 'notifications-history' ? ' is-active' : '' ?>" href="<?= Html::url('/notifications/historique') ?>">Historique des envois</a>
    <?php endif; ?>
    <?php if ($canSend): ?>
        <a class="btn btn-ghost<?= $current === 'notifications-send' ? ' is-active' : '' ?>" href="<?= Html::url('/notifications/envoyer') ?>" data-modal="Notifications">Envoyer</a>
    <?php endif; ?>
</nav>
