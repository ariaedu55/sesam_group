<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$service = $catalog['services']['boutique'];
$page = $service['page'];
$scene = $service['scene'];
$nav = $catalog['nav'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main class="bq-page" data-skin="boutique">
    <header class="bq-hero">
        <div class="bq-hero-copy">
            <p class="bq-kicker"><?php $name = 'store'; include $icon; ?><?= Html::e($page['hero_kicker']) ?></p>
            <h1><?= Html::e($page['hero_title']) ?> <span><?= Html::e($page['hero_title_line']) ?></span></h1>
            <p class="lead"><?= Html::e($page['hero_lead']) ?></p>
            <p class="landing-actions">
                <a class="btn btn-primary" href="<?= Html::url('/devis?activite=boutique') ?>">Activer ma boutique</a>
                <a class="btn btn-ghost landing-ghost" href="#fonctionnalites">Voir les fonctionnalités</a>
            </p>
            <ul class="bq-hero-points">
                <?php foreach ($service['points'] as $point): ?>
                    <li><?php $name = 'check'; include $icon; ?><?= Html::e($point) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <figure class="bq-hero-visual">
            <img src="<?= Html::asset('assets/images/' . $service['image'][0]) ?>" alt="<?= Html::e($service['image'][1]) ?>" width="960" height="720" decoding="async">
        </figure>
    </header>

    <ul class="bq-proof" aria-label="Sesam Boutique en chiffres">
        <?php foreach ($page['proof'] as [$value, $label]): ?>
            <li><strong><?= Html::e($value) ?></strong><span><?= Html::e($label) ?></span></li>
        <?php endforeach; ?>
    </ul>

    <section class="bq-section bq-innovation" aria-labelledby="bq-innovation-title">
        <header class="bq-head">
            <p class="eyebrow">L'innovation Sesam Boutique</p>
            <h2 id="bq-innovation-title"><?= Html::e($page['innovation_title']) ?></h2>
            <p><?= Html::e($page['innovation_lead']) ?></p>
        </header>
        <div class="bq-compare">
            <?php foreach ($page['compare'] as $index => [$title, $lines]): ?>
                <article class="<?= $index === 1 ? 'is-sesam' : 'is-classic' ?>">
                    <h3><?= Html::e($title) ?></h3>
                    <ul>
                        <?php foreach ($lines as $line): ?>
                            <li><?php $name = $index === 1 ? 'check' : 'close'; include $icon; ?><?= Html::e($line) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bq-section" aria-labelledby="bq-steps-title">
        <header class="bq-head">
            <p class="eyebrow">En 4 étapes</p>
            <h2 id="bq-steps-title"><?= Html::e($page['steps_title']) ?></h2>
        </header>
        <ol class="bq-steps">
            <?php foreach ($page['steps'] as $index => [$stepIcon, $title, $text]): ?>
                <li>
                    <span class="bq-step-num"><?= $index + 1 ?></span>
                    <span class="bq-icon" aria-hidden="true"><?php $name = $stepIcon; include $icon; ?></span>
                    <h3><?= Html::e($title) ?></h3>
                    <p><?= Html::e($text) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="bq-section bq-demo" aria-labelledby="bq-demo-title">
        <div class="bq-demo-copy">
            <p class="eyebrow">Côté client</p>
            <h2 id="bq-demo-title"><?= Html::e($page['customer_title']) ?></h2>
            <ul class="bq-list">
                <?php foreach ($page['customer'] as $line): ?>
                    <li><?php $name = 'check'; include $icon; ?><?= Html::e($line) ?></li>
                <?php endforeach; ?>
            </ul>
            <ol class="offer-flow" aria-label="Parcours du client">
                <?php foreach ($service['public'] as $step): ?>
                    <li><?= Html::e($step) ?></li>
                <?php endforeach; ?>
            </ol>
        </div>
        <div class="bq-phone" aria-hidden="true">
            <div class="bq-phone-screen">
                <p class="bq-phone-shop"><span><?= Html::e(Html::initials($scene['shop'])) ?></span><?= Html::e($scene['shop']) ?></p>
                <div class="bq-phone-grid">
                    <?php foreach ($scene['products'] as [$label, $price, $tone]): ?>
                        <div class="bq-phone-item <?= Html::e($tone) ?>">
                            <span class="bq-phone-img"></span>
                            <strong><?= Html::e($label) ?></strong>
                            <em><?= Html::e($price) ?></em>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="bq-phone-cart"><?= Html::e($scene['cart']) ?></p>
                <p class="bq-phone-wa"><?php $name = 'chat'; include $icon; ?>Commander sur WhatsApp</p>
            </div>
            <p class="bq-bubble"><?= Html::e($scene['bubble']) ?></p>
        </div>
    </section>

    <section class="bq-section" id="fonctionnalites" aria-labelledby="bq-features-title">
        <header class="bq-head">
            <p class="eyebrow">Gestion complète, vraiment simple</p>
            <h2 id="bq-features-title"><?= Html::e($page['features_title']) ?></h2>
            <p><?= Html::e($page['features_lead']) ?></p>
        </header>
        <div class="bq-features">
            <?php foreach ($page['features'] as [$featureIcon, $title, $text]): ?>
                <article>
                    <span class="bq-icon" aria-hidden="true"><?php $name = $featureIcon; include $icon; ?></span>
                    <h3><?= Html::e($title) ?></h3>
                    <p><?= Html::e($text) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="bq-section bq-duo" aria-label="Vous et vos clients">
        <article>
            <span class="bq-icon" aria-hidden="true"><?php $name = 'person'; include $icon; ?></span>
            <h2><?= Html::e($page['owner_title']) ?></h2>
            <ul class="bq-list">
                <?php foreach ($page['owner'] as $line): ?>
                    <li><?php $name = 'check'; include $icon; ?><?= Html::e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </article>
        <article>
            <span class="bq-icon" aria-hidden="true"><?php $name = 'gear'; include $icon; ?></span>
            <h2>Dans votre espace</h2>
            <ul class="bq-list">
                <?php foreach ($service['commerce'] as $line): ?>
                    <li><?php $name = 'check'; include $icon; ?><?= Html::e($line) ?></li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>

    <section class="bq-section" aria-labelledby="bq-audience-title">
        <header class="bq-head">
            <p class="eyebrow"><?= Html::e($service['sector']) ?></p>
            <h2 id="bq-audience-title"><?= Html::e($page['audience_title']) ?></h2>
        </header>
        <ul class="bq-audience">
            <?php foreach ($page['audience'] as [$title, $text]): ?>
                <li><strong><?= Html::e($title) ?></strong><span><?= Html::e($text) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="bq-section bq-faq" aria-labelledby="bq-faq-title">
        <header class="bq-head">
            <h2 id="bq-faq-title"><?= Html::e($page['faq_title']) ?></h2>
        </header>
        <?php foreach ($page['faq'] as [$question, $answer]): ?>
            <details>
                <summary><?= Html::e($question) ?></summary>
                <p><?= Html::e($answer) ?></p>
            </details>
        <?php endforeach; ?>
    </section>

    <section class="bq-cta" aria-labelledby="bq-cta-title">
        <h2 id="bq-cta-title"><?= Html::e($page['cta_title']) ?></h2>
        <p><?= Html::e($page['cta_lead']) ?></p>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/devis?activite=boutique') ?>">Activer Sesam Boutique</a>
            <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/contact') ?>">Poser une question</a>
        </p>
    </section>

    <nav class="offer-others" aria-label="Les autres services">
        <?php foreach ($nav as $other => $label): ?>
            <?php if ($other === 'boutique') {
                continue;
            } ?>
            <a href="<?= Html::url('/services/' . $other) ?>">
                <span class="offer-others-icon" aria-hidden="true">
                    <?php $name = (string) $catalog['services'][$other]['icon']; include $icon; ?>
                </span>
                <?= Html::e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</main>
