<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;

$tenant = $tenant instanceof Tenant ? $tenant : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canUpdate = (bool) ($canUpdate ?? false);
$display = is_scalar($old['display_name'] ?? null) ? (string) $old['display_name'] : ($tenant?->displayName() ?? '');

?>
<section class="staff-form">
    <h1>Paramètres du commerce</h1>
    <?php if ($tenant !== null): ?>
        <p class="muted"><?= Html::e($tenant->name()) ?> · <?= Html::e($tenant->sectorLabel()) ?></p>
        <p>Lien public (sans compte) : <a href="<?= Html::url('/t/' . $tenant->slug()) ?>"><?= Html::url('/t/' . $tenant->slug()) ?></a></p>
    <?php endif; ?>
    <form class="card" method="post" action="<?= Html::url('/parametres') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="display_name"><span>Nom affiché</span></label>
            <input class="input<?= isset($errors['display_name']) ? ' is-invalid' : '' ?>" id="display_name" name="display_name" type="text" required maxlength="120" value="<?= Html::e($display) ?>"<?= $canUpdate ? '' : ' readonly' ?><?= isset($errors['display_name']) ? ' aria-invalid="true" aria-describedby="display_name-error"' : '' ?>>
            <?php if (isset($errors['display_name'])): ?><p class="field-error" id="display_name-error"><?= Html::e($errors['display_name']) ?></p><?php endif; ?>
        </div>
        <div class="row">
            <?php if ($canUpdate): ?>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            <?php endif; ?>
            <a class="btn btn-secondary" href="<?= Html::url('/espace') ?>">Retour à l'espace</a>
        </div>
    </form>
</section>
