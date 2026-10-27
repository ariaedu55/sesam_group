<?php

declare(strict_types=1);

/*
 * Popups du site public.
 *  - Accueil, Services, Offres, À propos, Réalisations : une variante tirée au hasard parmi 7 à chaque visite.
 *  - Pages d'un service (Réservation, Boutique, Immo, Sites web) : le popup dédié à ce service.
 * Ouverture 2 secondes après l'arrivée, une fois par visite et par page (rien sur Contact et Devis,
 * pour ne pas gêner un formulaire). Fermeture par la croix, « Non merci », le fond ou Échap.
 * Mêmes styles et script que le popup de bienvenue (welcome.css, welcome.js).
 */

use Sesam\Helpers\Html;

$current = is_string($current ?? null) ? $current : '';
$variants = [
    'welcome' => [
        'scene' => 'home',
        'eyebrow' => 'Bienvenue chez SESAM',
        'title' => 'Votre commerce mérite des outils simples',
        'text' => 'Réservations, boutique en ligne, gestion locative ou site web : SESAM vous équipe en quelques minutes.',
        'bullets' => ['1 mois d\'essai gratuit', 'Wave et Orange Money', 'Une équipe à Dakar'],
        'primary' => ['Essayer gratuitement', '/commencer'],
        'secondary' => ['Les services', '/services'],
    ],
    'offer' => [
        'scene' => 'gift',
        'eyebrow' => 'Offre de bienvenue',
        'title' => 'Votre premier mois est offert',
        'text' => 'Toutes les fonctions, sans carte bancaire et sans engagement. Vous continuez seulement si ça vous plaît.',
        'bullets' => ['Prêt en 1 minute', 'Sans carte bancaire', 'Sans engagement'],
        'primary' => ['J\'en profite', '/commencer'],
        'secondary' => ['Voir les tarifs', '/offres'],
    ],
    'demo' => [
        'scene' => 'phone',
        'eyebrow' => 'Essayez sans rien installer',
        'title' => 'Voyez SESAM en vrai, tout de suite',
        'text' => 'Ouvrez un salon, une boutique ou une agence immobilière de démonstration, exactement comme vos clients les verront.',
        'bullets' => ['Démo en direct', 'Sur téléphone et ordinateur', 'Aucune inscription'],
        'primary' => ['Voir un salon', '/t/demo-reservation'],
        'secondary' => ['Voir une boutique', '/t/demo-boutique'],
    ],
    'reservation' => [
        'scene' => 'reservation',
        'eyebrow' => 'Sesam Réservation',
        'title' => 'Votre agenda se remplit, même la nuit',
        'text' => 'Vos clientes choisissent leur prestation et un horaire libre en 1 minute. Elles reçoivent un rappel la veille.',
        'bullets' => ['Lien à partager sur WhatsApp', 'Acompte Wave ou Orange Money', 'Rappels automatiques'],
        'primary' => ['Créer mon salon', '/commencer?service=reservation'],
        'secondary' => ['Voir la démo', '/t/demo-reservation'],
    ],
    'boutique' => [
        'scene' => 'boutique',
        'eyebrow' => 'Sesam Boutique',
        'title' => 'Vendez sur WhatsApp, sans effort',
        'text' => 'Votre catalogue en ligne, un panier simple, et la commande arrive toute prête sur WhatsApp.',
        'bullets' => ['Vitrine à vos couleurs', 'Stock tenu à jour', 'Alerte de stock bas'],
        'primary' => ['Ouvrir ma boutique', '/commencer?service=boutique'],
        'secondary' => ['Voir la démo', '/t/demo-boutique'],
    ],
    'immo' => [
        'scene' => 'immo',
        'eyebrow' => 'Sesam Immo',
        'title' => 'Vos loyers se suivent tout seuls',
        'text' => 'Biens, locataires, contrats de bail et quittances au même endroit. Les relances partent sans que vous y pensiez.',
        'bullets' => ['Loyers payés en ligne', 'Quittances en un clic', 'Relances automatiques'],
        'primary' => ['Essayer Sesam Immo', '/commencer?service=loyer'],
        'secondary' => ['Voir la démo', '/t/demo-loyer'],
    ],
    'agence' => [
        'scene' => 'agence',
        'eyebrow' => 'Sesam Digital',
        'title' => 'Un site web qui vous ressemble',
        'text' => 'Site vitrine, boutique ou refonte : un site rapide, beau sur téléphone et pensé pour faire venir vos clients.',
        'bullets' => ['Devis gratuit sous 48 h', 'Design sur mesure', 'Suivi après la mise en ligne'],
        'primary' => ['Demander mon devis', '/devis'],
        'secondary' => ['Nos réalisations', '/portfolio'],
    ],
];
$dedicated = [
    'service-reservation' => 'reservation',
    'service-boutique' => 'boutique',
    'service-immo' => 'immo',
    'service-agence' => 'agence',
];
$randomPages = ['accueil', 'services', 'offres', 'a-propos', 'portfolio'];
if (isset($dedicated[$current])) {
    $variantKey = $dedicated[$current];
} elseif (in_array($current, $randomPages, true)) {
    $keys = array_keys($variants);
    $variantKey = $keys[random_int(0, count($keys) - 1)];
} else {
    return;
}
$popup = $variants[$variantKey];
$scene = (string) $popup['scene'];

