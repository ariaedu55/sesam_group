<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main class="page-sheet">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
                <h1 class="s-display" style="margin-top: 1rem">Les sites livrés. <span class="s-mark">Bientôt ici.</span></h1>
            </div>
            <p class="s-lead">Les réalisations apparaîtront ici lorsqu'un site sera livré et autorisé à être montré. Rien n'est inventé en attendant.</p>
        </div>
    </header>

    <section class="s-wrap" style="padding-top: 24px">
        <div class="notice" data-reveal>
            <p class="s-kicker" style="color: var(--blue)">Pour l'instant</p>
            <h2 class="s-h2">Aucun projet publié</h2>
            <p class="s-lead">Sesam Digital livre au plus cinq projets web par mois. Un site neuf, ou un outil branché sur un site déjà en ligne. Le vôtre pourrait être le premier présenté ici.</p>
            <div class="s-actions">
                <a class="s-btn" href="<?= Html::url('/services/agence') ?>">Parler d'un projet de site <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/offres') ?>">Voir les offres</a>
            </div>
        </div>
    </section>
</main>
