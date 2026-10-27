<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$email = Html::e(is_scalar($old['email'] ?? null) ? (string) $old['email'] : '');
$status = is_string($status ?? null) ? $status : '';

?>
<section class="auth-card card">
    <p class="eyebrow">Connexion SESAM</p>
    <h1>Connexion</h1>
    <p class="muted">Une seule page pour toute l'équipe SESAM et pour les commerces abonnés (Réservation, Boutique, Immo). Utilisez l'e-mail reçu à la création de votre compte.</p>
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
        <button class="btn btn-primary" type="submit">Se connecter</button>
    </form>
    <p class="auth-help"><a href="<?= Html::url('/mot-de-passe') ?>">Mot de passe oublié</a></p>
    <p class="auth-help auth-help-secondary">Nouveau commerce&nbsp;? <a href="<?= Html::url('/commencer') ?>">Commencer l'essai gratuit</a></p>
</section>
