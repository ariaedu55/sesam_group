<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $service */
/** @var array<string, mixed> $scene */
?>
<div class="scene scene-pharma" data-scene="garde">
    <span class="pharma-cross" aria-hidden="true"></span>
    <div class="pharma-cal">
        <p class="scene-head">
            <span>Calendrier de garde</span>
            <em>Aperçu illustratif</em>
        </p>
        <ol class="cal-head" aria-hidden="true">
            <?php foreach ($scene['day_labels'] as $label): ?>
                <li><?= Html::e($label) ?></li>
            <?php endforeach; ?>
        </ol>
        <ol class="cal-grid">
            <?php for ($d = 1; $d <= (int) $scene['days']; $d++): ?>
                <?php $onDuty = $d >= (int) $scene['garde_from'] && $d <= (int) $scene['garde_to']; ?>
                <li class="<?= $onDuty ? 'is-garde' : '' ?>" style="--i: <?= $d ?>">
                    <?= $d ?>
                    <?php if ($d === (int) $scene['garde_from']): ?>
                        <span class="sr-only">Début de la semaine de garde</span>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>
        </ol>
        <p class="cal-legend"><span class="cal-swatch" aria-hidden="true"></span> Semaine de garde de la pharmacie</p>
    </div>

    <div class="pharma-stock">
        <p class="scene-head">
            <span>Stock et péremption</span>
        </p>
        <ol class="stock-list" data-path>
            <?php foreach ($service['alerts'] as [$state, $hint, $kind]): ?>
                <li class="stock-row is-<?= Html::e($kind) ?>">
                    <span class="stock-box" aria-hidden="true"></span>
                    <span class="stock-text">
                        <strong><?= Html::e($state) ?></strong>
                        <span><?= Html::e($hint) ?></span>
                    </span>
                    <span class="stock-badge"><?= Html::e($state) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</div>
