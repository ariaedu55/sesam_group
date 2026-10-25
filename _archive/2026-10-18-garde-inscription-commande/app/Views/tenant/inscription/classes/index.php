<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\InscriptionClass;

$classes = is_array($classes ?? null) ? $classes : [];
$canManage = (bool) ($canManage ?? false);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Classes</h1>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/inscription/classes/nouveau') ?>" data-modal="Inscription">Nouvelle classe</a>
        <?php endif; ?>
    </div>
    <?php if ($classes === []): ?>
        <div class="empty"><p>Aucune classe.</p></div>
    <?php else: ?>
        <ul class="link-list">
            <?php foreach ($classes as $item): ?>
                <?php if (!$item instanceof InscriptionClass) { continue; } ?>
                <li>
                    <?= Html::e($item->name()) ?>
                    <?php if (!$item->isActive()): ?><span class="muted"> (inactive)</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
