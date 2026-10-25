<?php

declare(strict_types=1);

use Sesam\Core\Payment\PaymentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\Payment;

$payment = ($payment ?? null) instanceof Payment ? $payment : null;
if ($payment === null) {
    return;
}
$pending = $payment->status() === PaymentStatus::PENDING;

?>
<section class="btq pay-sandbox">
    <p class="alert" role="status"><strong>Simulation de démonstration</strong> : cet écran imite la page de paiement NabooPay que reçoit le client. Aucun appel à Wave ni à Orange Money, aucun débit.</p>

    <article class="btq-card pay-checkout">
        <p class="pay-checkout-brand">NabooPay · paiement sécurisé</p>
        <p class="pay-checkout-label"><?= Html::e($payment->description()) ?></p>
        <p class="pay-checkout-amount"><?= Html::e(number_format($payment->amountMinor(), 0, ',', ' ')) ?> FCFA</p>
        <?php if ($payment->clientName() !== ''): ?><p class="muted">Client : <?= Html::e($payment->clientName()) ?></p><?php endif; ?>

        <?php if ($pending): ?>
            <fieldset class="pay-methods">
                <legend>Moyen de paiement (simulé)</legend>
                <label class="pay-method"><input type="radio" name="demo_method" value="wave" checked form="pay-confirm"><span class="pay-method-logo is-wave" aria-hidden="true">W</span><span>Wave</span></label>
                <label class="pay-method"><input type="radio" name="demo_method" value="orange-money" form="pay-confirm"><span class="pay-method-logo is-om" aria-hidden="true">OM</span><span>Orange Money</span></label>
            </fieldset>
            <form id="pay-confirm" method="post" action="<?= Html::url('/paiements/' . $payment->id() . '/bac-a-sable/confirmer') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <button class="btn btn-primary pay-checkout-btn" type="submit">Payer <?= Html::e(number_format($payment->amountMinor(), 0, ',', ' ')) ?> FCFA</button>
            </form>
            <form method="post" action="<?= Html::url('/paiements/' . $payment->id() . '/bac-a-sable/annuler') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <button class="btn btn-ghost pay-checkout-btn" type="submit">Le client abandonne</button>
            </form>
        <?php else: ?>
            <p class="btq-status btq-status-<?= $payment->status() === PaymentStatus::SUCCESS ? 'success' : 'muted' ?>">Ce paiement est déjà <?= $payment->status() === PaymentStatus::SUCCESS ? 'payé' : 'clos' ?>.</p>
        <?php endif; ?>
    </article>
    <p><a class="btn btn-ghost" href="<?= Html::url('/paiements/' . $payment->id()) ?>">← Retour au paiement</a></p>
</section>
