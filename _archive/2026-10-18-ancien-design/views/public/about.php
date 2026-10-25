<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$journey = $catalog['journey'];
$nav = $catalog['nav'];

?>
<main class="page-sheet">
    <header class="services-intro">
        <p class="landing-kicker"><?= Html::e($landing['signature']) ?></p>
        <h1 class="landing-title">Deux activités, une maison. <span>Dakar, suivi depuis New York.</span></h1>
        <p class="lead">Sesam Digital crée ou refond le site, facturé au projet. Sesam SaaS y ajoute l'outil du métier, en abonnement mensuel. Les supports sont en français. Chaque commerce ne voit que ses données, sous son nom.</p>
    </header>

    <ol class="services-path">
        <?php foreach ($journey as $step): ?>
            <li class="glass-card" data-reveal>
                <span><?= Html::e($step['step']) ?></span>
                <strong><?= Html::e($step['label']) ?></strong>
                <p><?= Html::e($step['text']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>

    <section class="glass-card page-note" data-reveal>
        <p class="eyebrow">Pour qui</p>
        <h2>Le site, puis l'outil du métier</h2>
        <p>L'agence s'adresse d'abord aux salons de beauté, instituts et salons de couture. Les outils couvrent ensuite le métier réel : salon, boutique, créatrice ou propriétaire.</p>
        <ul class="services-chips">
            <?php foreach ($nav as $key => $label): ?>
                <li><a href="<?= Html::url('/services/' . $key) ?>"><?= Html::e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <p class="services-end landing-actions">
        <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
        <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services') ?>">Voir les services</a>
    </p>
</main>
