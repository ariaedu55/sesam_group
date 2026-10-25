<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\GardeProduct;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$products = is_array($products ?? null) ? $products : [];

?>
<section class="garde-page">
    <header class="garde-hero garde-hero-compact">
        <div>
            <p class="eyebrow">Stock</p>
            <h1>Nouveau lot</h1>
            <p>La date de péremption classe le lot : Expiré, Bientôt (30 jours) ou OK.</p>
        </div>
    </header>
    <form class="stack card garde-form" method="post" action="<?= Html::url('/garde/lots') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="product_id"><span>Produit</span></label>
            <select class="input" id="product_id" name="product_id" required>
                <option value="">Choisir</option>
                <?php foreach ($products as $product): ?>
                    <?php if (!$product instanceof GardeProduct) { continue; } ?>
                    <option value="<?= Html::e((string) $product->id()) ?>"<?= (string) ($old['product_id'] ?? '') === (string) $product->id() ? ' selected' : '' ?>>
                        <?= Html::e($product->name()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['product_id'])): ?><p class="field-error"><?= Html::e($errors['product_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="lot_code"><span>N° de lot</span></label>
            <input class="input" id="lot_code" name="lot_code" required maxlength="80" value="<?= Html::e((string) ($old['lot_code'] ?? '')) ?>">
            <?php if (isset($errors['lot_code'])): ?><p class="field-error"><?= Html::e($errors['lot_code']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="quantity"><span>Quantité</span></label>
            <input class="input" id="quantity" name="quantity" type="number" min="1" max="100000" required value="<?= Html::e((string) ($old['quantity'] ?? '')) ?>">
            <?php if (isset($errors['quantity'])): ?><p class="field-error"><?= Html::e($errors['quantity']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="expires_on"><span>Date de péremption</span></label>
            <input class="input" id="expires_on" name="expires_on" type="date" required value="<?= Html::e((string) ($old['expires_on'] ?? '')) ?>">
            <?php if (isset($errors['expires_on'])): ?><p class="field-error"><?= Html::e($errors['expires_on']) ?></p><?php endif; ?>
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer le lot</button>
            <a class="btn btn-secondary" href="<?= Html::url('/garde/produits') ?>">Annuler</a>
        </p>
    </form>
</section>
