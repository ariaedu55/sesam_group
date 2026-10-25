<?php

declare(strict_types=1);

use Sesam\Core\Garde\ExpiryStatus;
use Sesam\Core\Garde\MovementType;
use Sesam\Helpers\Html;
use Sesam\Models\GardeBatch;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$batches = is_array($batches ?? null) ? $batches : [];

?>
<section class="garde-page">
    <header class="garde-hero garde-hero-compact">
        <div>
            <p class="eyebrow">Stock</p>
            <h1>Mouvement</h1>
            <p>Sortie pour une vente ou une destruction, entrée pour un réassort, ajustement pour un inventaire.</p>
        </div>
    </header>
    <form class="stack card garde-form" method="post" action="<?= Html::url('/garde/mouvements') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="batch_id"><span>Lot</span></label>
            <select class="input" id="batch_id" name="batch_id" required>
                <option value="">Choisir</option>
                <?php foreach ($batches as $batch): ?>
                    <?php if (!$batch instanceof GardeBatch) { continue; } ?>
                    <option value="<?= Html::e((string) $batch->id()) ?>"<?= (string) ($old['batch_id'] ?? '') === (string) $batch->id() ? ' selected' : '' ?>>
                        <?= Html::e($batch->productName()) ?> — <?= Html::e($batch->lotCode()) ?>
                        (<?= Html::e((string) $batch->quantity()) ?>, <?= Html::e(ExpiryStatus::label($batch->expiryStatus())) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['batch_id'])): ?><p class="field-error"><?= Html::e($errors['batch_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="type"><span>Type</span></label>
            <select class="input" id="type" name="type" required>
                <?php foreach (MovementType::LABELS as $value => $label): ?>
                    <option value="<?= Html::e($value) ?>"<?= (string) ($old['type'] ?? '') === $value ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['type'])): ?><p class="field-error"><?= Html::e($errors['type']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="quantity"><span>Quantité</span></label>
            <input class="input" id="quantity" name="quantity" type="number" min="0" max="100000" required value="<?= Html::e((string) ($old['quantity'] ?? '')) ?>">
            <?php if (isset($errors['quantity'])): ?><p class="field-error"><?= Html::e($errors['quantity']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="reason"><span>Motif</span></label>
            <input class="input" id="reason" name="reason" maxlength="180" value="<?= Html::e((string) ($old['reason'] ?? '')) ?>">
            <?php if (isset($errors['reason'])): ?><p class="field-error"><?= Html::e($errors['reason']) ?></p><?php endif; ?>
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/garde/mouvements') ?>">Annuler</a>
        </p>
    </form>
</section>
