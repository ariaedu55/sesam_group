<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$errors = is_array($errors ?? null) ? $errors : [];
$token = is_string($token ?? null) ? $token : '';

?>
<section class="auth-card card">
    <p class="eyebrow">Espace SESAM</p>
    <h1>Nouveau mot de passe</h1>
    <p class="muted">Choisissez un mot de passe d'au moins 12 caractères.</p>
    <form method="post" action="<?= Html::url('/mot-de-passe/reinitialiser/' . $token) ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="password"><span>Mot de passe</span></label>
            <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password"<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="password-error"' : '' ?>>
            <?php if (isset($errors['password'])): ?><p class="field-error" id="password-error"><?= Html::e($errors['password']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="password_confirmation"><span>Confirmation</span></label>
            <input class="input<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="255" autocomplete="new-password"<?= isset($errors['password_confirmation']) ? ' aria-invalid="true" aria-describedby="password-confirmation-error"' : '' ?>>
            <?php if (isset($errors['password_confirmation'])): ?><p class="field-error" id="password-confirmation-error"><?= Html::e($errors['password_confirmation']) ?></p><?php endif; ?>
        </div>
        <button class="btn btn-primary" type="submit">Enregistrer le mot de passe</button>
    </form>
</section>
