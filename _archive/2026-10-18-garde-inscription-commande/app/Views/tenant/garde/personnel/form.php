<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

?>
<section class="garde-page">
    <header class="garde-hero garde-hero-compact">
        <div>
            <p class="eyebrow">Personnel</p>
            <h1>Ajouter une personne</h1>
        </div>
    </header>
    <form class="stack card garde-form" method="post" action="<?= Html::url('/garde/personnel') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="name"><span>Nom</span></label>
            <input class="input" id="name" name="name" required maxlength="120" value="<?= Html::e((string) ($old['name'] ?? '')) ?>">
            <?php if (isset($errors['name'])): ?><p class="field-error"><?= Html::e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="role_label"><span>Fonction</span></label>
            <input class="input" id="role_label" name="role_label" maxlength="80" placeholder="Pharmacien, préparateur…" value="<?= Html::e((string) ($old['role_label'] ?? '')) ?>">
            <?php if (isset($errors['role_label'])): ?><p class="field-error"><?= Html::e($errors['role_label']) ?></p><?php endif; ?>
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/garde/personnel') ?>">Annuler</a>
        </p>
    </form>
</section>
