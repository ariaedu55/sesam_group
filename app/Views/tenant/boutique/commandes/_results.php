<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueOrder;

$page = is_array($page ?? null) ? $page : [];
$orders = is_array($page['orders'] ?? null) ? $page['orders'] : [];
$current = (int) ($page['page'] ?? 1);
$pages = (int) ($page['pages'] ?? 1);
$total = (int) ($page['total'] ?? count($orders));
$status = is_string($status ?? null) ? $status : '';
$query = is_string($query ?? null) ? $query : '';
$link = static function (string $statusKey, int $pageNumber = 1) use ($query): string {
    $params = array_filter(['statut' => $statusKey, 'q' => $query, 'page' => $pageNumber > 1 ? (string) $pageNumber : '']);

    return '/boutique/commandes' . ($params !== [] ? '?' . http_build_query($params) : '');
};

?>
    <?php if ($orders === []): ?>
        <div class="empty btq-card"><p><?= $query !== '' || $status !== '' ? 'Aucune commande ne correspond.' : 'Pas encore de commande. Partagez le lien de votre boutique pour recevoir les premières.' ?></p></div>
    <?php else: ?>
        <p class="muted"><?= Html::e((string) $total) ?> commande(s)<?= $pages > 1 ? ', page ' . Html::e((string) $current) . ' / ' . Html::e((string) $pages) : '' ?></p>
        <ul class="btq-list record-list-rich">
            <?php foreach ($orders as $order): ?>
                <?php if (!$order instanceof BoutiqueOrder) { continue; } ?>
                <li>
                    <a href="<?= Html::url('/boutique/commandes/' . $order->id()) ?>">
                        <span class="record-list-leading">
                            <span class="record-list-avatar" data-tone="order" aria-hidden="true"><?= Html::e(Html::initials($order->customerName())) ?></span>
                            <span>
                                <strong><?= Html::e($order->customerName()) ?></strong>
                                <small>
                                    <?= Html::e($order->orderNumber()) ?> · <?= Html::e(gmdate('d/m/Y H:i', strtotime($order->createdAt()) ?: time())) ?>
                                    · <?= Html::e((string) $order->itemCount()) ?> article(s)
                                    · <?= $order->source() === 'whatsapp' ? 'Boutique en ligne' : 'Saisie manuelle' ?>
                                </small>
                            </span>
                        </span>
                        <span class="record-list-meta btq-list-end">
                            <strong><?= Html::e(Money::format($order->totalMinor())) ?></strong>
                            <span class="btq-status btq-status-<?= Html::e(OrderStatus::tone($order->status())) ?>"><?= Html::e(OrderStatus::label($order->status())) ?></span>
                            <?php if ($order->isPaid()): ?><span class="btq-status btq-status-success">Payée</span><?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($pages > 1): ?>
            <nav class="btq-actions" data-live-filter-pages aria-label="Pages">
                <?php if ($current > 1): ?><a class="btn btn-secondary" href="<?= Html::url($link($status, $current - 1)) ?>">Précédent</a><?php endif; ?>
                <span class="muted">Page <?= Html::e((string) $current) ?> sur <?= Html::e((string) $pages) ?></span>
                <?php if ($current < $pages): ?><a class="btn btn-secondary" href="<?= Html::url($link($status, $current + 1)) ?>">Suivant</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
