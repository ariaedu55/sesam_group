<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\BoutiqueSettings;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$settings = ($settings ?? null) instanceof BoutiqueSettings ? $settings : new BoutiqueSettings();
$product = ($product ?? null) instanceof BoutiqueProduct ? $product : null;
if ($tenant === null || $product === null) {
    return;
}
$whatsapp = is_string($whatsapp ?? null) ? $whatsapp : '';
$name = $tenant->displayName();
$base = '/t/' . $tenant->slug();
$version = rawurlencode($settings->updatedAt());
$canOrder = $settings->isOpen() && $settings->whatsappPhone() !== '' && $product->isAvailable();

?>
<header class="shop-top">
    <a class="shop-brand" href="<?= Html::url($base) ?>">
        <?php if ($settings->logoPath() !== null): ?>
            <img class="shop-logo" src="<?= Html::url($base . '/logo') ?>?v=<?= Html::e($version) ?>" alt="" width="44" height="44">
        <?php else: ?>
            <span class="shop-logo shop-logo-text" aria-hidden="true"><?= Html::e(Html::initials($name)) ?></span>
        <?php endif; ?>
        <span class="shop-brand-name"><?= Html::e($name) ?></span>
    </a>
</header>

<main class="shop-product">
    <p class="shop-product-back"><a href="<?= Html::url($base) ?>">← Tous les produits</a></p>
    <article class="shop-product-card">
        <div class="shop-product-media">
            <?php if ($product->imagePath() !== null): ?>
                <img src="<?= Html::url($base . '/produit/' . $product->id() . '/image') ?>?v=<?= Html::e(rawurlencode($product->updatedAt())) ?>" alt="<?= Html::e($product->name()) ?>" width="720" height="720">
            <?php else: ?>
                <span class="shop-card-placeholder" aria-hidden="true"><?= Html::e(Html::initials($product->name())) ?></span>
            <?php endif; ?>
            <?php if (!$product->isAvailable()): ?>
                <span class="shop-badge shop-badge-out">Épuisé</span>
            <?php elseif ($product->comparePriceMinor() !== null): ?>
                <?php $off = $product->comparePriceMinor() > 0 ? (int) round(100 - $product->priceMinor() * 100 / $product->comparePriceMinor()) : 0; ?>
                <span class="shop-badge"><?= $off > 0 ? '−' . $off . ' %' : 'Promo' ?></span>
            <?php endif; ?>
        </div>
        <div class="shop-product-body">
            <?php if ($product->category() !== ''): ?><p class="shop-card-cat"><?= Html::e($product->category()) ?></p><?php endif; ?>
            <h1><?= Html::e($product->name()) ?></h1>
            <p class="shop-price">
                <strong><?= Html::e(Money::format($product->priceMinor())) ?></strong>
                <?php if ($product->comparePriceMinor() !== null): ?><s><?= Html::e(Money::format($product->comparePriceMinor())) ?></s><?php endif; ?>
            </p>
            <?php if ($product->description() !== ''): ?>
                <p class="shop-product-desc"><?= nl2br(Html::e($product->description())) ?></p>
            <?php endif; ?>
            <?php if ($settings->showStock() && $product->tracksStock() && $product->isAvailable()): ?>
                <p class="shop-stock"><?= Html::e((string) $product->stockQuantity()) ?> en stock</p>
            <?php endif; ?>
            <div class="shop-product-actions">
                <?php if ($canOrder): ?>
                    <a class="shop-btn" href="<?= Html::url($base . '?ajouter=' . $product->id()) ?>">Ajouter au panier</a>
                <?php elseif (!$product->isAvailable()): ?>
                    <p class="shop-stock">Ce produit est épuisé pour le moment.</p>
                <?php endif; ?>
                <?php if ($whatsapp !== ''): ?>
                    <a class="shop-btn shop-whatsapp" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">Poser une question sur WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </article>
</main>
