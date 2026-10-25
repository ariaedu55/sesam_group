<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

?>
<section>
    <h1>Nouveau type de frais</h1>
    <form class="stack" method="post" action="<?= Html::url('/inscription/frais') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="name"><span>Libellé</span></label>
            <input class="input" id="name" name="name" required maxlength="120" value="<?= Html::e((string) ($old['name'] ?? '')) ?>">
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= Html::e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="total_minor"><span>Montant total (FCFA)</span></label>
            <input class="input" id="total_minor" name="total_minor" type="number" min="100" required value="<?= Html::e((string) ($old['total_minor'] ?? '')) ?>">
            <?php if (isset($errors['total_minor'])): ?><p class="field-error"><?= Html::e($errors['total_minor']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="installment_count"><span>Nombre de tranches (1–12)</span></label>
            <input class="input" id="installment_count" name="installment_count" type="number" min="1" max="12" required value="<?= Html::e((string) ($old['installment_count'] ?? '3')) ?>">
            <?php if (isset($errors['installment_count'])): ?><p class="field-error"><?= Html::e($errors['installment_count']) ?></p><?php endif; ?>
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/inscription/frais') ?>">Annuler</a>
        </p>
    </form>
</section>
