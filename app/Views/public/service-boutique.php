<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$service = $catalog['services']['boutique'];
$page = $service['page'];
$nav = $catalog['nav'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$demo = require __DIR__ . '/partials/boutique-products.php';
$categories = $demo['categories'];
$products = $demo['products'];
$byKey = [];
foreach ($products as $product) {
    $byKey[$product[0]] = $product;
}
$art = static fn (string $key, string $class = ''): string => '<svg class="' . $class . '" viewBox="0 0 120 120" aria-hidden="true" focusable="false">' . $byKey[$key][7] . '</svg>';
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
                    <a class="s-btn s-btn--ghost s-btn--plain" href="#vitrine">Essayer la vitrine</a>
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
                                    <span class="bx-item" style="--d: <?= ($row * 4 + $index) * 0.18 ?>s; --bg: <?= Html::e($byKey[$key][6]) ?>"><?= $art($key) ?></span>
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
                                <span class="bx-phone-card"><span style="background: <?= Html::e($byKey[$key][6]) ?>"><?= $art($key) ?></span><b><?= Html::e(Money::format((int) $byKey[$key][3])) ?></b></span>
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
                    <span class="bx-marquee-item"><?= $art($product[0]) ?><?= Html::e($product[1]) ?></span>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>

    <div class="s-wrap">
        <ul class="figures bx-figures" aria-label="Sesam Boutique en chiffres">
            <?php foreach ($page['proof'] as [$value, $label]): ?>
                <li data-reveal><strong><?= Html::e($value) ?></strong><span><?= Html::e($label) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ================= La vitrine à essayer comme un client ================= -->
    <section class="s-section bx-shop" id="vitrine" aria-labelledby="bx-shop-title">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker" style="color: var(--blue)">Essayez comme un client</p>
                <h2 class="s-h2" id="bx-shop-title">Tous vos produits, rangés comme dans votre boutique</h2>
                <p class="s-lead">Vêtements, cosmétiques, téléphones, déco ou épicerie : chaque produit a sa photo, son prix et son rayon. Ajoutez au panier, puis commandez sur WhatsApp, exactement comme le feront vos clients.</p>
            </header>

            <div class="bx-shop-layout" data-bx-shop>
                <div class="bx-shop-main">
                    <div class="bx-tabs" role="tablist" aria-label="Rayons">
                        <button class="bx-tab is-on" type="button" role="tab" aria-selected="true" data-bx-filter="all">Tout</button>
                        <?php foreach ($categories as $key => $label): ?>
                            <button class="bx-tab" type="button" role="tab" aria-selected="false" data-bx-filter="<?= Html::e($key) ?>"><?= Html::e($label) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <ul class="bx-grid">
                        <?php foreach ($products as $index => [$key, $name, $category, $price, $old, $badge, $bg]): ?>
                            <li class="bx-card" data-bx-cat="<?= Html::e($category) ?>" data-reveal data-reveal-delay="<?= $index % 4 ?>">
                                <span class="bx-card-art" style="background: <?= Html::e($bg) ?>"><?= $art($key) ?></span>
                                <?php if ($badge !== ''): ?><span class="bx-badge<?= str_starts_with($badge, '-') || $badge === 'Promo' ? ' is-promo' : ($badge === 'Plus que 3' ? ' is-stock' : '') ?>"><?= Html::e($badge) ?></span><?php endif; ?>
                                <span class="bx-card-cat"><?= Html::e($categories[$category]) ?></span>
                                <strong class="bx-card-name"><?= Html::e($name) ?></strong>
                                <span class="bx-card-price"><b><?= Html::e(Money::format((int) $price)) ?></b><?php if ($old > 0): ?> <s><?= Html::e(Money::format((int) $old)) ?></s><?php endif; ?></span>
                                <button class="bx-add" type="button" data-bx-add data-name="<?= Html::e($name) ?>" data-price="<?= (int) $price ?>" aria-label="Ajouter « <?= Html::e($name) ?> » au panier"><span aria-hidden="true">+</span> Ajouter</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <aside class="bx-cart" aria-label="Panier de démonstration" data-bx-cart>
                    <p class="bx-cart-head"><span class="bx-cart-icon" aria-hidden="true">🛒<b data-bx-count>0</b></span> Votre panier</p>
                    <ul class="bx-cart-lines" data-bx-lines>
                        <li class="bx-cart-empty">Ajoutez un produit pour remplir le panier.</li>
                    </ul>
                    <p class="bx-cart-total"><span>Total</span><b data-bx-total>0 FCFA</b></p>
                    <button class="bx-wa" type="button" data-bx-order disabled><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg> Commander sur WhatsApp</button>
                    <div class="bx-chat" data-bx-chat hidden>
                        <p class="bx-chat-head"><b>Chez Awa</b><small>en ligne</small></p>
                        <p class="bx-bubble" data-bx-message></p>
                        <p class="bx-chat-note">C'est ce message, déjà rédigé avec le total, que vous recevez sur votre WhatsApp.</p>
                    </div>
                    <p class="bx-cart-note">Démonstration : rien n'est commandé.</p>
                </aside>
            </div>
        </div>
    </section>

    <!-- ================= Plus besoin d'un site ================= -->
    <section class="s-section" aria-labelledby="bq-innovation-title" style="padding-top: 0">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker" style="color: var(--blue)">L'innovation Sesam Boutique</p>
                <h2 class="s-h2" id="bq-innovation-title"><?= Html::e($page['innovation_title']) ?></h2>
                <p class="s-lead"><?= Html::e($page['innovation_lead']) ?></p>
            </header>
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
    <section class="s-section" id="fonctionnalites" aria-labelledby="bq-features-title">
        <div class="s-wrap">
            <header class="s-head s-head--split" data-reveal>
                <div>
                    <p class="s-kicker">Votre arrière-boutique</p>
                    <h2 class="s-h2" id="bq-features-title"><?= Html::e($page['features_title']) ?></h2>
                </div>
                <p class="s-lead"><?= Html::e($page['features_lead']) ?></p>
            </header>

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
                        <li><span><?= $art('baskets') ?>Baskets urbaines</span><span class="bx-bar is-low"><i style="--w: 15%"></i></span><b class="is-low">3</b></li>
                        <li><span><?= $art('robe') ?>Robe wax Teranga</span><span class="bx-bar"><i style="--w: 55%"></i></span><b>11</b></li>
                        <li><span><?= $art('cafe') ?>Café Touba 500 g</span><span class="bx-bar"><i style="--w: 90%"></i></span><b>40</b></li>
                        <li><span><?= $art('montre') ?>Montre connectée</span><span class="bx-bar is-out"><i style="--w: 0%"></i></span><b class="is-out">Épuisé</b></li>
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

    <section class="s-section" aria-labelledby="bq-audience-title">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker"><?= Html::e($service['sector']) ?></p>
                <h2 class="s-h2" id="bq-audience-title"><?= Html::e($page['audience_title']) ?></h2>
            </header>
            <ul class="audience">
                <?php foreach ($page['audience'] as [$title, $text]): ?>
                    <li data-reveal><strong><?= Html::e($title) ?></strong><span><?= Html::e($text) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
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
                    <span style="--d: <?= $index * 0.3 ?>s; background: <?= Html::e($byKey[$key][6]) ?>"><?= $art($key) ?></span>
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

    <nav class="s-wrap others" aria-label="Les autres services" style="padding-top: 32px">
        <?php foreach ($nav as $other => $label): ?>
            <?php if ($other === 'boutique') {
                continue;
            } ?>
            <a href="<?= Html::url('/services/' . $other) ?>">
                <span aria-hidden="true"><?php $name = (string) $catalog['services'][$other]['icon']; include $icon; ?></span>
                <?= Html::e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</main>
<script src="<?= Html::asset('assets/js/service-boutique.js') ?>" defer></script>
