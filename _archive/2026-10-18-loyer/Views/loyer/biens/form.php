<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

?>
<section>
    <h1>Nouveau bien</h1>
    <form class="stack" method="post" action="<?= Html::url('/loyer/biens') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="name"><span>Nom</span></label>
            <input class="input" id="name" name="name" required maxlength="120" value="<?= Html::e((string) ($old['name'] ?? '')) ?>">
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= Html::e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="address"><span>Adresse (optionnel)</span></label>
            <input class="input" id="address" name="address" maxlength="255" value="<?= Html::e((string) ($old['address'] ?? '')) ?>">
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/loyer/biens') ?>">Annuler</a>
        </p>
    </form>
</section>
