<?php

declare(strict_types=1);

use Sesam\Core\Boutique\BoutiqueTheme;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationSettings;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$settings = ($settings ?? null) instanceof ReservationSettings ? $settings : new ReservationSettings();
$name = $tenant?->displayName() ?? 'Salon';
$palette = BoutiqueTheme::palette($settings->theme());
$description = $settings->tagline() !== '' ? $settings->tagline() : 'Réservez votre rendez-vous chez ' . $name . ' en ligne.';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= Html::e($title ?? $name) ?></title>
    <meta name="description" content="<?= Html::e($description) ?>">
    <meta property="og:title" content="<?= Html::e($name) ?>">
    <meta property="og:description" content="<?= Html::e($description) ?>">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="<?= Html::e($palette['accent']) ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/salon.css') ?>">
</head>
<body class="salon" style="--shop-accent: <?= Html::e($palette['accent']) ?>; --shop-soft: <?= Html::e($palette['soft']) ?>; --shop-ink: <?= Html::e($palette['ink']) ?>;">
<?= $content ?>
<script src="<?= Html::asset('assets/js/salon.js') ?>" defer></script>
</body>
</html>
