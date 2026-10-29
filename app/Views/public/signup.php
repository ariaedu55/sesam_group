<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$sector = (string) ($old['sector'] ?? '');
$googleEnabled = !empty($googleEnabled);
$googlePending = is_array($googlePending ?? null) ? $googlePending : null;
$offer = is_array($offer ?? null) ? $offer : ['trial' => true, 'prices' => []];
$trial = !empty($offer['trial']);
$prices = is_array($offer['prices'] ?? null) ? $offer['prices'] : [];
$services = [
    'reservation' => ['Sesam Réservation', 'Salon, institut, coiffure', 'Agenda, réservation en ligne 24 h/24, acomptes, rappels clients.', 'calendar'],
    'boutique' => ['Sesam Boutique', 'Boutique, mode, alimentation', 'Catalogue en ligne, panier, commandes WhatsApp, stock.', 'bag'],
    'loyer' => ['Sesam Immo', 'Propriétaire, agence, gérance', 'Biens, locataires, loyers en ligne, quittances, relances.', 'building'],
];
$monthly = static fn (string $key): int => (int) ($prices[$key]['monthly'] ?? 0);
$setup = static fn (string $key): int => (int) ($prices[$key]['setup'] ?? 0);
$priceLabel = static fn (string $key): string => $monthly($key) > 0 ? Money::format($monthly($key)) . ' / mois' : 'Tarif sur demande';
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$cta = $trial ? 'Créer mon espace gratuitement' : 'Créer mon compte';

