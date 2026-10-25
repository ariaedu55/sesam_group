<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueOrder;

$page = is_array($page ?? null) ? $page : [];
$orders = is_array($page['orders'] ?? null) ? $page['orders'] : [];
$counts = is_array($page['counts'] ?? null) ? $page['counts'] : [];
$current = (int) ($page['page'] ?? 1);
$pages = (int) ($page['pages'] ?? 1);
$status = is_string($status ?? null) ? $status : '';
$query = is_string($query ?? null) ? $query : '';
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$all = array_sum(array_map('intval', $counts));
$link = static function (string $statusKey, int $pageNumber = 1) use ($query): string {
    $params = array_filter(['statut' => $statusKey, 'q' => $query, 'page' => $pageNumber > 1 ? (string) $pageNumber : '']);

    return '/boutique/commandes' . ($params !== [] ? '?' . http_build_query($params) : '');
};

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Commandes</h1>
            <p class="muted">Les commandes de votre boutique en ligne arrivent ici et sur votre WhatsApp.</p>
        </div>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/boutique/commandes/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Noter une vente</a>
        <?php endif; ?>
    </header>

    <nav class="btq-tabs" aria-label="Filtrer par étape">
        <a href="<?= Html::url($link('')) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>Toutes<span><?= Html::e((string) $all) ?></span></a>
        <?php foreach (OrderStatus::ALL as $key): ?>
            <a href="<?= Html::url($link($key)) ?>"<?= $status === $key ? ' aria-current="page"' : '' ?>><?= Html::e(OrderStatus::label($key)) ?><span><?= Html::e((string) ($counts[$key] ?? 0)) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <form class="btq-filters" method="get" action="<?= Html::url('/boutique/commandes') ?>" role="search">
        <?php if ($status !== ''): ?><input type="hidden" name="statut" value="<?= Html::e($status) ?>"><?php endif; ?>
        <label class="sr-only" for="q">Rechercher</label>
        <input class="input" id="q" name="q" type="search" placeholder="Nom, téléphone ou numéro de commande" value="<?= Html::e($query) ?>">
        <button class="btn btn-secondary" type="submit">Chercher</button>
    </form>

    <?php if ($orders === []): ?>
        <div class="empty btq-card"><p><?= $query !== '' || $status !== '' ? 'Aucune commande ne correspond.' : 'Pas encore de commande. Partagez le lien de votre boutique pour recevoir les premières.' ?></p></div>
    <?php else: ?>
        <ul class="btq-list">
            <?php foreach ($orders as $order): ?>
                <?php if (!$order instanceof BoutiqueOrder) { continue; } ?>
                <li>
                    <a href="<?= Html::url('/boutique/commandes/' . $order->id()) ?>">
                        <span>
                            <strong><?= Html::e($order->customerName()) ?></strong>
                            <small>
                                <?= Html::e($order->orderNumber()) ?> · <?= Html::e(gmdate('d/m/Y H:i', strtotime($order->createdAt()) ?: time())) ?>
                                · <?= Html::e((string) $order->itemCount()) ?> article(s)
                                · <?= $order->source() === 'whatsapp' ? 'Boutique en ligne' : 'Saisie manuelle' ?>
                            </small>
                        </span>
                        <span class="btq-list-end">
                            <strong><?= Html::e(Money::format($order->totalMinor())) ?></strong>
                            <span class="btq-status btq-status-<?= Html::e(OrderStatus::tone($order->status())) ?>"><?= Html::e(OrderStatus::label($order->status())) ?></span>
                            <?php if ($order->isPaid()): ?><span class="btq-status btq-status-success">Payée</span><?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($pages > 1): ?>
            <nav class="btq-actions" aria-label="Pages">
                <?php if ($current > 1): ?><a class="btn btn-secondary" href="<?= Html::url($link($status, $current - 1)) ?>">Précédent</a><?php endif; ?>
                <span class="muted">Page <?= Html::e((string) $current) ?> sur <?= Html::e((string) $pages) ?></span>
                <?php if ($current < $pages): ?><a class="btn btn-secondary" href="<?= Html::url($link($status, $current + 1)) ?>">Suivant</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
