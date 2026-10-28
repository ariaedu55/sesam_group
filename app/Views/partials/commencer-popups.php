<?php

declare(strict_types=1);

/*
 * Popups de la page « Commencer » (pilotés par commencer.js, styles welcome.css + commencer.css) :
 *  - guided : inscription guidée dans le popup (accueil cadeau, puis 4 étapes : outil, vous, connexion, récapitulatif) ;
 *  - wait   : le visiteur s'apprête à partir → rappel de l'offre, reprise en 1 minute ;
 *  - help   : le visiteur hésite sur le formulaire → proposition d'aide guidée.
 * Le formulaire du popup envoie exactement les mêmes champs que celui de la page (POST /commencer).
 */

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

$trial = !empty($trial);
$services = is_array($services ?? null) ? $services : [];
$monthly = $monthly ?? static fn (string $key): int => 0;
$setup = $setup ?? static fn (string $key): int => 0;
$priceLabel = $priceLabel ?? static fn (string $key): string => '';
$googleEnabled = !empty($googleEnabled) && ($googlePending ?? null) === null;
$close = '<button class="wl-close" type="button" data-cm-close aria-label="Fermer"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';
$giftArt = '<svg class="wl-scene" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid meet">'
    . '<g class="wl-stars"><circle cx="36" cy="34" r="1.8"/><circle cx="364" cy="30" r="2"/><circle cx="352" cy="186" r="1.5"/><circle cx="46" cy="182" r="1.8"/><circle cx="120" cy="20" r="1.3"/></g>'
    . '<g class="wl-burst"><path d="M200 70v-26M232 82l18-18M168 82l-18-18M244 112h24M156 112h-24"/></g>'
    . '<g class="wl-gift"><rect class="wl-gift-box" x="150" y="112" width="100" height="80" rx="10"/><rect class="wl-gift-ribbon" x="192" y="112" width="16" height="80"/></g>'
    . '<g class="wl-gift-lid"><rect class="wl-gift-box" x="142" y="94" width="116" height="24" rx="8"/><rect class="wl-gift-ribbon" x="192" y="94" width="16" height="24"/><path class="wl-gift-bow" d="M200 94c-14-22-36-14-26 0zM200 94c14-22 36-14 26 0z"/></g>'
    . '<g class="wl-float wl-float-a wl-badge-month"><circle cx="318" cy="76" r="34"/><text x="318" y="72" text-anchor="middle">1 mois</text><text x="318" y="90" text-anchor="middle" class="wl-badge-small">offert</text></g>'
    . '<g class="wl-float wl-float-b"><circle cx="72" cy="140" r="20"/><path class="wl-float-check" d="M63 140l6 6 12-13"/></g>'
    . '</svg>';
$rocketArt = '<svg class="wl-scene" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid meet">'
    . '<g class="wl-stars"><circle cx="40" cy="40" r="2"/><circle cx="330" cy="34" r="2"/><circle cx="362" cy="96" r="1.5"/><circle cx="58" cy="150" r="1.5"/></g>'
    . '<circle class="wl-orbit" cx="200" cy="112" r="82"/><circle class="wl-planet" cx="200" cy="112" r="46"/><ellipse class="wl-ring" cx="200" cy="116" rx="70" ry="14"/>'
    . '<g class="wl-rocket"><g class="wl-flame"><path d="M193 168 L200 192 L207 168 Z"/></g><path class="wl-rocket-body" d="M200 70 C214 84 218 112 214 164 L186 164 C182 112 186 84 200 70 Z"/><circle class="wl-rocket-window" cx="200" cy="112" r="8"/><path class="wl-rocket-fin" d="M186 140 L172 168 L186 162 Z M214 140 L228 168 L214 162 Z"/></g>'
    . '</svg>';

