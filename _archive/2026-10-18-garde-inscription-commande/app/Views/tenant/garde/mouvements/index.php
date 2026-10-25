<?php

declare(strict_types=1);

use Sesam\Core\Garde\MovementType;
use Sesam\Helpers\Html;
use Sesam\Models\GardeMovement;

$list = is_array($list ?? null) ? $list : [];
$movements = is_array($list['movements'] ?? null) ? $list['movements'] : [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$canManage = (bool) ($canManage ?? false);

?>
<section class="garde-page">
    <header class="garde-hero">
        <div>
            <p class="eyebrow">Stock</p>
            <h1>Mouvements</h1>
            <p>Entrées, sorties et ajustements. Chaque geste reste dans l’historique.</p>
        </div>
        <div class="garde-hero-actions">
            <a class="btn btn-secondary" href="<?= Html::url('/garde/produits') ?>">Produits</a>
            <?php if ($canManage): ?>
                <a class="btn btn-primary" href="<?= Html::url('/garde/mouvements/nouveau') ?>" data-modal="Garde">Nouveau mouvement</a>
            <?php endif; ?>
        </div>
    </header>
    <?php if ($movements === []): ?>
        <div class="empty"><p>Aucun mouvement pour le moment.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th scope="col">Quand</th>
                    <th scope="col">Type</th>
                    <th scope="col">Produit</th>
                    <th scope="col">Lot</th>
                    <th scope="col">Quantité</th>
                    <th scope="col">Motif</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($movements as $item): ?>
                    <?php if (!$item instanceof GardeMovement) { continue; } ?>
                    <tr>
                        <td><?= Html::e(gmdate('d/m/Y H:i', strtotime($item->createdAt()) ?: time())) ?></td>
                        <td><?= Html::e(MovementType::label($item->type())) ?></td>
                        <td><?= Html::e($item->productName()) ?></td>
                        <td><?= Html::e($item->lotCode()) ?></td>
                        <td><?= Html::e((string) $item->quantity()) ?></td>
                        <td><?= Html::e($item->reason() !== '' ? $item->reason() : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <p class="row">
                <?php if ($page > 1): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/garde/mouvements?page=' . ($page - 1)) ?>">Précédent</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/garde/mouvements?page=' . ($page + 1)) ?>">Suivant</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>
