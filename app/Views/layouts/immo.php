<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$agency = is_array($agency ?? null) ? $agency : [];
$name = is_string($agency['display_name'] ?? null) && $agency['display_name'] !== ''
    ? (string) $agency['display_name']
    : ($tenant?->displayName() ?? 'Agence');
$lead = is_string($lead ?? null) ? $lead : 'Parcourez les biens et laissez vos coordonnées.';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= Html::e($title ?? $name) ?></title>
    <meta name="description" content="<?= Html::e($lead) ?>">
    <meta property="og:title" content="<?= Html::e($name) ?>">
    <meta property="og:description" content="<?= Html::e($lead) ?>">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#071a3d">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/immo-portal.css') ?>">
</head>
<body class="im">
<?= $content ?>
</body>
</html>
