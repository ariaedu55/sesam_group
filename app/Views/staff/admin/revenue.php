<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Tenant\Sector;
use Sesam\Helpers\Html;

$revenue = is_array($revenue ?? null) ? $revenue : [];
$methods = is_array($methods ?? null) ? $methods : [];
$year = (int) ($revenue['year'] ?? gmdate('Y'));
$month = (string) ($revenue['month'] ?? '');
$rows = is_array($revenue['rows'] ?? null) ? $revenue['rows'] : [];
$payments = is_array($revenue['payments'] ?? null) ? $revenue['payments'] : [];
$mrr = is_array($revenue['mrr'] ?? null) ? $revenue['mrr'] : ['total' => 0, 'count' => 0, 'by_sector' => []];
$unpaid = is_array($revenue['unpaid'] ?? null) ? $revenue['unpaid'] : [];
$due = is_array($revenue['due'] ?? null) ? $revenue['due'] : [];
$totalSaas = (int) ($revenue['totalSaas'] ?? 0);
$totalAgency = (int) ($revenue['totalAgency'] ?? 0);
$max = max(1, ...array_values(array_map(static fn (array $r): int => $r['saas'] + $r['agency'], $rows ?: [["saas" => 0, "agency" => 0]])));
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$date = static fn (string $value): string => $value === '' ? '—' : gmdate('d/m/Y', (int) strtotime($value . ' UTC'));
$exportQuery = http_build_query(array_filter(['annee' => (string) $year, 'mois' => $month]));

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Revenus <?= Html::e((string) $year) ?></h1>
            <p class="muted">Abonnements SaaS encaissés et factures agence payées. Cliquez sur un mois pour voir son détail.</p>
        </div>
        <div class="btq-actions">
            <a class="btn btn-secondary btn-sm" href="<?= Html::url('/revenus?annee=' . ($year - 1)) ?>">← <?= $year - 1 ?></a>
            <?php if ($year < (int) gmdate('Y')): ?><a class="btn btn-secondary btn-sm" href="<?= Html::url('/revenus?annee=' . ($year + 1)) ?>"><?= $year + 1 ?> →</a><?php endif; ?>
            <a class="btn btn-primary btn-sm" href="<?= Html::url('/revenus/export?' . $exportQuery) ?>"><?php $name = 'file'; include $icon; ?>Exporter (CSV)</a>
        </div>
    </header>

    <div class="btq-kpis">
        <article class="btq-kpi"><p>Total encaissé</p><strong><?= Html::e(Money::format($totalSaas + $totalAgency)) ?></strong><span class="muted">en <?= Html::e((string) $year) ?></span></article>
        <article class="btq-kpi"><p>Abonnements SaaS</p><strong><?= Html::e(Money::format($totalSaas)) ?></strong><span class="muted">MRR actuel <?= Html::e(Money::format((int) $mrr['total'])) ?></span></article>
        <article class="btq-kpi"><p>Agence</p><strong><?= Html::e(Money::format($totalAgency)) ?></strong><span class="muted">factures payées</span></article>
        <article class="btq-kpi<?= (int) ($unpaid['amount'] ?? 0) > 0 ? ' is-alert' : '' ?>"><p>Factures agence à payer</p><strong><?= Html::e(Money::format((int) ($unpaid['amount'] ?? 0))) ?></strong><span class="muted"><?= Html::e((string) ($unpaid['overdue_count'] ?? 0)) ?> en retard</span></article>
    </div>

    <section class="btq-card">
        <div class="console-chart is-large">
            <?php foreach ($rows as $key => $row): ?>
                <?php $total = $row['saas'] + $row['agency']; ?>
                <a class="console-month<?= $month === $key ? ' is-on' : '' ?>" href="<?= Html::url('/revenus?annee=' . $year . '&mois=' . $key) ?>" title="<?= Html::e($row['label'] . ' : ' . Money::format($total)) ?>">
                    <em><?= $total > 0 ? Html::e(number_format(intdiv($total, 1000), 0, ',', ' ')) . ' k' : '' ?></em>
                    <span class="console-month-stack" style="height: <?= (int) round($total * 100 / $max) ?>%">
                        <i class="is-agency" style="height: <?= $total > 0 ? (int) round($row['agency'] * 100 / $total) : 0 ?>%"></i>
                        <i class="is-saas" style="height: <?= $total > 0 ? (int) round($row['saas'] * 100 / $total) : 0 ?>%"></i>
                    </span>
                    <small><?= Html::e($row['label']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="console-legend"><span class="is-saas">Abonnements SaaS</span><span class="is-agency">Agence (sites web)</span></p>
    </section>

    <div class="btq-two">
        <section class="btq-card">
            <header class="btq-card-head">
                <h2>Encaissements <?= $month !== '' ? 'de ' . Html::e($rows[$month]['label'] ?? $month) . ' ' . $year : 'de l\'année' ?></h2>
                <?php if ($month !== ''): ?><a class="muted" href="<?= Html::url('/revenus?annee=' . $year) ?>">Toute l'année</a><?php endif; ?>
            </header>
            <?php if ($payments === []): ?>
                <p class="muted">Aucun encaissement sur cette période.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table console-payments">
                        <thead><tr><th scope="col">Date</th><th scope="col">Libellé</th><th scope="col">Moyen</th><th scope="col">Montant</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= Html::e($date($payment['date'])) ?></td>
                                <td><a href="<?= Html::url($payment['link']) ?>"><?= Html::e($payment['label']) ?></a><?php if ($payment['reference'] !== ''): ?><br><small class="muted"><?= Html::e($payment['reference']) ?></small><?php endif; ?></td>
                                <td><?= Html::e($methods[$payment['method']] ?? ($payment['method'] !== '' ? $payment['method'] : '—')) ?></td>
                                <td><strong><?= Html::e(Money::format($payment['amount'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="btq">
            <section class="btq-card">
                <h2>Revenu mensuel récurrent</h2>
                <p class="console-big"><?= Html::e(Money::format((int) $mrr['total'])) ?></p>
                <ul class="btq-list">
                    <?php foreach (Sector::LABELS as $sector => $label): ?>
                        <?php $item = $mrr['by_sector'][$sector] ?? ['amount' => 0, 'count' => 0]; ?>
                        <li><div><span><strong>Sesam <?= Html::e($label) ?></strong><small><?= Html::e((string) $item['count']) ?> abonnement(s)</small></span><strong><?= Html::e(Money::format($item['amount'])) ?></strong></div></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="btq-card">
                <h2>Échéances des 30 prochains jours</h2>
                <?php if ($due === []): ?>
                    <p class="muted">Aucune échéance proche.</p>
                <?php else: ?>
                    <ul class="btq-list">
                        <?php foreach ($due as $item): ?>
                            <li><a href="<?= Html::url('/commerces/' . $item['id']) ?>"><span><strong><?= Html::e($item['name']) ?></strong><small><?= Html::e(Money::format($item['amount'])) ?>/mois</small></span><span class="btq-status btq-status-<?= $item['overdue'] ? 'danger' : 'warning' ?>"><?= Html::e($date($item['period_end'])) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
