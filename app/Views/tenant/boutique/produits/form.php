<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\Tenant;

$product = ($product ?? null) instanceof BoutiqueProduct ? $product : null;
$categories = is_array($categories ?? null) ? $categories : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$action = $product === null ? '/boutique/produits' : '/boutique/produits/' . $product->id();
$val = static fn (string $key): string => is_scalar($old[$key] ?? null) ? (string) $old[$key] : '';
$checked = static fn (string $key): string => $val($key) !== '' ? ' checked' : '';
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error" id="' . Html::e($key) . '-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid" aria-invalid="true" aria-describedby="' . $key . '-error' : '';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1><?= $product === null ? 'Ajouter un produit' : 'Modifier le produit' ?></h1>
            <p class="muted">Seuls le nom et le prix sont obligatoires. Une belle photo fait vendre davantage.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/boutique/produits') ?>">Retour aux produits</a>
    </header>

    <?php if ($errors !== []): ?>
        <p class="alert alert-danger" role="alert">Vérifiez les champs en rouge.</p>
    <?php endif; ?>

    <form class="btq-form" method="post" action="<?= Html::url($action) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

        <fieldset>
            <legend>1. La photo</legend>
            <div class="btq-upload">
                <?php if ($product?->imagePath() !== null && $tenant !== null): ?>
                    <img class="btq-upload-preview" id="product-preview" src="<?= Html::url('/t/' . $tenant->slug() . '/produit/' . $product->id() . '/image') ?>?v=<?= Html::e(rawurlencode($product->updatedAt())) ?>" alt="">
                <?php else: ?>
                    <span class="btq-upload-preview" id="product-preview"><?php $name = 'image'; include $icon; ?></span>
                <?php endif; ?>
                <div class="field">
                    <label for="image"><span>Choisir une photo</span></label>
                    <input class="input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="product-preview">
                    <p class="btq-help">JPEG, PNG ou WebP, 4 Mo maximum. Une photo prise au téléphone suffit.</p>
                    <?php $err('image'); ?>
                </div>
            </div>
            <?php if ($product?->imagePath() !== null): ?>
                <label class="btq-switch"><input type="checkbox" name="remove_image" value="1"><span>Retirer la photo actuelle</span></label>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>2. Le produit</legend>
            <div class="field">
                <label for="name"><span>Nom du produit</span></label>
                <input class="input<?= $invalid('name') ?>" id="name" name="name" required maxlength="120" placeholder="Ex. : Robe wax taille M" value="<?= Html::e($val('name')) ?>">
                <?php $err('name'); ?>
            </div>
            <div class="btq-grid-2">
                <div class="field">
                    <label for="price_minor"><span>Prix (FCFA)</span></label>
                    <input class="input<?= $invalid('price_minor') ?>" id="price_minor" name="price_minor" required inputmode="numeric" placeholder="15000" value="<?= Html::e($val('price_minor')) ?>">
                    <?php $err('price_minor'); ?>
                </div>
                <div class="field">
                    <label for="compare_price_minor"><span>Ancien prix (facultatif)</span></label>
                    <input class="input<?= $invalid('compare_price_minor') ?>" id="compare_price_minor" name="compare_price_minor" inputmode="numeric" placeholder="20000" value="<?= Html::e($val('compare_price_minor')) ?>">
                    <p class="btq-help">S'il est plus élevé, il s'affiche barré avec un badge « Promo ».</p>
                    <?php $err('compare_price_minor'); ?>
                </div>
            </div>
            <div class="field">
                <label for="category"><span>Catégorie (facultatif)</span></label>
                <input class="input<?= $invalid('category') ?>" id="category" name="category" maxlength="60" list="category-list" placeholder="Ex. : Robes, Chaussures, Cosmétiques" value="<?= Html::e($val('category')) ?>">
                <datalist id="category-list">
                    <?php foreach ($categories as $item): ?>
                        <option value="<?= Html::e((string) $item) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="btq-help">Vos clients pourront filtrer la boutique par catégorie.</p>
                <?php $err('category'); ?>
            </div>
            <div class="field">
                <label for="description"><span>Description (facultatif)</span></label>
                <textarea class="input<?= $invalid('description') ?>" id="description" name="description" rows="4" maxlength="1000" placeholder="Matière, tailles disponibles, couleurs…"><?= Html::e($val('description')) ?></textarea>
                <?php $err('description'); ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>3. Le stock</legend>
            <label class="btq-switch">
                <input type="checkbox" name="track_stock" value="1"<?= $checked('track_stock') ?>>
                <span><strong>Suivre le stock de ce produit</strong><span class="btq-help">Le stock baisse tout seul quand vous confirmez une commande. À zéro, le produit s'affiche « Épuisé ».</span></span>
            </label>
            <div class="btq-grid-2">
                <?php if ($product === null): ?>
                    <div class="field">
                        <label for="stock_quantity"><span>Quantité disponible</span></label>
                        <input class="input<?= $invalid('stock_quantity') ?>" id="stock_quantity" name="stock_quantity" inputmode="numeric" placeholder="10" value="<?= Html::e($val('stock_quantity')) ?>">
                        <?php $err('stock_quantity'); ?>
                    </div>
                <?php else: ?>
                    <div class="field">
                        <span>Quantité disponible</span>
                        <p><strong><?= Html::e((string) $product->stockQuantity()) ?></strong> — <a href="<?= Html::url('/boutique/stock?produit=' . $product->id()) ?>">changer dans Stock</a></p>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="low_stock_threshold"><span>Me prévenir quand il en reste</span></label>
                    <input class="input<?= $invalid('low_stock_threshold') ?>" id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" max="10000" value="<?= Html::e($val('low_stock_threshold') !== '' ? $val('low_stock_threshold') : '3') ?>">
                    <?php $err('low_stock_threshold'); ?>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>4. Visibilité</legend>
            <label class="btq-switch">
                <input type="checkbox" name="is_published" value="1"<?= $checked('is_published') ?>>
                <span><strong>Afficher dans ma boutique en ligne</strong><span class="btq-help">Décochez pour le cacher sans le supprimer.</span></span>
            </label>
        </fieldset>

        <div class="btq-sticky-save">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <?php if ($product === null): ?>
                <button class="btn btn-secondary" type="submit" name="encore" value="1">Enregistrer et ajouter un autre</button>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= Html::url('/boutique/produits') ?>">Annuler</a>
        </div>
    </form>

    <?php if ($product !== null): ?>
        <form class="btq-card" method="post" action="<?= Html::url('/boutique/produits/' . $product->id() . '/supprimer') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <h2>Supprimer ce produit</h2>
            <p class="muted">Il disparaît de la boutique. Les commandes passées gardent son nom et son prix. Pour le cacher seulement, décochez « Afficher dans ma boutique ».</p>
            <button class="btn btn-danger" type="submit">Supprimer définitivement</button>
        </form>
    <?php endif; ?>
</section>
