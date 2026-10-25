<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$nav = $catalog['nav'];
$services = $catalog['services'];
$boutique = $services['boutique'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$mocks = __DIR__ . '/mocks/';
$arrow = static function () use ($icon): void {
    $name = 'arrow';
    echo '<span class="s-btn-arrow">';
    include $icon;
    echo '</span>';
};
$words = ['votre boutique', 'vos réservations', 'vos loyers', 'votre site'];
$bento = [
    'boutique' => ['blue', 'wide', 'shop'],
    'reservation' => ['lilac', 'narrow', 'booking'],
    'immo' => ['lime', 'narrow', 'rent'],
    'agence' => ['ink', 'wide', 'site'],
];

?>
<main>
    <section class="hx" data-hero aria-labelledby="hx-title">
        <span class="hx-blob" data-blob aria-hidden="true"></span>
        <div class="s-wrap hx-grid">
            <div class="hx-copy">
                <a class="s-eyebrow" href="<?= Html::url('/services/boutique') ?>"><b>Nouveau</b> Sesam Boutique : vendez sur WhatsApp →</a>
                <h1 class="s-display hx-title" id="hx-title">
                    <span class="line"><span>Le digital</span></span>
                    <span class="line"><span>qui fait vendre</span></span>
                    <span class="line"><span>
                        <span class="sr-only">votre boutique, vos réservations, vos loyers et votre site.</span>
                        <span class="hx-rot" aria-hidden="true" data-hx-rot><span class="hx-rot-list">
                            <?php foreach ([...$words, $words[0]] as $word): ?>
                                <span><?= Html::e($word) ?></span>
                            <?php endforeach; ?>
                        </span></span>
                    </span></span>
                </h1>
                <p class="s-lead"><?= Html::e($landing['pitch']) ?> Vos clients commandent, réservent et paient depuis un simple lien, sans application.</p>
                <div class="s-actions">
                    <a class="s-btn" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <?php $arrow(); ?></a>
                    <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/services') ?>">Tous les services</a>
                </div>
                <p class="hx-proof">
                    <span class="hx-faces" aria-hidden="true">
                        <span style="background: var(--accent)">AD</span>
                        <span style="background: var(--lilac)">FS</span>
                        <span style="background: var(--coral)">MN</span>
                        <span style="background: var(--sun)">KB</span>
                    </span>
                    <span><strong>Pensé pour les commerces du Sénégal.</strong><br>Wave, Orange Money et WhatsApp intégrés.</span>
                </p>
            </div>

            <?php require __DIR__ . '/partials/home-hero-stage.php'; ?>
        </div>
    </section>

    <div class="mq" aria-hidden="true">
        <div class="mq-track">
            <?php for ($loop = 0; $loop < 2; $loop++): ?>
                <?php foreach (['Sites web', 'Boutique en ligne', 'Commandes WhatsApp', 'Réservation', 'Gestion immobilière', 'Wave', 'Orange Money', 'Sans application'] as $word): ?>
                    <span><?= Html::e($word) ?></span>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>

    <section class="s-section" aria-labelledby="bento-title">
        <div class="s-wrap">
            <header class="s-head s-head--split" data-reveal>
                <div>
                    <p class="s-kicker">Une plateforme, quatre leviers</p>
                    <h2 class="s-h2" id="bento-title">Tout ce qu'il faut pour <span class="s-mark">vendre plus</span>, au même endroit.</h2>
                </div>
                <p class="s-lead">Le site d'abord, puis l'outil de votre métier. Chaque commerce a son espace à son nom et ne voit que ses données.</p>
            </header>
            <div class="bento">
                <?php $delay = 0; ?>
                <?php foreach ($bento as $key => [$tone, $size, $mock]): ?>
                    <?php $service = $services[$key]; ?>
                    <a class="bento-card bento-card--<?= Html::e($tone) ?> bento-card--<?= Html::e($size) ?>" href="<?= Html::url('/services/' . $key) ?>" data-service="<?= Html::e($key) ?>" data-reveal data-reveal-delay="<?= $delay++ % 2 ?>">
                        <div class="bento-copy">
                            <div class="bento-top">
                                <span class="bento-tag"><?php $name = (string) $service['icon']; include $icon; ?><?= Html::e($nav[$key]) ?></span>
                                <span class="bento-go" aria-hidden="true"><?php $name = 'arrow'; include $icon; ?></span>
                            </div>
                            <h3><?= Html::e($service['title']) ?></h3>
                            <p><?= Html::e($service['points'][0]) ?>.</p>
                        </div>
                        <div class="bento-visual"><?php require $mocks . $mock . '.php'; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="s-section" aria-labelledby="spot-title" style="padding-top: 0">
        <div class="s-wrap spot">
            <div class="spot-visual" data-reveal aria-hidden="true">
                <?php require $mocks . 'shop.php'; ?>
                <p class="spot-bubble"><strong>Fatou</strong>Bonjour ! Je souhaite commander 1 × Robe wax et 1 × Sac raphia. Total : 37 000 FCFA</p>
                <p class="spot-label">+1 commande <span>reçue sur WhatsApp</span></p>
            </div>
            <div class="spot-copy" data-reveal data-reveal-delay="1">
                <p class="s-kicker" style="color: var(--blue)"><?= Html::e($boutique['label']) ?></p>
                <h2 class="s-h2" id="spot-title">Votre boutique en ligne. <span class="s-mark">Sans créer de site.</span></h2>
                <p class="s-lead"><?= Html::e($boutique['lead']) ?></p>
                <ul class="ticks">
                    <?php foreach ([
                        ['link', 'Un lien à votre nom', 'À partager dans vos statuts, votre bio Instagram, vos groupes.'],
                        ['chat', 'Commandes sur WhatsApp', 'Le panier arrive chez vous, déjà rédigé, avec le total.'],
                        ['box', 'Stock et clients suivis', 'Le stock baisse tout seul, chaque client a sa fiche.'],
                    ] as [$tickIcon, $tickTitle, $tickText]): ?>
                        <li><span><?php $name = $tickIcon; include $icon; ?></span><span><strong><?= Html::e($tickTitle) ?></strong><small><?= Html::e($tickText) ?></small></span></li>
                    <?php endforeach; ?>
                </ul>
                <div class="s-actions">
                    <a class="s-btn s-btn--blue" href="<?= Html::url('/services/boutique') ?>">Découvrir Sesam Boutique <?php $arrow(); ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="s-section" aria-labelledby="steps-title" style="padding-top: 0">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker">Comment ça se passe</p>
                <h2 class="s-h2" id="steps-title">Du site à la gestion de tous les jours.</h2>
            </header>
            <ol class="steps">
                <?php foreach ($catalog['journey'] as $index => $step): ?>
                    <li data-reveal data-reveal-delay="<?= $index ?>">
                        <h3><?= Html::e($step['label']) ?></h3>
                        <p><?= Html::e($step['text']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <section class="s-wrap" aria-labelledby="figures-title">
        <div class="dark" data-reveal>
            <p class="s-kicker" style="color: var(--accent)">SESAM en chiffres</p>
            <h2 class="s-h2" id="figures-title">Simple pour vous. <span class="s-muted">Évident pour vos clients.</span></h2>
            <ul class="figures">
                <li><strong>5</strong><span>projets web livrés par mois, pour garder la qualité</span></li>
                <li><strong>1</strong><span>lien à partager, aucune application à installer</span></li>
                <li><strong>3</strong><span>outils métier : Boutique, Réservation, Immo</span></li>
                <li><strong>2</strong><span>moyens de paiement : Wave et Orange Money</span></li>
            </ul>
        </div>
    </section>

    <section class="s-section" aria-labelledby="offers-title">
        <div class="s-wrap">
            <header class="s-head s-head--split" data-reveal>
                <div>
                    <p class="s-kicker">Sesam Digital — Agence</p>
                    <h2 class="s-h2" id="offers-title">Un site à votre image, facturé au projet.</h2>
                </div>
                <p class="s-lead">L'outil SaaS de votre métier s'y ajoute ensuite en abonnement mensuel, quand vous le souhaitez.</p>
            </header>
            <div class="offers">
                <?php foreach ($catalog['offers'] as $index => $offer): ?>
                    <article class="offer<?= $index === 1 ? ' offer--hot' : '' ?>" data-reveal data-reveal-delay="<?= $index ?>">
                        <?php if ($index === 1): ?><span class="offer-badge">Le plus choisi</span><?php endif; ?>
                        <h3><?= Html::e($offer['name']) ?></h3>
                        <p class="offer-price"><?= Html::e($offer['price']) ?></p>
                        <p><?= Html::e($offer['description']) ?></p>
                        <a class="s-link" href="<?= Html::url('/services/agence') ?>">Voir l'offre site web <?php $name = 'arrow'; include $icon; ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="s-wrap" aria-labelledby="cta-title" style="padding-bottom: 8px">
        <div class="cta" data-reveal>
            <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
            <h2 class="s-display" id="cta-title">Prêt à faire décoller votre commerce&nbsp;?</h2>
            <div class="s-actions">
                <a class="s-btn" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <?php $arrow(); ?></a>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/contact') ?>">Nous écrire</a>
            </div>
            <div class="cta-orb" aria-hidden="true">
                <svg viewBox="0 0 120 120">
                    <defs><path id="cta-circle" d="M60 60m-46 0a46 46 0 1 1 92 0a46 46 0 1 1 -92 0"/></defs>
                    <text font-size="12" font-weight="800" letter-spacing="2.6" fill="#3cc4f5"><textPath href="#cta-circle">DEVIS GRATUIT · RÉPONSE RAPIDE · </textPath></text>
                </svg>
            </div>
        </div>
    </section>
</main>
