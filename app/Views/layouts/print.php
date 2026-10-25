<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? 'Document') ?></title>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/print-doc.css') ?>">
</head>
<body class="doc">
<?= $content ?>
<script src="<?= Html::asset('assets/js/print-doc.js') ?>" defer></script>
</body>
</html>
