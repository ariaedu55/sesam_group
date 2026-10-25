<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ReservationPrestation;

$prestation = ($prestation ?? null) instanceof ReservationPrestation ? $prestation : null;
$categories = is_array($categories ?? null) ? $categories : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$val = static fn (string $key): string => is_scalar($old[$key] ?? null) ? (string) $old[$key] : '';
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$action = $prestation === null ? '/reservation/prestations' : '/reservation/prestations/' . $prestation->id();
$durations = [15, 30, 45, 60, 75, 90, 120, 150, 180, 240];
$tenantSlug = ($tenant ?? null) instanceof \Sesam\Models\Tenant ? $tenant->slug() : '';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1><?= $prestation === null ? 'Nouvelle prestation' : 'Modifier la prestation' ?></h1>
            <p class="muted">La durée sert à proposer les bons créneaux à vos clientes.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/reservation/prestations') ?>">Retour</a>
    </header>
    <?php if ($errors !== []): ?>
        <p class="alert alert-danger" role="alert">Vérifiez les champs en rouge.</p>
    <?php endif; ?>
    <form class="btq-form" method="post" action="<?= Html::url($action) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <fieldset>
            <legend>La prestation</legend>
            <div class="field">
                <label for="name"><span>Nom</span></label>
                <input class="input" id="name" name="name" required maxlength="120" placeholder="Ex. : Tresses africaines" value="<?= Html::e($val('name')) ?>">
                <?php $err('name'); ?>
            </div>
            <div class="field">
                <label for="category"><span>Catégorie (facultatif)</span></label>
                <input class="input" id="category" name="category" maxlength="60" list="category-list" placeholder="Ex. : Coiffure, Ongles, Soins" value="<?= Html::e($val('category')) ?>">
                <datalist id="category-list"><?php foreach ($categories as $item): ?><option value="<?= Html::e((string) $item) ?>"></option><?php endforeach; ?></datalist>
                <p class="btq-help">Les prestations sont rangées par catégorie sur votre page de réservation.</p>
                <?php $err('category'); ?>
            </div>
            <div class="field">
                <label for="description"><span>Description (facultatif)</span></label>
                <textarea class="input" id="description" name="description" rows="3" maxlength="500" placeholder="Ce qui est inclus, ce que la cliente doit prévoir…"><?= Html::e($val('description')) ?></textarea>
                <?php $err('description'); ?>
            </div>
        </fieldset>
        <fieldset>
            <legend>Photo</legend>
            <div class="btq-upload">
                <?php if ($prestation !== null && $prestation->imagePath() !== null && $tenantSlug !== ''): ?>
                    <img class="btq-upload-preview is-wide" id="image-preview" src="<?= Html::url('/t/' . $tenantSlug . '/prestation/' . $prestation->id() . '/image') ?>?v=<?= Html::e($prestation->imageVersion()) ?>" alt="">
                <?php else: ?>
                    <span class="btq-upload-preview is-wide" id="image-preview"><?php $name = 'image'; include dirname(__DIR__, 4) . '/Views/partials/ui-icon.php'; ?></span>
                <?php endif; ?>
                <div class="field">
                    <label for="image"><span>Photo de la prestation (facultatif)</span></label>
                    <input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="image-preview">
                    <p class="btq-help">Une belle photo (coiffure, ongles, soin…) donne envie de réserver. JPG, PNG ou WebP, compressée automatiquement.</p>
                    <?php $err('image'); ?>
                    <?php if ($prestation !== null && $prestation->imagePath() !== null): ?><label class="btq-switch"><input type="checkbox" name="remove_image" value="1"><span>Retirer la photo</span></label><?php endif; ?>
                </div>
            </div>
        </fieldset>
        <fieldset>
            <legend>Durée et prix</legend>
            <div class="btq-grid-2">
                <div class="field">
                    <label for="duration_minutes"><span>Durée</span></label>
                    <select class="input" id="duration_minutes" name="duration_minutes">
                        <?php $current = (int) $val('duration_minutes'); ?>
                        <?php if (!in_array($current, $durations, true) && $current > 0): ?><option value="<?= $current ?>" selected><?= $current ?> min</option><?php endif; ?>
                        <?php foreach ($durations as $minutes): ?>
                            <option value="<?= $minutes ?>"<?= $current === $minutes ? ' selected' : '' ?>><?= $minutes < 60 ? $minutes . ' min' : intdiv($minutes, 60) . ' h' . ($minutes % 60 ? ' ' . ($minutes % 60) : '') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $err('duration_minutes'); ?>
                </div>
                <div class="field">
                    <label for="price_minor"><span>Prix (FCFA)</span></label>
                    <input class="input" id="price_minor" name="price_minor" inputmode="numeric" required placeholder="10000" value="<?= Html::e($val('price_minor')) ?>">
                    <?php $err('price_minor'); ?>
                </div>
            </div>
            <div class="field">
                <label for="deposit_minor"><span>Acompte demandé en ligne (FCFA)</span></label>
                <input class="input" id="deposit_minor" name="deposit_minor" inputmode="numeric" value="<?= Html::e($val('deposit_minor')) ?>">
                <p class="btq-help">Laissez 0 pour ne rien demander. Avec un acompte, la cliente paie par Wave ou Orange Money pour bloquer son créneau : moins d'absences.</p>
            </div>
            <?php if ($prestation !== null): ?>
                <label class="btq-switch"><input type="checkbox" name="is_active" value="1"<?= $val('is_active') !== '' ? ' checked' : '' ?>><span><strong>Proposée à la réservation</strong></span></label>
            <?php endif; ?>
        </fieldset>
        <div class="btq-sticky-save">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <?php if ($prestation === null): ?><button class="btn btn-secondary" type="submit" name="encore" value="1">Enregistrer et en ajouter une autre</button><?php endif; ?>
        </div>
    </form>
</section>