?>
<link rel="stylesheet" href="<?= Html::asset('assets/css/welcome.css') ?>">
<div class="wl wl-site wl-scene-<?= Html::e($scene) ?>" data-welcome data-variant="<?= Html::e($variantKey) ?>" data-delay="2000" data-once="<?= Html::e($current) ?>" data-once-storage="session" hidden>
    <div class="wl-backdrop" data-welcome-close></div>
    <section class="wl-card" role="dialog" aria-modal="true" aria-labelledby="wl-title">
        <button class="wl-close" type="button" data-welcome-close aria-label="Fermer">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <div class="wl-art" aria-hidden="true">
            <span class="wl-dots"></span>
            <svg class="wl-scene" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid meet">
                <g class="wl-stars"><circle cx="36" cy="34" r="1.8"/><circle cx="364" cy="30" r="2"/><circle cx="352" cy="186" r="1.5"/><circle cx="46" cy="182" r="1.8"/><circle cx="120" cy="20" r="1.3"/><circle cx="290" cy="200" r="1.3"/></g>
                <?php if ($scene === 'home'): ?>
                    <!-- Fusée entourée des trois métiers -->
                    <circle class="wl-orbit" cx="200" cy="112" r="82"/>
                    <circle class="wl-planet" cx="200" cy="112" r="46"/>
                    <ellipse class="wl-ring" cx="200" cy="116" rx="70" ry="14"/>
                    <circle class="wl-moonlet" cx="282" cy="112" r="7"/>
                    <g class="wl-rocket">
                        <g class="wl-flame"><path d="M193 168 L200 192 L207 168 Z"/></g>
                        <path class="wl-rocket-body" d="M200 70 C214 84 218 112 214 164 L186 164 C182 112 186 84 200 70 Z"/>
                        <circle class="wl-rocket-window" cx="200" cy="112" r="8"/>
                        <path class="wl-rocket-fin" d="M186 140 L172 168 L186 162 Z M214 140 L228 168 L214 162 Z"/>
                    </g>
                    <g class="wl-float wl-float-a"><rect x="36" y="64" width="74" height="50" rx="12"/><path class="wl-icon-stroke" d="M52 80h22v18H52zM52 86h22M58 76v6M68 76v6"/><path class="wl-float-line" d="M84 84h14M84 94h10"/></g>
                    <g class="wl-float wl-float-b"><rect x="290" y="128" width="76" height="50" rx="12"/><path class="wl-icon-stroke" d="M306 146h18l-2 16h-14zM310 146v-3a5 5 0 0 1 10 0v3"/><path class="wl-float-line" d="M334 148h18M334 158h12"/></g>
                    <g class="wl-float wl-float-c"><rect x="52" y="140" width="64" height="44" rx="12"/><path class="wl-icon-stroke" d="M66 166l9-8 9 8v10H66zM72 176v-6h6v6"/><path class="wl-float-line" d="M92 162h12"/></g>
                <?php elseif ($scene === 'gift'): ?>
                    <!-- Cadeau dont le couvercle saute, étoiles qui jaillissent, badge « 1 mois » -->
                    <g class="wl-burst"><path d="M200 70v-26M232 82l18-18M168 82l-18-18M244 112h24M156 112h-24"/></g>
                    <g class="wl-gift">
                        <rect class="wl-gift-box" x="150" y="112" width="100" height="80" rx="10"/>
                        <rect class="wl-gift-ribbon" x="192" y="112" width="16" height="80"/>
                    </g>
                    <g class="wl-gift-lid">
                        <rect class="wl-gift-box" x="142" y="94" width="116" height="24" rx="8"/>
                        <rect class="wl-gift-ribbon" x="192" y="94" width="16" height="24"/>
                        <path class="wl-gift-bow" d="M200 94c-14-22-36-14-26 0zM200 94c14-22 36-14 26 0z"/>
                    </g>
                    <g class="wl-float wl-float-a wl-badge-month"><circle cx="318" cy="76" r="34"/><text x="318" y="72" text-anchor="middle">1 mois</text><text x="318" y="90" text-anchor="middle" class="wl-badge-small">offert</text></g>
                    <g class="wl-float wl-float-b"><circle cx="72" cy="140" r="20"/><path class="wl-float-check" d="M63 140l6 6 12-13"/></g>
                <?php elseif ($scene === 'phone'): ?>
                    <!-- Téléphone où arrivent des notifications, doigt qui touche l'écran -->
                    <g class="wl-phone">
                        <rect class="wl-phone-body" x="148" y="22" width="104" height="196" rx="20"/>
                        <rect class="wl-phone-screen" x="158" y="40" width="84" height="170" rx="10"/>
                        <rect class="wl-notif wl-notif-a" x="164" y="56" width="72" height="30" rx="8"/>
                        <rect class="wl-notif wl-notif-b" x="164" y="94" width="72" height="30" rx="8"/>
                        <rect class="wl-notif wl-notif-c" x="164" y="132" width="72" height="30" rx="8"/>
                        <circle class="wl-notif-dot wl-notif-a" cx="176" cy="71" r="6"/><circle class="wl-notif-dot wl-notif-b" cx="176" cy="109" r="6"/><circle class="wl-notif-dot wl-notif-c" cx="176" cy="147" r="6"/>
                    </g>
                    <g class="wl-tap"><circle cx="230" cy="180" r="10"/><circle class="wl-tap-wave" cx="230" cy="180" r="10"/></g>
                    <g class="wl-float wl-float-a"><rect x="40" y="70" width="80" height="50" rx="12"/><path class="wl-icon-stroke" d="M56 86h22v18H56zM56 92h22M62 82v6M72 82v6"/><path class="wl-float-line" d="M88 90h22M88 100h14"/></g>
                    <g class="wl-float wl-float-b"><rect x="282" y="120" width="80" height="50" rx="12"/><path class="wl-icon-stroke" d="M298 138h18l-2 16h-14zM302 138v-3a5 5 0 0 1 10 0v3"/><path class="wl-float-line" d="M326 140h24M326 150h14"/></g>
                <?php elseif ($scene === 'reservation'): ?>
                    <!-- Agenda dont les créneaux se cochent, horloge qui tourne, message WhatsApp -->
                    <g class="wl-cal">
                        <rect class="wl-card-white" x="96" y="44" width="170" height="146" rx="16"/>
                        <path class="wl-cal-head" d="M96 60 a16 16 0 0 1 16 -16 h138 a16 16 0 0 1 16 16 v14 H96 Z"/>
                        <path class="wl-cal-ring" d="M130 36v18M232 36v18"/>
                        <?php for ($r = 0; $r < 3; $r++): for ($c = 0; $c < 4; $c++): ?>
                            <rect class="wl-cal-cell<?= in_array($r * 4 + $c, [1, 6, 10], true) ? ' is-booked' : '' ?>" x="<?= 112 + $c * 38 ?>" y="<?= 88 + $r * 32 ?>" width="28" height="22" rx="6" style="animation-delay: <?= 0.3 + ($r * 4 + $c) * 0.08 ?>s"/>
                        <?php endfor; endfor; ?>
                    </g>
                    <g class="wl-clock"><circle cx="300" cy="78" r="30"/><path class="wl-clock-hand wl-clock-long" d="M300 78V58"/><path class="wl-clock-hand wl-clock-short" d="M300 78h14"/><circle class="wl-clock-pin" cx="300" cy="78" r="3"/></g>
                    <g class="wl-float wl-float-a wl-bubble"><path d="M276 132h86a12 12 0 0 1 12 12v22a12 12 0 0 1 -12 12h-62l-14 12v-12h-10a12 12 0 0 1 -12 -12v-22a12 12 0 0 1 12 -12z"/><path class="wl-bubble-check" d="M290 156l7 7 12-13"/><path class="wl-float-line" d="M318 152h40M318 164h26"/></g>
                <?php elseif ($scene === 'boutique'): ?>
                    <!-- Sac de courses où tombent des articles, étiquette prix qui se balance -->
                    <g class="wl-drop wl-drop-a"><rect x="168" y="40" width="26" height="30" rx="6"/></g>
                    <g class="wl-drop wl-drop-b"><circle cx="222" cy="52" r="14"/></g>
                    <g class="wl-drop wl-drop-c"><path d="M196 30l12 20h-24z"/></g>
                    <g class="wl-bag">
                        <path class="wl-bag-handle" d="M176 98v-14a24 24 0 0 1 48 0v14"/>
                        <path class="wl-bag-body" d="M146 96h108l-10 100h-88z"/>
                        <circle class="wl-bag-dot" cx="180" cy="120" r="5"/><circle class="wl-bag-dot" cx="220" cy="120" r="5"/>
                        <path class="wl-bag-smile" d="M182 140q18 16 36 0"/>
                    </g>
                    <g class="wl-tag"><path d="M292 66h46l14 18-14 18h-46z"/><circle class="wl-tag-hole" cx="300" cy="84" r="4"/><path class="wl-float-line" d="M312 80h20M312 90h12"/></g>
                    <g class="wl-float wl-float-b wl-bubble"><path d="M40 120h76a12 12 0 0 1 12 12v20a12 12 0 0 1 -12 12h-52l-12 12v-12h-12a12 12 0 0 1 -12 -12v-20a12 12 0 0 1 12 -12z"/><path class="wl-bubble-check" d="M50 142l6 6 11-12"/><path class="wl-float-line" d="M76 138h36M76 150h22"/></g>
                <?php elseif ($scene === 'immo'): ?>
                    <!-- Immeuble dont les fenêtres s'allument une à une, clé qui flotte -->
                    <g class="wl-building">
                        <rect class="wl-card-white" x="120" y="50" width="110" height="150" rx="10"/>
                        <path class="wl-roof" d="M110 56l65-30 65 30z"/>
                        <?php for ($r = 0; $r < 4; $r++): for ($c = 0; $c < 3; $c++): ?>
                            <rect class="wl-window-light" x="<?= 136 + $c * 30 ?>" y="<?= 68 + $r * 28 ?>" width="18" height="16" rx="3" style="animation-delay: <?= 0.2 + (($r * 3 + $c) * 7 % 12) * 0.12 ?>s"/>
                        <?php endfor; endfor; ?>
                        <rect class="wl-door" x="164" y="176" width="22" height="24" rx="4"/>
                    </g>
                    <rect class="wl-card-white" x="250" y="120" width="70" height="80" rx="8"/>
                    <path class="wl-roof" d="M244 124l41-24 41 24z"/>
                    <rect class="wl-window-light" x="264" y="136" width="16" height="14" rx="3" style="animation-delay: 1.4s"/>
                    <rect class="wl-window-light" x="290" y="136" width="16" height="14" rx="3" style="animation-delay: 1.7s"/>
                    <g class="wl-float wl-float-a wl-key"><circle cx="64" cy="96" r="16"/><circle class="wl-key-hole" cx="64" cy="96" r="6"/><path d="M78 102l30 18M96 112l-6 8M104 117l-6 8"/></g>
                    <path class="wl-hills" d="M0 204 Q100 186 200 200 T400 196 V220 H0 Z"/>
                <?php else: ?>
                    <!-- Fenêtre de navigateur qui se construit bloc par bloc -->
                    <g class="wl-window">
                        <rect class="wl-window-frame" x="70" y="30" width="260" height="168" rx="16"/>
                        <path class="wl-window-bar" d="M70 46 a16 16 0 0 1 16 -16 h228 a16 16 0 0 1 16 16 v10 H70 Z"/>
                        <circle class="wl-light" cx="88" cy="43" r="4"/><circle class="wl-light" cx="102" cy="43" r="4"/><circle class="wl-light" cx="116" cy="43" r="4"/>
                        <rect class="wl-build wl-build-hero" x="88" y="70" width="224" height="46" rx="8"/>
                        <rect class="wl-build" x="88" y="126" width="68" height="54" rx="8"/>
                        <rect class="wl-build" x="166" y="126" width="68" height="54" rx="8"/>
                        <rect class="wl-build" x="244" y="126" width="68" height="54" rx="8"/>
                        <path class="wl-build-text" d="M104 88h90M104 100h56"/>
                    </g>
                    <g class="wl-float wl-float-a wl-cursor"><path d="M300 150l0 34 9-9 7 15 7-3-7-15h13z"/></g>
                    <g class="wl-float wl-float-b"><rect x="22" y="120" width="60" height="60" rx="14"/><path class="wl-icon-stroke" d="M38 158l8-18 8 12 6-8 8 14z"/></g>
                <?php endif; ?>
            </svg>
        </div>
        <div class="wl-body">
            <p class="wl-eyebrow"><?= Html::e((string) $popup['eyebrow']) ?></p>
            <h2 id="wl-title"><?= Html::e((string) $popup['title']) ?></h2>
            <p class="wl-text"><?= Html::e((string) $popup['text']) ?></p>
            <ul class="wl-bullets">
                <?php foreach ($popup['bullets'] as $bullet): ?>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><?= Html::e((string) $bullet) ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="wl-site-actions">
                <a class="wl-btn" href="<?= Html::url((string) $popup['primary'][1]) ?>" data-welcome-main><?= Html::e((string) $popup['primary'][0]) ?></a>
                <a class="wl-btn wl-btn-ghost" href="<?= Html::url((string) $popup['secondary'][1]) ?>"><?= Html::e((string) $popup['secondary'][0]) ?></a>
            </div>
            <button class="wl-nothanks" type="button" data-welcome-close>Non merci</button>
        </div>
    </section>
</div>
<script src="<?= Html::asset('assets/js/welcome.js') ?>" defer></script>
