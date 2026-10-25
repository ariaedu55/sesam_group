<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var string $serviceKey */
$catalog = require __DIR__ . '/home-catalog.php';
$key = $serviceKey;
$service = $catalog['services'][$key];
$nav = $catalog['nav'];
$common = $catalog['common'];
$offers = $catalog['offers'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$mocks = __DIR__ . '/mocks/';
$arrow = static function () use ($icon): void {
    $name = 'arrow';
    echo '<span class="s-btn-arrow">';
    include $icon;
    echo '</span>';
};
[$tone, $mock] = match ($key) {
    'agence' => ['ink', 'site'],
    'reservation' => ['lilac', 'booking'],
    'immo' => ['lime', 'rent'],
    default => ['blue', 'shop'],
};
$pointIcons = ['check', 'spark', 'users', 'chat'];

?>
<main class="offer-page" data-skin="<?= Html::e($service['skin']) ?>" data-scene="<?= Html::e($key) ?>">
    <header class="s-wrap sv-hero">
        <div class="sv-hero-copy">
            <p class="s-eyebrow"><b><?= Html::e($service['num']) ?></b><?= Html::e($service['label']) ?></p>
            <h1 class="s-display" style="font-size: clamp(1.9rem, 4vw, 3.3rem)"><?= Html::e($service['title']) ?></h1>
            <p class="s-kicker" style="color: var(--muted)"><?= Html::e($service['sector']) ?></p>
            <p class="s-lead"><?= Html::e($service['lead']) ?></p>
            <div class="s-actions">
                <?php if ($key === 'agence'): ?><a class="s-btn" href="<?= Html::url('/devis') ?>">Demander un devis pour mon site <?php $arrow(); ?></a><?php else: ?><a class="s-btn" href="<?= Html::url('/commencer?service=' . ($key === 'immo' ? 'loyer' : $key)) ?>">Essayer gratuitement 1 mois <?php $arrow(); ?></a><?php endif; ?>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/services') ?>">Tous les services</a>
            </div>
        </div>
        <div class="sv-stage" data-tone="<?= Html::e($tone) ?>" data-reveal>
            <?php require $mocks . $mock . '.php'; ?>
        </div>
    </header>

    <section class="s-section" aria-labelledby="practice-title" style="padding-top: 32px">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker">En pratique</p>
                <h2 class="s-h2" id="practice-title">Ce que <?= Html::e($nav[$key]) ?> change pour vous.</h2>
            </header>
            <div class="cards">
                <?php foreach ($service['points'] as $index => $point): ?>
                    <article class="card" data-reveal data-reveal-delay="<?= $index ?>">
                        <span class="chip-ico" aria-hidden="true"><?php $name = $pointIcons[$index] ?? 'check'; include $icon; ?></span>
                        <p style="color: var(--ink); font-weight: 650; font-size: 1.08rem"><?= Html::e($point) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($service['public'] !== [] || $service['commerce'] !== []): ?>
        <section class="s-wrap split" aria-label="Côté client et côté commerce" style="padding-bottom: clamp(48px, 7vw, 96px)">
            <?php if ($service['public'] !== []): ?>
                <article class="panel" data-reveal>
                    <p class="s-kicker" style="color: var(--blue)">Côté client</p>
                    <h2 class="s-h3">Un parcours en quelques secondes, depuis un simple lien.</h2>
                    <ol class="flow" aria-label="Parcours du client">
                        <?php foreach ($service['public'] as $step): ?>
                            <li><?= Html::e($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                    <p class="s-muted"><?= Html::e($common['whatsapp']) ?>. Paiement par <?= Html::e($common['wallets'][0]) ?> ou <?= Html::e($common['wallets'][1]) ?>.</p>
                </article>
            <?php endif; ?>
            <?php if ($service['commerce'] !== []): ?>
                <article class="panel panel--ink" data-reveal data-reveal-delay="1">
                    <p class="s-kicker" style="color: var(--accent)"><?= Html::e($common['commerce_label']) ?></p>
                    <h2 class="s-h3">Tout se gère depuis votre espace.</h2>
                    <ul class="ticks">
                        <?php foreach ($service['commerce'] as $item): ?>
                            <li><span><?php $name = 'check'; include $icon; ?></span><span><strong><?= Html::e($item) ?></strong></span></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($key === 'agence'): ?>
        <section class="s-section" aria-labelledby="agency-offers" style="padding-top: 0">
            <div class="s-wrap">
                <header class="s-head" data-reveal>
                    <p class="s-kicker">Deux chemins</p>
                    <h2 class="s-h2" id="agency-offers">Un site neuf, ou des outils sur le vôtre.</h2>
                    <p class="s-lead"><?= Html::e((string) $service['scene']['capacity']) ?> <?= Html::e((string) $service['scene']['capacity_label']) ?>.</p>
                </header>
                <div class="offers">
                    <?php foreach ($offers as $index => $offer): ?>
                        <article class="offer<?= $index === 1 ? ' offer--hot' : '' ?>" data-reveal data-reveal-delay="<?= $index ?>">
                            <?php if ($index === 1): ?><span class="offer-badge">Le plus choisi</span><?php endif; ?>
                            <h3><?= Html::e($offer['name']) ?></h3>
                            <p class="offer-price"><?= Html::e($offer['price']) ?></p>
                            <p><?= Html::e($offer['description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="s-wrap">
        <div class="cta" data-reveal>
            <p class="s-kicker"><?= Html::e($service['label']) ?></p>
            <h2 class="s-display" style="font-size: clamp(1.9rem, 4vw, 3.3rem)"><?= $key === 'agence' ? 'Parlons de votre site.' : 'Activez ' . Html::e($nav[$key]) . ' pour votre commerce.' ?></h2>
            <div class="s-actions">
                <?php if ($key === 'agence'): ?><a class="s-btn" href="<?= Html::url('/devis') ?>">Demander un devis pour mon site <?php $arrow(); ?></a><?php else: ?><a class="s-btn" href="<?= Html::url('/commencer?service=' . ($key === 'immo' ? 'loyer' : $key)) ?>">Essayer gratuitement 1 mois <?php $arrow(); ?></a><?php endif; ?>
            </div>
        </div>
    </section>

    <nav class="s-wrap others" aria-label="Les autres services" style="padding-top: 32px">
        <?php foreach ($nav as $other => $label): ?>
            <?php if ($other === $key) {
                continue;
            } ?>
            <a href="<?= Html::url('/services/' . $other) ?>">
                <span aria-hidden="true"><?php $name = (string) $catalog['services'][$other]['icon']; include $icon; ?></span>
                <?= Html::e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</main>
