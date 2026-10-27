<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$value = static fn (string $key): string => Html::e(is_scalar($old[$key] ?? null) ? (string) $old[$key] : '');
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error" id="' . $key . '-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid" aria-invalid="true" aria-describedby="' . $key . '-error' : '';
$sector = (string) ($old['sector'] ?? '');
$services = [
    'reservation' => ['Sesam Réservation', 'Salon, institut, coiffure : agenda, rendez-vous en ligne, rappels WhatsApp.', 'calendar'],
    'boutique' => ['Sesam Boutique', 'Catalogue, stock, commandes WhatsApp et clients.', 'bag'],
    'loyer' => ['Sesam Immo', 'Biens, locataires, loyers, quittances et relances.', 'building'],
];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main class="page-sheet page-commencer">
    <header class="signup-hero">
        <div class="s-wrap signup-hero-inner">
            <p class="signup-hero-badge">Essai gratuit 1 mois · sans carte bancaire</p>
            <h1 class="signup-hero-title">Créez votre espace <span class="s-mark">en une minute</span></h1>
            <p class="signup-hero-lead">Trois étapes : votre métier, vos coordonnées, puis votre compte. Vous entrez ensuite directement dans votre espace.</p>
        </div>
    </header>

    <section class="s-wrap signup-layout quote">
        <form class="signup-form quote-form" method="post" action="<?= Html::url('/commencer') ?>" data-signup-form novalidate>
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

            <ol class="signup-steps-nav" data-signup-nav aria-label="Étapes du formulaire">
                <li data-step="1"><span>1</span> Service</li>
                <li data-step="2"><span>2</span> Vous</li>
                <li data-step="3"><span>3</span> Connexion</li>
            </ol>

            <fieldset class="signup-step signup-services is-current" data-signup-step="1">
                <legend class="signup-step-title"><span class="signup-step-num" aria-hidden="true">1</span> Votre service</legend>
                <div class="signup-service-grid">
                    <?php foreach ($services as $key => [$label, $hint, $iconName]): ?>
                        <label class="signup-service">
                            <input type="radio" name="sector" value="<?= Html::e($key) ?>"<?= $sector === $key ? ' checked' : '' ?> required>
                            <span class="signup-service-icon" aria-hidden="true"><?php $name = $iconName; include $icon; ?></span>
                            <span class="signup-service-copy">
                                <strong><?= Html::e($label) ?></strong>
                                <small><?= Html::e($hint) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php $err('sector'); ?>
            </fieldset>

            <fieldset class="signup-step" data-signup-step="2">
                <legend class="signup-step-title"><span class="signup-step-num" aria-hidden="true">2</span> Vous</legend>
                <div class="signup-fields">
                    <div class="field">
                        <label for="full_name">Votre nom</label>
                        <input class="input signup-input<?= $invalid('full_name') ?>" id="full_name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?= $value('full_name') ?>">
                        <?php $err('full_name'); ?>
                    </div>
                    <div class="field">
                        <label for="business_name">Nom de votre commerce <small>(facultatif)</small></label>
                        <input class="input signup-input" id="business_name" name="business_name" type="text" maxlength="120" autocomplete="organization" placeholder="Ex. Salon Awa, Boutique Teranga…" value="<?= $value('business_name') ?>">
                    </div>
                    <div class="field">
                        <label for="phone">Numéro WhatsApp</label>
                        <input class="input signup-input<?= $invalid('phone') ?>" id="phone" name="phone" type="tel" required maxlength="40" autocomplete="tel" placeholder="77 123 45 67" value="<?= $value('phone') ?>">
                        <?php $err('phone'); ?>
                    </div>
                </div>
            </fieldset>

            <fieldset class="signup-step" data-signup-step="3">
                <legend class="signup-step-title"><span class="signup-step-num" aria-hidden="true">3</span> Votre connexion</legend>
                <div class="signup-fields">
                    <div class="field">
                        <label for="email">E-mail</label>
                        <input class="input signup-input<?= $invalid('email') ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="vous@exemple.com" value="<?= $value('email') ?>">
                        <?php $err('email'); ?>
                    </div>
                    <?php
                    $id = 'password';
                    $name = 'password';
                    $label = 'Mot de passe';
                    $error = $errors['password'] ?? null;
                    $errorId = 'password-error';
                    $autocomplete = 'new-password';
                    $minlength = 12;
                    include dirname(__DIR__) . '/partials/password-field.php';
                    ?>
                    <?php
                    $id = 'password_confirm';
                    $name = 'password_confirm';
                    $label = 'Confirmer le mot de passe';
                    $error = $errors['password_confirm'] ?? null;
                    $errorId = 'password_confirm-error';
                    $autocomplete = 'new-password';
                    $minlength = 12;
                    include dirname(__DIR__) . '/partials/password-field.php';
                    ?>
                    <label class="signup-terms">
                        <input type="checkbox" name="terms" value="1"<?= ($old['terms'] ?? '') !== '' ? ' checked' : '' ?> required>
                        <span>J'accepte que SESAM crée mon espace et me contacte pour m'aider à démarrer.</span>
                    </label>
                    <?php $err('terms'); ?>
                </div>
            </fieldset>

            <div class="signup-nav">
                <button class="s-btn s-btn--ghost s-btn--plain signup-nav-back" type="button" data-signup-back hidden>Retour</button>
                <button class="s-btn signup-nav-next" type="button" data-signup-next>
                    Continuer
                    <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span>
                </button>
            </div>
            <div class="signup-submit" data-signup-submit>
                <button class="s-btn signup-submit-btn" type="submit">
                    Créer mon espace gratuitement
                    <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span>
                </button>
            </div>
            <p class="signup-login">Déjà un compte&nbsp;? <a href="<?= Html::url('/connexion') ?>">Se connecter</a></p>
            <script src="<?= Html::asset('assets/js/signup.js') ?>"></script>
        </form>

        <aside class="quote-side signup-aside">
            <div class="panel panel--ink signup-aside-panel">
                <p class="s-kicker" style="color: var(--accent)">Ce qui se passe ensuite</p>
                <ul class="ticks signup-ticks">
                    <li><span>1</span><span><strong>Votre espace s'ouvre tout de suite</strong><small>Vous êtes connecté dès l'inscription.</small></span></li>
                    <li><span>2</span><span><strong>1 mois pour tout essayer</strong><small>Toutes les fonctions, sans engagement.</small></span></li>
                    <li><span>3</span><span><strong>Vous continuez si ça vous plaît</strong><small>Réabonnement en 2 clics depuis la page Abonnement.</small></span></li>
                </ul>
            </div>
            <div class="panel signup-aside-panel signup-aside-secondary">
                <p class="s-kicker" style="color: var(--blue)">Un site web aussi&nbsp;?</p>
                <p class="s-h3">Sesam Digital crée votre site sur devis.</p>
                <p><a class="s-link" href="<?= Html::url('/devis') ?>">Demander un devis site web</a></p>
            </div>
        </aside>
    </section>
</main>
<script src="<?= Html::asset('assets/js/auth.js') ?>" defer></script>