?>
<link rel="stylesheet" href="<?= Html::asset('assets/css/welcome.css') ?>">
<link rel="stylesheet" href="<?= Html::asset('assets/css/commencer.css') ?>">
<main class="page-sheet page-commencer cm<?= $trial ? ' cm-trial' : ' cm-paid' ?>">

    <!-- ============ Bannière : l'offre en un coup d'œil ============ -->
    <header class="cm-hero">
        <div class="s-wrap cm-hero-inner">
            <div class="cm-hero-copy">
                <?php if ($trial): ?>
                    <p class="cm-badge"><span class="cm-badge-gift" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M4 11h16v9H4z" fill="#fff"/><path d="M3 7h18v4H3z" fill="#fff"/><path d="M11 7h2v13h-2z" fill="#ff7a59"/><path d="M12 7c-2-4-6-3-5 0zM12 7c2-4 6-3 5 0z" fill="#ff7a59"/></svg></span> Cadeau de bienvenue : <strong>1 mois offert</strong></p>
                    <h1 class="cm-title">Lancez votre commerce en ligne. <span>Le premier mois est pour nous.</span></h1>
                    <p class="cm-lead">Créez votre espace en 3 minutes et utilisez <strong>toutes les fonctions gratuitement pendant 30 jours</strong>. Pas de carte bancaire, pas d'engagement : vous continuez seulement si ça vous plaît.</p>
                <?php else: ?>
                    <p class="cm-badge"><span class="cm-badge-gift" aria-hidden="true">🚀</span> Activation immédiate après paiement</p>
                    <h1 class="cm-title">Lancez votre commerce en ligne. <span>Prêt en 3 minutes.</span></h1>
                    <p class="cm-lead">Créez votre compte, réglez votre premier mois par <strong>Wave ou Orange Money</strong>, et votre espace s'ouvre aussitôt, avec toutes les fonctions. Sans engagement : vous arrêtez quand vous voulez.</p>
                <?php endif; ?>
                <ul class="cm-trust">
                    <li><span aria-hidden="true">✓</span><?= $trial ? 'Sans carte bancaire' : 'Wave et Orange Money' ?></li>
                    <li><span aria-hidden="true">✓</span>Sans engagement</li>
                    <li><span aria-hidden="true">✓</span>Équipe à Dakar pour vous aider</li>
                </ul>
                <div class="cm-hero-actions">
                    <a class="s-btn cm-btn-big" href="#inscription-choix" data-cm-scroll><?= $trial ? 'Commencer mon mois offert' : 'Créer mon compte' ?> <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
                    <button class="cm-btn-guided" type="button" data-cm-signup="phone"><span aria-hidden="true">📱</span> Avec mon numéro de téléphone</button>
                </div>
            </div>

            <!-- Illustration : cadeau qui s'ouvre (essai) ou fusée (activation payante) -->
            <div class="cm-hero-art" aria-hidden="true">
                <span class="cm-halo"></span>
                <?php if ($trial): ?>
                    <svg class="cm-gift" viewBox="0 0 320 300">
                        <g class="cm-sparkles">
                            <path d="M60 70l5 12 12 5-12 5-5 12-5-12-12-5 12-5z"/><path d="M262 54l4 9 9 4-9 4-4 9-4-9-9-4 9-4z"/>
                            <path d="M276 190l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/><path d="M40 196l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
                        </g>
                        <g class="cm-gift-body">
                            <rect class="cm-gift-box" x="88" y="150" width="144" height="118" rx="14"/>
                            <rect class="cm-gift-ribbon" x="148" y="150" width="24" height="118"/>
                            <rect class="cm-gift-shade" x="88" y="150" width="144" height="12"/>
                        </g>
                        <g class="cm-gift-lid">
                            <rect class="cm-gift-box" x="76" y="122" width="168" height="34" rx="10"/>
                            <rect class="cm-gift-ribbon" x="148" y="122" width="24" height="34"/>
                            <path class="cm-gift-bow" d="M160 122c-22-36-62-24-46 0zM160 122c22-36 62-24 46 0z"/>
                        </g>
                        <g class="cm-gift-ticket">
                            <rect x="118" y="70" width="84" height="44" rx="10"/>
                            <text x="160" y="90" text-anchor="middle" class="cm-ticket-big">1 MOIS</text>
                            <text x="160" y="106" text-anchor="middle" class="cm-ticket-small">OFFERT</text>
                        </g>
                    </svg>
                    <div class="cm-confetti"><?php for ($i = 0; $i < 16; $i++): ?><i></i><?php endfor; ?></div>
                    <p class="cm-art-tag">0 FCFA aujourd'hui</p>
                <?php else: ?>
                    <svg class="cm-gift cm-rocket-art" viewBox="0 0 320 300">
                        <g class="cm-sparkles"><path d="M60 70l5 12 12 5-12 5-5 12-5-12-12-5 12-5z"/><path d="M262 54l4 9 9 4-9 4-4 9-4-9-9-4 9-4z"/><path d="M276 190l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/></g>
                        <circle class="cm-planet" cx="160" cy="160" r="86"/>
                        <g class="cm-rocket">
                            <path class="cm-flame" d="M150 236 L160 268 L170 236 Z"/>
                            <path class="cm-rocket-body" d="M160 80 C180 100 186 140 180 236 L140 236 C134 140 140 100 160 80 Z"/>
                            <circle class="cm-rocket-window" cx="160" cy="148" r="12"/>
                            <path class="cm-rocket-fin" d="M140 200 L120 240 L140 232 Z M180 200 L200 240 L180 232 Z"/>
                        </g>
                    </svg>
                    <p class="cm-art-tag">Ouvert dès le paiement</p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ============ Comment ça marche ============ -->
    <section class="s-wrap cm-how" aria-labelledby="cm-how-title">
        <h2 id="cm-how-title" class="cm-section-title">Comment ça marche&nbsp;?</h2>
        <ol class="cm-how-list">
            <li><span class="cm-how-num">1</span><strong>Choisissez votre outil</strong><small>Réservation, Boutique ou Immo, selon votre métier.</small></li>
            <li><span class="cm-how-num">2</span><strong>Créez votre compte</strong><small>Avec votre numéro de téléphone, votre e-mail ou Google.</small></li>
            <li><span class="cm-how-num">3</span><strong><?= $trial ? 'Profitez de votre mois offert' : 'Activez et démarrez' ?></strong><small><?= $trial ? 'Votre espace s\'ouvre tout de suite, gratuit pendant 30 jours.' : 'Réglez le premier mois par Wave ou Orange Money : l\'espace s\'ouvre aussitôt.' ?></small></li>
        </ol>
    </section>

    <!-- ============ Formulaire + récapitulatif de l'offre ============ -->
    <section class="s-wrap cm-layout quote" id="inscription">
        <div class="cm-choose" id="inscription-choix">
            <header class="cm-form-head">
                <p class="cm-form-kicker"><?= $trial ? 'Inscription gratuite' : 'Inscription' ?></p>
                <h2>Créez votre espace</h2>
                <p class="cm-form-sub">Choisissez comment vous inscrire. Environ 2 minutes, sans paiement<?= $trial ? '' : ' avant la fin' ?>.</p>
            </header>

            <?php if ($googlePending !== null): ?>
                <button class="cm-choose-btn is-primary" type="button" data-cm-signup="google">
                    <span class="cm-choose-icon" aria-hidden="true">✓</span>
                    <span><strong>Terminer mon inscription Google</strong><small><?= Html::e((string) $googlePending['email']) ?> · il ne manque que votre outil et votre numéro</small></span>
                    <span class="cm-choose-arrow" aria-hidden="true"><?php $name = 'arrow'; include $icon; ?></span>
                </button>
            <?php endif; ?>
            <div class="cm-choose-list">
                <button class="cm-choose-btn<?= $googlePending === null ? ' is-primary' : '' ?>" type="button" data-cm-signup="phone">
                    <span class="cm-choose-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><rect x="6" y="2.5" width="12" height="19" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10.5 18.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                    <span><strong>Continuer avec mon numéro de téléphone</strong><small>Le plus simple : vous vous connecterez avec votre numéro</small></span>
                    <span class="cm-choose-arrow" aria-hidden="true"><?php $name = 'arrow'; include $icon; ?></span>
                </button>
                <button class="cm-choose-btn" type="button" data-cm-signup="email">
                    <span class="cm-choose-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><rect x="3" y="5" width="18" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 7l8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></span>
                    <span><strong>Continuer avec mon e-mail</strong><small>Votre numéro WhatsApp vous sera aussi demandé</small></span>
                    <span class="cm-choose-arrow" aria-hidden="true"><?php $name = 'arrow'; include $icon; ?></span>
                </button>
                <?php if ($googleEnabled && $googlePending === null): ?>
                    <p class="cm-choose-or"><span>ou</span></p>
                    <div class="cm-choose-google">
                        <?php $googleFrom = 'signup'; $googleService = $sector; include dirname(__DIR__) . '/partials/google-button.php'; ?>
                    </div>
                <?php endif; ?>
            </div>
            <p class="signup-login">Déjà un compte&nbsp;? <a href="<?= Html::url('/connexion') ?>">Se connecter</a></p>
        </div>

        <aside class="cm-aside">
            <!-- Récapitulatif qui suit le service choisi -->
            <section class="cm-offer" aria-live="polite" data-cm-offer>
                <p class="cm-offer-kicker"><?= $trial ? '🎁 Votre offre de bienvenue' : 'Votre offre' ?></p>
                <h3 data-cm-offer-title><?= $sector !== '' && isset($services[$sector]) ? Html::e($services[$sector][0]) : 'Choisissez votre outil' ?></h3>
                <dl class="cm-offer-lines">
                    <div><dt>Aujourd'hui</dt><dd data-cm-offer-today><?= $trial ? '0 FCFA' : '—' ?></dd></div>
                    <?php if ($trial): ?>
                        <div><dt>Pendant 30 jours</dt><dd>Gratuit, tout inclus</dd></div>
                        <div><dt>Ensuite</dt><dd data-cm-offer-after>Selon l'outil choisi</dd></div>
                    <?php else: ?>
                        <div><dt>Ensuite</dt><dd data-cm-offer-after>Chaque mois, sans engagement</dd></div>
                    <?php endif; ?>
                </dl>
                <ul class="cm-offer-ticks">
                    <li>Toutes les fonctions, sans limite</li>
                    <li>Page publique à partager sur WhatsApp</li>
                    <li>Notifications sur votre téléphone</li>
                    <li>Aide au démarrage par l'équipe SESAM</li>
                </ul>
            </section>

            <section class="cm-next">
                <p class="cm-next-kicker">Ce qui se passe ensuite</p>
                <ol>
                    <?php if ($trial): ?>
                        <li><b>Votre espace s'ouvre tout de suite</b><small>Vous êtes connecté dès l'inscription.</small></li>
                        <li><b>30 jours pour tout essayer</b><small>Rappel avant la fin de l'essai, aucune surprise.</small></li>
                        <li><b>Vous continuez si ça vous plaît</b><small>Abonnement en 2 clics, par Wave ou Orange Money.</small></li>
                    <?php else: ?>
                        <li><b>Votre compte est créé</b><small>Vous arrivez sur la page de paiement.</small></li>
                        <li><b>Vous réglez le premier mois</b><small>Wave ou Orange Money, en toute sécurité.</small></li>
                        <li><b>Votre espace s'ouvre aussitôt</b><small>Toutes les fonctions, sans engagement.</small></li>
                    <?php endif; ?>
                </ol>
            </section>

            <section class="cm-help">
                <p><b>Une question avant de vous lancer&nbsp;?</b> Notre équipe vous répond.</p>
                <button class="cm-help-btn" type="button" data-cm-signup="phone">📱 Inscription avec mon numéro</button>
                <a class="cm-help-link" href="<?= Html::url('/devis') ?>">Besoin d'un site web sur mesure&nbsp;? Demander un devis</a>
            </section>
        </aside>
    </section>

    <!-- ============ Questions fréquentes ============ -->
    <section class="s-wrap cm-faq" aria-labelledby="cm-faq-title">
        <h2 id="cm-faq-title" class="cm-section-title">Vos questions</h2>
        <div class="cm-faq-list">
            <?php if ($trial): ?>
                <details open><summary>Dois-je payer quelque chose aujourd'hui&nbsp;?</summary><p>Non. Votre premier mois est offert, sans carte bancaire. Vous ne payez que si vous décidez de continuer après 30 jours.</p></details>
                <details><summary>Que se passe-t-il à la fin du mois offert&nbsp;?</summary><p>Vous recevez un rappel quelques jours avant. Vous choisissez alors la durée (1, 3, 6 ou 12 mois) et payez par Wave ou Orange Money. Si vous ne continuez pas, votre espace est mis en pause et vos données sont conservées.</p></details>
            <?php else: ?>
                <details open><summary>Combien coûte l'activation&nbsp;?</summary><p>Le prix du premier mois de l'outil choisi (indiqué sur chaque carte), plus d'éventuels frais d'activation affichés au même endroit. Vous payez par Wave ou Orange Money et votre espace s'ouvre aussitôt.</p></details>
                <details><summary>Y a-t-il un engagement&nbsp;?</summary><p>Non. Vous payez mois par mois (ou 3, 6, 12 mois si vous préférez) et vous arrêtez quand vous voulez.</p></details>
            <?php endif; ?>
            <details><summary>Ai-je besoin d'une application ou d'un ordinateur&nbsp;?</summary><p>Non. SESAM fonctionne dans le navigateur de votre téléphone. Vous pouvez aussi l'ajouter à votre écran d'accueil.</p></details>
            <details><summary>Puis-je être aidé pour démarrer&nbsp;?</summary><p>Oui. Après l'inscription, l'équipe SESAM vous contacte sur WhatsApp pour configurer votre espace avec vous.</p></details>
        </div>
    </section>

    <!-- ============ Popups de la page (inscription guidée, « attendez », aide) ============ -->
    <?php
    $signupErrors = $errors;
    $signupOld = $old;
    $signupMode = $googlePending !== null ? 'google' : (in_array($old['mode'] ?? '', ['phone', 'email'], true) ? (string) $old['mode'] : 'phone');
    $signupAutoOpen = $errors !== [] || $googlePending !== null;
    include dirname(__DIR__) . '/partials/commencer-popups.php';
    ?>
</main>
<script src="<?= Html::asset('assets/js/auth.js') ?>" defer></script>
<script src="<?= Html::asset('assets/js/commencer.js') ?>" defer></script>
