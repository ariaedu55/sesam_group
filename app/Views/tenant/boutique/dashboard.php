<?php

declare(strict_types=1);

use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueOrder;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\User;

$actor = ($actor ?? null) instanceof User ? $actor : null;
$board = is_array($board ?? null) ? $board : [];
$greeting = is_string($greeting ?? null) ? $greeting : '';
$tenant = $board['tenant'] ?? null;
$publicUrl = (string) ($board['publicUrl'] ?? '');
$shareLink = (string) ($board['shareLink'] ?? '');
$shareText = (string) ($board['shareText'] ?? '');
$counts = is_array($board['statusCounts'] ?? null) ? $board['statusCounts'] : [];
$products = is_array($board['products'] ?? null) ? $board['products'] : [];
$recent = is_array($board['recentOrders'] ?? null) ? $board['recentOrders'] : [];
$lowStock = is_array($board['lowStock'] ?? null) ? $board['lowStock'] : [];
$checklist = is_array($board['checklist'] ?? null) ? $board['checklist'] : [];
$remaining = count(array_filter($checklist, static fn ($step): bool => is_array($step) && empty($step['done'])));
$can = static fn (string $permission): bool => $actor instanceof User && $actor->can($permission);
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$shopName = $tenant instanceof \Sesam\Models\Tenant ? $tenant->displayName() : '';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Bonjour <?= Html::e($greeting) ?></h1>
            <p class="muted">Voici votre boutique <?= Html::e($shopName) ?> aujourd'hui.</p>
        </div>
    </header>

    <?php if (empty($board['whatsappReady'])): ?>
        <p class="alert alert-strong" role="status">
            Ajoutez le numéro WhatsApp de votre boutique : c'est là que vous recevrez les commandes de vos clients.
            <?php if ($can(Permission::BOUTIQUE_SETTINGS)): ?>
                <a href="<?= Html::url('/boutique/parametres') ?>">Ajouter mon numéro</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <section class="btq-card btq-share" id="partager" aria-labelledby="share-title">
        <div>
            <h2 id="share-title">Votre boutique en ligne</h2>
            <p>Partagez ce lien sur WhatsApp, Facebook ou Instagram. Vos clients voient vos produits et commandent en un clic.</p>
        </div>
        <div class="btq-share-url">
            <label class="sr-only" for="public-url">Lien de la boutique</label>
            <input id="public-url" type="text" readonly value="<?= Html::e($publicUrl) ?>">
        </div>
        <div class="btq-actions">
            <button class="btn btn-primary" type="button" data-copy="public-url">
                <?php $name = 'link'; include $icon; ?><span data-copy-label>Copier le lien</span>
            </button>
            <a class="btn" href="<?= Html::e($shareLink) ?>" target="_blank" rel="noopener">
                <?php $name = 'chat'; include $icon; ?>Envoyer sur WhatsApp
            </a>
            <button class="btn" type="button" hidden data-share="<?= Html::e($publicUrl) ?>" data-share-title="<?= Html::e($shopName) ?>" data-share-text="<?= Html::e($shareText) ?>">
                <?php $name = 'share'; include $icon; ?>Partager
            </button>
            <a class="btn" href="<?= Html::e($publicUrl) ?>" target="_blank" rel="noopener">
                <?php $name = 'eye'; include $icon; ?>Voir ma boutique
            </a>
        </div>
    </section>

    <nav class="btq-tiles" aria-label="Actions rapides">
        <?php if ($can(Permission::BOUTIQUE_PRODUCTS)): ?>
            <a class="btq-tile" href="<?= Html::url('/boutique/produits/nouveau') ?>">
                <span class="btq-tile-icon"><?php $name = 'plus'; include $icon; ?></span>
                Ajouter un produit
                <small>Photo, prix, quantité</small>
            </a>
        <?php endif; ?>
        <?php if ($can(Permission::BOUTIQUE_ORDERS)): ?>
            <a class="btq-tile" data-tone="blue" href="<?= Html::url('/boutique/commandes/nouveau') ?>">
                <span class="btq-tile-icon"><?php $name = 'bag'; include $icon; ?></span>
                Noter une vente
                <small>Vente en boutique ou par téléphone</small>
            </a>
        <?php endif; ?>
        <a class="btq-tile" data-tone="amber" href="<?= Html::url('/boutique/stock') ?>">
            <span class="btq-tile-icon"><?php $name = 'box'; include $icon; ?></span>
            Mettre à jour le stock
            <small>Arrivage, sortie, inventaire</small>
        </a>
        <a class="btq-tile" data-tone="violet" href="<?= Html::url('/boutique/parametres') ?>">
            <span class="btq-tile-icon"><?php $name = 'store'; include $icon; ?></span>
            Personnaliser ma boutique
            <small>Logo, couleurs, WhatsApp</small>
        </a>
    </nav>

    <div class="btq-kpis">
        <article class="btq-kpi<?= ($counts[OrderStatus::NEW] ?? 0) > 0 ? ' is-alert' : '' ?>">
            <p>Commandes à traiter</p>
            <strong><?= Html::e((string) ($counts[OrderStatus::NEW] ?? 0)) ?></strong>
            <a href="<?= Html::url('/boutique/commandes?statut=new') ?>">Voir</a>
        </article>
        <article class="btq-kpi">
            <p>Commandes aujourd'hui</p>
            <strong><?= Html::e((string) ($board['ordersToday'] ?? 0)) ?></strong>
        </article>
        <article class="btq-kpi">
            <p>Ventes du mois</p>
            <strong><?= Html::e(Money::format((int) ($board['revenueMonth'] ?? 0))) ?></strong>
        </article>
        <article class="btq-kpi<?= ((int) ($products['out'] ?? 0) + (int) ($products['low'] ?? 0)) > 0 ? ' is-alert' : '' ?>">
            <p>Produits en ligne</p>
            <strong><?= Html::e((string) ($products['published'] ?? 0)) ?></strong>
            <?php if ((int) ($products['out'] ?? 0) > 0): ?>
                <a href="<?= Html::url('/boutique/produits?filtre=rupture') ?>"><?= Html::e((string) $products['out']) ?> en rupture</a>
            <?php elseif ((int) ($products['low'] ?? 0) > 0): ?>
                <a href="<?= Html::url('/boutique/produits?filtre=faible') ?>"><?= Html::e((string) $products['low']) ?> bientôt épuisé(s)</a>
            <?php endif; ?>
        </article>
    </div>

    <div class="btq-two">
        <section class="btq-card">
            <header class="btq-card-head">
                <h2>Dernières commandes</h2>
                <a class="muted" href="<?= Html::url('/boutique/commandes') ?>">Tout voir</a>
            </header>
            <?php if ($recent === []): ?>
                <p class="muted">Pas encore de commande. Partagez le lien de votre boutique pour recevoir les premières.</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($recent as $order): ?>
                        <?php if (!$order instanceof BoutiqueOrder) { continue; } ?>
                        <li>
                            <a href="<?= Html::url('/boutique/commandes/' . $order->id()) ?>">
                                <span>
                                    <strong><?= Html::e($order->customerName()) ?></strong>
                                    <small><?= Html::e($order->orderNumber()) ?> · <?= Html::e((string) $order->itemCount()) ?> article(s)</small>
                                </span>
                                <span class="btq-list-end">
                                    <strong><?= Html::e(Money::format($order->totalMinor())) ?></strong>
                                    <span class="btq-status btq-status-<?= Html::e(OrderStatus::tone($order->status())) ?>"><?= Html::e(OrderStatus::label($order->status())) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="btq">
            <?php if ($remaining > 0): ?>
                <section class="btq-card">
                    <h2>Pour bien démarrer</h2>
                    <ol class="btq-steps">
                        <?php foreach ($checklist as $step): ?>
                            <?php if (!is_array($step)) { continue; } ?>
                            <li class="<?= !empty($step['done']) ? 'is-done' : '' ?>">
                                <a href="<?= Html::url((string) $step['href']) ?>">
                                    <span class="btq-check" aria-hidden="true"><?php if (!empty($step['done'])) { $name = 'check'; include $icon; } ?></span>
                                    <span><?= Html::e((string) $step['label']) ?><?= !empty($step['done']) ? ' (fait)' : '' ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>

            <section class="btq-card">
                <header class="btq-card-head">
                    <h2>Stock à surveiller</h2>
                    <a class="muted" href="<?= Html::url('/boutique/stock') ?>">Stock</a>
                </header>
                <?php if ($lowStock === []): ?>
                    <p class="muted">Tout va bien : aucun produit n'est presque épuisé.</p>
                <?php else: ?>
                    <ul class="btq-list">
                        <?php foreach ($lowStock as $product): ?>
                            <?php if (!$product instanceof BoutiqueProduct) { continue; } ?>
                            <li>
                                <a href="<?= Html::url('/boutique/stock?produit=' . $product->id()) ?>">
                                    <strong><?= Html::e($product->name()) ?></strong>
                                    <span class="btq-status btq-status-<?= $product->isOutOfStock() ? 'danger' : 'warning' ?>">
                                        <?= $product->isOutOfStock() ? 'Épuisé' : Html::e((string) $product->stockQuantity()) . ' restant(s)' ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
