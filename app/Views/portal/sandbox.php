<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Payment;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$payment = ($payment ?? null) instanceof Payment ? $payment : null;
if ($payment === null || $tenant === null) {
    return;
}
$base = '/t/' . $tenant->slug() . '/paiement/' . $payment->id();

?>
<section class="portal-pay">
    <p class="eyebrow">Paiement</p>
    <h1>Bac à sable NabooPay</h1>
    <p class="alert" role="status">Simulation locale — aucun appel API et aucun débit. Les clés live ne sont pas utilisées ici.</p>
    <article class="portal-confirm-card">
        <p><strong><?= Html::e($payment->description()) ?></strong></p>
        <p class="portal-amount"><?= Html::e(number_format($payment->amountMinor(), 0, ',', ' ')) ?> FCFA</p>
        <p class="muted">Référence <?= Html::e($payment->reference()) ?></p>
    </article>
    <div class="portal-pay-actions">
        <form class="portal-form-inline" method="post" action="<?= Html::url($base . '/confirmer') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <button class="btn btn-primary" type="submit">Simuler un paiement réussi</button>
        </form>
        <form class="portal-form-inline" method="post" action="<?= Html::url($base . '/annuler') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <button class="btn btn-secondary" type="submit">Simuler une annulation</button>
        </form>
    </div>
</section>
