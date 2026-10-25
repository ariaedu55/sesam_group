<?php

declare(strict_types=1);

use Sesam\Core\Commande\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\CommandeOrder;

$list = is_array($list ?? null) ? $list : ['orders' => [], 'page' => 1, 'pages' => 1];
$orders = is_array($list['orders'] ?? null) ? $list['orders'] : [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$canManage = (bool) ($canManage ?? false);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Commandes</h1>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/commande/commandes/nouveau') ?>" data-modal="Commande">Nouvelle commande</a>
        <?php endif; ?>
    </div>
    <?php if ($orders === []): ?>
        <div class="empty"><p>Aucune commande.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">N°</th>
                    <th scope="col">Client</th>
                    <th scope="col">Montant</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Créée</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $row): ?>
                    <?php if (!$row instanceof CommandeOrder) { continue; } ?>
                    <tr>
                        <td><a href="<?= Html::url('/commande/commandes/' . $row->id()) ?>"><?= Html::e($row->orderNumber()) ?></a></td>
                        <td><?= Html::e($row->clientName()) ?></td>
                        <td><?= Html::e(number_format($row->totalMinor(), 0, ',', ' ')) ?> FCFA</td>
                        <td><?= Html::e(OrderStatus::label($row->status())) ?></td>
                        <td><?= Html::e($row->createdAt()) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= Html::url('/commande/commandes?page=' . ($page - 1)) ?>">Précédent</a>
                <?php endif; ?>
                <span class="muted">Page <?= Html::e((string) $page) ?> / <?= Html::e((string) $pages) ?></span>
                <?php if ($page < $pages): ?>
                    <a href="<?= Html::url('/commande/commandes?page=' . ($page + 1)) ?>">Suivant</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
