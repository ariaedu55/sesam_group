<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array{agent_embed_src: string} $jotform */
$jotform = require dirname(__DIR__, 3) . '/config/jotform.php';
$jotformSrc = trim($jotform['agent_embed_src'] ?? '');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? 'SESAM') ?></title>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/sesam.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/app-glass.css') ?>">
</head>
<body class="auth-body">
<div class="auth-page">
    <aside class="auth-showcase" aria-label="SESAM">
        <div class="auth-showcase-glow" aria-hidden="true"></div>
        <div class="auth-showcase-inner">
            <a class="auth-showcase-brand" href="<?= Html::url('/') ?>">
                <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="" width="72" height="72">
                <span>SESAM</span>
            </a>
            <p class="auth-showcase-kicker">L'innovation à portée de clic</p>
            <p class="auth-showcase-title">Votre activité, <em>en ligne</em> et sous contrôle.</p>
            <ul class="auth-showcase-list">
                <li>Réservation, boutique ou loyers</li>
                <li>Wave, Orange Money et WhatsApp</li>
                <li>Équipe à Dakar, suivi depuis New York</li>
            </ul>
        </div>
    </aside>
    <main class="auth-main">
        <div class="auth-main-inner">
            <p class="auth-back auth-back--top">
                <a href="<?= Html::url('/') ?>">← Retour au site</a>
            </p>
            <div class="auth-sheet">
<?php // $content est le HTML déjà produit par une vue du projet. Chaque vue échappe ses propres données. ?>
                <?= $content ?>
            </div>
        </div>
    </main>
</div>
<script src="<?= Html::asset('assets/js/auth.js') ?>" defer></script>
<?php if ($jotformSrc !== ''): ?>
<script src="<?= Html::e($jotformSrc) ?>"></script>
<?php endif; ?>
</body>
</html>
