<?php

declare(strict_types=1);

use Sesam\Core\Push\NotificationCatalog;
use Sesam\Helpers\Html;
use Sesam\Models\Notification;
use Sesam\Models\User;

$items = is_array($items ?? null) ? $items : [];
$actor = ($actor ?? null) instanceof User ? $actor : null;
$isTenant = $actor?->tenantId() !== null;
$unread = count(array_filter($items, static fn ($item): bool => $item instanceof Notification && !$item->isRead()));
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$when = static function (string $value): string {
    $time = strtotime($value . ' UTC');
    if ($time === false) {
        return $value;
    }
    $diff = time() - $time;

    return match (true) {
        $diff < 60 => 'à l\'instant',
        $diff < 3600 => 'il y a ' . intdiv($diff, 60) . ' min',
        $diff < 86400 => 'il y a ' . intdiv($diff, 3600) . ' h',
        $diff < 172800 => 'hier',
        default => gmdate('d/m/Y', $time),
    };
};

?>
<section class="btq notif-page">
    <header class="btq-head">
        <div>
            <h1>Notifications</h1>
            <p class="muted"><?= $unread > 0 ? $unread . ' non lue(s).' : 'Tout est lu.' ?> Elles arrivent aussi sur vos appareils une fois les notifications activées.</p>
        </div>
        <div class="btq-actions">
            <?php if ($unread > 0): ?>
                <form method="post" action="<?= Html::url('/notifications/tout-lu') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <button class="btn btn-secondary btn-sm" type="submit">Tout marquer comme lu</button>
                </form>
            <?php endif; ?>
            <a class="btn btn-primary btn-sm" href="<?= Html::url('/notifications/reglages') ?>"><?php $name = 'gear'; include $icon; ?>Réglages et appareils</a>
        </div>
    </header>

    <?php if ($isTenant): ?>
        <?php require __DIR__ . '/_nav.php'; ?>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="btq-card empty">
            <p>Aucune notification pour le moment.</p>
            <p class="muted">Nouvelles commandes, réservations, loyers, paiements, rappels : tout ce qui demande votre attention arrivera ici.</p>
        </div>
    <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($items as $item): ?>
                <?php if (!$item instanceof Notification) { continue; } ?>
                <li class="notif-item is-<?= Html::e($item->level()) ?><?= $item->isRead() ? '' : ' is-unread' ?>">
                    <span class="notif-dot" aria-hidden="true"></span>
                    <div class="notif-body">
                        <p class="notif-meta"><?= Html::e(NotificationCatalog::CATEGORIES[$item->category()]['label'] ?? 'Notification') ?> · <?= Html::e($when($item->createdAt())) ?></p>
                        <h2><?= Html::e($item->title()) ?></h2>
                        <p><?= Html::e($item->body()) ?></p>
                    </div>
                    <form class="notif-actions" method="post" action="<?= Html::url('/notifications/' . $item->id() . '/lu') ?>">
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <?php if ($item->link() !== null): ?>
                            <button class="btn btn-primary btn-sm" type="submit" name="ouvrir" value="1">Voir</button>
                        <?php endif; ?>
                        <?php if (!$item->isRead()): ?>
                            <button class="btn btn-ghost btn-sm" type="submit">Marquer lue</button>
                        <?php endif; ?>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
