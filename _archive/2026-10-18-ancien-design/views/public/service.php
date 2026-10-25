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
$scene = $service['scene'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$name = (string) $service['icon'];

?>
<main class="offer-page is-<?= Html::e($service['skin']) ?>" data-skin="<?= Html::e($service['skin']) ?>">
    <header class="offer-hero">
        <div class="offer-hero-copy">
            <p class="offer-hero-icon" aria-hidden="true"><?php include $icon; ?></p>
            <div>
                <p class="eyebrow"><?= Html::e($service['label']) ?></p>
                <h1><?= Html::e($service['title']) ?></h1>
                <p class="service-sector"><?= Html::e($service['sector']) ?></p>
                <p class="lead"><?= Html::e($service['lead']) ?></p>
                <p class="landing-actions">
                    <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
                    <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services') ?>">Tous les services</a>
                </p>
            </div>
        </div>
        <figure class="offer-visual">
            <?= Html::picture($service['image'][0], $service['image'][1], 1200, 900, false) ?>
            <?php if (!empty($service['badge'])): ?>
                <figcaption class="offer-badge"><?= Html::e($service['badge']) ?></figcaption>
            <?php endif; ?>
        </figure>
    </header>

    <div class="offer-layout">
        <section class="offer-steps" aria-labelledby="offer-steps-title">
            <h2 id="offer-steps-title">En pratique</h2>
            <ol>
                <?php foreach ($service['points'] as $point): ?>
                    <li><?= Html::e($point) ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if ($service['public'] !== []): ?>
                <ol class="offer-flow" aria-label="Parcours du client">
                    <?php foreach ($service['public'] as $step): ?>
                        <li><?= Html::e($step) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
            <?php if ($service['alerts'] !== []): ?>
                <ul class="offer-alerts">
                    <?php foreach ($service['alerts'] as $alert): ?>
                        <li class="is-<?= Html::e($alert[2]) ?>"><strong><?= Html::e($alert[0]) ?></strong> <?= Html::e($alert[1]) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="offer-scene">
            <?php require __DIR__ . '/scenes/' . $key . '.php'; ?>
        </div>

        <?php if ($service['commerce'] !== []): ?>
            <section class="offer-commerce">
                <h2><?= Html::e($common['commerce_label']) ?></h2>
                <ul>
                    <?php foreach ($service['commerce'] as $item): ?>
                        <li><?= Html::e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>

    <section class="offer-cta" aria-label="Demander un devis">
        <p><?= $key === 'agence'
            ? 'Facturé au projet, avec 5 projets de conception web par mois.'
            : Html::e($common['whatsapp']) . '.' ?></p>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
        </p>
    </section>

    <nav class="offer-others" aria-label="Les autres services">
        <?php foreach ($nav as $other => $label): ?>
            <?php if ($other === $key) {
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
