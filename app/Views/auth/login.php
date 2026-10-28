<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$email = Html::e(is_scalar($old['email'] ?? null) ? (string) $old['email'] : '');
$status = is_string($status ?? null) ? $status : '';

?>
<section class="auth-card auth-card--login">
    <header class="auth-card-logo">
        <a class="auth-card-logo-link" href="<?= Html::url('/') ?>">
            <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="SESAM" width="88" height="88">
            <span class="auth-card-logo-name">SESAM</span>
        </a>
    </header>
    <?php if ($status !== ''): ?>
        <p class="alert" role="status"><?= Html::e($status) ?></p>
    <?php endif; ?>
    <?php if (!empty($googleEnabled)): ?>
        <?php $googleFrom = 'login'; include dirname(__DIR__) . '/partials/google-button.php'; ?>
    <?php endif; ?>
    <form class="auth-form" method="post" action="<?= Html::url('/connexion') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="email"><span>E-mail</span></label>
            <input class="input input--auth<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="username" placeholder="vous@exemple.com" value="<?= $email ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p class="field-error" id="email-error"><?= Html::e($errors['email']) ?></p><?php endif; ?>
        </div>
        <?php
        $id = 'password';
        $name = 'password';
        $label = 'Mot de passe';
        $error = $errors['password'] ?? null;
        $errorId = 'password-error';
        $autocomplete = 'current-password';
        $minlength = null;
        include dirname(__DIR__) . '/partials/password-field.php';
        ?>
        <div class="auth-form-row">
            <a class="auth-inline-link" href="<?= Html::url('/mot-de-passe') ?>">Mot de passe oublié&nbsp;?</a>
        </div>
        <button class="btn btn-primary btn-auth-submit" type="submit">Se connecter</button>
    </form>
    <footer class="auth-card-foot">
        <p>Première fois sur SESAM&nbsp;? <a href="<?= Html::url('/commencer') ?>">Commencer l'essai gratuit</a></p>
    </footer>
</section>
