<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';

$landing = $catalog['landing'];
$offers = $catalog['offers'];
$nav = $catalog['nav'];
$common = $catalog['common'];
$journey = $catalog['journey'];
$services = $catalog['services'];
$agency = $services['agence'];

$asset = static function (string $file, string $alt, bool $lazy = true): void {
    echo Html::picture($file, $alt, 1200, 900, $lazy);
};

?>
<main class="page-services">
    <header class="services-intro">
        <p class="landing-kicker"><?= Html::e($landing['signature']) ?></p>
        <h1 class="landing-title"><?= Html::e($landing['title']) ?> <span><?= Html::e($landing['title_line']) ?></span></h1>
        <p class="lead"><?= Html::e($landing['lead']) ?></p>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
            <a class="btn btn-ghost landing-ghost" href="#sesam-agence">Voir l'agence</a>
        </p>
    </header>

    <article class="services-agency glass-card" id="sesam-agence" data-reveal>
        <div class="services-agency-copy">
            <p class="eyebrow"><?= Html::e($agency['label']) ?></p>
            <h2><?= Html::e($nav['agence']) ?></h2>
            <p class="service-title"><?= Html::e($agency['title']) ?></p>
            <p class="service-sector"><?= Html::e($agency['sector']) ?></p>
            <p><?= Html::e($agency['lead']) ?></p>
            <ul>
                <?php foreach ($agency['points'] as $point): ?>
                    <li><?= Html::e($point) ?></li>
                <?php endforeach; ?>
            </ul>
            <ol class="services-offers">
                <?php foreach ($offers as $offer): ?>
                    <li>
                        <strong><?= Html::e($offer['name']) ?></strong>
                        <span><?= Html::e($offer['price']) ?></span>
                        <em><?= Html::e($offer['description']) ?></em>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="landing-actions">
                <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
                <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services/agence') ?>">Voir la page agence</a>
            </p>
        </div>
        <figure class="service-visual">
            <?php $asset($agency['image'][0], $agency['image'][1], false); ?>
        </figure>
    </article>

    <section class="services-saas" aria-labelledby="services-saas-title">
        <header class="services-saas-head">
            <p class="eyebrow">Sesam SaaS</p>
            <h2 id="services-saas-title">Trois outils, un abonnement mensuel</h2>
            <p>Chaque commerce a son espace. Il ne voit que ses données, sous son nom. Le client final ouvre un lien : pas de compte, pas d'application.</p>
        </header>
        <div class="services-grid">
            <?php foreach ($services as $key => $service): ?>
                <?php if ($key === 'agence') {
                    continue;
                } ?>
                <article class="service-card glass-card is-<?= Html::e($service['skin']) ?>" id="<?= Html::e($key) ?>" data-reveal>
                    <figure class="service-card-visual">
                        <?php $asset($service['image'][0], $service['image'][1]); ?>
                    </figure>
                    <div class="service-card-copy">
                        <p class="eyebrow"><?= Html::e($service['label']) ?></p>
                        <h3><?= Html::e($nav[$key]) ?></h3>
                        <p class="service-title"><?= Html::e($service['title']) ?></p>
                        <p class="service-sector"><?= Html::e($service['sector']) ?></p>
                        <p><?= Html::e($service['lead']) ?></p>
                        <ul>
                            <?php foreach ($service['points'] as $point): ?>
                                <li><?= Html::e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="landing-actions">
                            <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
                            <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services/' . $key) ?>">Voir ce service</a>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="services-common glass-card" data-reveal>
        <p class="eyebrow">Fonctions communes</p>
        <h2>Inclus dans chaque outil SaaS</h2>
        <p>Ce n'est pas un service de plus. C'est le même socle pour Réservation, Boutique et Loyer.</p>
        <ul class="services-chips">
            <li>Lien unique, sans application</li>
            <li><?= Html::e($common['wallets'][0]) ?> et <?= Html::e($common['wallets'][1]) ?></li>
            <li>Acompte ou totalité</li>
            <li><?= Html::e($common['whatsapp']) ?></li>
        </ul>
    </section>

    <ol class="services-path">
        <?php foreach ($journey as $step): ?>
            <li class="glass-card" data-reveal>
                <span><?= Html::e($step['step']) ?></span>
                <strong><?= Html::e($step['label']) ?></strong>
                <p><?= Html::e($step['text']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>

    <p class="services-end landing-actions">
        <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
        <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/offres') ?>">Voir les offres agence</a>
    </p>
</main>
