<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $service */
/** @var array<string, mixed> $scene */
/** @var array<string, mixed> $common */
?>
<div class="scene scene-ticket" data-scene="commande">
    <span class="ticket-notch ticket-notch--l" aria-hidden="true"></span>
    <span class="ticket-notch ticket-notch--r" aria-hidden="true"></span>
    <p class="scene-head">
        <span>Bon de commande de l'atelier</span>
        <em>Aperçu illustratif</em>
    </p>

    <p class="ticket-product">
        <span>Produit</span>
        <strong><?= Html::e($scene['product']) ?></strong>
    </p>

    <dl class="measures">
        <?php foreach ($scene['measures'] as [$label, $pct]): ?>
            <div class="measure" style="--w: <?= (int) $pct ?>%">
                <dt><?= Html::e($label) ?></dt>
                <dd><span class="measure-bar" aria-hidden="true"></span></dd>
            </div>
        <?php endforeach; ?>
    </dl>

    <ol class="stepper" data-path>
        <?php foreach ($service['public'] as $i => $step): ?>
            <li class="<?= $i < $scene['step_on'] ? 'is-done' : ($i === $scene['step_on'] ? 'is-now' : '') ?>">
                <span class="stepper-dot" aria-hidden="true"></span>
                <span><?= Html::e($step) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="pay-row">
        <?php foreach ($common['pay_modes'] as $i => $mode): ?>
            <span class="pay-mode<?= $i === 0 ? ' is-on' : '' ?>"><?= Html::e($mode) ?></span>
        <?php endforeach; ?>
        <span class="pay-sep" aria-hidden="true"></span>
        <?php foreach ($common['wallets'] as $wallet): ?>
            <span class="wallet"><?= Html::e($wallet) ?></span>
        <?php endforeach; ?>
    </div>
</div>
