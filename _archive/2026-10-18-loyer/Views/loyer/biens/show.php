<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\LoyerProperty;
use Sesam\Models\LoyerUnit;

$property = ($property ?? null) instanceof LoyerProperty ? $property : null;
$units = is_array($units ?? null) ? $units : [];
$canManage = (bool) ($canManage ?? false);

if ($property === null) {
    return;
}

?>
<section>
    <h1><?= Html::e($property->name()) ?></h1>
    <?php if ($property->address() !== ''): ?><p class="muted"><?= Html::e($property->address()) ?></p><?php endif; ?>
    <h2>Unités</h2>
    <?php if ($units === []): ?>
        <p class="muted">Aucune unité.</p>
    <?php else: ?>
        <ul class="crm-list">
            <?php foreach ($units as $unit): ?>
                <?php if (!$unit instanceof LoyerUnit) { continue; } ?>
                <li><?= Html::e($unit->label()) ?> — <?= Html::e(number_format($unit->defaultRentMinor(), 0, ',', ' ')) ?> FCFA</li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($canManage): ?>
        <form class="stack card" data-modal-form="Ajouter une unité" data-modal-title="Ajouter une unité" data-modal-kicker="Loyer" method="post" action="<?= Html::url('/loyer/biens/' . $property->id() . '/unites') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <h3>Ajouter une unité</h3>
            <div class="field">
                <label for="label"><span>Libellé</span></label>
                <input class="input" id="label" name="label" required maxlength="80" placeholder="Appt 2A">
            </div>
            <div class="field">
                <label for="default_rent_minor"><span>Loyer de référence (FCFA)</span></label>
                <input class="input" id="default_rent_minor" name="default_rent_minor" type="number" min="0" value="0">
            </div>
            <button class="btn btn-secondary" type="submit">Ajouter</button>
        </form>
    <?php endif; ?>
    <p><a href="<?= Html::url('/loyer/biens') ?>">← Biens</a></p>
</section>
