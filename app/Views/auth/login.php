<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$email = Html::e(is_scalar($old['email'] ?? null) ? (string) $old['email'] : '');
$phone = Html::e(is_scalar($old['phone'] ?? null) ? (string) $old['phone'] : '');
$status = is_string($status ?? null) ? $status : '';
$mode = ($old['mode'] ?? '') === 'phone' ? 'phone' : (($old['mode'] ?? '') === 'email' ? 'email' : '');
$loginError = (string) ($errors['phone'] ?? $errors['email'] ?? $errors['password'] ?? '');
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<link rel="stylesheet" href="<?= Html::asset('assets/css/login.css') ?>">
<section class="auth-card auth-card--login lg">
    <header class="auth-card-logo">
        <a class="auth-card-logo-link" href="<?= Html::url('/') ?>">
            <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="SESAM" width="88" height="88">
            <span class="auth-card-logo-name">SESAM</span>
        </a>
    </header>
    <h1 class="lg-title">Connexion</h1>
    <p class="lg-sub">Choisissez comment vous connecter à votre espace.</p>
    <?php if ($status !== ''): ?>
        <p class="alert" role="status"><?= Html::e($status) ?></p>
    <?php endif; ?>

    <div class="lg-choices">
        <button class="lg-choice is-primary" type="button" data-login-open="phone">
            <span class="lg-choice-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><rect x="6" y="2.5" width="12" height="19" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10.5 18.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            <span>Continuer avec mon numéro de téléphone</span>
        </button>
        <button class="lg-choice" type="button" data-login-open="email">
            <span class="lg-choice-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><rect x="3" y="5" width="18" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 7l8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span>
            <span>Continuer avec mon e-mail</span>
        </button>
        <?php if (!empty($googleEnabled)): ?>
            <p class="lg-or"><span>ou</span></p>
            <div class="lg-google">
                <?php $googleFrom = 'login'; include dirname(__DIR__) . '/partials/google-button.php'; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="auth-card-foot">
        <p>Première fois sur SESAM&nbsp;? <a href="<?= Html::url('/commencer') ?>">Créer mon espace</a></p>
    </footer>
</section>

<!-- Formulaire de connexion (téléphone ou e-mail) dans une fenêtre -->
<div class="lg-modal" data-login-modal data-login-mode="<?= Html::e($mode !== '' ? $mode : 'phone') ?>"<?= $mode !== '' ? ' data-login-autoopen' : '' ?> hidden>
    <div class="lg-backdrop" data-login-close></div>
    <section class="lg-dialog" role="dialog" aria-modal="true" aria-labelledby="lg-dialog-title">
        <button class="lg-close" type="button" data-login-close aria-label="Fermer"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
        <h2 id="lg-dialog-title">
            <span data-only="phone">Connexion avec votre numéro</span>
            <span data-only="email">Connexion avec votre e-mail</span>
        </h2>
        <?php if ($loginError !== ''): ?>
            <p class="lg-error" role="alert" id="login-error"><?= Html::e($loginError) ?></p>
        <?php endif; ?>
        <form class="auth-form" method="post" action="<?= Html::url('/connexion') ?>" data-login-form>
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <input type="hidden" name="mode" value="<?= Html::e($mode !== '' ? $mode : 'phone') ?>" data-login-mode-input>
            <div class="field" data-only="phone">
                <label for="login-phone"><span>Numéro de téléphone</span></label>
                <input class="input input--auth<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="login-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="<?= $phone ?>"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
            </div>
            <div class="field" data-only="email">
                <label for="email"><span>E-mail</span></label>
                <input class="input input--auth<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" maxlength="190" autocomplete="username" placeholder="vous@exemple.com" value="<?= $email ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?>>
            </div>
            <?php
            $id = 'password';
            $name = 'password';
            $label = 'Mot de passe';
            $error = null;
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
        <p class="lg-switch">
            <span data-only="phone">Inscrit avec un e-mail&nbsp;? <button type="button" data-login-switch="email">Se connecter avec l'e-mail</button></span>
            <span data-only="email">Inscrit avec votre numéro&nbsp;? <button type="button" data-login-switch="phone">Se connecter avec le téléphone</button></span>
        </p>
    </section>
</div>
<script src="<?= Html::asset('assets/js/login.js') ?>" defer></script>
