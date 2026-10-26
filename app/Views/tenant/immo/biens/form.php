<?php

declare(strict_types=1);

use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;
use Sesam\Models\Tenant;

$id = isset($id) && is_int($id) ? $id : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$slug = $tenant?->slug() ?? '';
$hasPhoto = is_string($old['image_path'] ?? null) && $old['image_path'] !== '';
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$val = static fn (string $key): string => Html::e(is_scalar($old[$key] ?? null) ? (string) $old[$key] : '');
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1><?= $id === null ? 'Nouveau bien' : 'Modifier le bien' ?></h1>
            <p class="muted">Un bien regroupe un ou plusieurs logements (ex. un immeuble de 6 appartements).</p>
        </div>
    </header>
    <form class="btq-form" method="post" action="<?= Html::url('/immo/biens' . ($id !== null ? '/' . $id : '')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <fieldset>
            <legend>Photo du bien</legend>
            <div class="btq-upload">
                <?php if ($hasPhoto && $id !== null && $slug !== ''): ?>
                    <img class="btq-upload-preview is-wide" id="property-preview" src="<?= Html::url('/t/' . $slug . '/bien/' . $id . '/image') ?>?v=<?= Html::e(rawurlencode((string) ($old['updated_at'] ?? ''))) ?>" alt="">
                <?php else: ?>
                    <span class="btq-upload-preview is-wide" id="property-preview"><?php $name = 'image'; include $icon; ?></span>
                <?php endif; ?>
                <div class="field">
                    <label for="image"><span>Ajouter une photo</span></label>
                    <input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="property-preview">
                    <p class="btq-help">JPEG, PNG, WebP ou GIF, 4 Mo maximum. Une photo de façade ou d’entrée suffit : elle s’affiche sur la page publique.</p>
                    <?php $err('image'); ?>
                </div>
            </div>
            <?php if ($hasPhoto): ?>
                <label class="btq-switch"><input type="checkbox" name="remove_image" value="1"><span>Retirer la photo actuelle</span></label>
            <?php endif; ?>
        </fieldset>
        <fieldset>
            <legend>Le bien</legend>
            <div class="btq-grid-2">
                <div class="field"><label for="name"><span>Nom</span></label><input class="input<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" required maxlength="120" placeholder="Immeuble Liberté 6" value="<?= $val('name') ?>"><?php $err('name'); ?></div>
                <div class="field"><label for="kind"><span>Type</span></label><select class="input" id="kind" name="kind"><?php foreach (ImmoCatalog::PROPERTY_KINDS as $key => $label): ?><option value="<?= Html::e($key) ?>"<?= ($old['kind'] ?? 'immeuble') === $key ? ' selected' : '' ?>><?= Html::e($label) ?></option><?php endforeach; ?></select><?php $err('kind'); ?></div>
                <div class="field"><label for="address"><span>Adresse</span></label><input class="input" id="address" name="address" maxlength="255" value="<?= $val('address') ?>"></div>
                <div class="field"><label for="city"><span>Ville / quartier</span></label><input class="input" id="city" name="city" maxlength="80" placeholder="Dakar" value="<?= $val('city') ?>"></div>
            </div>
        </fieldset>
        <fieldset>
            <legend>Propriétaire (si vous gérez pour quelqu'un)</legend>
            <div class="btq-grid-2">
                <div class="field"><label for="owner_name"><span>Nom du propriétaire</span></label><input class="input" id="owner_name" name="owner_name" maxlength="120" value="<?= $val('owner_name') ?>"></div>
                <div class="field"><label for="owner_phone"><span>Téléphone</span></label><input class="input" id="owner_phone" name="owner_phone" type="tel" maxlength="40" value="<?= $val('owner_phone') ?>"></div>
            </div>
            <div class="field"><label for="notes"><span>Notes</span></label><textarea class="input" id="notes" name="notes" rows="3" maxlength="1000"><?= $val('notes') ?></textarea></div>
        </fieldset>
        <div class="btq-sticky-save">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-ghost" href="<?= Html::url($id !== null ? '/immo/biens/' . $id : '/immo/biens') ?>">Annuler</a>
        </div>
    </form>
</section>
