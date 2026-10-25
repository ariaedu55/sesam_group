<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\CommandeProduct;

$items = is_array($items ?? null) ? $items : [];
$canManage = (bool) ($canManage ?? false);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Produits</h1>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/commande/produits/nouveau') ?>" data-modal="Commande">Nouveau produit</a>
        <?php endif; ?>
    </div>
    <?php if ($items === []): ?>
        <div class="empty"><p>Aucun produit.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Tarif</th>
                    <th scope="col">Acompte</th>
                    <th scope="col">Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php if (!$item instanceof CommandeProduct) { continue; } ?>
                    <tr>
                        <td><?= Html::e($item->name()) ?></td>
                        <td><?= Html::e(number_format($item->priceMinor(), 0, ',', ' ')) ?> FCFA</td>
                        <td><?= Html::e(number_format($item->depositMinor(), 0, ',', ' ')) ?> FCFA</td>
                        <td><?= $item->isActive() ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
