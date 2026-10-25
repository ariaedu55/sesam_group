<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\InscriptionFeeType;

$feeTypes = is_array($feeTypes ?? null) ? $feeTypes : [];
$canManage = (bool) ($canManage ?? false);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Types de frais</h1>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/inscription/frais/nouveau') ?>" data-modal="Inscription">Nouveau type</a>
        <?php endif; ?>
    </div>
    <?php if ($feeTypes === []): ?>
        <div class="empty"><p>Aucun type de frais.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Total</th>
                    <th scope="col">Tranches</th>
                    <th scope="col">Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($feeTypes as $item): ?>
                    <?php if (!$item instanceof InscriptionFeeType) { continue; } ?>
                    <tr>
                        <td><?= Html::e($item->name()) ?></td>
                        <td><?= Html::e(number_format($item->totalMinor(), 0, ',', ' ')) ?> FCFA</td>
                        <td><?= Html::e((string) $item->installmentCount()) ?></td>
                        <td><?= $item->isActive() ? 'Actif' : 'Inactif' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