?>
<!-- ======================= Inscription guidée ======================= -->
<div class="wl wl-site cm-modal" data-cm-modal="guided" hidden>
    <div class="wl-backdrop" data-cm-close></div>
    <section class="wl-card cm-guided" role="dialog" aria-modal="true" aria-labelledby="cm-guided-title">
        <?= $close ?>
        <div class="wl-art cm-guided-art" aria-hidden="true"><span class="wl-dots"></span><?= $trial ? $giftArt : $rocketArt ?></div>
        <form class="wl-body cm-guided-body" method="post" action="<?= Html::url('/commencer') ?>" data-cm-guided novalidate>
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

            <!-- Écran d'accueil -->
            <div class="cm-g-step" data-g-step="intro">
                <p class="wl-eyebrow"><?= $trial ? '🎁 Cadeau de bienvenue' : 'Inscription guidée' ?></p>
                <h2 id="cm-guided-title"><?= $trial ? 'Votre premier mois est offert' : 'Votre espace prêt en 3 minutes' ?></h2>
                <p class="wl-text"><?= $trial
                    ? 'Je vous guide en 4 petites étapes, ici même. Aucun paiement aujourd\'hui, aucune carte bancaire.'
                    : 'Je vous guide en 4 petites étapes, ici même. Ensuite, vous réglez le premier mois et votre espace s\'ouvre.' ?></p>
                <ul class="wl-bullets"><li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>2 minutes</li><li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Sans engagement</li><li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Aide de l'équipe</li></ul>
                <div class="wl-site-actions">
                    <button class="wl-btn" type="button" data-g-next>C'est parti</button>
                    <button class="wl-btn wl-btn-ghost" type="button" data-cm-close data-cm-goto-form>Je préfère la page</button>
                </div>
            </div>

            <!-- Étape 1 : l'outil -->
            <div class="cm-g-step" data-g-step="sector" hidden>
                <p class="cm-g-progress"><span>Étape 1 sur 4</span><i style="--p: 25%"></i></p>
                <h2>Quel outil vous faut-il&nbsp;?</h2>
                <div class="cm-g-services">
                    <?php foreach ($services as $key => [$label, $for]): ?>
                        <label class="cm-g-service">
                            <input type="radio" name="sector" value="<?= Html::e($key) ?>" data-cm-label="<?= Html::e($label) ?>" data-cm-monthly="<?= $monthly($key) ?>" data-cm-setup="<?= $setup($key) ?>">
                            <span><b><?= Html::e($label) ?></b><small><?= Html::e($for) ?> · <?= $trial ? '1 mois offert' : Html::e($priceLabel($key)) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="cm-g-error" data-g-error hidden></p>
            </div>

            <!-- Étape 2 : vous -->
            <div class="cm-g-step" data-g-step="you" hidden>
                <p class="cm-g-progress"><span>Étape 2 sur 4</span><i style="--p: 50%"></i></p>
                <h2>Parlez-nous de vous</h2>
                <div class="cm-g-fields">
                    <label>Votre nom<input name="full_name" type="text" maxlength="120" autocomplete="name" placeholder="Prénom et nom"></label>
                    <label>Nom de votre commerce <small>(facultatif)</small><input name="business_name" type="text" maxlength="120" autocomplete="organization" placeholder="Ex. Salon Awa"></label>
                    <label>Numéro WhatsApp<input name="phone" type="tel" inputmode="tel" maxlength="40" autocomplete="tel" placeholder="77 123 45 67"></label>
                </div>
                <p class="cm-g-error" data-g-error hidden></p>
            </div>

            <!-- Étape 3 : connexion -->
            <div class="cm-g-step" data-g-step="login" hidden>
                <p class="cm-g-progress"><span>Étape 3 sur 4</span><i style="--p: 75%"></i></p>
                <h2>Votre connexion</h2>
                <div class="cm-g-fields">
                    <label>E-mail<input name="email" type="email" maxlength="190" autocomplete="email" placeholder="vous@exemple.com"></label>
                    <label>Mot de passe <small>(12 caractères minimum)</small><input name="password" type="password" minlength="12" autocomplete="new-password"></label>
                    <label>Confirmer le mot de passe<input name="password_confirm" type="password" minlength="12" autocomplete="new-password"></label>
                </div>
                <?php if ($googleEnabled): ?>
                    <button class="cm-g-google" type="button" data-cm-google>Ou continuer avec Google</button>
                <?php endif; ?>
                <p class="cm-g-error" data-g-error hidden></p>
            </div>

            <!-- Étape 4 : récapitulatif et création -->
            <div class="cm-g-step" data-g-step="recap" hidden>
                <p class="cm-g-progress"><span>Étape 4 sur 4</span><i style="--p: 100%"></i></p>
                <h2>On vérifie ensemble&nbsp;?</h2>
                <dl class="cm-g-recap">
                    <div><dt>Outil</dt><dd data-g-recap="sector">—</dd><button type="button" data-g-goto="sector">Modifier</button></div>
                    <div><dt>Vous</dt><dd data-g-recap="you">—</dd><button type="button" data-g-goto="you">Modifier</button></div>
                    <div><dt>E-mail</dt><dd data-g-recap="email">—</dd><button type="button" data-g-goto="login">Modifier</button></div>
                    <div class="is-price"><dt>Aujourd'hui</dt><dd data-g-recap="today"><?= $trial ? '0 FCFA · 1 mois offert' : '—' ?></dd></div>
                </dl>
                <label class="cm-g-terms"><input type="checkbox" name="terms" value="1"> <span>J'accepte que SESAM crée mon espace et me contacte pour m'aider à démarrer.</span></label>
                <p class="cm-g-error" data-g-error hidden></p>
            </div>

            <div class="cm-g-nav" data-g-nav hidden>
                <button class="wl-btn wl-btn-ghost" type="button" data-g-back>Retour</button>
                <button class="wl-btn" type="button" data-g-next>Continuer</button>
                <button class="wl-btn cm-g-submit" type="submit" data-g-submit hidden><?= $trial ? 'Créer mon espace gratuit' : 'Créer mon compte' ?></button>
            </div>
        </form>
    </section>
