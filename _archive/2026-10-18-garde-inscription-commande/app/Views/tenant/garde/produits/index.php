<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\GardeProduct;

$products = is_array($products ?? null) ? $products : [];
$canManage = (bool) ($canManage ?? false);

?>
<section class="garde-page">
    <header class="garde-hero">
        <div>
            <p class="eyebrow">Stock</p>
            <h1>Produits</h1>
            <p>Lots, quantités et seuil d’alerte. La péremption se lit sur chaque lot.</p>
        </div>
        <div class="garde-hero-actions">
            <a class="btn btn-secondary" href="<?= Html::url('/garde/mouvements') ?>">Mouvements</a>
            <?php if ($canManage): ?>
                <a class="btn btn-primary" href="<?= Html::url('/garde/produits/nouveau') ?>" data-modal="Garde">Nouveau produit</a>
            <?php endif; ?>
        </div>
    </header>
    <?php if ($products === []): ?>
        <div class="empty"><p>Aucun produit. Commencez par le catalogue, puis ajoutez un lot avec une date de péremption.</p></div>
    <?php else: ?>
        <div class="garde-product-grid">
            <?php foreach ($products as $product): ?>
                <?php if (!$product instanceof GardeProduct) { continue; } ?>
                <a class="card garde-product-card" href="<?= Html::url('/garde/produits/' . $product->id()) ?>">
                    <span class="garde-product-photo">
                        <?php if ($product->hasImage()): ?>
                            <img src="<?= Html::url('/garde/produits/' . $product->id() . '/image') ?>" alt="" width="480" height="480" loading="lazy">
                        <?php else: ?>
                            <span class="garde-product-fallback" aria-hidden="true"><?= Html::e(Html::initials($product->name())) ?></span>
                        <?php endif; ?>
                    </span>
                    <strong><?= Html::e($product->name()) ?></strong>
                    <p class="muted"><?= Html::e($product->unit()) ?></p>
                    <p class="garde-stock-figure"><?= Html::e((string) $product->stockQuantity()) ?></p>
                    <?php if ($product->minQuantity() > 0 && $product->stockQuantity() <= $product->minQuantity()): ?>
                        <span class="badge">Sous le seuil</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
