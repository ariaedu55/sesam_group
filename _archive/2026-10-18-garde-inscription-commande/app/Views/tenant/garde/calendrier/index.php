<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\GardeShift;

$calendar = is_array($calendar ?? null) ? $calendar : [];
$canManage = (bool) ($canManage ?? false);
$cells = is_array($calendar['cells'] ?? null) ? $calendar['cells'] : [];
$weekdays = is_array($calendar['weekdays'] ?? null) ? $calendar['weekdays'] : [];
$month = (string) ($calendar['month'] ?? gmdate('Y-m'));

?>
<section class="garde-page">
    <header class="garde-hero">
        <div>
            <p class="eyebrow">Pharmacie de garde</p>
            <h1><?= Html::e((string) ($calendar['title'] ?? 'Calendrier')) ?></h1>
            <p>Qui tient l’officine, jour par jour. Touchez une date pour planifier.</p>
        </div>
        <div class="garde-hero-actions">
            <a class="btn btn-secondary" href="<?= Html::url('/garde/calendrier?mois=' . rawurlencode((string) ($calendar['prev'] ?? ''))) ?>">Mois précédent</a>
            <a class="btn btn-secondary" href="<?= Html::url('/garde/calendrier?mois=' . rawurlencode((string) ($calendar['next'] ?? ''))) ?>">Mois suivant</a>
            <?php if ($canManage): ?>
                <a class="btn btn-primary" href="<?= Html::url('/garde/calendrier/nouveau?mois=' . rawurlencode($month)) ?>" data-modal="Garde">Planifier</a>
            <?php endif; ?>
        </div>
    </header>
    <div class="garde-cal" aria-label="<?= Html::e((string) ($calendar['title'] ?? '')) ?>">
        <?php foreach ($weekdays as $day): ?>
            <span class="garde-cal-wd"><?= Html::e((string) $day) ?></span>
        <?php endforeach; ?>
        <?php foreach ($cells as $cell): ?>
            <?php
            if (!is_array($cell)) {
                continue;
            }
            $date = (string) ($cell['date'] ?? '');
            $shifts = is_array($cell['shifts'] ?? null) ? $cell['shifts'] : [];
            $classes = ['garde-cal-day'];
            if ($date === '') {
                $classes[] = 'is-pad';
            }
            if (!empty($cell['today'])) {
                $classes[] = 'is-today';
            }
            if ($shifts !== []) {
                $classes[] = 'is-duty';
            }
            ?>
            <article class="<?= Html::e(implode(' ', $classes)) ?>">
                <?php if ($date !== ''): ?>
                    <header>
                        <span><?= Html::e((string) ($cell['label'] ?? '')) ?></span>
                        <?php if ($canManage): ?>
                            <a href="<?= Html::url('/garde/calendrier/nouveau?date=' . rawurlencode($date) . '&mois=' . rawurlencode($month)) ?>" data-modal="Garde" aria-label="Planifier une garde le <?= Html::e($date) ?>">+</a>
                        <?php endif; ?>
                    </header>
                    <ul>
                        <?php foreach ($shifts as $shift): ?>
                            <?php if (!$shift instanceof GardeShift) { continue; } ?>
                            <li>
                                <strong><?= Html::e($shift->staffName()) ?></strong>
                                <?php if ($canManage): ?>
                                    <form method="post" action="<?= Html::url('/garde/calendrier/' . $shift->id() . '/retirer') ?>">
                                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                        <input type="hidden" name="mois" value="<?= Html::e($month) ?>">
                                        <button type="submit" aria-label="Retirer <?= Html::e($shift->staffName()) ?>">×</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