</div>

<!-- ======================= « Attendez ! » (le visiteur s'en va) ======================= -->
<div class="wl wl-site cm-modal" data-cm-modal="wait" hidden>
    <div class="wl-backdrop" data-cm-close></div>
    <section class="wl-card" role="dialog" aria-modal="true" aria-labelledby="cm-wait-title">
        <?= $close ?>
        <div class="wl-art" aria-hidden="true"><span class="wl-dots"></span><?= $trial ? $giftArt : $rocketArt ?></div>
        <div class="wl-body">
            <p class="wl-eyebrow">Attendez&nbsp;!</p>
            <h2 id="cm-wait-title"><?= $trial ? 'Votre mois offert vous attend 🎁' : 'Il ne vous reste que 2 minutes' ?></h2>
            <p class="wl-text"><?= $trial
                ? 'Vous y êtes presque : terminez votre inscription et profitez de 30 jours gratuits, sans carte bancaire. Vous pouvez arrêter à tout moment.'
                : 'Terminez votre inscription : votre espace s\'ouvre dès le paiement du premier mois, sans engagement.' ?></p>
            <div class="wl-site-actions">
                <button class="wl-btn" type="button" data-cm-switch="guided">Terminer en 1 minute</button>
                <button class="wl-btn wl-btn-ghost" type="button" data-cm-close>Plus tard</button>
            </div>
        </div>
    </section>
</div>

<!-- ======================= Aide (le visiteur hésite sur le formulaire) ======================= -->
<div class="wl wl-site cm-modal" data-cm-modal="help" hidden>
    <div class="wl-backdrop" data-cm-close></div>
    <section class="wl-card" role="dialog" aria-modal="true" aria-labelledby="cm-help-title">
        <?= $close ?>
        <div class="wl-art" aria-hidden="true"><span class="wl-dots"></span>
            <svg class="wl-scene" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid meet">
                <g class="wl-stars"><circle cx="36" cy="34" r="1.8"/><circle cx="364" cy="30" r="2"/><circle cx="352" cy="186" r="1.5"/></g>
                <g class="wl-float wl-float-a wl-bubble"><path d="M110 60h150a16 16 0 0 1 16 16v44a16 16 0 0 1 -16 16h-104l-22 20v-20h-24a16 16 0 0 1 -16 -16v-44a16 16 0 0 1 16 -16z"/><path class="wl-float-line" d="M130 88h110M130 108h70"/></g>
                <g class="wl-float wl-float-b wl-bubble"><path d="M236 130h70a14 14 0 0 1 14 14v26a14 14 0 0 1 -14 14h-10l-14 14v-14h-46a14 14 0 0 1 -14 -14v-26a14 14 0 0 1 14 -14z"/><path class="wl-bubble-check" d="M250 158l7 7 13-14"/></g>
            </svg>
        </div>
        <div class="wl-body">
            <p class="wl-eyebrow">Besoin d'un coup de main&nbsp;?</p>
            <h2 id="cm-help-title">On fait l'inscription ensemble&nbsp;?</h2>
            <p class="wl-text">Je vous pose les questions une par une, avec un récapitulatif avant de valider. C'est plus simple et ça prend 2 minutes.</p>
            <div class="wl-site-actions">
                <button class="wl-btn" type="button" data-cm-switch="guided">Oui, guidez-moi</button>
                <button class="wl-btn wl-btn-ghost" type="button" data-cm-close>Je continue seul</button>
            </div>
        </div>
    </section>
</div>
