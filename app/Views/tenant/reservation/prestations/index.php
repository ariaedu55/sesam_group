<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ReservationPrestation;
use Sesam\Services\Reservation\BookingService;

$items = is_array($items ?? null) ? $items : [];
$canManage = (bool) ($canManage ?? false);
$slug = ($tenant ?? null) instanceof \Sesam\Models\Tenant ? $tenant->slug() : '';
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$groups = [];
foreach ($items as $item) {
    if ($item instanceof ReservationPrestation) {
        $groups[$item->category() !== '' ? $item->category() : 'Autres prestations'][] = $item;
    }
}

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Prestations</h1>
            <p class="muted">Ce que vos clientes peuvent réserver en ligne, avec la durée et le prix.</p>
        </div>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/reservation/prestations/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouvelle prestation</a>
        <?php endif; ?>
    </header>

    <?php if ($groups === []): ?>
        <div class="empty btq-card">
            <p>Aucune prestation pour l'instant. Commencez par vos prestations les plus demandées : coupe, tresses, soin du visage…</p>
            <?php if ($canManage): ?><p><a class="btn btn-primary" href="<?= Html::url('/reservation/prestations/nouveau') ?>">Ajouter ma première prestation</a></p><?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($groups as $category => $list): ?>
            <section class="btq-card">
                <h2><?= Html::e((string) $category) ?></h2>
                <ul class="btq-list">
                    <?php foreach ($list as $item): ?>
                        <li>
                            <div class="presta-row"<?= $item->isActive() ? '' : ' style="opacity: .55"' ?>>
                                <?php if ($item->imagePath() !== null && $slug !== ''): ?>
                                    <img class="presta-thumb" src="<?= Html::url('/t/' . $slug . '/prestation/' . $item->id() . '/image') ?>?v=<?= Html::e($item->imageVersion()) ?>" alt="" width="56" height="56" loading="lazy">
                                <?php else: ?>
                                    <span class="presta-thumb is-empty" aria-hidden="true"><?= Html::e(Html::initials($item->name())) ?></span>
                                <?php endif; ?>
                                <span class="presta-text">
                                    <strong><?= Html::e($item->name()) ?><?= $item->isActive() ? '' : ' (retirée)' ?></strong>
                                    <small><?= Html::e($item->durationLabel()) ?><?= $item->depositMinor() >= 100 ? ' · acompte ' . Html::e(BookingService::money($item->depositMinor())) : '' ?><?= $item->description() !== '' ? ' · ' . Html::e(mb_strimwidth($item->description(), 0, 70, '…')) : '' ?></small>
                                </span>
                                <span class="btq-list-end">
                                    <strong><?= Html::e(BookingService::money($item->priceMinor())) ?></strong>
                                    <?php if ($canManage): ?>
                                        <a class="btn btn-secondary btn-sm" href="<?= Html::url('/reservation/prestations/' . $item->id() . '/modifier') ?>">Modifier</a>
                                        <form class="btq-inline" method="post" action="<?= Html::url('/reservation/prestations/' . $item->id() . '/activation') ?>">
                                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                            <button class="btn btn-ghost btn-sm" type="submit"><?= $item->isActive() ? 'Retirer' : 'Proposer' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
