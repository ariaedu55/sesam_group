<?php

declare(strict_types=1);

use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$unit = is_array($unit ?? null) ? $unit : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$val = static fn (string $key): string => Html::e(is_scalar($old[$key] ?? null) ? (string) $old[$key] : '');
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};

?>
<section class="btq immo">
    <p><a class="muted" href="<?= Html::url('/immo/biens/' . (int) ($unit['property_id'] ?? 0)) ?>">← <?= Html::e((string) ($unit['property_name'] ?? 'Bien')) ?></a></p>
    <header class="btq-head"><div><h1>Modifier « <?= Html::e((string) ($unit['label'] ?? '')) ?> »</h1><p class="muted">Le loyer prévu sert de base aux nouveaux baux ; un bail en cours garde son propre montant.</p></div></header>
    <form class="btq-form" method="post" action="<?= Html::url('/immo/logements/' . (int) ($unit['id'] ?? 0)) ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <fieldset>
            <legend>Logement</legend>
            <div class="btq-grid-2">
                <div class="field"><label for="label"><span>Nom</span></label><input class="input<?= isset($errors['label']) ? ' is-invalid' : '' ?>" id="label" name="label" maxlength="80" value="<?= $val('label') ?>"><?php $err('label'); ?></div>
                <div class="field"><label for="kind"><span>Type</span></label><select class="input" id="kind" name="kind"><?php foreach (ImmoCatalog::UNIT_KINDS as $key => $label): ?><option value="<?= Html::e($key) ?>"<?= ($old['kind'] ?? '') === $key ? ' selected' : '' ?>><?= Html::e($label) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="rooms"><span>Pièces</span></label><input class="input" id="rooms" name="rooms" inputmode="numeric" value="<?= $val('rooms') ?>"></div>
                <div class="field"><label for="surface_m2"><span>Surface (m²)</span></label><input class="input" id="surface_m2" name="surface_m2" inputmode="numeric" value="<?= $val('surface_m2') ?>"></div>
                <div class="field"><label for="floor_label"><span>Étage</span></label><input class="input" id="floor_label" name="floor_label" maxlength="30" value="<?= $val('floor_label') ?>"></div>
                <div class="field"><label for="default_rent_minor"><span>Loyer prévu (FCFA)</span></label><input class="input<?= isset($errors['default_rent_minor']) ? ' is-invalid' : '' ?>" id="default_rent_minor" name="default_rent_minor" inputmode="numeric" value="<?= $val('default_rent_minor') ?>"><?php $err('default_rent_minor'); ?></div>
                <div class="field"><label for="default_charges_minor"><span>Charges (FCFA)</span></label><input class="input" id="default_charges_minor" name="default_charges_minor" inputmode="numeric" value="<?= $val('default_charges_minor') ?>"></div>
                <div class="field"><label for="default_deposit_minor"><span>Caution (FCFA)</span></label><input class="input" id="default_deposit_minor" name="default_deposit_minor" inputmode="numeric" value="<?= $val('default_deposit_minor') ?>"></div>
            </div>
            <div class="field"><label for="description"><span>Description</span></label><input class="input" id="description" name="description" maxlength="500" value="<?= $val('description') ?>"></div>
            <label class="btq-switch"><input type="checkbox" name="is_active" value="1"<?= (string) ($old['is_active'] ?? '1') === '1' ? ' checked' : '' ?>><span><strong>Proposé à la location</strong><span class="btq-help">Décochez pour un logement en travaux ou retiré.</span></span></label>
        </fieldset>
        <div class="btq-sticky-save"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
    </form>
</section>
