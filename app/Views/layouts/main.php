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
<body>
<?php // $content est le HTML déjà produit par une vue du projet. Chaque vue échappe ses propres données. ?>
<?= $content ?>
<script src="<?= Html::asset('assets/js/sesam.js') ?>" defer></script>
</body>
</html>
