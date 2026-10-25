<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\GardeStaff;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$staff = is_array($staff ?? null) ? $staff : [];

?>
<section class="garde-page">
    <header class="garde-hero garde-hero-compact">
        <div>
            <p class="eyebrow">Calendrier</p>
            <h1>Planifier une garde</h1>
            <p>Une personne, un jour. Plusieurs collègues peuvent se relayer le même soir.</p>
        </div>
    </header>
    <form class="stack card garde-form" method="post" action="<?= Html::url('/garde/calendrier') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="staff_id"><span>Personnel</span></label>
            <select class="input" id="staff_id" name="staff_id" required>
                <option value="">Choisir</option>
                <?php foreach ($staff as $member): ?>
                    <?php if (!$member instanceof GardeStaff) { continue; } ?>
                    <option value="<?= Html::e((string) $member->id()) ?>"<?= (string) ($old['staff_id'] ?? '') === (string) $member->id() ? ' selected' : '' ?>>
                        <?= Html::e($member->name()) ?><?php if ($member->roleLabel() !== ''): ?> — <?= Html::e($member->roleLabel()) ?><?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['staff_id'])): ?><p class="field-error"><?= Html::e($errors['staff_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="shift_date"><span>Jour</span></label>
            <input class="input" id="shift_date" name="shift_date" type="date" required value="<?= Html::e((string) ($old['shift_date'] ?? '')) ?>">
            <?php if (isset($errors['shift_date'])): ?><p class="field-error"><?= Html::e($errors['shift_date']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="note"><span>Note (optionnel)</span></label>
            <input class="input" id="note" name="note" maxlength="180" value="<?= Html::e((string) ($old['note'] ?? '')) ?>">
            <?php if (isset($errors['note'])): ?><p class="field-error"><?= Html::e($errors['note']) ?></p><?php endif; ?>
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/garde/calendrier') ?>">Annuler</a>
        </p>
    </form>
</section>
