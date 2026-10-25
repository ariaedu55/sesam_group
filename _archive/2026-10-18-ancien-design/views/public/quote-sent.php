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
        <h1 class="landing-title">Demande envoyée. <span>Elle est enregistrée.</span></h1>
        <p class="lead">SESAM s'y reportera pour préparer le devis. Aucun e-mail automatique n'est promis.</p>
    </header>
    <section class="glass-card page-note" data-reveal>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/') ?>">Retour à l'accueil</a>
            <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services') ?>">Revoir les services</a>
        </p>
    </section>
</main>
