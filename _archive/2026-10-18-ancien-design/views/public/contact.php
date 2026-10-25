<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$notes = [
    ['file', 'Décrivez votre besoin', "Vous décrivez le site, ou l'outil : Réservation, Boutique en ligne, Loyer."],
    ['card', 'Pour vos clients', 'Wave, Orange Money et WhatsApp concernent vos clients, pas ce formulaire.'],
    ['folder', 'Discrétion', "Rien n'est publié tant qu'un projet n'est pas livré."],
];

?>
<main class="page-sheet page-contact">
    <header class="services-intro">
        <p class="landing-kicker"><?= Html::e($landing['signature']) ?></p>
        <h1 class="landing-title">Écrivez par le devis. <span>Pas de numéro public.</span></h1>
        <p class="lead">Aucune adresse, aucun téléphone et aucun e-mail ne sont affichés. La demande est enregistrée
            pour l'équipe SESAM, à Dakar pour le marché, avec un suivi depuis New York.</p>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Écrire une demande</a>
            <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services') ?>">Tous les services</a>
        </p>
    </header>

    <section class="glass-card page-note" data-reveal>
        <p class="eyebrow">Comment ça se passe</p>
        <h2>Un formulaire, une réponse</h2>
        <ul>
            <?php foreach ($notes as [$noteIcon, $noteTitle, $noteText]): ?>
                <li>
                    <span class="note-icon" aria-hidden="true">
                        <?php $name = $noteIcon;
                        include $icon; ?>
                    </span>
                    <strong><?= Html::e($noteTitle) ?></strong>
                    <span><?= Html::e($noteText) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Écrire une demande</a>
        </p>
    </section>
</main>
