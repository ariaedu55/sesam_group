<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$email = Html::e(is_scalar($old['email'] ?? null) ? (string) $old['email'] : '');
$status = is_string($status ?? null) ? $status : '';

?>
<section class="auth-card card">
    <p class="eyebrow">Espace SESAM</p>
    <h1>Connexion</h1>
    <p class="muted">Cet espace n'est pas une inscription publique. Seuls les comptes créés par SESAM peuvent entrer.</p>
    <?php if ($status !== ''): ?>
        <p class="alert" role="status"><?= Html::e($status) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= Html::url('/connexion') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="email"><span>E-mail</span></label>
            <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= $email ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p class="field-error" id="email-error"><?= Html::e($errors['email']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="password"><span>Mot de passe</span></label>
            <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" required maxlength="255" autocomplete="current-password"<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="password-error"' : '' ?>>
            <?php if (isset($errors['password'])): ?><p class="field-error" id="password-error"><?= Html::e($errors['password']) ?></p><?php endif; ?>
        </div>
        <button class="btn btn-primary" type="submit">Se connecter</button>
    </form>
    <p class="auth-help"><a href="<?= Html::url('/mot-de-passe') ?>">Mot de passe oublié</a></p>
</section>
