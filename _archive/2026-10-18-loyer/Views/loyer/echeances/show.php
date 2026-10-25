<?php

declare(strict_types=1);

use Sesam\Core\Loyer\RentDueStatus;
use Sesam\Helpers\Html;
use Sesam\Models\LoyerRentDue;

$due = ($due ?? null) instanceof LoyerRentDue ? $due : null;
$canManage = (bool) ($canManage ?? false);
$canRemind = (bool) ($canRemind ?? false);
$canPay = (bool) ($canPay ?? false);

if ($due === null) {
    return;
}

?>
<section class="crm-detail">
    <h1>Échéance du <?= Html::e($due->dueDate()) ?></h1>
    <p class="muted"><?= Html::e($due->clientName()) ?> — <?= Html::e($due->propertyName()) ?> / <?= Html::e($due->unitLabel()) ?></p>
    <article class="card">
        <dl class="crm-dl">
            <dt>Montant</dt>
            <dd><?= Html::e(number_format($due->amountMinor(), 0, ',', ' ')) ?> FCFA</dd>
            <dt>Statut</dt>
            <dd><?= $due->isLate() ? 'En retard' : Html::e(RentDueStatus::label($due->status())) ?></dd>
            <?php if ($due->paymentId() !== null): ?>
                <dt>Paiement</dt>
                <dd><a href="<?= Html::url('/paiements/' . $due->paymentId()) ?>">#<?= Html::e((string) $due->paymentId()) ?></a></dd>
            <?php endif; ?>
        </dl>
    </article>
    <?php if ($canManage && $due->status() !== RentDueStatus::PAID): ?>
        <div class="row">
            <?php if ($canPay): ?>
                <form method="post" action="<?= Html::url('/loyer/echeances/' . $due->id() . '/encaisser') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <button class="btn btn-primary" type="submit">Encaisser le loyer</button>
                </form>
            <?php endif; ?>
            <?php if ($canRemind): ?>
                <form method="post" action="<?= Html::url('/loyer/echeances/' . $due->id() . '/relancer') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <button class="btn btn-secondary" type="submit">Relancer le locataire</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <p><a href="<?= Html::url('/loyer/echeances') ?>">← Échéances</a></p>
</section>
