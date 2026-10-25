<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];

?>
<main class="page-sheet">
    <header class="services-intro">
        <p class="landing-kicker"><?= Html::e($landing['signature']) ?></p>
        <h1 class="landing-title">Les sites livrés. <span>Aucun projet publié.</span></h1>
        <p class="lead">Les réalisations apparaîtront ici lorsqu'un site sera livré et autorisé à être montré. Rien n'est inventé en attendant.</p>
    </header>

    <section class="glass-card page-note" data-reveal>
        <p class="eyebrow">Pour l'instant</p>
        <h2>Aucun projet publié</h2>
        <p>Sesam Digital livre au plus cinq projets web par mois. Un site neuf, ou un outil branché sur un site déjà en ligne.</p>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Parler d'un projet</a>
            <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/offres') ?>">Voir les offres</a>
        </p>
    </section>
</main>
