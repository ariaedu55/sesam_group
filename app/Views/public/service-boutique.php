<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$service = $catalog['services']['boutique'];
$page = $service['page'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$demo = require __DIR__ . '/partials/boutique-products.php';
$products = $demo['products'];
$byKey = [];
foreach ($products as $product) {
    $byKey[$product[0]] = $product;
}
$pic = static function (string $key, bool $eager = false) use ($byKey): string {
    return Html::picture('boutique/' . $byKey[$key][7], '', 240, 240, !$eager);
};
$arrow = static function () use ($icon): void {
    $name = 'arrow';
    echo '<span class="s-btn-arrow">';
    include $icon;
    echo '</span>';
};
// Étagères de la devanture : un peu de chaque rayon.
$shelves = [
    ['robe', 'parfum', 'ecouteurs', 'plante'],
    ['baskets', 'karite', 'montre', 'sac'],
    ['cafe', 'bissap', 'bougie', 'bijoux'],
];
$phoneItems = ['robe', 'baskets', 'parfum', 'ecouteurs'];

?>
<link rel="stylesheet" href="<?= Html::asset('assets/css/service-boutique.css') ?>">
<main class="bq-page bx" data-skin="boutique">

    <!-- ================= Bannière : une vraie boutique, en ligne ================= -->
    <header class="bx-hero">
        <div class="s-wrap bx-hero-grid">
            <div class="bx-hero-copy">
                <p class="s-eyebrow"><b>Nouveau</b><?= Html::e($service['label']) ?></p>
                <h1 class="s-display bx-title"><?= Html::e($page['hero_title']) ?></h1>
                <p class="s-h3"><span class="s-mark"><?= Html::e($page['hero_title_line']) ?></span></p>
                <p class="s-lead"><?= Html::e($page['hero_lead']) ?></p>
                <div class="s-actions">
                    <a class="s-btn s-btn--blue" href="<?= Html::url('/commencer?service=boutique') ?>">Essayer gratuitement 1 mois <?php $arrow(); ?></a>
                    <a class="s-btn s-btn--ghost s-btn--plain" href="#fonctionnalites">Voir l'arrière-boutique</a>
                </div>
                <ul class="bx-chips" aria-label="Ce qui est inclus">
                    <li>Mode</li><li>Beauté</li><li>Électronique</li><li>Maison</li><li>Épicerie</li><li>Accessoires</li>
                </ul>
            </div>

            <div class="bx-store" aria-hidden="true">
                <!-- Store rayé et enseigne -->
                <svg class="bx-awning" viewBox="0 0 520 96" preserveAspectRatio="none">
                    <rect x="0" y="0" width="520" height="22" rx="6" fill="#071a3d"/>
                    <?php for ($i = 0; $i < 13; $i++): ?>
                        <path d="M<?= $i * 40 ?> 20h40v46a20 20 0 0 1 -40 0z" fill="<?= $i % 2 === 0 ? '#ff7a59' : '#ffffff' ?>"/>
                    <?php endfor; ?>
                </svg>
                <p class="bx-sign"><span>Chez Awa</span><small>Boutique en ligne</small></p>

                <div class="bx-facade">
                    <div class="bx-window">
                        <?php foreach ($shelves as $row => $items): ?>
                            <div class="bx-shelf-row">
                                <?php foreach ($items as $index => $key): ?>
                                    <span class="bx-item" style="--d: <?= ($row * 4 + $index) * 0.18 ?>s"><?= $pic($key, true) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="bx-door">
                        <span class="bx-door-sign">Ouvert</span>
                        <span class="bx-door-knob"></span>
                    </div>
                </div>
                <div class="bx-ground"></div>

                <!-- Le téléphone : la même boutique, en ligne -->
                <div class="bx-phone">
                    <div class="bx-phone-screen">
                        <p class="bx-phone-head"><b>Chez Awa</b><span>🛒 2</span></p>
                        <p class="bx-phone-search">Rechercher un produit…</p>
                        <div class="bx-phone-grid">
                            <?php foreach ($phoneItems as $key): ?>
                                <span class="bx-phone-card"><span><?= $pic($key, true) ?></span><b><?= Html::e(Money::format((int) $byKey[$key][3])) ?></b></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="bx-phone-cta">Commander sur WhatsApp</p>
                    </div>
                </div>

                <p class="bx-toast bx-toast--order"><i>✓</i><span><strong>Nouvelle commande</strong><small>Fatou · 37 000 FCFA</small></span></p>
                <p class="bx-toast bx-toast--stock"><i>📦</i><span><strong>Stock à jour</strong><small>Baskets : 3 restantes</small></span></p>
                <p class="bx-toast bx-toast--pay"><i>₣</i><span><strong>Paiement reçu</strong><small>Wave · 25 000 FCFA</small></span></p>
            </div>
        </div>
    </header>

    <!-- ================= Bandeau des rayons qui défile ================= -->
    <div class="bx-marquee" aria-hidden="true">
        <div class="bx-marquee-track">
            <?php for ($loop = 0; $loop < 2; $loop++): ?>
                <?php foreach ($products as $product): ?>
                    <span class="bx-marquee-item"><?= $pic($product[0]) ?><?= Html::e($product[1]) ?></span>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>

    <!-- ================= Plus besoin d'un site ================= -->
    <section class="s-section" aria-label="Site e-commerce classique ou Sesam Boutique">
        <div class="s-wrap">
            <div class="compare">
                <?php foreach ($page['compare'] as $index => [$title, $lines]): ?>
                    <article class="panel<?= $index === 1 ? ' panel--lime' : '' ?>" data-reveal data-reveal-delay="<?= $index ?>">
                        <h3 class="s-h3"><?= Html::e($title) ?></h3>
                        <ul>
                            <?php foreach ($lines as $line): ?>
                                <li><?php $name = $index === 1 ? 'check' : 'close'; include $icon; ?><?= Html::e($line) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================= En 4 étapes ================= -->
    <section class="s-section bx-steps-section" aria-labelledby="bq-steps-title">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker">En 4 étapes</p>
                <h2 class="s-h2" id="bq-steps-title"><?= Html::e($page['steps_title']) ?></h2>
            </header>
            <ol class="bx-steps">
                <?php foreach ($page['steps'] as $index => [$stepIcon, $title, $text]): ?>
                    <li data-reveal data-reveal-delay="<?= $index ?>">
                        <span class="bx-step-icon" aria-hidden="true"><?php $name = $stepIcon; include $icon; ?></span>
                        <span class="bx-step-num"><?= $index + 1 ?></span>
                        <h3><?= Html::e($title) ?></h3>
                        <p><?= Html::e($text) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <!-- ================= Votre arrière-boutique ================= -->
    <section class="s-section" id="fonctionnalites" aria-label="Commandes et stock">
        <div class="s-wrap">
            <div class="bx-office" data-reveal>
                <div class="bx-office-panel">
                    <p class="bx-office-title">Commandes du jour <span>4</span></p>
                    <ul class="bx-orders">
                        <li><span class="bx-av">F</span><span><b>Fatou Ndiaye</b><small>Robe wax + Sac en raphia</small></span><span class="bx-st is-new">Nouvelle</span><b>37 000</b></li>
                        <li><span class="bx-av is-b">M</span><span><b>Moussa Diop</b><small>Écouteurs sans fil</small></span><span class="bx-st is-ok">Confirmée</span><b>15 000</b></li>
                        <li><span class="bx-av is-c">A</span><span><b>Aïda Sarr</b><small>Parfum Oud Royal</small></span><span class="bx-st is-done">Livrée</span><b>21 000</b></li>
                        <li><span class="bx-av is-d">K</span><span><b>Khady Ba</b><small>Café Touba × 3</small></span><span class="bx-st is-paid">Payée</span><b>10 500</b></li>
                    </ul>
                </div>
                <div class="bx-office-panel">
                    <p class="bx-office-title">Stock</p>
                    <ul class="bx-stock">
                        <li><span><?= $pic('baskets') ?>Baskets urbaines</span><span class="bx-bar is-low"><i style="--w: 15%"></i></span><b class="is-low">3</b></li>
                        <li><span><?= $pic('robe') ?>Robe wax Teranga</span><span class="bx-bar"><i style="--w: 55%"></i></span><b>11</b></li>
                        <li><span><?= $pic('cafe') ?>Café Touba 500 g</span><span class="bx-bar"><i style="--w: 90%"></i></span><b>40</b></li>
                        <li><span><?= $pic('montre') ?>Montre connectée</span><span class="bx-bar is-out"><i style="--w: 0%"></i></span><b class="is-out">Épuisé</b></li>
                    </ul>
                    <p class="bx-alert">⚠️ Bientôt épuisé : Baskets urbaines</p>
                </div>
            </div>

            <div class="cards bx-features">
                <?php foreach ($page['features'] as $index => [$featureIcon, $title, $text]): ?>
                    <article class="card" data-reveal data-reveal-delay="<?= $index % 4 ?>">
                        <span class="chip-ico" aria-hidden="true"><?php $name = $featureIcon; include $icon; ?></span>
                        <h3><?= Html::e($title) ?></h3>
                        <p><?= Html::e($text) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="s-wrap split" aria-label="Vous et votre espace">
        <article class="panel panel--ink" data-reveal>
            <p class="s-kicker" style="color: var(--accent)">Au quotidien</p>
            <h2 class="s-h3"><?= Html::e($page['owner_title']) ?></h2>
            <ul class="ticks">
                <?php foreach ($page['owner'] as $line): ?>
                    <li><span><?php $name = 'check'; include $icon; ?></span><span><strong><?= Html::e($line) ?></strong></span></li>
                <?php endforeach; ?>
            </ul>
        </article>
        <article class="panel" data-reveal data-reveal-delay="1">
            <p class="s-kicker" style="color: var(--blue)">Dans votre espace</p>
            <h2 class="s-h3">Une gestion complète de la boutique.</h2>
            <ul class="ticks">
                <?php foreach ($service['commerce'] as $line): ?>
                    <li><span><?php $name = 'check'; include $icon; ?></span><span><strong><?= Html::e($line) ?></strong></span></li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>

    <section class="s-section" aria-labelledby="bq-faq-title" style="padding-top: 0">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <h2 class="s-h2" id="bq-faq-title"><?= Html::e($page['faq_title']) ?></h2>
            </header>
            <div class="faq">
                <?php foreach ($page['faq'] as [$question, $answer]): ?>
                    <details data-reveal>
                        <summary><?= Html::e($question) ?></summary>
                        <p><?= Html::e($answer) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="s-wrap" aria-labelledby="bq-cta-title">
        <div class="cta bx-cta" data-reveal>
            <div class="bx-cta-art" aria-hidden="true">
                <?php foreach (['sac', 'parfum', 'baskets', 'plante', 'ecouteurs', 'mangues'] as $index => $key): ?>
                    <span style="--d: <?= $index * 0.3 ?>s"><?= $pic($key) ?></span>
                <?php endforeach; ?>
            </div>
            <p class="s-kicker"><?= Html::e($service['label']) ?></p>
            <h2 class="s-display" id="bq-cta-title"><?= str_replace([' ?', ' !'], ['&nbsp;?', '&nbsp;!'], Html::e($page['cta_title'])) ?></h2>
            <p class="s-lead"><?= Html::e($page['cta_lead']) ?></p>
            <div class="s-actions">
                <a class="s-btn" href="<?= Html::url('/commencer?service=boutique') ?>">Commencer avec Sesam Boutique <?php $arrow(); ?></a>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/contact') ?>">Poser une question</a>
            </div>
        </div>
    </section>

</main>
