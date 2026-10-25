<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

require __DIR__ . '/_helpers.php';

$invoices = is_array($invoices ?? null) ? $invoices : [];
$totals = is_array($totals ?? null) ? $totals : [];
$statusFilter = is_string($statusFilter ?? null) ? $statusFilter : '';
$today = is_string($today ?? null) ? $today : gmdate('Y-m-d');
$base = Html::url('/agence/factures');
$segments = ['' => 'Toutes', 'issued' => 'À payer', 'overdue' => 'En retard', 'paid' => 'Payées'];
$showProject = true;
$agencyTab = 'factures';

?>
<section class="console agency">
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">SESAM Digital · agence</p>
            <h1>Factures et paiements</h1>
            <p>Factures émises sur les projets web. Les paiements Wave, Orange Money, espèces ou virement sont enregistrés à réception.</p>
        </div>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="console-kpis">
        <article class="dash-kpi">
            <span class="dash-kpi-icon dash-kpi-icon-violet" aria-hidden="true"></span>
            <div><p>Reste à encaisser</p><strong class="agency-kpi-money"><?= $money((int) ($totals['outstanding'] ?? 0)) ?></strong></div>
        </article>
        <a class="dash-kpi console-kpi-link" href="<?= $base ?>?statut=overdue">
            <span class="dash-kpi-icon dash-kpi-icon-alert" aria-hidden="true"></span>
            <div><p>En retard</p><strong class="agency-kpi-money"><?= $money((int) ($totals['overdue'] ?? 0)) ?></strong><small><?= (int) ($totals['overdue_count'] ?? 0) ?> facture<?= (int) ($totals['overdue_count'] ?? 0) > 1 ? 's' : '' ?></small></div>
        </a>
        <article class="dash-kpi">
            <span class="dash-kpi-icon dash-kpi-icon-cyan" aria-hidden="true"></span>
            <div><p>Encaissé ce mois</p><strong class="agency-kpi-money"><?= $money((int) ($totals['paid_month'] ?? 0)) ?></strong></div>
        </article>
    </div>

    <div class="dash-card console-list">
        <div class="agency-segment" role="group" aria-label="Statut">
            <?php foreach ($segments as $value => $label): ?>
                <a href="<?= $base ?><?= $value === '' ? '' : '?statut=' . Html::e($value) ?>"<?= $statusFilter === $value ? ' aria-current="true"' : '' ?>><?= Html::e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($invoices === []): ?>
            <div class="dash-empty console-empty">
                <p><?= $statusFilter === '' ? 'Aucune facture. Elles s\'émettent depuis la fiche d\'un projet.' : 'Aucune facture dans cette catégorie.' ?></p>
            </div>
        <?php else: ?>
            <ul class="agency-invoices agency-invoices-wide">
                <?php foreach ($invoices as $invoice): ?>
                    <?php require __DIR__ . '/_invoice.php'; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
