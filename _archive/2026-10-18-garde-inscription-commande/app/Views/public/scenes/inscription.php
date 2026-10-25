<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $service */
/** @var array<string, mixed> $scene */
/** @var array<string, mixed> $common */

$done = 0;
foreach ($scene['tranches'] as [$label, $state, $kind]) {
    if ($kind === 'done') {
        $done++;
    }
}
$total = count($scene['tranches']);
?>
<div class="scene scene-file" data-scene="inscription">
    <p class="folder-tab">Dossier école</p>
    <p class="scene-head">
        <span>Dossier de scolarité</span>
        <em>Aperçu illustratif</em>
    </p>

    <p class="file-head">
        <strong><?= Html::e($scene['file'][0]) ?></strong>
        <span><?= Html::e($scene['file'][1]) ?></span>
    </p>

    <p class="file-progress-label">
        <span>Frais de scolarité</span>
        <strong><?= $done ?> / <?= $total ?> tranches réglées</strong>
    </p>
    <ol class="file-progress" aria-hidden="true">
        <?php foreach ($scene['tranches'] as [$label, $state, $kind]): ?>
            <li class="is-<?= Html::e($kind) ?>"></li>
        <?php endforeach; ?>
    </ol>

    <ol class="tranche-list" data-path>
        <?php foreach ($scene['tranches'] as [$label, $state, $kind]): ?>
            <li class="is-<?= Html::e($kind) ?>">
                <span class="tranche-name"><?= Html::e($label) ?></span>
                <span class="tranche-state"><?= Html::e($state) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="pay-row">
        <?php foreach ($common['wallets'] as $wallet): ?>
            <span class="wallet"><?= Html::e($wallet) ?></span>
        <?php endforeach; ?>
        <span class="pay-sep" aria-hidden="true"></span>
        <span class="pay-mode is-on">Rappel WhatsApp avant la tranche suivante</span>
    </div>
</div>
