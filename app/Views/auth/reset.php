<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$errors = is_array($errors ?? null) ? $errors : [];
$token = is_string($token ?? null) ? $token : '';

?>
<section class="auth-card card">
    <p class="eyebrow">Connexion SESAM</p>
    <h1>Nouveau mot de passe</h1>
    <p class="muted">Choisissez un mot de passe d'au moins 12 caractères pour votre compte (équipe SESAM ou commerce).</p>
    <form method="post" action="<?= Html::url('/mot-de-passe/reinitialiser/' . $token) ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <?php
        $id = 'password';
        $name = 'password';
        $label = 'Mot de passe';
        $error = $errors['password'] ?? null;
        $errorId = 'password-error';
        $autocomplete = 'new-password';
        $minlength = 12;
        include dirname(__DIR__) . '/partials/password-field.php';
        $id = 'password_confirmation';
        $name = 'password_confirmation';
        $label = 'Confirmation';
        $error = $errors['password_confirmation'] ?? null;
        $errorId = 'password-confirmation-error';
        include dirname(__DIR__) . '/partials/password-field.php';
        ?>
        <button class="btn btn-primary" type="submit">Enregistrer le mot de passe</button>
    </form>
    <p class="auth-help"><a href="<?= Html::url('/connexion') ?>">Retour à la connexion</a></p>
</section>
