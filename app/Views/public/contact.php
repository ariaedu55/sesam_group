<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$notes = [
    ['file', 'Décrivez votre besoin', "Vous décrivez le site, ou l'outil : Réservation, Boutique en ligne, Immo."],
    ['card', 'Pour vos clients', 'Wave, Orange Money et WhatsApp concernent vos clients, pas ce formulaire.'],
    ['folder', 'Discrétion', "Rien n'est publié tant qu'un projet n'est pas livré."],
];

?>
<main class="page-sheet page-contact">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
                <h1 class="s-display" style="margin-top: 1rem">Une question&nbsp;? <span class="s-mark">On vous répond.</span></h1>
            </div>
            <div style="display: grid; gap: 1.4rem">
                <p class="s-lead">Aucune adresse, aucun téléphone et aucun e-mail ne sont affichés. La demande est enregistrée pour l'équipe SESAM, à Dakar pour le marché, avec un suivi depuis New York.</p>
                <div class="s-actions">
                    <a class="s-btn" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
                    <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/devis') ?>">Projet de site web : demander un devis</a>
                </div>
            </div>
        </div>
    </header>

    <section class="s-wrap" style="padding-block: 24px 16px" aria-labelledby="contact-how">
        <h2 class="sr-only" id="contact-how">Un formulaire, une réponse</h2>
        <div class="cards">
            <?php foreach ($notes as $index => [$noteIcon, $noteTitle, $noteText]): ?>
                <article class="card" data-reveal data-reveal-delay="<?= $index ?>">
                    <span class="chip-ico" aria-hidden="true"><?php $name = $noteIcon; include $icon; ?></span>
                    <h3><?= Html::e($noteTitle) ?></h3>
                    <p><?= Html::e($noteText) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
