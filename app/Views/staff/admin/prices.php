<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$prices = is_array($prices ?? null) ? $prices : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$ro = $canManage ? '' : ' disabled';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Tarifs des modules</h1>
            <p class="muted">Mensualité proposée par défaut à l'activation d'un abonnement. Chaque commerce peut avoir un montant négocié dans sa fiche.</p>
        </div>
    </header>
    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert"><?= Html::e(implode(' ', $errors)) ?></p><?php endif; ?>
    <form class="btq-form" method="post" action="<?= Html::url('/tarifs') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <?php foreach ($prices as $sector => $price): ?>
            <fieldset>
                <legend>Sesam <?= Html::e((string) $price['label']) ?></legend>
                <div class="btq-grid-2">
                    <div class="field">
                        <label for="<?= Html::e($sector) ?>_monthly"><span>Mensualité (FCFA)</span></label>
                        <input class="input" id="<?= Html::e($sector) ?>_monthly" name="<?= Html::e($sector) ?>_monthly" inputmode="numeric" value="<?= Html::e((string) ($old[$sector . '_monthly'] ?? $price['monthly_amount'])) ?>"<?= $ro ?>>
                    </div>
                    <div class="field">
                        <label for="<?= Html::e($sector) ?>_setup"><span>Frais de mise en place (FCFA)</span></label>
                        <input class="input" id="<?= Html::e($sector) ?>_setup" name="<?= Html::e($sector) ?>_setup" inputmode="numeric" value="<?= Html::e((string) ($old[$sector . '_setup'] ?? $price['setup_fee'])) ?>"<?= $ro ?>>
                    </div>
                </div>
            </fieldset>
        <?php endforeach; ?>
        <?php if ($canManage): ?>
            <div class="btq-sticky-save"><button class="btn btn-primary" type="submit">Enregistrer les tarifs</button></div>
        <?php endif; ?>
    </form>
</section>
