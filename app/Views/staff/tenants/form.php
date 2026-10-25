<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;

$edited = $edited instanceof Tenant ? $edited : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$sectors = is_array($sectors ?? null) ? $sectors : [];
$canUpdate = (bool) ($canUpdate ?? false);
$value = static function (string $key) use ($old): string {
    $item = $old[$key] ?? '';

    return Html::e(is_scalar($item) ? (string) $item : '');
};
$action = $edited === null ? Html::url('/commerces') : Html::url('/commerces/' . $edited->id());
$locked = $edited !== null && !$canUpdate;
$currentSector = is_scalar($old['sector'] ?? '') ? (string) $old['sector'] : '';
$currentStatus = is_scalar($old['status'] ?? '') ? (string) $old['status'] : 'active';

?>
<section class="staff-form">
    <h1><?= $edited === null ? 'Nouveau commerce' : 'Modifier le commerce' ?></h1>
    <form class="card" method="post" action="<?= $action ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="name"><span>Nom interne</span></label>
            <input class="input<?= isset($errors['name']) ? ' is-invalid' : '' ?>" id="name" name="name" type="text" required maxlength="120" value="<?= $value('name') ?>"<?= $locked ? ' readonly' : '' ?><?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="name-error"' : '' ?>>
            <?php if (isset($errors['name'])): ?><p class="field-error" id="name-error"><?= Html::e($errors['name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="display_name"><span>Nom affiché</span></label>
            <input class="input<?= isset($errors['display_name']) ? ' is-invalid' : '' ?>" id="display_name" name="display_name" type="text" required maxlength="120" value="<?= $value('display_name') ?>"<?= $locked ? ' readonly' : '' ?><?= isset($errors['display_name']) ? ' aria-invalid="true" aria-describedby="display_name-error"' : '' ?>>
            <?php if (isset($errors['display_name'])): ?><p class="field-error" id="display_name-error"><?= Html::e($errors['display_name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="slug"><span>Slug</span></label>
            <input class="input<?= isset($errors['slug']) ? ' is-invalid' : '' ?>" id="slug" name="slug" type="text" required maxlength="80" value="<?= $value('slug') ?>"<?= $locked ? ' readonly' : '' ?><?= isset($errors['slug']) ? ' aria-invalid="true" aria-describedby="slug-error"' : '' ?>>
            <?php if (isset($errors['slug'])): ?><p class="field-error" id="slug-error"><?= Html::e($errors['slug']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="sector"><span>Secteur</span></label>
            <select class="select<?= isset($errors['sector']) ? ' is-invalid' : '' ?>" id="sector" name="sector" required<?= $locked ? ' disabled' : '' ?><?= isset($errors['sector']) ? ' aria-invalid="true" aria-describedby="sector-error"' : '' ?>>
                <?php foreach ($sectors as $slug => $label): ?>
                    <?php if (!is_string($slug) || !is_string($label)) { continue; } ?>
                    <option value="<?= Html::e($slug) ?>"<?= $currentSector === $slug ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['sector'])): ?><p class="field-error" id="sector-error"><?= Html::e($errors['sector']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="status"><span>État</span></label>
            <select class="select<?= isset($errors['status']) ? ' is-invalid' : '' ?>" id="status" name="status" required<?= $locked ? ' disabled' : '' ?><?= isset($errors['status']) ? ' aria-invalid="true" aria-describedby="status-error"' : '' ?>>
                <option value="active"<?= $currentStatus === 'active' ? ' selected' : '' ?>>Actif</option>
                <option value="suspended"<?= $currentStatus === 'suspended' ? ' selected' : '' ?>>Suspendu</option>
            </select>
            <?php if (isset($errors['status'])): ?><p class="field-error" id="status-error"><?= Html::e($errors['status']) ?></p><?php endif; ?>
            <p class="muted">Un commerce suspendu ne peut plus se connecter.</p>
        </div>
        <?php if ($edited === null): ?>
            <fieldset class="field">
                <legend>Responsable du commerce</legend>
                <div class="field">
                    <label for="owner_full_name"><span>Nom</span></label>
                    <input class="input<?= isset($errors['owner_full_name']) ? ' is-invalid' : '' ?>" id="owner_full_name" name="owner_full_name" type="text" required maxlength="120" value="<?= $value('owner_full_name') ?>"<?= isset($errors['owner_full_name']) ? ' aria-invalid="true" aria-describedby="owner_full_name-error"' : '' ?>>
                    <?php if (isset($errors['owner_full_name'])): ?><p class="field-error" id="owner_full_name-error"><?= Html::e($errors['owner_full_name']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="owner_email"><span>E-mail</span></label>
                    <input class="input<?= isset($errors['owner_email']) ? ' is-invalid' : '' ?>" id="owner_email" name="owner_email" type="email" required maxlength="190" autocomplete="off" value="<?= $value('owner_email') ?>"<?= isset($errors['owner_email']) ? ' aria-invalid="true" aria-describedby="owner_email-error"' : '' ?>>
                    <?php if (isset($errors['owner_email'])): ?><p class="field-error" id="owner_email-error"><?= Html::e($errors['owner_email']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="owner_password"><span>Mot de passe</span></label>
                    <input class="input<?= isset($errors['owner_password']) ? ' is-invalid' : '' ?>" id="owner_password" name="owner_password" type="password" required minlength="12" maxlength="255" autocomplete="new-password"<?= isset($errors['owner_password']) ? ' aria-invalid="true" aria-describedby="owner_password-error"' : '' ?>>
                    <?php if (isset($errors['owner_password'])): ?><p class="field-error" id="owner_password-error"><?= Html::e($errors['owner_password']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="owner_phone"><span>Téléphone WhatsApp (facultatif)</span></label>
                    <input class="input" id="owner_phone" name="owner_phone" type="tel" maxlength="40" value="<?= $value('owner_phone') ?>">
                </div>
            </fieldset>
            <fieldset class="field">
                <legend>Démarrage</legend>
                <?php $trial = (string) ($old['trial_days'] ?? '30'); ?>
                <div class="trial-choices">
                    <label class="trial-choice"><input type="radio" name="trial_days" value="15"<?= $trial === '15' ? ' checked' : '' ?>><span><strong>Essai 15 jours</strong><small>Gratuit, puis abonnement</small></span></label>
                    <label class="trial-choice"><input type="radio" name="trial_days" value="30"<?= $trial === '30' ? ' checked' : '' ?>><span><strong>Essai 1 mois</strong><small>Gratuit, puis abonnement</small></span></label>
                    <label class="trial-choice"><input type="radio" name="trial_days" value="0"<?= $trial === '0' ? ' checked' : '' ?>><span><strong>Sans essai</strong><small>Activer l'abonnement depuis la fiche</small></span></label>
                </div>
                <?php if (isset($errors['trial_days'])): ?><p class="field-error"><?= Html::e($errors['trial_days']) ?></p><?php endif; ?>
            </fieldset>
        <?php endif; ?>
        <div class="row">
            <?php if (!$locked): ?>
                <button class="btn btn-primary" type="submit"><?= $edited === null ? 'Créer le commerce' : 'Enregistrer' ?></button>
            <?php endif; ?>
            <?php if ($edited === null): ?>
                <a class="btn btn-secondary" href="<?= Html::url('/commerces') ?>">Retour à la liste</a>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?= Html::url('/commerces/' . $edited->id()) ?>">Retour à la fiche</a>
            <?php endif; ?>
        </div>
    </form>
</section>
