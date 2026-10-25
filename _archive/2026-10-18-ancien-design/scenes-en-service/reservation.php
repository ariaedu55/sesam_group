<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $service */
/** @var array<string, mixed> $scene */
/** @var array<string, mixed> $common */
?>
<div class="scene scene-agenda" data-scene="reservation" data-agenda>
    <p class="scene-head">
        <span><?= Html::e($scene['link_label']) ?></span>
        <em>Aperçu illustratif</em>
    </p>
    <p class="link-chip">
        <span class="link-dot" aria-hidden="true"></span>
        <?= Html::e($scene['link_hint']) ?>
    </p>

    <div class="agenda-row" role="group" aria-label="Prestation">
        <?php foreach ($scene['services'] as $i => $label): ?>
            <button type="button" class="chip<?= $i === $scene['service_on'] ? ' is-on' : '' ?>" data-agenda-service aria-pressed="<?= $i === $scene['service_on'] ? 'true' : 'false' ?>"><?= Html::e($label) ?></button>
        <?php endforeach; ?>
    </div>

    <ol class="agenda-days" role="group" aria-label="Jour" data-path>
        <?php foreach ($scene['days'] as $i => $day): ?>
            <li>
                <button type="button" class="day<?= $i === $scene['day_on'] ? ' is-on' : '' ?>" data-agenda-day aria-pressed="<?= $i === $scene['day_on'] ? 'true' : 'false' ?>"><?= Html::e($day) ?></button>
            </li>
        <?php endforeach; ?>
    </ol>

    <ol class="agenda-slots" role="group" aria-label="Créneau" data-path>
        <?php foreach ($scene['slots'] as $i => [$time, $state]): ?>
            <li>
                <?php if ($state === 'réservé'): ?>
                    <span class="slot is-taken" aria-disabled="true"><?= Html::e($time) ?><small>réservé</small></span>
                <?php else: ?>
                    <button type="button" class="slot<?= $i === $scene['slot_on'] ? ' is-on' : '' ?>" data-agenda-slot aria-pressed="<?= $i === $scene['slot_on'] ? 'true' : 'false' ?>"><?= Html::e($time) ?><small>libre</small></button>
                <?php endif; ?>
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

    <p class="wa-bubble">
        <strong>WhatsApp · <span data-agenda-pick><?= Html::e($scene['slots'][$scene['slot_on']][0]) ?></span></strong>
        <span><?= Html::e($scene['bubble']) ?></span>
    </p>
</div>
