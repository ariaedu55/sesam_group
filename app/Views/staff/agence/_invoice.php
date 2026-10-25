<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\AgencyInvoice;
use Sesam\Services\Agency\AgencyCatalog;

if (!$invoice instanceof AgencyInvoice) {
    return;
}
$overdue = $invoice->isOverdue($today);
$showProject = (bool) ($showProject ?? false);

?>
<li class="agency-invoice<?= $overdue ? ' is-overdue' : '' ?>">
    <div class="agency-invoice-head">
        <span>
            <strong><?= Html::e($invoice->reference()) ?></strong>
            <span class="muted"><?= Html::e($invoice->label()) ?></span>
            <?php if ($showProject): ?>
                <a class="agency-invoice-project" href="<?= Html::url('/agence/projets/' . $invoice->projectId()) ?>"><?= Html::e($invoice->projectTitle()) ?></a>
            <?php endif; ?>
        </span>
        <span class="agency-invoice-amount"><?= $money($invoice->amount()) ?></span>
    </div>
    <p class="agency-invoice-meta">
        <?php if ($invoice->isPaid()): ?>
            <span class="chip chip-ok">Payée le <?= $date($invoice->paidOn()) ?></span>
            <span class="muted"><?= Html::e($invoice->paymentMethodLabel()) ?></span>
        <?php else: ?>
            <span class="chip <?= $overdue ? 'chip-off' : 'chip-warn' ?>"><?= $overdue ? 'En retard' : 'À payer' ?></span>
            <span class="muted">Échéance <?= $date($invoice->dueOn()) ?></span>
        <?php endif; ?>
    </p>
    <?php if (!$invoice->isPaid() && (bool) ($canInvoices ?? false)): ?>
        <form class="agency-pay" data-modal-form="Encaisser" data-modal-title="Encaisser <?= Html::e($invoice->reference()) ?>" data-modal-kicker="Agence" method="post" action="<?= Html::url('/agence/factures/' . $invoice->id() . '/payer') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <?php if ($showProject): ?><input type="hidden" name="retour" value="factures"><?php endif; ?>
            <label class="sr-only" for="method-<?= $invoice->id() ?>">Moyen de paiement</label>
            <select class="select" id="method-<?= $invoice->id() ?>" name="payment_method" required>
                <option value="">Moyen reçu…</option>
                <?php foreach (AgencyCatalog::PAYMENT_METHODS as $slug => $label): ?>
                    <option value="<?= Html::e($slug) ?>"><?= Html::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="paid-<?= $invoice->id() ?>">Date du paiement</label>
            <input class="input" id="paid-<?= $invoice->id() ?>" name="paid_on" type="date" max="<?= Html::e($today) ?>" value="<?= Html::e($today) ?>">
            <button class="btn btn-primary btn-sm" type="submit">Encaisser</button>
        </form>
    <?php endif; ?>
</li>
