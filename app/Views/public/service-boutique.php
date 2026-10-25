<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$service = $catalog['services']['boutique'];
$page = $service['page'];
$nav = $catalog['nav'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$mocks = __DIR__ . '/mocks/';
$arrow = static function () use ($icon): void {
    $name = 'arrow';
    echo '<span class="s-btn-arrow">';
    include $icon;
    echo '</span>';
};

?>
<main class="bq-page" data-skin="boutique">
    <header class="hx" data-hero>
        <span class="hx-blob" data-blob aria-hidden="true"></span>
        <div class="s-wrap hx-grid">
            <div class="hx-copy">
                <p class="s-eyebrow"><b>Nouveau</b><?= Html::e($service['label']) ?></p>
                <h1 class="s-display hx-title">
                    <span class="line"><span><?= Html::e($page['hero_title']) ?></span></span>
                </h1>
                <p class="s-h3"><span class="s-mark"><?= Html::e($page['hero_title_line']) ?></span></p>
                <p class="s-lead"><?= Html::e($page['hero_lead']) ?></p>
                <div class="s-actions">
                    <a class="s-btn s-btn--blue" href="<?= Html::url('/commencer?service=boutique') ?>">Essayer gratuitement 1 mois <?php $arrow(); ?></a>
                    <a class="s-btn s-btn--ghost s-btn--plain" href="#fonctionnalites">Voir les fonctionnalités</a>
                </div>
            </div>
            <div class="hx-stage" aria-hidden="true">
                <?php require $mocks . 'site.php'; ?>
                <?php require $mocks . 'shop.php'; ?>
                <p class="hx-toast hx-toast--order"><i><?php $name = 'chat'; include $icon; ?></i><span><strong>Commande sur WhatsApp</strong><small>Fatou · 37 000 FCFA</small></span></p>
                <p class="hx-toast hx-toast--rdv"><i><?php $name = 'box'; include $icon; ?></i><span><strong>Stock à jour</strong><small>Robe wax : 11 restantes</small></span></p>
            </div>
        </div>
    </header>

    <div class="s-wrap">
        <ul class="figures" style="margin-top: 0" aria-label="Sesam Boutique en chiffres">
            <?php foreach ($page['proof'] as [$value, $label]): ?>
                <li style="border-top-color: var(--line)"><strong style="color: var(--blue)"><?= Html::e($value) ?></strong><span style="color: var(--muted)"><?= Html::e($label) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <section class="s-section" aria-labelledby="bq-innovation-title">
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

    <section class="s-section" aria-labelledby="bq-steps-title" style="padding-top: 0">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker">En 4 étapes</p>
                <h2 class="s-h2" id="bq-steps-title"><?= Html::e($page['steps_title']) ?></h2>
            </header>
            <ol class="steps steps--4">
                <?php foreach ($page['steps'] as $index => [$stepIcon, $title, $text]): ?>
                    <li data-reveal data-reveal-delay="<?= $index ?>">
                        <h3><?= Html::e($title) ?></h3>
                        <p><?= Html::e($text) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <section class="s-section" aria-labelledby="bq-demo-title" style="padding-top: 0">
        <div class="s-wrap spot">
            <div class="spot-visual" data-reveal aria-hidden="true">
                <?php require $mocks . 'shop.php'; ?>
                <p class="spot-bubble"><strong>Fatou</strong><?= Html::e($service['scene']['bubble']) ?></p>
                <p class="spot-label">Panier <span><?= Html::e($service['scene']['cart']) ?></span></p>
            </div>
            <div class="spot-copy" data-reveal data-reveal-delay="1">
                <p class="s-kicker" style="color: var(--blue)">Côté client</p>
                <h2 class="s-h2" id="bq-demo-title"><?= Html::e($page['customer_title']) ?></h2>
                <ul class="ticks">
                    <?php foreach ($page['customer'] as $line): ?>
                        <li><span><?php $name = 'check'; include $icon; ?></span><span><strong><?= Html::e($line) ?></strong></span></li>
                    <?php endforeach; ?>
                </ul>
                <ol class="flow" aria-label="Parcours du client">
                    <?php foreach ($service['public'] as $step): ?>
                        <li><?= Html::e($step) ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
    </section>

    <section class="s-section" id="fonctionnalites" aria-labelledby="bq-features-title" style="padding-top: 0">
        <div class="s-wrap">
            <header class="s-head s-head--split" data-reveal>
                <div>
                    <p class="s-kicker">Gestion complète, vraiment simple</p>
                    <h2 class="s-h2" id="bq-features-title"><?= Html::e($page['features_title']) ?></h2>
                </div>
                <p class="s-lead"><?= Html::e($page['features_lead']) ?></p>
            </header>
            <div class="cards">
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
        <div class="cta" data-reveal>
            <p class="s-kicker"><?= Html::e($service['label']) ?></p>
            <h2 class="s-display" id="bq-cta-title"><?= Html::e($page['cta_title']) ?></h2>
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
