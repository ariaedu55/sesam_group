<?php

declare(strict_types=1);

use Sesam\Core\Loyer\RentDueStatus;
use Sesam\Helpers\Html;
use Sesam\Models\LoyerRentDue;

$list = is_array($list ?? null) ? $list : ['dues' => [], 'page' => 1, 'pages' => 1, 'filterLate' => false];
$dues = is_array($list['dues'] ?? null) ? $list['dues'] : [];
$filterLate = (bool) ($list['filterLate'] ?? false);
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Échéances</h1>
        <div class="row">
            <?php if ($filterLate): ?>
                <a class="btn btn-secondary" href="<?= Html::url('/loyer/echeances') ?>">Toutes</a>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?= Html::url('/loyer/echeances?retard=1') ?>">Retards uniquement</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($dues === []): ?>
        <div class="empty"><p>Aucune échéance<?= $filterLate ? ' en retard' : '' ?>.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Locataire</th>
                    <th scope="col">Bien</th>
                    <th scope="col">Montant</th>
                    <th scope="col">Statut</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($dues as $row): ?>
                    <?php if (!$row instanceof LoyerRentDue) { continue; } ?>
                    <tr>
                        <td><a href="<?= Html::url('/loyer/echeances/' . $row->id()) ?>"><?= Html::e($row->dueDate()) ?></a></td>
                        <td><?= Html::e($row->clientName()) ?></td>
                        <td><?= Html::e($row->propertyName()) ?> — <?= Html::e($row->unitLabel()) ?></td>
                        <td><?= Html::e(number_format($row->amountMinor(), 0, ',', ' ')) ?> FCFA</td>
                        <td><?= $row->isLate() ? 'En retard' : Html::e(RentDueStatus::label($row->status())) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
