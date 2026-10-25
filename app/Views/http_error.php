<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

?>
<main class="page">
<h1><?= Html::e($title ?? 'Erreur') ?></h1>
<p><?= Html::e($message ?? 'Une erreur est survenue.') ?></p>
<?php if (isset($errors) && is_array($errors) && $errors !== []): ?>
    <ul>
        <?php foreach ($errors as $error): ?>
            <li><?= Html::e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
</main>
