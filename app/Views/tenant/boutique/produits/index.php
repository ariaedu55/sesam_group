<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\Tenant;

$page = is_array($page ?? null) ? $page : [];
$products = is_array($page['products'] ?? null) ? $page['products'] : [];
$categories = is_array($page['categories'] ?? null) ? $page['categories'] : [];
$summary = is_array($page['summary'] ?? null) ? $page['summary'] : [];
$query = is_string($query ?? null) ? $query : '';
$category = is_string($category ?? null) ? $category : '';
$filter = is_string($filter ?? null) ? $filter : '';
$canManage = (bool) ($canManage ?? false);
$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$slug = $tenant?->slug() ?? '';
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$tabs = [
    '' => ['Tous', (int) ($summary['total'] ?? 0)],
    'faible' => ['Bientôt épuisés', (int) ($summary['low'] ?? 0)],
    'rupture' => ['Épuisés', (int) ($summary['out'] ?? 0)],
    'masques' => ['Masqués', (int) ($summary['total'] ?? 0) - (int) ($summary['published'] ?? 0)],
];

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Mes produits</h1>
            <p class="muted"><?= Html::e((string) ($summary['published'] ?? 0)) ?> produit(s) visibles dans votre boutique en ligne.</p>
        </div>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/boutique/produits/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Ajouter un produit</a>
        <?php endif; ?>
    </header>

    <nav class="btq-tabs" aria-label="Filtrer les produits">
        <?php foreach ($tabs as $key => [$label, $count]): ?>
            <a href="<?= Html::url('/boutique/produits' . ($key !== '' ? '?filtre=' . $key : '')) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?><span><?= Html::e((string) max(0, $count)) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <form class="btq-filters" method="get" action="<?= Html::url('/boutique/produits') ?>" role="search">
        <label class="sr-only" for="q">Rechercher</label>
        <input class="input" id="q" name="q" type="search" placeholder="Rechercher un produit" value="<?= Html::e($query) ?>">
        <?php if ($categories !== []): ?>
            <label class="sr-only" for="categorie">Catégorie</label>
            <select class="input" id="categorie" name="categorie">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $item): ?>
                    <option value="<?= Html::e((string) $item) ?>"<?= $category === $item ? ' selected' : '' ?>><?= Html::e((string) $item) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php if ($filter !== ''): ?><input type="hidden" name="filtre" value="<?= Html::e($filter) ?>"><?php endif; ?>
        <button class="btn btn-secondary" type="submit">Chercher</button>
    </form>

    <?php if ($products === []): ?>
        <div class="empty btq-card">
            <p><?= $query !== '' || $filter !== '' || $category !== '' ? 'Aucun produit ne correspond.' : 'Votre boutique est encore vide. Ajoutez votre premier produit : une photo, un nom, un prix, et c\'est en ligne.' ?></p>
            <?php if ($canManage && $query === '' && $filter === ''): ?>
                <p><a class="btn btn-primary" href="<?= Html::url('/boutique/produits/nouveau') ?>">Ajouter mon premier produit</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="btq-products">
            <?php foreach ($products as $product): ?>
                <?php if (!$product instanceof BoutiqueProduct) { continue; } ?>
                <article class="btq-product<?= $product->isPublished() ? '' : ' is-hidden' ?>">
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
                            <a class="btn btn-secondary btn-sm" href="<?= Html::url('/boutique/produits/' . $product->id() . '/modifier') ?>"><?php $name = 'edit'; include $icon; ?>Modifier</a>
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
</section>
