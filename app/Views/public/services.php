<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$nav = $catalog['nav'];
$common = $catalog['common'];
$services = $catalog['services'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$mocks = __DIR__ . '/mocks/';
$arrow = static function () use ($icon): void {
    $name = 'arrow';
    echo '<span class="s-btn-arrow">';
    include $icon;
    echo '</span>';
};
$look = [
    'agence' => ['ink', 'site'],
    'boutique' => ['blue', 'shop'],
    'reservation' => ['lilac', 'booking'],
    'immo' => ['lime', 'rent'],
];

?>
<main class="page-services">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
                <h1 class="s-display" style="margin-top: 1rem">Un site clair. <span class="s-mark">Trois outils</span> pour vendre.</h1>
            </div>
            <div style="display: grid; gap: 1.4rem">
                <p class="s-lead"><?= Html::e($landing['lead']) ?></p>
                <div class="s-actions">
                    <a class="s-btn" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <?php $arrow(); ?></a>
                </div>
            </div>
        </div>
    </header>

    <section class="s-section" style="padding-top: 24px" aria-labelledby="services-saas-title">
        <div class="s-wrap">
            <h2 class="sr-only" id="services-saas-title">Trois outils, un abonnement mensuel</h2>
            <div class="bento">
                <?php $index = 0; ?>
                <?php foreach (['boutique', 'reservation', 'immo', 'agence'] as $key): ?>
                    <?php [$tone, $mock] = $look[$key]; $service = $services[$key]; ?>
                    <a class="bento-card bento-card--<?= Html::e($tone) ?> <?= $index === 0 || $index === 3 ? 'bento-card--wide' : 'bento-card--narrow' ?>" id="<?= Html::e($key) ?>" href="<?= Html::url('/services/' . $key) ?>" data-reveal>
                        <div class="bento-copy">
                            <div class="bento-top">
                                <span class="bento-tag"><?php $name = (string) $service['icon']; include $icon; ?><?= Html::e($service['label']) ?></span>
                                <span class="bento-go" aria-hidden="true"><?php $name = 'arrow'; include $icon; ?></span>
                            </div>
                            <h3><?= Html::e($service['title']) ?></h3>
                            <p><?= Html::e($service['lead']) ?></p>
                        </div>
                        <div class="bento-visual"><?php require $mocks . $mock . '.php'; ?></div>
                    </a>
                    <?php $index++; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="s-wrap" aria-labelledby="common-title">
        <div class="dark" data-reveal>
            <p class="s-kicker" style="color: var(--accent)">Inclus dans chaque outil SaaS</p>
            <h2 class="s-h2" id="common-title">Le même socle pour Réservation, Boutique et Immo.</h2>
            <ul class="figures">
                <li><strong>1</strong><span>lien unique, sans application ni compte client</span></li>
                <li><strong>2</strong><span><?= Html::e($common['wallets'][0]) ?> et <?= Html::e($common['wallets'][1]) ?></span></li>
                <li><strong>100%</strong><span>vos données, sous votre nom, invisibles des autres commerces</span></li>
                <li><strong>24/7</strong><span><?= Html::e($common['whatsapp']) ?></span></li>
            </ul>
        </div>
    </section>

    <section class="s-section" aria-labelledby="path-title">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker">Le parcours</p>
                <h2 class="s-h2" id="path-title">Trois étapes, un seul interlocuteur.</h2>
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

    <section class="s-wrap">
        <div class="cta" data-reveal>
            <p class="s-kicker">Agence : à partir de 200 000 FCFA</p>
            <h2 class="s-display">On démarre votre projet&nbsp;?</h2>
            <div class="s-actions">
                <a class="s-btn" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <?php $arrow(); ?></a>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/offres') ?>">Voir les offres agence</a>
            </div>
        </div>
    </section>
</main>
