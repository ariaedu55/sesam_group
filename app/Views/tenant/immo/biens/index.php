<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$items = is_array($items ?? null) ? $items : [];
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Biens</h1>
            <p class="muted">Vos immeubles, villas et locaux, avec leurs logements loués ou libres.</p>
        </div>
        <?php if ($canManage): ?><a class="btn btn-primary" href="<?= Html::url('/immo/biens/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau bien</a><?php endif; ?>
    </header>

    <?php if ($items === []): ?>
        <div class="btq-card empty">
            <p>Commencez par ajouter un bien (immeuble, villa…), puis ses logements.</p>
            <?php if ($canManage): ?><p><a class="btn btn-primary" href="<?= Html::url('/immo/biens/nouveau') ?>">Ajouter un bien</a></p><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="immo-cards">
            <?php foreach ($items as $item): ?>
                <?php $units = (int) $item['units']; $occupied = (int) $item['occupied']; ?>
                <a class="btq-card immo-property" href="<?= Html::url('/immo/biens/' . $item['id']) ?>">
                    <span class="immo-property-kind"><?= Html::e(ImmoCatalog::PROPERTY_KINDS[$item['kind']] ?? $item['kind']) ?></span>
                    <strong><?= Html::e($item['name']) ?></strong>
                    <small><?= Html::e(trim(($item['address'] ?? '') . (($item['city'] ?? '') !== '' && $item['city'] !== null ? ', ' . $item['city'] : ''), ', ')) ?: '&nbsp;' ?></small>
                    <span class="immo-occupancy">
                        <span class="immo-meter" aria-hidden="true"><i style="width: <?= $units > 0 ? (int) round($occupied * 100 / $units) : 0 ?>%"></i></span>
                        <small><?= $occupied ?> loué(s) sur <?= $units ?> logement(s)<?= $units - $occupied > 0 ? ' · ' . ($units - $occupied) . ' libre(s)' : '' ?></small>
                    </span>
                    <span class="immo-property-foot">
                        <strong><?= Html::e(Money::format((int) $item['monthly'])) ?></strong><small>/ mois</small>
                        <?php if ((int) $item['open_interventions'] > 0): ?><span class="btq-status btq-status-warning"><?= (int) $item['open_interventions'] ?> intervention(s)</span><?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
