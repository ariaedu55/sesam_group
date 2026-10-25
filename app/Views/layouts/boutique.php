<?php

declare(strict_types=1);

use Sesam\Core\Boutique\BoutiqueTheme;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueSettings;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$settings = ($settings ?? null) instanceof BoutiqueSettings ? $settings : new BoutiqueSettings();
$name = $tenant?->displayName() ?? 'Boutique';
$palette = BoutiqueTheme::palette($settings->theme());
$description = $settings->tagline() !== '' ? $settings->tagline() : 'Découvrez les produits de ' . $name . ' et commandez sur WhatsApp.';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? $name) ?></title>
    <meta name="description" content="<?= Html::e($description) ?>">
    <meta property="og:type" content="<?= isset($ogTitle) ? 'product' : 'website' ?>">
    <meta property="og:site_name" content="<?= Html::e($name) ?>">
    <meta property="og:title" content="<?= Html::e($ogTitle ?? $name) ?>">
    <meta property="og:description" content="<?= Html::e($ogDescription ?? $description) ?>">
    <?php if (!empty($ogUrl)): ?><meta property="og:url" content="<?= Html::e($ogUrl) ?>"><?php endif; ?>
    <?php if (!empty($ogImage)): ?>
        <meta property="og:image" content="<?= Html::e($ogImage) ?>">
        <meta property="og:image:alt" content="<?= Html::e($ogTitle ?? $name) ?>">
        <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <meta name="theme-color" content="<?= Html::e($palette['accent']) ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/boutique.css') ?>">
</head>
<body class="shop" style="--shop-accent: <?= Html::e($palette['accent']) ?>; --shop-soft: <?= Html::e($palette['soft']) ?>; --shop-ink: <?= Html::e($palette['ink']) ?>;">
<?= $content ?>
<script src="<?= Html::asset('assets/js/boutique.js') ?>" defer></script>
</body>
</html>
