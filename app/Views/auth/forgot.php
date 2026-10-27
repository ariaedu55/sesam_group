<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$email = Html::e(is_scalar($old['email'] ?? null) ? (string) $old['email'] : '');
$status = is_string($status ?? null) ? $status : '';

?>
<section class="auth-card">
    <header class="auth-card-head">
        <p class="auth-card-eyebrow">Compte SESAM</p>
        <h1>Mot de passe oublié</h1>
        <p class="auth-card-lead">Saisissez l'e-mail de votre compte : un lien vous sera envoyé (valable une heure).</p>
    </header>
    <?php if ($status !== ''): ?>
        <p class="alert" role="status"><?= Html::e($status) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= Html::url('/mot-de-passe') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="email"><span>E-mail</span></label>
            <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= $email ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p class="field-error" id="email-error"><?= Html::e($errors['email']) ?></p><?php endif; ?>
        </div>
        <button class="btn btn-primary" type="submit">Recevoir le lien par e-mail</button>
    </form>
    <p class="auth-help"><a href="<?= Html::url('/connexion') ?>">Retour à la connexion</a></p>
</section>
