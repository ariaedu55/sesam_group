<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueOrder;
use Sesam\Models\BoutiqueOrderItem;
use Sesam\Models\Client;

$detail = is_array($detail ?? null) ? $detail : [];
$order = ($detail['order'] ?? null) instanceof BoutiqueOrder ? $detail['order'] : null;
$items = is_array($detail['items'] ?? null) ? $detail['items'] : [];
$client = ($detail['client'] ?? null) instanceof Client ? $detail['client'] : null;
$whatsapp = is_string($detail['whatsapp'] ?? null) ? $detail['whatsapp'] : '';
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
if ($order === null) {
    return;
}
$status = $order->status();
$flow = [OrderStatus::NEW, OrderStatus::CONFIRMED, OrderStatus::DELIVERED];
$reached = array_search($status, $flow, true);
$date = static fn (?string $value): string => $value !== null && $value !== '' ? gmdate('d/m/Y H:i', strtotime($value) ?: time()) : '';
$actions = [];
if ($status === OrderStatus::NEW) {
    $actions[] = [OrderStatus::CONFIRMED, 'Confirmer la commande', 'btn-primary', 'check'];
    $actions[] = [OrderStatus::DELIVERED, 'Marquer comme livrée', 'btn-secondary', 'truck'];
    $actions[] = [OrderStatus::CANCELLED, 'Annuler', 'btn-ghost', 'close'];
} elseif ($status === OrderStatus::CONFIRMED) {
    $actions[] = [OrderStatus::DELIVERED, 'Marquer comme livrée', 'btn-primary', 'truck'];
    $actions[] = [OrderStatus::CANCELLED, 'Annuler (remet le stock)', 'btn-ghost', 'close'];
} elseif ($status === OrderStatus::CANCELLED) {
    $actions[] = [OrderStatus::NEW, 'Réactiver la commande', 'btn-secondary', 'bag'];
}

$heroBackHref = '/boutique/commandes';
$heroBackLabel = 'Commandes';
$heroTitle = 'Commande ' . $order->orderNumber();
$heroSubtitle = 'Reçue le ' . $date($order->createdAt()) . ' · ' . ($order->source() === 'whatsapp' ? 'boutique en ligne' : 'saisie manuelle');
$heroTone = 'electric';
$heroInitials = null;
$heroStatusText = OrderStatus::label($status);
$heroStatusTone = OrderStatus::tone($status);
$heroBadgesHtml = $order->isPaid() ? '<span class="btq-status btq-status-success">Payée</span>' : '';
$heroStats = [
    ['label' => 'Total', 'value' => Money::format($order->totalMinor())],
    ['label' => 'Articles', 'value' => (string) $order->itemCount()],
    ['label' => 'Client', 'value' => $order->customerName()],
    ['label' => 'Paiement', 'value' => $order->isPaid() ? 'Reçu' : 'En attente', 'alert' => !$order->isPaid()],
];
$heroActions = '';

?>
<section class="record-detail btq">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($errors as $message): ?><p><?= Html::e((string) $message) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($status === OrderStatus::CANCELLED): ?>
        <p class="alert" role="status">Cette commande est annulée.</p>
    <?php else: ?>
        <div class="btq-steps-bar" aria-label="Étape de la commande">
            <?php foreach ($flow as $index => $step): ?>
                <span class="<?= $reached !== false && $index <= $reached ? 'is-on' : '' ?>"><?= Html::e(OrderStatus::label($step)) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="record-body is-two btq-two">
        <div class="btq">
            <section class="btq-card record-panel">
                <?php $panelTitle = 'Produits'; $panelIcon = 'tag'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr><th scope="col">Produit</th><th scope="col">Qté</th><th scope="col">Prix</th><th scope="col">Total</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php if (!$item instanceof BoutiqueOrderItem) { continue; } ?>
                            <tr>
                                <td><?= Html::e($item->productName()) ?></td>
                                <td><?= Html::e((string) $item->quantity()) ?></td>
                                <td><?= Html::e(Money::format($item->unitPriceMinor())) ?></td>
                                <td><?= Html::e(Money::format($item->lineTotalMinor())) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr><th scope="row" colspan="3">Total</th><td><strong><?= Html::e(Money::format($order->totalMinor())) ?></strong></td></tr>
                        </tfoot>
                    </table>
                </div>
                <p class="muted"><?= $order->stockApplied() ? 'Le stock a été retiré pour cette commande.' : 'Le stock sera retiré quand vous confirmerez la commande.' ?></p>
            </section>

            <?php if ($canManage && $actions !== []): ?>
                <section class="btq-card record-panel">
                    <?php $panelTitle = 'Actions'; $panelIcon = 'spark'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                    <div class="btq-big-actions">
                        <?php foreach ($actions as [$next, $label, $class, $iconName]): ?>
                            <form method="post" action="<?= Html::url('/boutique/commandes/' . $order->id() . '/statut') ?>">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <input type="hidden" name="status" value="<?= Html::e($next) ?>">
                                <button class="btn <?= Html::e($class) ?>" type="submit"><?php $name = $iconName; include $icon; ?><?= Html::e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="btq">
            <section class="btq-card record-panel">
                <?php $panelTitle = 'Client'; $panelIcon = 'person'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <dl class="record-facts is-grid">
                    <div><dt>Nom</dt><dd><?= Html::e($order->customerName()) ?></dd></div>
                    <?php if ($order->customerPhone() !== ''): ?><div><dt>Téléphone</dt><dd><a href="tel:<?= Html::e(preg_replace('/[^\d+]/', '', $order->customerPhone()) ?? '') ?>"><?= Html::e($order->customerPhone()) ?></a></dd></div><?php endif; ?>
                    <?php if ($order->customerAddress() !== ''): ?><div><dt>Adresse</dt><dd><?= Html::e($order->customerAddress()) ?></dd></div><?php endif; ?>
                    <?php if ($order->note() !== ''): ?><div><dt>Message</dt><dd><?= nl2br(Html::e($order->note())) ?></dd></div><?php endif; ?>
                </dl>
                <div class="btq-big-actions">
                    <?php if ($whatsapp !== ''): ?>
                        <a class="btn btq-whatsapp" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener"><?php $name = 'chat'; include $icon; ?>Écrire au client sur WhatsApp</a>
                    <?php endif; ?>
                    <?php if ($client !== null): ?>
                        <a class="btn btn-secondary" href="<?= Html::url('/clients/' . $client->id()) ?>"><?php $name = 'person'; include $icon; ?>Fiche client</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="btq-card record-panel">
                <?php $panelTitle = 'Paiement'; $panelIcon = 'coin'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <p><span class="btq-status btq-status-<?= $order->isPaid() ? 'success' : 'warning' ?>"><?= $order->isPaid() ? 'Payée' : 'Pas encore payée' ?></span></p>
                <?php if ($canManage): ?>
                    <form method="post" action="<?= Html::url('/boutique/commandes/' . $order->id() . '/paiement') ?>">
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <button class="btn btn-secondary" type="submit"><?= $order->isPaid() ? 'Marquer comme non payée' : 'Marquer comme payée' ?></button>
                    </form>
                <?php endif; ?>
                <dl class="btq-dl">
                    <?php if ($order->confirmedAt() !== null): ?><dt>Confirmée</dt><dd><?= Html::e($date($order->confirmedAt())) ?></dd><?php endif; ?>
                    <?php if ($order->deliveredAt() !== null): ?><dt>Livrée</dt><dd><?= Html::e($date($order->deliveredAt())) ?></dd><?php endif; ?>
                </dl>
            </section>
        </div>
    </div>
</section>
