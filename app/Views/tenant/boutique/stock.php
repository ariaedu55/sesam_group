<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\StockMovementType;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\BoutiqueStockMovement;

$page = is_array($page ?? null) ? $page : [];
$products = is_array($page['products'] ?? null) ? $page['products'] : [];
$movements = is_array($page['movements'] ?? null) ? $page['movements'] : [];
$summary = is_array($page['summary'] ?? null) ? $page['summary'] : [];
$selected = ($page['selected'] ?? null) instanceof BoutiqueProduct ? $page['selected'] : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$oldProduct = (string) ($old['product_id'] ?? ($selected?->id() ?? ''));
$oldType = (string) ($old['type'] ?? StockMovementType::IN);
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$types = [
    StockMovementType::IN => ['J\'ai reçu de la marchandise', 'Ajoute la quantité au stock'],
    StockMovementType::OUT => ['J\'ai retiré des articles', 'Casse, cadeau, usage personnel…'],
    StockMovementType::ADJUST => ['J\'ai compté mon stock', 'Remplace par la quantité comptée'],
];

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Stock</h1>
            <p class="muted">Les ventes confirmées retirent le stock toutes seules. Ici, vous notez les arrivages et les corrections.</p>
        </div>
    </header>

    <div class="btq-kpis">
        <article class="btq-kpi"><p>Produits suivis</p><strong><?= Html::e((string) count($products)) ?></strong></article>
        <article class="btq-kpi<?= (int) ($summary['low'] ?? 0) > 0 ? ' is-alert' : '' ?>"><p>Bientôt épuisés</p><strong><?= Html::e((string) ($summary['low'] ?? 0)) ?></strong></article>
        <article class="btq-kpi<?= (int) ($summary['out'] ?? 0) > 0 ? ' is-alert' : '' ?>"><p>Épuisés</p><strong><?= Html::e((string) ($summary['out'] ?? 0)) ?></strong></article>
        <article class="btq-kpi"><p>Valeur du stock</p><strong><?= Html::e(Money::format((int) ($summary['stock_value'] ?? 0))) ?></strong></article>
    </div>

    <div class="btq-two">
        <section class="btq-card">
            <h2>Quantités</h2>
            <?php if ($products === []): ?>
                <p class="muted">Aucun produit ne suit son stock. Ajoutez un produit en cochant « Suivre le stock ».</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($products as $product): ?>
                        <?php if (!$product instanceof BoutiqueProduct) { continue; } ?>
                        <li>
                            <a href="<?= Html::url('/boutique/stock?produit=' . $product->id()) ?>"<?= $selected?->id() === $product->id() ? ' aria-current="true"' : '' ?>>
                                <span>
                                    <strong><?= Html::e($product->name()) ?></strong>
                                    <small>Alerte à <?= Html::e((string) $product->lowStockThreshold()) ?></small>
                                </span>
                                <span class="btq-status btq-status-<?= $product->isOutOfStock() ? 'danger' : ($product->isLowStock() ? 'warning' : 'success') ?>">
                                    <?= Html::e((string) $product->stockQuantity()) ?> en stock
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($canManage && $products !== []): ?>
            <form class="btq-card btq-form" method="post" action="<?= Html::url('/boutique/stock') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <h2>Mettre à jour</h2>
                <div class="field">
                    <label for="product_id"><span>Produit</span></label>
                    <select class="input" id="product_id" name="product_id" required>
                        <option value="">Choisir…</option>
                        <?php foreach ($products as $product): ?>
                            <?php if (!$product instanceof BoutiqueProduct) { continue; } ?>
                            <option value="<?= $product->id() ?>"<?= $oldProduct === (string) $product->id() ? ' selected' : '' ?>><?= Html::e($product->name()) ?> (<?= Html::e((string) $product->stockQuantity()) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php $err('product_id'); ?>
                </div>
                <fieldset>
                    <legend>Que s'est-il passé ?</legend>
                    <?php foreach ($types as $type => [$label, $hint]): ?>
                        <label class="btq-switch">
                            <input type="radio" name="type" value="<?= Html::e($type) ?>"<?= $oldType === $type ? ' checked' : '' ?>>
                            <span><strong><?= Html::e($label) ?></strong><span class="btq-help"><?= Html::e($hint) ?></span></span>
                        </label>
                    <?php endforeach; ?>
                    <?php $err('type'); ?>
                </fieldset>
                <div class="field">
                    <label for="quantity"><span>Quantité</span></label>
                    <input class="input" id="quantity" name="quantity" type="number" min="0" max="100000" required inputmode="numeric" value="<?= Html::e((string) ($old['quantity'] ?? '')) ?>">
                    <?php $err('quantity'); ?>
                </div>
                <div class="field">
                    <label for="note"><span>Note (facultatif)</span></label>
                    <input class="input" id="note" name="note" maxlength="255" placeholder="Ex. : livraison du fournisseur" value="<?= Html::e((string) ($old['note'] ?? '')) ?>">
                </div>
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </form>
        <?php endif; ?>
    </div>

    <section class="btq-card">
        <header class="btq-card-head">
            <h2>Historique<?= $selected !== null ? ' — ' . Html::e($selected->name()) : '' ?></h2>
            <?php if ($selected !== null): ?><a class="muted" href="<?= Html::url('/boutique/stock') ?>">Tous les produits</a><?php endif; ?>
        </header>
        <?php if ($movements === []): ?>
            <p class="muted">Aucun mouvement pour le moment.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Produit</th>
                        <th scope="col">Quoi</th>
                        <th scope="col">Quantité</th>
                        <th scope="col">Stock après</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <?php if (!$movement instanceof BoutiqueStockMovement) { continue; } ?>
                        <tr>
                            <td><?= Html::e(gmdate('d/m/Y H:i', strtotime($movement->createdAt()) ?: time())) ?></td>
                            <td><?= Html::e($movement->productName()) ?></td>
                            <td>
                                <?= Html::e(StockMovementType::label($movement->type())) ?>
                                <?php if ($movement->orderId() !== null): ?>
                                    — <a href="<?= Html::url('/boutique/commandes/' . $movement->orderId()) ?>"><?= Html::e($movement->note()) ?></a>
                                <?php elseif ($movement->note() !== ''): ?>
                                    <br><small class="muted"><?= Html::e($movement->note()) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= $movement->quantity() > 0 ? '+' : '' ?><?= Html::e((string) $movement->quantity()) ?></strong></td>
                            <td><?= Html::e((string) $movement->stockAfter()) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
