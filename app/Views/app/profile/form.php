<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$value = static function (string $key) use ($old): string {
    $item = $old[$key] ?? '';

    return Html::e(is_scalar($item) ? (string) $item : '');
};

?>
<section class="staff-form">
    <h1>Mon profil</h1>
    <form class="card" method="post" action="<?= Html::url('/profil') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="full_name"><span>Nom affiché</span></label>
            <input class="input<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>" id="full_name" name="full_name" type="text" required maxlength="120" value="<?= $value('full_name') ?>"<?= isset($errors['full_name']) ? ' aria-invalid="true"' : '' ?>>
            <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= Html::e($errors['full_name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="email"><span>E-mail</span></label>
            <input class="input" id="email" name="email" type="email" readonly value="<?= $value('email') ?>">
            <p class="muted">L'e-mail est modifié par un administrateur.</p>
        </div>
        <fieldset class="field">
            <legend>Changer le mot de passe</legend>
            <p class="muted">Laissez vide pour conserver le mot de passe actuel.</p>
            <div class="field">
                <label for="current_password"><span>Mot de passe actuel</span></label>
                <input class="input<?= isset($errors['current_password']) ? ' is-invalid' : '' ?>" id="current_password" name="current_password" type="password" maxlength="255" autocomplete="current-password">
                <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= Html::e($errors['current_password']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="password"><span>Nouveau mot de passe</span></label>
                <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" minlength="12" maxlength="255" autocomplete="new-password">
                <?php if (isset($errors['password'])): ?><p class="field-error"><?= Html::e($errors['password']) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="password_confirmation"><span>Confirmer le mot de passe</span></label>
                <input class="input<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" maxlength="255" autocomplete="new-password">
                <?php if (isset($errors['password_confirmation'])): ?><p class="field-error"><?= Html::e($errors['password_confirmation']) ?></p><?php endif; ?>
            </div>
        </fieldset>
        <div class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/espace') ?>">Retour</a>
        </div>
    </form>
</section>
