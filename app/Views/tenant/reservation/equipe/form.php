<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ReservationPrestation;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$prestations = is_array($prestations ?? null) ? $prestations : [];
$selected = array_map('strval', is_array($old['service_ids'] ?? null) ? $old['service_ids'] : []);
$val = static fn (string $key): string => is_scalar($old[$key] ?? null) ? (string) $old[$key] : '';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Ajouter une personne</h1>
            <p class="muted">Vous réglerez ses horaires et ses absences juste après.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/reservation/equipe') ?>">Retour</a>
    </header>
    <form class="btq-form" method="post" action="<?= Html::url('/reservation/equipe') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <fieldset>
            <legend>Qui est-ce ?</legend>
            <div class="field">
                <label for="name"><span>Prénom et nom</span></label>
                <input class="input" id="name" name="name" required maxlength="120" value="<?= Html::e($val('name')) ?>">
                <?php if (isset($errors['name'])): ?><p class="field-error"><?= Html::e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="btq-grid-2">
                <div class="field">
                    <label for="role_label"><span>Métier (facultatif)</span></label>
                    <input class="input" id="role_label" name="role_label" maxlength="80" placeholder="Ex. : Coiffeuse, Esthéticienne" value="<?= Html::e($val('role_label')) ?>">
                </div>
                <div class="field">
                    <label for="phone"><span>Téléphone (facultatif)</span></label>
                    <input class="input" id="phone" name="phone" type="tel" maxlength="40" value="<?= Html::e($val('phone')) ?>">
                </div>
            </div>
        </fieldset>
        <?php if ($prestations !== []): ?>
            <fieldset>
                <legend>Ce qu'elle fait</legend>
                <p class="btq-help">Ne cochez rien si elle peut tout faire.</p>
                <div class="chip-list">
                    <?php foreach ($prestations as $item): ?>
                        <?php if (!$item instanceof ReservationPrestation || !$item->isActive()) { continue; } ?>
                        <label><input type="checkbox" name="service_ids[]" value="<?= $item->id() ?>"<?= in_array((string) $item->id(), $selected, true) ? ' checked' : '' ?>><?= Html::e($item->name()) ?></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>
        <div class="btq-sticky-save">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
        </div>
    </form>
</section>
