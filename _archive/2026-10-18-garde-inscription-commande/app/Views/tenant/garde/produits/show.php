<?php

declare(strict_types=1);

use Sesam\Core\Garde\ExpiryStatus;
use Sesam\Helpers\Html;
use Sesam\Models\GardeBatch;
use Sesam\Models\GardeProduct;

$detail = is_array($detail ?? null) ? $detail : [];
$product = $detail['product'] ?? null;
$batches = is_array($detail['batches'] ?? null) ? $detail['batches'] : [];
$canManage = (bool) ($canManage ?? false);
$errors = is_array($errors ?? null) ? $errors : [];
if (!$product instanceof GardeProduct) {
    return;
}

?>
<section class="garde-page">
    <div class="garde-product-head">
        <div class="garde-product-photo garde-product-photo-lg">
            <?php if ($product->hasImage()): ?>
                <img src="<?= Html::url('/garde/produits/' . $product->id() . '/image') ?>" alt="<?= Html::e($product->name()) ?>" width="960" height="960">
            <?php else: ?>
                <span class="garde-product-fallback" aria-hidden="true"><?= Html::e(Html::initials($product->name())) ?></span>
            <?php endif; ?>
        </div>
        <header class="garde-hero">
            <div>
                <p class="eyebrow"><a href="<?= Html::url('/garde/produits') ?>">Produits</a></p>
                <h1><?= Html::e($product->name()) ?></h1>
                <p><?= Html::e((string) $product->stockQuantity()) ?> <?= Html::e($product->unit()) ?> en stock.</p>
            </div>
            <?php if ($canManage): ?>
                <div class="garde-hero-actions">
                    <a class="btn btn-secondary" href="<?= Html::url('/garde/mouvements/nouveau') ?>" data-modal="Garde">Mouvement</a>
                    <a class="btn btn-primary" href="<?= Html::url('/garde/lots/nouveau?produit=' . $product->id()) ?>" data-modal="Garde">Nouveau lot</a>
                </div>
            <?php endif; ?>
        </header>
    </div>
    <?php if ($canManage): ?>
        <form class="card garde-photo-form" data-modal-form="<?= $product->hasImage() ? 'Changer la photo' : 'Ajouter une photo' ?>" data-modal-title="Photo du produit" data-modal-kicker="Garde" method="post" action="<?= Html::url('/garde/produits/' . $product->id() . '/image') ?>" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <div>
                <p class="eyebrow">Photo</p>
                <p class="muted">JPEG, PNG, WebP ou GIF. L’image est convertie en WebP, recadrée au plus 960 px et compressée.</p>
            </div>
            <label class="garde-file garde-file-compact" for="image">
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required data-image-preview>
                <span class="garde-file-ui">
                    <span class="garde-file-preview" data-image-preview-target hidden></span>
                    <span class="garde-file-copy">
                        <strong><?= $product->hasImage() ? 'Remplacer la photo' : 'Ajouter une photo' ?></strong>
                    </span>
                </span>
            </label>
            <button class="btn btn-primary" type="submit">Enregistrer la photo</button>
            <?php if (isset($errors['image'])): ?><p class="field-error"><?= Html::e($errors['image']) ?></p><?php endif; ?>
        </form>
    <?php endif; ?>
    <?php if ($batches === []): ?>
        <div class="empty"><p>Aucun lot. Ajoutez un numéro de lot et une date de péremption.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th scope="col">Lot</th>
                    <th scope="col">Quantité</th>
                    <th scope="col">Péremption</th>
                    <th scope="col">État</th>
                    <th scope="col"><span class="sr-only">Action</span></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($batches as $batch): ?>
                    <?php if (!$batch instanceof GardeBatch) { continue; } ?>
                    <?php $status = $batch->expiryStatus(); ?>
                    <tr>
                        <td><?= Html::e($batch->lotCode()) ?></td>
                        <td><?= Html::e((string) $batch->quantity()) ?> <?= Html::e($product->unit()) ?></td>
                        <td><?= Html::e(gmdate('d/m/Y', strtotime($batch->expiresOn()) ?: time())) ?></td>
                        <td><span class="garde-chip garde-chip-<?= Html::e($status) ?>"><?= Html::e(ExpiryStatus::label($status)) ?></span></td>
                        <td>
                            <?php if ($canManage && $batch->quantity() > 0): ?>
                                <a href="<?= Html::url('/garde/mouvements/nouveau?lot=' . $batch->id()) ?>" data-modal="Garde">Sortie</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
