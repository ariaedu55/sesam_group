<?php

declare(strict_types=1);

use Sesam\Core\Inscription\EnrollmentStatus;
use Sesam\Core\Inscription\InstallmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\InscriptionEnrollment;
use Sesam\Models\InscriptionInstallment;

$detail = is_array($detail ?? null) ? $detail : [];
$enrollment = ($detail['enrollment'] ?? null) instanceof InscriptionEnrollment ? $detail['enrollment'] : null;
$installments = is_array($detail['installments'] ?? null) ? $detail['installments'] : [];
$totalMinor = (int) ($detail['total_minor'] ?? 0);
$paidMinor = (int) ($detail['paid_minor'] ?? 0);
$pendingMinor = (int) ($detail['pending_minor'] ?? 0);
$canManage = (bool) ($canManage ?? false);
$canPay = (bool) ($canPay ?? false);

if ($enrollment === null) {
    return;
}

?>
<section class="crm-detail">
    <h1><?= Html::e($enrollment->studentName()) ?></h1>
    <p class="muted"><?= Html::e($enrollment->className()) ?> — responsable : <?= Html::e($enrollment->guardianName()) ?></p>
    <article class="card">
        <dl class="crm-dl">
            <dt>Statut dossier</dt>
            <dd><?= Html::e(EnrollmentStatus::label($enrollment->status())) ?></dd>
            <dt>Total frais</dt>
            <dd><?= Html::e(number_format($totalMinor, 0, ',', ' ')) ?> FCFA</dd>
            <dt>Payé</dt>
            <dd><?= Html::e(number_format($paidMinor, 0, ',', ' ')) ?> FCFA</dd>
            <dt>Reste à payer</dt>
            <dd><?= Html::e(number_format($pendingMinor, 0, ',', ' ')) ?> FCFA</dd>
        </dl>
    </article>

    <h2>Tranches</h2>
    <?php if ($installments === []): ?>
        <p class="muted">Aucune tranche.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">Libellé</th>
                    <th scope="col">Échéance</th>
                    <th scope="col">Montant</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($installments as $row): ?>
                    <?php if (!$row instanceof InscriptionInstallment) { continue; } ?>
                    <tr>
                        <td><?= Html::e($row->label()) ?></td>
                        <td><?= Html::e($row->dueDate()) ?></td>
                        <td><?= Html::e(number_format($row->amountMinor(), 0, ',', ' ')) ?> FCFA</td>
                        <td><?= Html::e(InstallmentStatus::label($row->status())) ?></td>
                        <td>
                            <?php if ($canManage && $canPay && $row->status() !== InstallmentStatus::PAID): ?>
                                <form method="post" action="<?= Html::url('/inscription/dossiers/' . $enrollment->id() . '/tranches/' . $row->id() . '/encaisser') ?>">
                                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                    <button class="btn btn-secondary" type="submit">Encaisser</button>
                                </form>
                            <?php elseif ($row->status() === InstallmentStatus::PAID && $row->paidAt() !== null): ?>
                                <span class="muted">Reçu — <?= Html::e(substr($row->paidAt(), 0, 10)) ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p><a href="<?= Html::url('/inscription/dossiers') ?>">← Dossiers</a></p>
</section>
