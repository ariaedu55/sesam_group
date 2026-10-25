<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $service */
/** @var array<string, mixed> $scene */
/** @var array<int, array<string, string>> $offers */
?>
<div class="scene scene-agency" data-scene="agence">
    <p class="scene-head">
        <span>Deux chemins</span>
        <em>Aperçu illustratif</em>
    </p>
    <?php if (!empty($scene['paths'])): ?>
        <ul class="path-pair" data-path>
            <?php foreach ($scene['paths'] as $path): ?>
                <li>
                    <strong><?= Html::e($path[0]) ?></strong>
                    <span><?= Html::e($path[1]) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <ol class="offer-stack" data-path data-offer-tour>
        <?php foreach ($offers as $offerIndex => $offer): ?>
            <li class="offer-tile<?= $offerIndex === 1 ? ' is-lit' : '' ?>">
                <p class="offer-tile-name"><?= Html::e($offer['name']) ?></p>
                <p class="offer-tile-price"><?= Html::e($offer['price']) ?></p>
                <p class="offer-tile-text"><?= Html::e($offer['description']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
    <div class="capacity">
        <p class="capacity-label">
            <strong><?= Html::e((string) $scene['capacity']) ?></strong>
            <span><?= Html::e($scene['capacity_label']) ?></span>
        </p>
        <ol class="capacity-slots" aria-hidden="true">
            <?php for ($i = 0; $i < (int) $scene['capacity']; $i++): ?>
                <li style="--i: <?= $i ?>"></li>
            <?php endfor; ?>
        </ol>
    </div>
</div>
