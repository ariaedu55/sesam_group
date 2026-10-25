<?php

declare(strict_types=1);

use Sesam\Core\Payment\PaymentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\Payment;

$list = is_array($list ?? null) ? $list : ['payments' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$payments = $list['payments'] ?? [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$total = (int) ($list['total'] ?? 0);
$canCreate = (bool) ($canCreate ?? false);

$statusLabel = static function (string $status): string {
    return match ($status) {
        PaymentStatus::SUCCESS => 'Réussi',
        PaymentStatus::FAILED => 'Échoué',
        PaymentStatus::CANCELLED => 'Annulé',
        default => 'En attente',
    };
};

?>
<section>
    <div class="row staff-toolbar">
        <h1>Paiements</h1>
        <?php if ($canCreate): ?>
            <a class="btn btn-primary" href="<?= Html::url('/paiements/nouveau') ?>" data-modal="Paiements">Initier un paiement</a>
        <?php endif; ?>
    </div>
    <p class="muted">Passerelle configurée : <strong>NabooPay</strong> (bac à sable tant que les clés API ne sont pas renseignées).</p>
    <?php if ($payments === []): ?>
        <div class="empty">
            <p>Aucun paiement enregistré.</p>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= Html::url('/paiements/nouveau') ?>" data-modal="Paiements">Premier paiement test</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Libellé</th>
                        <th>Montant</th>
                        <th>Client</th>
                        <th>Statut</th>
                        <th>Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <?php if (!$payment instanceof Payment) { continue; } ?>
                        <tr>
                            <td><a href="<?= Html::url('/paiements/' . $payment->id()) ?>"><?= Html::e($payment->description()) ?></a></td>
                            <td><?= Html::e(number_format($payment->amountMinor(), 0, ',', ' ')) ?> FCFA</td>
                            <td><?= $payment->clientName() !== '' ? Html::e($payment->clientName()) : '—' ?></td>
                            <td><?= Html::e($statusLabel($payment->status())) ?></td>
                            <td><?= Html::e($payment->mode() === 'sandbox' ? 'Bac à sable' : 'Live') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted"><?= Html::e((string) $total) ?> paiement(s), page <?= Html::e((string) $page) ?> / <?= Html::e((string) $pages) ?></p>
    <?php endif; ?>
</section>
