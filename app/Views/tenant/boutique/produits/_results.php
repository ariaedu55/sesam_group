<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\Tenant;

$page = is_array($page ?? null) ? $page : [];
$products = is_array($page['products'] ?? null) ? $page['products'] : [];
$query = is_string($query ?? null) ? $query : '';
$filter = is_string($filter ?? null) ? $filter : '';
$category = is_string($category ?? null) ? $category : '';
$canManage = (bool) ($canManage ?? false);
$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$slug = $tenant?->slug() ?? '';
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';

?>
    <?php if ($products === []): ?>
        <div class="empty btq-card">
            <p><?= $query !== '' || $filter !== '' || $category !== '' ? 'Aucun produit ne correspond.' : 'Votre boutique est encore vide. Ajoutez votre premier produit : une photo, un nom, un prix, et c\'est en ligne.' ?></p>
            <?php if ($canManage && $query === '' && $filter === ''): ?>
                <p><a class="btn btn-primary" href="<?= Html::url('/boutique/produits/nouveau') ?>">Ajouter mon premier produit</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="muted record-index-count"><?= Html::e((string) count($products)) ?> produit(s) affiché(s)</p>
        <div class="btq-products">
            <?php foreach ($products as $product): ?>
                <?php if (!$product instanceof BoutiqueProduct) { continue; } ?>
                <?php $editUrl = Html::url('/boutique/produits/' . $product->id() . '/modifier'); ?>
                <article class="btq-product<?= $product->isPublished() ? '' : ' is-hidden' ?>" tabindex="0" data-row-href="<?= $editUrl ?>">
                    <div class="btq-product-media">
                        <?php if ($product->imagePath() !== null): ?>
                            <img src="<?= Html::url('/t/' . $slug . '/produit/' . $product->id() . '/image') ?>?v=<?= Html::e(rawurlencode($product->updatedAt())) ?>" alt="" width="320" height="240" loading="lazy">
                        <?php else: ?>
                            <span class="btq-placeholder"><?php $name = 'image'; include $icon; ?></span>
                        <?php endif; ?>
                        <?php if (!$product->isPublished()): ?>
                            <span class="btq-status btq-status-muted">Masqué</span>
                        <?php elseif ($product->isOutOfStock()): ?>
                            <span class="btq-status btq-status-danger">Épuisé</span>
                        <?php elseif ($product->isLowStock()): ?>
                            <span class="btq-status btq-status-warning">Bientôt épuisé</span>
                        <?php endif; ?>
                    </div>
                    <div class="btq-product-body">
                        <h3><?= Html::e($product->name()) ?></h3>
                        <?php if ($product->category() !== ''): ?><p class="muted"><?= Html::e($product->category()) ?></p><?php endif; ?>
                        <p class="btq-price"><?= Html::e(Money::format($product->priceMinor())) ?></p>
                        <p class="muted"><?= $product->tracksStock() ? Html::e((string) $product->stockQuantity()) . ' en stock' : 'Stock non suivi' ?></p>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="btq-product-foot">
                            <a class="btn btn-secondary btn-sm" href="<?= $editUrl ?>"><?php $name = 'edit'; include $icon; ?>Modifier</a>
                            <?php if ($product->tracksStock()): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= Html::url('/boutique/stock?produit=' . $product->id()) ?>"><?php $name = 'box'; include $icon; ?>Stock</a>
                            <?php endif; ?>
                            <form class="btq-inline" method="post" action="<?= Html::url('/boutique/produits/' . $product->id() . '/visibilite') ?>">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <button class="btn btn-ghost btn-sm" type="submit"><?= $product->isPublished() ? 'Masquer' : 'Afficher' ?></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
