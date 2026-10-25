<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$journey = $catalog['journey'];
$nav = $catalog['nav'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main class="page-sheet">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
                <h1 class="s-display" style="margin-top: 1rem">Deux activités, <span class="s-mark">une maison.</span></h1>
            </div>
            <p class="s-lead">Sesam Digital crée ou refond le site, facturé au projet. Sesam SaaS y ajoute l'outil du métier, en abonnement mensuel. Les supports sont en français. Chaque commerce ne voit que ses données, sous son nom.</p>
        </div>
    </header>

    <section class="s-wrap split" style="padding-bottom: 24px">
        <article class="panel panel--ink" data-reveal>
            <p class="s-kicker" style="color: var(--accent)">Où nous sommes</p>
            <h2 class="s-h2">Dakar, suivi depuis New York.</h2>
            <p style="color: rgba(255,255,255,.75)">Le marché est à Dakar, au plus près des commerces. Le pilotage et le suivi technique se font depuis New York. Une équipe, deux fuseaux, un suivi continu.</p>
        </article>
        <article class="panel" data-reveal data-reveal-delay="1">
            <p class="s-kicker" style="color: var(--blue)">Pour qui</p>
            <h2 class="s-h3">Le site, puis l'outil du métier.</h2>
            <p class="s-muted">L'agence s'adresse d'abord aux salons de beauté, instituts et salons de couture. Les outils couvrent ensuite le métier réel : salon, boutique, créatrice ou propriétaire.</p>
            <nav class="others" aria-label="Services">
                <?php foreach ($nav as $key => $label): ?>
                    <a href="<?= Html::url('/services/' . $key) ?>"><span aria-hidden="true"><?php $name = (string) $catalog['services'][$key]['icon']; include $icon; ?></span><?= Html::e($label) ?></a>
                <?php endforeach; ?>
            </nav>
        </article>
    </section>

    <section class="s-section">
        <div class="s-wrap">
            <header class="s-head" data-reveal>
                <p class="s-kicker">Notre méthode</p>
                <h2 class="s-h2">Simple, progressif, sans surprise.</h2>
            </header>
            <ol class="steps">
                <?php foreach ($journey as $index => $step): ?>
                    <li data-reveal data-reveal-delay="<?= $index ?>">
                        <h3><?= Html::e($step['label']) ?></h3>
                        <p><?= Html::e($step['text']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <section class="s-wrap">
        <div class="cta" data-reveal>
            <h2 class="s-display">Construisons la suite ensemble.</h2>
            <div class="s-actions">
                <a class="s-btn" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/services') ?>">Voir les services</a>
            </div>
        </div>
    </section>
</main>
