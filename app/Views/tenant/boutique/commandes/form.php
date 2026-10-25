<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueProduct;
use Sesam\Models\Client;

$data = is_array($data ?? null) ? $data : [];
$products = is_array($data['products'] ?? null) ? $data['products'] : [];
$clients = is_array($data['clients'] ?? null) ? $data['clients'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$val = static fn (string $key): string => is_scalar($old[$key] ?? null) ? (string) $old[$key] : '';
$oldProducts = is_array($old['product_id'] ?? null) ? array_values($old['product_id']) : [''];
$oldQuantities = is_array($old['quantity'] ?? null) ? array_values($old['quantity']) : ['1'];
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Noter une vente</h1>
            <p class="muted">Pour une vente faite en boutique, au téléphone ou par message. Les commandes en ligne s'ajoutent toutes seules.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/boutique/commandes') ?>">Retour</a>
    </header>

    <?php if ($products === []): ?>
        <div class="empty btq-card">
            <p>Ajoutez d'abord un produit pour pouvoir noter une vente.</p>
            <p><a class="btn btn-primary" href="<?= Html::url('/boutique/produits/nouveau') ?>">Ajouter un produit</a></p>
        </div>
    <?php else: ?>
        <?php if ($errors !== []): ?>
            <p class="alert alert-danger" role="alert">Vérifiez les informations indiquées en rouge.</p>
        <?php endif; ?>
        <form class="btq-form" method="post" action="<?= Html::url('/boutique/commandes') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

            <fieldset>
                <legend>1. Les produits</legend>
                <div class="btq-lines" data-lines>
                    <?php foreach ($oldProducts as $index => $productId): ?>
                        <div class="btq-line" data-line>
                            <div class="field">
                                <label class="sr-only" for="product-<?= (int) $index ?>">Produit</label>
                                <select class="input" id="product-<?= (int) $index ?>" name="product_id[]">
                                    <option value="">Choisir un produit…</option>
                                    <?php foreach ($products as $product): ?>
                                        <?php if (!$product instanceof BoutiqueProduct) { continue; } ?>
                                        <option value="<?= $product->id() ?>"<?= (string) $productId === (string) $product->id() ? ' selected' : '' ?>>
                                            <?= Html::e($product->name()) ?> — <?= Html::e(Money::format($product->priceMinor())) ?><?= $product->tracksStock() ? ' (' . Html::e((string) $product->stockQuantity()) . ' en stock)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="sr-only" for="qty-<?= (int) $index ?>">Quantité</label>
                                <input class="input" id="qty-<?= (int) $index ?>" name="quantity[]" type="number" min="1" max="99" value="<?= Html::e((string) ($oldQuantities[$index] ?? '1')) ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="btn btn-ghost" type="button" data-add-line><?php $name = 'plus'; include $icon; ?>Ajouter un autre produit</button>
                <?php $err('cart'); ?>
            </fieldset>

            <fieldset>
                <legend>2. Le client</legend>
                <div class="field">
                    <label for="client_id"><span>Client déjà enregistré</span></label>
                    <select class="input" id="client_id" name="client_id" data-client-select>
                        <option value="">Nouveau client</option>
                        <?php foreach ($clients as $client): ?>
                            <?php if (!$client instanceof Client) { continue; } ?>
                            <option value="<?= $client->id() ?>"<?= $val('client_id') === (string) $client->id() ? ' selected' : '' ?>><?= Html::e($client->fullName()) ?><?= $client->phone() !== null ? ' — ' . Html::e($client->phone()) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $err('client_id'); ?>
                </div>
                <div class="btq-grid-2" data-new-client>
                    <div class="field">
                        <label for="customer_name"><span>Nom du client</span></label>
                        <input class="input" id="customer_name" name="customer_name" maxlength="120" value="<?= Html::e($val('customer_name')) ?>">
                        <?php $err('customer_name'); ?>
                    </div>
                    <div class="field">
                        <label for="customer_phone"><span>Téléphone (facultatif)</span></label>
                        <input class="input" id="customer_phone" name="customer_phone" type="tel" maxlength="40" value="<?= Html::e($val('customer_phone')) ?>">
                        <?php $err('customer_phone'); ?>
                    </div>
                </div>
                <div class="field">
                    <label for="customer_address"><span>Adresse de livraison (facultatif)</span></label>
                    <input class="input" id="customer_address" name="customer_address" maxlength="255" value="<?= Html::e($val('customer_address')) ?>">
                </div>
                <div class="field">
                    <label for="note"><span>Note (facultatif)</span></label>
                    <textarea class="input" id="note" name="note" rows="2" maxlength="500"><?= Html::e($val('note')) ?></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>3. Où en est la vente ?</legend>
                <?php foreach ([OrderStatus::NEW => 'À préparer (le stock ne bouge pas encore)', OrderStatus::CONFIRMED => 'Confirmée (le stock baisse)', OrderStatus::DELIVERED => 'Déjà remise au client'] as $key => $label): ?>
                    <label class="btq-switch">
                        <input type="radio" name="status" value="<?= Html::e($key) ?>"<?= ($val('status') !== '' ? $val('status') : OrderStatus::NEW) === $key ? ' checked' : '' ?>>
                        <span><strong><?= Html::e($label) ?></strong></span>
                    </label>
                <?php endforeach; ?>
                <?php $err('status'); ?>
                <label class="btq-switch">
                    <input type="checkbox" name="is_paid" value="1"<?= $val('is_paid') !== '' ? ' checked' : '' ?>>
                    <span><strong>Le client a déjà payé</strong></span>
                </label>
            </fieldset>

            <div class="btq-sticky-save">
                <button class="btn btn-primary" type="submit">Enregistrer la vente</button>
                <a class="btn btn-ghost" href="<?= Html::url('/boutique/commandes') ?>">Annuler</a>
            </div>
        </form>
    <?php endif; ?>
</section>
