<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var string $panelTitle */
/** @var string $panelIcon nom ui-icon */
/** @var string $panelActions HTML optionnel */

$panelTitle = is_string($panelTitle ?? null) ? $panelTitle : '';
$panelIcon = is_string($panelIcon ?? null) ? $panelIcon : 'file';
$panelActions = is_string($panelActions ?? null) ? $panelActions : '';
$icon = __DIR__ . '/ui-icon.php';

?>
<div class="record-panel-head">
    <span class="record-panel-icon" aria-hidden="true"><?php $name = $panelIcon; include $icon; ?></span>
    <h2><?= Html::e($panelTitle) ?></h2>
    <?php if ($panelActions !== ''): ?>
        <div class="record-panel-actions"><?= $panelActions ?></div>
    <?php endif; ?>
</div>
