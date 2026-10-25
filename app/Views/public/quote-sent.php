<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main class="page-sheet">
    <section class="s-wrap" style="padding-top: clamp(40px, 7vw, 96px)">
        <div class="cta">
            <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
            <h1 class="s-display">Demande envoyée. Elle est enregistrée.</h1>
            <p class="s-lead">SESAM s'y reportera pour préparer le devis. Aucun e-mail automatique n'est promis.</p>
            <div class="s-actions">
                <a class="s-btn" href="<?= Html::url('/') ?>">Retour à l'accueil <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
                <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/services') ?>">Revoir les services</a>
            </div>
        </div>
    </section>
</main>
