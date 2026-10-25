<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\BoutiqueSettings;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$settings = ($settings ?? null) instanceof BoutiqueSettings ? $settings : new BoutiqueSettings();
$products = is_array($products ?? null) ? $products : [];
$categories = is_array($categories ?? null) ? $categories : [];
$whatsapp = is_string($whatsapp ?? null) ? $whatsapp : '';
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$slug = $tenant?->slug() ?? '';
$name = $tenant?->displayName() ?? 'Boutique';
$base = '/t/' . $slug;
$version = rawurlencode($settings->updatedAt());
$oldItems = is_array($old['items'] ?? null) ? $old['items'] : [];
$canOrder = $settings->isOpen() && $settings->whatsappPhone() !== '';
$val = static function (string $key) use ($old): string {
    $value = $old[$key] ?? '';

    return is_scalar($value) ? (string) $value : '';
};
$socials = array_filter([
    'Instagram' => $settings->instagramUrl(),
    'Facebook' => $settings->facebookUrl(),
    'TikTok' => $settings->tiktokUrl(),
]);

?>
<header class="shop-top">
    <a class="shop-brand" href="<?= Html::url($base) ?>">
        <?php if ($settings->logoPath() !== null): ?>
            <img class="shop-logo" src="<?= Html::url($base . '/logo') ?>?v=<?= Html::e($version) ?>" alt="" width="40" height="40">
        <?php else: ?>
            <span class="shop-logo shop-logo-text" aria-hidden="true"><?= Html::e(Html::initials($name)) ?></span>
        <?php endif; ?>
        <span class="shop-brand-name"><?= Html::e($name) ?></span>
    </a>
    <?php if ($canOrder): ?>
        <a class="shop-cart-link" href="#commande" data-cart-open aria-label="Voir mon panier">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 7h12l-1.2 11.1a2 2 0 0 1-2 1.9H9.2a2 2 0 0 1-2-1.9L6 7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9V6a3 3 0 0 1 6 0v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span class="shop-cart-label">Panier</span>
            <span class="shop-cart-count" data-cart-count>0</span>
        </a>
    <?php endif; ?>
</header>

<section class="shop-hero<?= $settings->coverPath() !== null ? ' has-cover' : '' ?>">
    <?php if ($settings->coverPath() !== null): ?>
        <div class="shop-hero-media"><img class="shop-cover" src="<?= Html::url($base . '/couverture') ?>?v=<?= Html::e($version) ?>" alt="" width="1600" height="640"></div>
    <?php endif; ?>
    <div class="shop-hero-inner">
        <?php if ($settings->logoPath() !== null): ?>
            <img class="shop-avatar" src="<?= Html::url($base . '/logo') ?>?v=<?= Html::e($version) ?>" alt="Logo de <?= Html::e($name) ?>" width="112" height="112">
        <?php else: ?>
            <span class="shop-avatar is-text" aria-hidden="true"><?= Html::e(Html::initials($name)) ?></span>
        <?php endif; ?>
        <div class="shop-hero-text">
            <p class="shop-open<?= $canOrder ? ' is-open' : '' ?>"><i aria-hidden="true"></i><?= $canOrder ? 'Commandes ouvertes' : ($settings->isOpen() ? 'Commandes bientôt ouvertes' : 'Boutique fermée pour le moment') ?></p>
            <h1><?= Html::e($name) ?></h1>
            <?php if ($settings->tagline() !== ''): ?>
                <p class="shop-tagline"><?= Html::e($settings->tagline()) ?></p>
            <?php endif; ?>
            <ul class="shop-facts">
                <?php if ($settings->address() !== ''): ?>
                    <li><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="9.5" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><?= Html::e($settings->address()) ?></li>
                <?php endif; ?>
                <?php if ($settings->openingHours() !== ''): ?>
                    <li><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5V12l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><?= Html::e($settings->openingHours()) ?></li>
                <?php endif; ?>
                <li><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><?= count($products) ?> produit<?= count($products) > 1 ? 's' : '' ?></li>
            </ul>
            <div class="shop-hero-actions">
                <?php if ($products !== []): ?>
                    <a class="shop-btn shop-btn-light" href="#produits">Voir les produits</a>
                <?php endif; ?>
                <?php if ($whatsapp !== ''): ?>
                    <a class="shop-btn shop-btn-ghost" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">Poser une question sur WhatsApp</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<main class="shop-main">
<ul class="shop-perks" aria-label="Comment commander">
    <li><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 7h12l-1.2 11.1a2 2 0 0 1-2 1.9H9.2a2 2 0 0 1-2-1.9L6 7Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 9V6a3 3 0 0 1 6 0v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span><b>Commande facile</b>sans compte ni application</span></li>
    <li><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg><span><b>Sur WhatsApp</b>la boutique vous répond</span></li>
    <li><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="7" cy="17.5" r="1.8" fill="currentColor"/><circle cx="17" cy="17.5" r="1.8" fill="currentColor"/></svg><span><b>Livraison</b><?= Html::e($settings->deliveryInfo() !== '' ? $settings->deliveryInfo() : 'ou retrait, à convenir') ?></span></li>
