<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

?>
<section class="garde-page">
    <header class="garde-hero garde-hero-compact">
        <div>
            <p class="eyebrow">Stock</p>
            <h1>Nouveau produit</h1>
            <p>Une photo aide l’équipe à reconnaître la boîte dans le rayon.</p>
        </div>
    </header>
    <form class="stack card garde-form" method="post" action="<?= Html::url('/garde/produits') ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="name"><span>Nom</span></label>
            <input class="input" id="name" name="name" required maxlength="160" value="<?= Html::e((string) ($old['name'] ?? '')) ?>">
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= Html::e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="unit"><span>Unité</span></label>
            <input class="input" id="unit" name="unit" required maxlength="40" value="<?= Html::e((string) ($old['unit'] ?? 'boîte')) ?>">
            <?php if (isset($errors['unit'])): ?><p class="field-error"><?= Html::e($errors['unit']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="min_quantity"><span>Seuil minimum</span></label>
            <input class="input" id="min_quantity" name="min_quantity" type="number" min="0" max="100000" value="<?= Html::e((string) ($old['min_quantity'] ?? '0')) ?>">
            <?php if (isset($errors['min_quantity'])): ?><p class="field-error"><?= Html::e($errors['min_quantity']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <span class="garde-file-caption">Photo du produit</span>
            <label class="garde-file" for="image">
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview>
                <span class="garde-file-ui">
                    <span class="garde-file-preview" data-image-preview-target hidden></span>
                    <span class="garde-file-copy">
                        <strong>Ajouter une photo</strong>
                        <span class="muted">JPEG, PNG, WebP ou GIF · 4 Mo max · convertie et compressée automatiquement</span>
                    </span>
                </span>
            </label>
            <?php if (isset($errors['image'])): ?><p class="field-error"><?= Html::e($errors['image']) ?></p><?php endif; ?>
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/garde/produits') ?>">Annuler</a>
        </p>
    </form>
</section>
