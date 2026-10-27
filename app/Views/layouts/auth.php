<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

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
<main class="auth-sheet">
    <a class="auth-brand" href="<?= Html::url('/') ?>" aria-label="SESAM">
        <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="SESAM" width="180" height="180">
    </a>
<?php // $content est le HTML déjà produit par une vue du projet. Chaque vue échappe ses propres données. ?>
    <?= $content ?>
    <p class="auth-back"><a href="<?= Html::url('/') ?>">Retour au site</a></p>
</main>
<script src="<?= Html::asset('assets/js/auth.js') ?>" defer></script>
</body>
</html>