</ul>

<?php if ($settings->description() !== ''): ?>
    <section class="shop-about">
        <h2>À propos de <?= Html::e($name) ?></h2>
        <p><?= nl2br(Html::e($settings->description())) ?></p>
    </section>
<?php endif; ?>

<?php if (!$settings->isOpen()): ?>
    <p class="shop-notice" role="status">La boutique est fermée pour le moment. Vous pouvez regarder les produits, les commandes reprendront bientôt.</p>
<?php elseif ($settings->whatsappPhone() === ''): ?>
    <p class="shop-notice" role="status">Les commandes en ligne ouvrent bientôt.</p>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="shop-alert" role="alert" id="erreurs">
        <?php foreach ($errors as $message): ?>
            <p><?= Html::e((string) $message) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form class="shop-form" method="post" action="<?= Html::url($base . '/commander') ?>" data-shop="<?= Html::e($slug) ?>" novalidate>
    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

    <?php if ($products === []): ?>
        <div class="shop-empty">
            <svg viewBox="0 0 48 48" width="56" height="56" aria-hidden="true"><path d="M10 16h28l-2.5 22a3 3 0 0 1-3 2.7H15.5a3 3 0 0 1-3-2.7L10 16Z" fill="var(--shop-soft)" stroke="var(--shop-accent)" stroke-width="2.4" stroke-linejoin="round"/><path d="M17 19v-5a7 7 0 0 1 14 0v5" fill="none" stroke="var(--shop-accent)" stroke-width="2.4" stroke-linecap="round"/></svg>
            <p>Les produits arrivent très bientôt.</p>
        </div>
    <?php else: ?>
        <div class="shop-section-head" id="produits">
            <h2>Nos produits</h2>
            <p>Ajoutez vos articles au panier : la commande part toute prête sur WhatsApp.</p>
        </div>
        <div class="shop-tools">
            <label class="shop-search">
                <span class="sr-only">Rechercher un produit</span>
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <input type="search" placeholder="Rechercher un produit" data-shop-search autocomplete="off">
            </label>
            <?php if ($categories !== []): ?>
                <div class="shop-chips" role="group" aria-label="Catégories">
                    <button type="button" class="shop-chip is-on" data-category="">Tout</button>
                    <?php foreach ($categories as $category): ?>
                        <button type="button" class="shop-chip" data-category="<?= Html::e((string) $category) ?>"><?= Html::e((string) $category) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="shop-grid" data-shop-grid>
            <?php foreach ($products as $product): ?>
                <?php
                if (!$product instanceof BoutiqueProduct) {
                    continue;
                }
                $id = $product->id();
                $max = $product->tracksStock() ? min(99, max(0, $product->stockQuantity())) : 99;
                $qty = (int) ($oldItems[$id] ?? 0);
                ?>
                <article class="shop-card<?= $product->isAvailable() ? '' : ' is-out' ?>"
                         data-product="<?= $id ?>"
                         data-name="<?= Html::e($product->name()) ?>"
                         data-price="<?= $product->priceMinor() ?>"
                         data-category="<?= Html::e($product->category()) ?>"
                         data-max="<?= $max ?>">
                    <a class="shop-card-media" href="<?= Html::url($base . '/produit/' . $id) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($product->imagePath() !== null): ?>
                            <img src="<?= Html::url($base . '/produit/' . $id . '/image') ?>?v=<?= Html::e(rawurlencode($product->updatedAt())) ?>" alt="<?= Html::e($product->name()) ?>" width="480" height="480" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="shop-card-placeholder" aria-hidden="true"><?= Html::e(Html::initials($product->name())) ?></span>
                        <?php endif; ?>
                        <?php if (!$product->isAvailable()): ?>
                            <span class="shop-badge shop-badge-out">Épuisé</span>
                        <?php elseif ($product->comparePriceMinor() !== null): ?>
                            <?php $off = $product->comparePriceMinor() > 0 ? (int) round(100 - $product->priceMinor() * 100 / $product->comparePriceMinor()) : 0; ?>
                            <span class="shop-badge"><?= $off > 0 ? '−' . $off . ' %' : 'Promo' ?></span>
                        <?php elseif ($product->isLowStock()): ?>
                            <span class="shop-badge shop-badge-low">Bientôt épuisé</span>
                        <?php endif; ?>
                    </a>
                    <div class="shop-card-body">
                        <?php if ($product->category() !== ''): ?>
                            <p class="shop-card-cat"><?= Html::e($product->category()) ?></p>
                        <?php endif; ?>
                        <h3><a class="shop-card-link" href="<?= Html::url($base . '/produit/' . $id) ?>"><?= Html::e($product->name()) ?></a></h3>
                        <p class="shop-price">
                            <strong><?= Html::e(Money::format($product->priceMinor())) ?></strong>
                            <?php if ($product->comparePriceMinor() !== null): ?>
                                <s><?= Html::e(Money::format($product->comparePriceMinor())) ?></s>
                            <?php endif; ?>
                        </p>
                        <?php if ($product->description() !== ''): ?>
                            <p class="shop-card-desc"><?= Html::e($product->description()) ?></p>
                        <?php endif; ?>
                        <?php if ($settings->showStock() && $product->tracksStock() && $product->isAvailable()): ?>
                            <p class="shop-stock"><?= Html::e((string) $product->stockQuantity()) ?> en stock</p>
                        <?php endif; ?>
                        <?php if ($canOrder && $product->isAvailable()): ?>
                            <div class="shop-qty" data-qty>
                                <button type="button" class="shop-qty-btn" data-step="-1" aria-label="Retirer un <?= Html::e($product->name()) ?>">−</button>
                                <label class="sr-only" for="qty-<?= $id ?>">Quantité de <?= Html::e($product->name()) ?></label>
                                <input id="qty-<?= $id ?>" name="items[<?= $id ?>]" type="number" inputmode="numeric" min="0" max="<?= $max ?>" value="<?= $qty ?>">
                                <button type="button" class="shop-qty-btn" data-step="1" aria-label="Ajouter un <?= Html::e($product->name()) ?>">+</button>
                            </div>
                            <button type="button" class="shop-btn shop-add" data-add><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Ajouter<span class="shop-add-more"> au panier</span></button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="shop-noresult" data-shop-noresult hidden>Aucun produit ne correspond à votre recherche.</p>
    <?php endif; ?>

    <?php if ($canOrder && $products !== []): ?>
        <section class="shop-checkout" id="commande" data-checkout aria-labelledby="checkout-title">
            <div class="shop-checkout-sheet">
                <header class="shop-checkout-head">
                    <h2 id="checkout-title">Ma commande</h2>
                    <button type="button" class="shop-close" data-cart-close aria-label="Fermer le panier">×</button>
                </header>
                <ul class="shop-lines" data-cart-lines>
                    <li class="shop-lines-empty">Choisissez vos produits avec les boutons + puis revenez ici.</li>
                </ul>
                <p class="shop-total">Total <strong data-cart-total>0 FCFA</strong></p>
                <div class="shop-fields">
                    <div class="shop-field">
                        <label for="customer_name">Votre nom</label>
                        <input id="customer_name" name="customer_name" required maxlength="120" autocomplete="name" value="<?= Html::e($val('customer_name')) ?>"<?= isset($errors['customer_name']) ? ' aria-invalid="true"' : '' ?>>
                    </div>
                    <div class="shop-field">
                        <label for="customer_phone">Votre téléphone</label>
                        <input id="customer_phone" name="customer_phone" type="tel" required maxlength="40" autocomplete="tel" placeholder="77 123 45 67" value="<?= Html::e($val('customer_phone')) ?>"<?= isset($errors['customer_phone']) ? ' aria-invalid="true"' : '' ?>>
                    </div>
                    <div class="shop-field">
                        <label for="customer_address">Adresse de livraison <span>(facultatif)</span></label>
                        <input id="customer_address" name="customer_address" maxlength="255" autocomplete="street-address" value="<?= Html::e($val('customer_address')) ?>">
                    </div>
                    <div class="shop-field">
                        <label for="note">Un message pour la boutique <span>(facultatif)</span></label>
                        <textarea id="note" name="note" rows="2" maxlength="500"><?= Html::e($val('note')) ?></textarea>
                    </div>
                </div>
                <button class="shop-btn shop-whatsapp" type="submit" data-cart-submit>
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 2.2A9.7 9.7 0 0 0 3.6 16.8L2.3 21.7l5-1.3A9.7 9.7 0 1 0 12 2.2Zm0 17.7a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 19.9Zm4.4-6c-.2-.1-1.4-.7-1.7-.8-.2-.1-.4-.1-.5.1l-.8.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.5-.4h-.5a.9.9 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1 4.9 4.9 0 0 0 1 2.6 11.2 11.2 0 0 0 4.3 3.8c1.6.7 2.2.7 3 .6a2.6 2.6 0 0 0 1.7-1.2 2.1 2.1 0 0 0 .2-1.2c-.1-.2-.3-.2-.5-.3Z"/></svg>
                    Commander sur WhatsApp
                </button>
                <p class="shop-hint">Votre commande s'ouvre dans WhatsApp, déjà écrite. Il ne reste qu'à l'envoyer.</p>
            </div>
        </section>
        <div class="shop-bar" data-cart-bar hidden>
            <span><strong data-bar-count>0</strong> article(s) · <strong data-bar-total>0 FCFA</strong></span>
            <a class="shop-btn" href="#commande" data-cart-open>Voir ma commande</a>
        </div>
    <?php endif; ?>
</form>
</main>

<footer class="shop-foot">
    <?php if ($socials !== []): ?>
        <p class="shop-socials">
            <?php foreach ($socials as $label => $url): ?>
                <a href="<?= Html::e($url) ?>" target="_blank" rel="noopener"><?= Html::e($label) ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <p>Boutique en ligne créée avec <a href="<?= Html::url('/services/boutique') ?>">Sesam Boutique</a></p>
</footer>
