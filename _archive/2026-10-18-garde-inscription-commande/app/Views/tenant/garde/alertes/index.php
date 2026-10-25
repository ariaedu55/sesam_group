<?php

declare(strict_types=1);

use Sesam\Core\Garde\ExpiryStatus;
use Sesam\Helpers\Html;
use Sesam\Models\GardeBatch;

$batches = is_array($batches ?? null) ? $batches : [];
$filter = is_string($filter ?? null) ? $filter : 'all';
$canManage = (bool) ($canManage ?? false);
$tabs = [
    'all' => 'Tous',
    ExpiryStatus::EXPIRED => 'Expiré',
    ExpiryStatus::SOON => 'Bientôt',
    ExpiryStatus::OK => 'OK',
];

?>
<section class="garde-page">
    <header class="garde-hero">
        <div>
            <p class="eyebrow">Vigilance</p>
            <h1>Alertes de péremption</h1>
            <p>Expiré, bientôt (30 jours) ou OK. Seuls les lots encore en stock sont listés.</p>
        </div>
        <?php if ($canManage): ?>
            <div class="garde-hero-actions">
                <a class="btn btn-primary" href="<?= Html::url('/garde/mouvements/nouveau') ?>" data-modal="Garde">Enregistrer une sortie</a>
            </div>
        <?php endif; ?>
    </header>
    <p class="garde-tabs" role="tablist">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="garde-tab<?= $filter === $key ? ' is-current' : '' ?>" href="<?= Html::url('/garde/alertes?etat=' . rawurlencode($key)) ?>"><?= Html::e($label) ?></a>
        <?php endforeach; ?>
    </p>
    <?php if ($batches === []): ?>
        <div class="empty"><p>Aucun lot dans cet état.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th scope="col">Produit</th>
                    <th scope="col">Lot</th>
                    <th scope="col">Quantité</th>
                    <th scope="col">Péremption</th>
                    <th scope="col">État</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($batches as $batch): ?>
                    <?php if (!$batch instanceof GardeBatch) { continue; } ?>
                    <?php $status = $batch->expiryStatus(); ?>
                    <tr>
                        <td><a href="<?= Html::url('/garde/produits/' . $batch->productId()) ?>"><?= Html::e($batch->productName()) ?></a></td>
                        <td><?= Html::e($batch->lotCode()) ?></td>
                        <td><?= Html::e((string) $batch->quantity()) ?></td>
                        <td><?= Html::e(gmdate('d/m/Y', strtotime($batch->expiresOn()) ?: time())) ?></td>
                        <td><span class="garde-chip garde-chip-<?= Html::e($status) ?>"><?= Html::e(ExpiryStatus::label($status)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
