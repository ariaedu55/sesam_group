<?php

declare(strict_types=1);

use Sesam\Core\Payment\PaymentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\Payment;
use Sesam\Models\PaymentTransaction;

$payment = ($payment ?? null) instanceof Payment ? $payment : null;
$transactions = is_array($transactions ?? null) ? $transactions : [];
$canSimulate = (bool) ($canSimulate ?? false);

if ($payment === null) {
    return;
}

$state = match ($payment->status()) {
    PaymentStatus::SUCCESS => ['success', 'Paiement reçu', '✓'],
    PaymentStatus::FAILED => ['danger', 'Paiement échoué', '✕'],
    PaymentStatus::CANCELLED => ['muted', 'Paiement annulé', '✕'],
    default => ['warning', 'En attente de paiement', '…'],
};
$kinds = [
    'checkout_created' => 'Lien de paiement créé',
    'sandbox_confirm' => 'Paiement confirmé (simulation NabooPay)',
    'sandbox_cancel' => 'Paiement annulé (simulation NabooPay)',
    'webhook' => 'Notification NabooPay reçue',
];
$statuses = [
    PaymentStatus::SUCCESS => 'réussi',
    PaymentStatus::PENDING => 'en attente',
    PaymentStatus::FAILED => 'échoué',
    PaymentStatus::CANCELLED => 'annulé',
];
$date = static fn (?string $value): string => $value === null || $value === '' ? '—' : gmdate('d/m/Y à H:i', (int) strtotime($value . ' UTC'));
$isSandbox = $payment->mode() === 'sandbox';

?>
<section class="btq pay-detail">
    <p><a class="muted" href="<?= Html::url('/paiements') ?>">← Tous les paiements</a></p>

    <article class="btq-card pay-receipt is-<?= Html::e($state[0]) ?>">
        <span class="pay-receipt-icon" aria-hidden="true"><?= Html::e($state[2]) ?></span>
        <p class="pay-receipt-state"><?= Html::e($state[1]) ?></p>
        <p class="pay-receipt-amount"><?= Html::e(number_format($payment->amountMinor(), 0, ',', ' ')) ?> FCFA</p>
        <p class="pay-receipt-label"><?= Html::e($payment->description()) ?></p>
        <?php if ($payment->paidAt() !== null): ?>
            <p class="muted">Payé le <?= Html::e($date($payment->paidAt())) ?></p>
        <?php endif; ?>
        <?php if ($payment->status() === PaymentStatus::PENDING && $isSandbox && $canSimulate): ?>
            <div class="pay-receipt-actions">
                <a class="btn btn-primary" href="<?= Html::url('/paiements/' . $payment->id() . '/bac-a-sable') ?>">Simuler le paiement du client</a>
            </div>
            <p class="btq-help">Démonstration : ouvre l'écran de paiement Wave / Orange Money tel que le client le verrait. Aucun argent ne circule.</p>
        <?php endif; ?>
    </article>

    <article class="btq-card">
        <h2>Détails</h2>
        <dl class="btq-dl">
            <dt>Client</dt><dd><?= $payment->clientName() !== '' ? Html::e($payment->clientName()) : '—' ?></dd>
            <dt>Créé le</dt><dd><?= Html::e($date($payment->createdAt())) ?></dd>
            <dt>Passerelle</dt><dd>NabooPay<?= $isSandbox ? ' · simulation' : '' ?></dd>
            <?php if ($payment->externalId() !== null): ?><dt>Transaction</dt><dd><?= Html::e($payment->externalId()) ?></dd><?php endif; ?>
            <dt>Référence</dt><dd class="pay-ref"><?= Html::e($payment->reference()) ?></dd>
        </dl>
    </article>

    <article class="btq-card">
        <h2>Historique</h2>
        <?php if ($transactions === []): ?>
            <p class="muted">Aucun événement.</p>
        <?php else: ?>
            <ol class="pay-timeline">
                <?php foreach ($transactions as $tx): ?>
                    <?php if (!$tx instanceof PaymentTransaction) { continue; } ?>
                    <li class="is-<?= Html::e($tx->status()) ?>">
                        <strong><?= Html::e($kinds[$tx->kind()] ?? $tx->kind()) ?></strong>
                        <span class="muted"><?= Html::e($date($tx->createdAt())) ?> · <?= Html::e($statuses[$tx->status()] ?? $tx->status()) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </article>
</section>
