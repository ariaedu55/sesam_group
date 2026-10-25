<?php

declare(strict_types=1);

use Sesam\Core\Loyer\LeaseStatus;
use Sesam\Helpers\Html;
use Sesam\Models\LoyerLease;

$list = is_array($list ?? null) ? $list : ['leases' => [], 'page' => 1, 'pages' => 1];
$leases = is_array($list['leases'] ?? null) ? $list['leases'] : [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$canManage = (bool) ($canManage ?? false);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Contrats</h1>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/loyer/contrats/nouveau') ?>" data-modal="Loyer">Nouveau contrat</a>
        <?php endif; ?>
    </div>
    <?php if ($leases === []): ?>
        <div class="empty"><p>Aucun contrat.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">Locataire</th>
                    <th scope="col">Bien / unité</th>
                    <th scope="col">Loyer</th>
                    <th scope="col">Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($leases as $row): ?>
                    <?php if (!$row instanceof LoyerLease) { continue; } ?>
                    <tr>
                        <td><a href="<?= Html::url('/loyer/contrats/' . $row->id()) ?>"><?= Html::e($row->clientName()) ?></a></td>
                        <td><?= Html::e($row->propertyName()) ?> — <?= Html::e($row->unitLabel()) ?></td>
                        <td><?= Html::e(number_format($row->rentMinor(), 0, ',', ' ')) ?> FCFA</td>
                        <td><?= Html::e(LeaseStatus::label($row->status())) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
