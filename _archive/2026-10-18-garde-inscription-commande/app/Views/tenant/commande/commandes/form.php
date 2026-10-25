<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;
use Sesam\Models\CommandeProduct;

$catalog = is_array($catalog ?? null) ? $catalog : ['products' => [], 'clients' => []];
$products = is_array($catalog['products'] ?? null) ? $catalog['products'] : [];
$clients = is_array($catalog['clients'] ?? null) ? $catalog['clients'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

?>
<section>
    <h1>Nouvelle commande</h1>
    <form class="stack" method="post" action="<?= Html::url('/commande/commandes') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="client_id"><span>Client</span></label>
            <select class="input" id="client_id" name="client_id" required>
                <option value="">— Choisir —</option>
                <?php foreach ($clients as $client): ?>
                    <?php if (!$client instanceof Client) { continue; } ?>
                    <option value="<?= Html::e((string) $client->id()) ?>"<?= (string) ($old['client_id'] ?? '') === (string) $client->id() ? ' selected' : '' ?>>
                        <?= Html::e($client->fullName()) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['client_id'])): ?><p class="field-error"><?= Html::e($errors['client_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="product_id"><span>Produit</span></label>
            <select class="input" id="product_id" name="product_id" required>
                <option value="">— Choisir —</option>
                <?php foreach ($products as $product): ?>
                    <?php if (!$product instanceof CommandeProduct) { continue; } ?>
                    <option value="<?= Html::e((string) $product->id()) ?>"<?= (string) ($old['product_id'] ?? '') === (string) $product->id() ? ' selected' : '' ?>>
                        <?= Html::e($product->name()) ?> — <?= Html::e(number_format($product->priceMinor(), 0, ',', ' ')) ?> FCFA
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['product_id'])): ?><p class="field-error"><?= Html::e($errors['product_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="quantity"><span>Quantité</span></label>
            <input class="input" id="quantity" name="quantity" type="number" min="1" max="99" value="<?= Html::e((string) ($old['quantity'] ?? '1')) ?>" required>
            <?php if (isset($errors['quantity'])): ?><p class="field-error"><?= Html::e($errors['quantity']) ?></p><?php endif; ?>
            <?php if (isset($errors['lines'])): ?><p class="field-error"><?= Html::e($errors['lines']) ?></p><?php endif; ?>
        </div>
        <p class="row">
            <button class="btn btn-primary" type="submit">Créer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/commande/commandes') ?>">Annuler</a>
        </p>
    </form>
</section>
