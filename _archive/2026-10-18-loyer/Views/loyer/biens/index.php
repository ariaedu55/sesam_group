<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\LoyerProperty;

$items = is_array($items ?? null) ? $items : [];
$canManage = (bool) ($canManage ?? false);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Biens</h1>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/loyer/biens/nouveau') ?>" data-modal="Loyer">Nouveau bien</a>
        <?php endif; ?>
    </div>
    <?php if ($items === []): ?>
        <div class="empty"><p>Aucun bien enregistré.</p></div>
    <?php else: ?>
        <ul class="crm-list">
            <?php foreach ($items as $item): ?>
                <?php if (!$item instanceof LoyerProperty) { continue; } ?>
                <li><a href="<?= Html::url('/loyer/biens/' . $item->id()) ?>"><?= Html::e($item->name()) ?></a>
                    <?php if ($item->address() !== ''): ?> — <span class="muted"><?= Html::e($item->address()) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
