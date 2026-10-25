<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

?>
<section>
    <h1>Nouveau produit</h1>
    <form class="stack" method="post" action="<?= Html::url('/commande/produits') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="name"><span>Type / nom</span></label>
            <input class="input" id="name" name="name" required maxlength="120" value="<?= Html::e((string) ($old['name'] ?? '')) ?>">
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= Html::e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="price_minor"><span>Tarif (FCFA)</span></label>
            <input class="input" id="price_minor" name="price_minor" type="number" min="0" required value="<?= Html::e((string) ($old['price_minor'] ?? '')) ?>">
            <?php if (isset($errors['price_minor'])): ?><p class="field-error"><?= Html::e($errors['price_minor']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="deposit_minor"><span>Acompte (FCFA)</span></label>
            <input class="input" id="deposit_minor" name="deposit_minor" type="number" min="0" value="<?= Html::e((string) ($old['deposit_minor'] ?? '0')) ?>">
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/commande/produits') ?>">Annuler</a>
        </p>
    </form>
</section>
