<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Services\QuoteService;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$value = static function (string $key) use ($old): string {
    $item = $old[$key] ?? '';

    return Html::e(is_scalar($item) ? (string) $item : '');
};
$picked = static fn (string $key): array => is_array($old[$key] ?? null) ? $old[$key] : [];
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error" id="' . $key . '-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' is-invalid" aria-invalid="true" aria-describedby="' . $key . '-error' : '';
$radio = static function (string $name, array $options, string $current): void {
    echo '<div class="choice-grid">';
    foreach ($options as $key => $label) {
        echo '<label class="choice-card"><input type="radio" name="' . $name . '" value="' . Html::e((string) $key) . '"' . ($current === (string) $key ? ' checked' : '') . '><span>' . Html::e($label) . '</span></label>';
    }
    echo '</div>';
};
$checks = static function (string $name, array $options, array $current): void {
    echo '<div class="choice-grid">';
    foreach ($options as $label) {
        echo '<label class="choice-card"><input type="checkbox" name="' . $name . '[]" value="' . Html::e($label) . '"' . (in_array($label, $current, true) ? ' checked' : '') . '><span>' . Html::e($label) . '</span></label>';
    }
    echo '</div>';
};
$activity = is_scalar($old['activity'] ?? null) ? (string) $old['activity'] : '';
/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$activities = $catalog['activities'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main class="page-sheet">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker">Sesam Digital · sites web</p>
                <h1 class="s-display" style="margin-top: 1rem">Votre site web. <span class="s-mark">Décrit en 3 minutes.</span></h1>
            </div>
            <p class="s-lead">Plus vous nous en dites, plus le devis est juste. Vous voulez seulement l'outil Réservation, Boutique ou Immo&nbsp;? <a href="<?= Html::url('/commencer') ?>">Commencez gratuitement</a>, sans devis.</p>
        </div>
    </header>
    <section class="s-wrap quote" style="padding-top: 16px">
    <form class="quote-form" method="post" action="<?= Html::url('/devis') ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

        <fieldset>
            <legend>1. Votre projet</legend>
            <?php $radio('site_state', QuoteService::SITE_STATES, (string) ($old['site_state'] ?? '')); ?>
            <?php $err('site_state'); ?>
            <div class="field">
                <label for="current_site">Adresse du site actuel <small>(si vous en avez un)</small></label>
                <input class="input" id="current_site" name="current_site" type="text" maxlength="200" placeholder="www.monsite.sn" value="<?= $value('current_site') ?>">
            </div>
            <p class="quote-label">Type de site</p>
            <?php $radio('site_type', QuoteService::SITE_TYPES, (string) ($old['site_type'] ?? '')); ?>
            <?php $err('site_type'); ?>
        </fieldset>

        <fieldset>
            <legend>2. Contenu et fonctions</legend>
            <p class="quote-label">Pages souhaitées</p>
            <?php $checks('pages', QuoteService::PAGES, $picked('pages')); ?>
            <p class="quote-label">Fonctions utiles</p>
            <?php $checks('features', QuoteService::FEATURES, $picked('features')); ?>
            <div class="field">
                <label for="message">Décrivez votre projet</label>
                <textarea class="input<?= $invalid('message') ?>" id="message" name="message" required maxlength="2000" rows="5" placeholder="Votre activité, ce que le site doit faire, vos clients…"><?= $value('message') ?></textarea>
                <?php $err('message'); ?>
            </div>
            <div class="field">
                <label for="inspiration">Des sites que vous aimez <small>(facultatif)</small></label>
                <input class="input" id="inspiration" name="inspiration" type="text" maxlength="500" value="<?= $value('inspiration') ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend>3. Budget et délai</legend>
            <p class="quote-label">Nom de domaine</p>
            <?php $radio('has_domain', QuoteService::DOMAINS, (string) ($old['has_domain'] ?? '')); ?>
            <p class="quote-label">Budget envisagé</p>
            <?php $radio('budget', QuoteService::BUDGETS, (string) ($old['budget'] ?? '')); ?>
            <p class="quote-label">Pour quand&nbsp;?</p>
            <?php $radio('deadline', QuoteService::DEADLINES, (string) ($old['deadline'] ?? '')); ?>
        </fieldset>

        <fieldset>
            <legend>4. Vos coordonnées</legend>
            <div class="row-2">
                <div class="field">
                    <label for="full_name">Nom</label>
                    <input class="input<?= $invalid('full_name') ?>" id="full_name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?= $value('full_name') ?>">
                    <?php $err('full_name'); ?>
                </div>
                <div class="field">
                    <label for="business_name">Entreprise <small>(facultatif)</small></label>
                    <input class="input" id="business_name" name="business_name" type="text" maxlength="120" autocomplete="organization" value="<?= $value('business_name') ?>">
                </div>
                <div class="field">
                    <label for="email">E-mail</label>
                    <input class="input<?= $invalid('email') ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $value('email') ?>">
                    <?php $err('email'); ?>
                </div>
                <div class="field">
                    <label for="phone">Téléphone</label>
                    <input class="input<?= $invalid('phone') ?>" id="phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" value="<?= $value('phone') ?>">
                    <?php $err('phone'); ?>
                </div>
            </div>
            <div class="field">
                <label for="activity">Activité</label>
                <select class="select<?= $invalid('activity') ?>" id="activity" name="activity" required>
                    <option value="">Choisir</option>
                    <?php foreach ($activities as $code => $label): ?>
                        <option value="<?= Html::e($code) ?>"<?= $activity === $code ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $err('activity'); ?>
            </div>
        </fieldset>
        <button class="s-btn" type="submit" style="justify-self: start">Envoyer ma demande de devis <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></button>
    </form>
    <aside class="quote-side">
        <div class="panel panel--ink">
            <p class="s-kicker" style="color: var(--accent)">Ce qui se passe ensuite</p>
            <ul class="ticks">
                <li><span>1</span><span><strong>Votre projet est étudié</strong><small>L'équipe Sesam Digital lit chaque demande.</small></span></li>
                <li><span>2</span><span><strong>Un devis détaillé</strong><small>Sous 48 h, par téléphone ou e-mail.</small></span></li>
                <li><span>3</span><span><strong>Votre site en ligne</strong><small>Maquette, validation, mise en ligne.</small></span></li>
            </ul>
        </div>
        <div class="panel">
            <p class="s-kicker" style="color: var(--blue)">Agence</p>
            <p class="s-h3">À partir de 200 000 FCFA, cinq projets par mois.</p>
        </div>
    </aside>
    </section>
</main>
