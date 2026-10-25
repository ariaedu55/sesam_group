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
<main class="page-sheet">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker">Essai gratuit 1 mois · sans carte bancaire</p>
                <h1 class="s-display" style="margin-top: 1rem">Créez votre espace. <span class="s-mark">En 1 minute.</span></h1>
            </div>
            <p class="s-lead">Choisissez votre outil, créez votre compte : vous entrez directement dans votre espace. À la fin de l'essai, vous continuez avec l'abonnement mensuel, payé par Wave ou Orange Money.</p>
        </div>
    </header>

    <section class="s-wrap quote signup" style="padding-top: 16px">
        <form class="quote-form" method="post" action="<?= Html::url('/commencer') ?>" novalidate>
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

            <fieldset class="signup-services">
                <legend>1. Votre service</legend>
                <?php foreach ($services as $key => [$label, $hint, $iconName]): ?>
                    <label class="signup-service">
                        <input type="radio" name="sector" value="<?= Html::e($key) ?>"<?= $sector === $key ? ' checked' : '' ?> required>
                        <span class="signup-service-icon" aria-hidden="true"><?php $name = $iconName; include $icon; ?></span>
                        <span><strong><?= Html::e($label) ?></strong><small><?= Html::e($hint) ?></small></span>
                    </label>
                <?php endforeach; ?>
                <?php $err('sector'); ?>
            </fieldset>

            <fieldset>
                <legend>2. Vous</legend>
                <div class="field">
                    <label for="full_name">Votre nom</label>
                    <input class="input<?= $invalid('full_name') ?>" id="full_name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?= $value('full_name') ?>">
                    <?php $err('full_name'); ?>
                </div>
                <div class="field">
                    <label for="business_name">Nom de votre commerce <small>(facultatif)</small></label>
                    <input class="input" id="business_name" name="business_name" type="text" maxlength="120" autocomplete="organization" placeholder="Ex. Salon Awa, Boutique Teranga…" value="<?= $value('business_name') ?>">
                </div>
                <div class="field">
                    <label for="phone">Numéro WhatsApp</label>
                    <input class="input<?= $invalid('phone') ?>" id="phone" name="phone" type="tel" required maxlength="40" autocomplete="tel" placeholder="77 123 45 67" value="<?= $value('phone') ?>">
                    <?php $err('phone'); ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>3. Votre connexion</legend>
                <div class="field">
                    <label for="email">E-mail</label>
                    <input class="input<?= $invalid('email') ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $value('email') ?>">
                    <?php $err('email'); ?>
                </div>
                <div class="field">
                    <label for="password">Mot de passe <small>(12 caractères minimum)</small></label>
                    <input class="input<?= $invalid('password') ?>" id="password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password">
                    <?php $err('password'); ?>
                </div>
                <div class="field">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input class="input<?= $invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" type="password" required minlength="12" maxlength="255" autocomplete="new-password">
                    <?php $err('password_confirm'); ?>
                </div>
                <label class="signup-terms"><input type="checkbox" name="terms" value="1"<?= ($old['terms'] ?? '') !== '' ? ' checked' : '' ?> required> <span>J'accepte que SESAM crée mon espace et me contacte pour m'aider à démarrer.</span></label>
                <?php $err('terms'); ?>
            </fieldset>

            <button class="s-btn" type="submit" style="justify-self: start">Créer mon espace gratuitement <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></button>
            <p class="signup-login">Déjà un compte&nbsp;? <a href="<?= Html::url('/connexion') ?>">Se connecter</a></p>
        </form>

        <aside class="quote-side">
            <div class="panel panel--ink">
                <p class="s-kicker" style="color: var(--accent)">Ce qui se passe ensuite</p>
                <ul class="ticks">
                    <li><span>1</span><span><strong>Votre espace s'ouvre tout de suite</strong><small>Vous êtes connecté dès l'inscription.</small></span></li>
                    <li><span>2</span><span><strong>1 mois pour tout essayer</strong><small>Toutes les fonctions, sans engagement.</small></span></li>
                    <li><span>3</span><span><strong>Vous continuez si ça vous plaît</strong><small>Réabonnement en 2 clics depuis la page Abonnement.</small></span></li>
                </ul>
            </div>
            <div class="panel">
                <p class="s-kicker" style="color: var(--blue)">Un site web aussi&nbsp;?</p>
                <p class="s-h3">Sesam Digital crée votre site sur devis.</p>
                <p><a class="s-link" href="<?= Html::url('/devis') ?>">Demander un devis site web</a></p>
            </div>
        </aside>
    </section>
</main>
