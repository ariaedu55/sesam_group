<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$offers = $catalog['offers'];
$landing = $catalog['landing'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main class="page-sheet">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
                <h1 class="s-display" style="margin-top: 1rem">Trois formules. <span class="s-mark">Un site qui vous ressemble.</span></h1>
            </div>
            <p class="s-lead">Ces montants concernent la conception ou la refonte du site, facturée au projet. L'abonnement mensuel de Réservation, Boutique ou Immo se décide avec vous : aucun tarif SaaS n'est affiché ici.</p>
        </div>
    </header>

    <section class="s-wrap" style="padding-block: 24px 64px">
        <div class="offers">
            <?php foreach ($offers as $index => $offer): ?>
                <article class="offer<?= $index === 1 ? ' offer--hot' : '' ?>" data-reveal data-reveal-delay="<?= $index ?>">
                    <p class="s-kicker">Agence</p>
                    <?php if ($index === 1): ?><span class="offer-badge">Le plus choisi</span><?php endif; ?>
                    <h2 class="s-h3"><?= Html::e($offer['name']) ?></h2>
                    <p class="offer-price"><?= Html::e($offer['price']) ?></p>
                    <p><?= Html::e($offer['description']) ?></p>
                    <a class="s-link" href="<?= Html::url('/devis?offre=' . $index) ?>">Demander un devis pour ce site <?php $name = 'arrow'; include $icon; ?></a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="s-wrap">
        <div class="dark" data-reveal>
            <p class="s-kicker" style="color: var(--accent)">Capacité</p>
            <h2 class="s-h2">Cinq projets web par mois. <span class="s-muted">Pas un de plus.</span></h2>
            <p class="s-lead" style="color: rgba(255,255,255,.75); margin-top: 1rem">Sesam Digital limite les livraisons pour garder la qualité. Le site peut être neuf, ou recevoir un outil sur un site déjà en ligne.</p>
            <div class="s-actions" style="margin-top: 1.6rem">
                <a class="s-btn s-btn--lime" href="<?= Html::url('/devis') ?>">Réserver ma place <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/services') ?>">Voir les services</a>
            </div>
        </div>
    </section>
</main>
