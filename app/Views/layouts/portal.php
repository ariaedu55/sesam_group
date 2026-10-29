<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$name = $tenant?->displayName() ?? 'Commerce';
$initials = Html::initials($name);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? $name) ?></title>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/sesam.css') ?>">
<?php include dirname(__DIR__) . '/partials/phone-input-assets.php'; ?>
</head>
<body class="portal">
    <header class="portal-top">
        <a class="portal-brand" href="<?= Html::url('/t/' . ($tenant?->slug() ?? '')) ?>">
            <span class="portal-mark" aria-hidden="true"><?= Html::e($initials) ?></span>
            <span class="portal-brand-text">
                <strong><?= Html::e($name) ?></strong>
                <?php if ($tenant !== null): ?>
                    <span><?= Html::e($tenant->sectorLabel()) ?></span>
                <?php endif; ?>
            </span>
        </a>
    </header>
    <main class="portal-main">
        <?= $content ?>
    </main>
    <footer class="portal-foot">
        <p>Lien public du commerce — sans compte à créer.</p>
        <p><a href="<?= Html::url('/') ?>">SESAM</a></p>
    </footer>
</body>
</html>
