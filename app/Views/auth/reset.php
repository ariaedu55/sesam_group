<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$errors = is_array($errors ?? null) ? $errors : [];
$token = is_string($token ?? null) ? $token : '';

?>
<section class="auth-card">
    <header class="auth-card-head">
        <p class="auth-card-eyebrow">Compte SESAM</p>
        <h1>Nouveau mot de passe</h1>
        <p class="auth-card-lead">Au moins 12 caractères, lettres et chiffres recommandés.</p>
    </header>
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
