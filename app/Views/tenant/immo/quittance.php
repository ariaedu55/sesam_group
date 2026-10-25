<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$payment = is_array($payment ?? null) ? $payment : [];
$settings = is_array($settings ?? null) ? $settings : [];
$methods = is_array($methods ?? null) ? $methods : [];
if ($payment === []) {
    return;
}
$full = $payment['due_status'] === 'paid';
$month = ImmoCatalog::monthLabel((string) $payment['due_date']);
$remaining = max(0, (int) $payment['due_amount'] - (int) $payment['paid_minor']);
$phone = (string) ($settings['whatsapp_phone'] ?? '');

?>
<div class="doc-actions">
    <button type="button" class="doc-btn" data-print>Imprimer / enregistrer en PDF</button>
    <a class="doc-btn is-ghost" href="<?= Html::url('/immo/loyers/' . (int) $payment['due_id']) ?>">Retour</a>
</div>
<article class="doc-page">
    <header class="doc-head">
        <div>
            <p class="doc-brand"><?= Html::e((string) $settings['display_name']) ?></p>
            <?php if (($settings['address'] ?? '') !== ''): ?><p><?= Html::e((string) $settings['address']) ?></p><?php endif; ?>
            <p><?= $phone !== '' ? Html::e(WhatsApp::display($phone)) : '' ?><?= ($settings['email'] ?? '') !== '' ? ' · ' . Html::e((string) $settings['email']) : '' ?></p>
        </div>
        <div class="doc-ref">
            <p class="doc-title"><?= $full ? 'Quittance de loyer' : 'Reçu de paiement partiel' ?></p>
            <p>N° <?= Html::e((string) $payment['receipt_number']) ?></p>
            <p>Le <?= Html::e(ImmoCatalog::date((string) $payment['paid_on'])) ?></p>
        </div>
    </header>

    <section class="doc-block">
        <p><strong>Locataire :</strong> <?= Html::e((string) $payment['full_name']) ?><?= $payment['phone'] ? ' — ' . Html::e((string) $payment['phone']) : '' ?></p>
        <p><strong>Logement :</strong> <?= Html::e($payment['property_name'] . ' — ' . $payment['unit_label']) ?><?= $payment['address'] ? ', ' . Html::e((string) $payment['address']) : '' ?></p>
        <p><strong>Contrat de bail :</strong> <?= Html::e((string) ($payment['lease_reference'] ?? '')) ?> · <strong>Période :</strong> <?= Html::e(ucfirst($month)) ?></p>
    </section>

    <div class="table-wrap">
    <table class="doc-table">
        <tbody>
            <tr><td>Loyer</td><td><?= Html::e(Money::format((int) $payment['rent_part_minor'])) ?></td></tr>
            <?php if ((int) $payment['charges_part_minor'] > 0): ?><tr><td>Charges</td><td><?= Html::e(Money::format((int) $payment['charges_part_minor'])) ?></td></tr><?php endif; ?>
            <tr class="is-total"><td>Total du mois</td><td><?= Html::e(Money::format((int) $payment['due_amount'])) ?></td></tr>
            <tr><td>Montant reçu ce jour (<?= Html::e($methods[$payment['method']] ?? (string) $payment['method']) ?><?= $payment['reference'] ? ', réf. ' . Html::e((string) $payment['reference']) : '' ?>)</td><td><strong><?= Html::e(Money::format((int) $payment['amount_minor'])) ?></strong></td></tr>
            <tr><td>Total payé pour ce mois</td><td><?= Html::e(Money::format((int) $payment['paid_minor'])) ?></td></tr>
            <tr class="<?= $remaining > 0 ? 'is-due' : 'is-ok' ?>"><td>Reste à payer</td><td><?= Html::e(Money::format($remaining)) ?></td></tr>
        </tbody>
    </table>
    </div>

    <p class="doc-statement">
        <?php if ($full): ?>
            Je soussigné(e) <?= Html::e((string) ($settings['signature_name'] ?: $settings['display_name'])) ?> déclare avoir reçu de <?= Html::e((string) $payment['full_name']) ?> la somme de <?= Html::e(Money::format((int) $payment['due_amount'])) ?> au titre du loyer et des charges de <?= Html::e($month) ?>, et lui en donne quittance.
        <?php else: ?>
            Reçu de <?= Html::e((string) $payment['full_name']) ?> la somme de <?= Html::e(Money::format((int) $payment['amount_minor'])) ?> en paiement partiel du loyer de <?= Html::e($month) ?>. Il reste <?= Html::e(Money::format($remaining)) ?> à régler ; la quittance sera remise au paiement complet.
        <?php endif; ?>
    </p>

    <footer class="doc-foot">
        <div class="doc-sign"><p>Signature</p><p class="doc-sign-name"><?= Html::e((string) ($settings['signature_name'] ?: $settings['display_name'])) ?></p></div>
        <?php if (($settings['receipt_footer'] ?? '') !== ''): ?><p class="doc-note"><?= Html::e((string) $settings['receipt_footer']) ?></p><?php endif; ?>
        <p class="doc-note">Document édité avec Sesam Immo.</p>
    </footer>
</article>
