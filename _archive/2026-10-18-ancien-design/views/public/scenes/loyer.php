<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $service */
/** @var array<string, mixed> $scene */
/** @var array<string, mixed> $common */
?>
<div class="scene scene-ledger" data-scene="loyer">
    <p class="scene-head">
        <span>Échéancier du bien</span>
        <em>Aperçu illustratif</em>
    </p>

    <div class="agenda-row" role="group" aria-label="Bien">
        <?php foreach ($scene['assets'] as $i => $label): ?>
            <span class="chip<?= $i === $scene['asset_on'] ? ' is-on' : '' ?>"><?= Html::e($label) ?></span>
        <?php endforeach; ?>
    </div>

    <ol class="ledger" data-path>
        <?php foreach ($scene['dues'] as [$label, $state, $kind]): ?>
            <li class="ledger-row is-<?= Html::e($kind) ?>">
                <span class="ledger-dot" aria-hidden="true"></span>
                <span class="ledger-label"><?= Html::e($label) ?></span>
                <span class="ledger-state"><?= Html::e($state) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="pay-row">
        <?php foreach ($common['wallets'] as $wallet): ?>
            <span class="wallet"><?= Html::e($wallet) ?></span>
        <?php endforeach; ?>
        <span class="pay-sep" aria-hidden="true"></span>
        <span class="pay-mode is-on">Rappel WhatsApp avant chaque échéance</span>
    </div>
</div>
