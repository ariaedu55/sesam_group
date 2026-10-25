<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$offers = $catalog['offers'];
$landing = $catalog['landing'];

?>
<main class="page-sheet">
    <header class="services-intro">
        <p class="landing-kicker"><?= Html::e($landing['signature']) ?></p>
        <h1 class="landing-title">Trois formules pour le site. <span>L'outil se choisit ensuite.</span></h1>
        <p class="lead">Ces montants concernent la conception ou la refonte du site, facturée au projet. L'abonnement mensuel de Réservation, Boutique ou Loyer se décide avec vous : aucun tarif SaaS n'est affiché ici.</p>
    </header>

    <ol class="page-offer-grid">
        <?php foreach ($offers as $offer): ?>
            <li class="glass-card" data-reveal>
                <p class="eyebrow">Agence</p>
                <h2><?= Html::e($offer['name']) ?></h2>
                <p class="page-offer-price"><?= Html::e($offer['price']) ?></p>
                <p><?= Html::e($offer['description']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>

    <section class="glass-card page-note" data-reveal>
        <p class="eyebrow">Capacité</p>
        <h2>Cinq projets web par mois</h2>
        <p>Sesam Digital limite les livraisons pour garder la qualité. Le site peut être neuf, ou recevoir un outil sur un site déjà en ligne.</p>
    </section>

    <p class="services-end landing-actions">
        <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
        <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services') ?>">Voir les services</a>
    </p>
</main>
