<?php

declare(strict_types=1);

use Sesam\Core\Auth\Permission;
use Sesam\Helpers\Html;
use Sesam\Models\Tenant;
use Sesam\Models\User;

$actor = $actor instanceof User ? $actor : null;
$tenant = $tenant instanceof Tenant ? $tenant : null;
$report = is_array($report ?? null) ? $report : [];
$period = (array) ($report['period'] ?? []);
$periods = (array) ($report['periods'] ?? []);
$revenue = (array) ($report['revenue'] ?? []);
$payments = (array) ($report['payments'] ?? []);
$unpaid = (array) ($report['unpaid'] ?? []);
$clients = (array) ($report['clients'] ?? []);
$trend = (array) ($report['trend'] ?? []);
$paymentMix = (array) ($report['paymentMix'] ?? []);
$activity = (array) ($report['activity'] ?? []);
$module = (array) ($report['module'] ?? []);

$number = static fn (int $value): string => number_format($value, 0, ',', ' ');
$money = static fn (int $value): string => number_format($value, 0, ',', ' ') . ' FCFA';
$compact = static function (int $value): string {
    if ($value >= 1000000) {
        return str_replace('.', ',', rtrim(rtrim(number_format($value / 1000000, 1, '.', ''), '0'), '.')) . ' M';
    }

    return $value >= 1000 ? number_format(round($value / 1000), 0, ',', ' ') . ' k' : (string) $value;
};
$percent = static fn (?int $value): string => $value === null ? '—' : $value . ' %';
$date = static function (string $value): string {
    $time = strtotime($value);

    return $time === false ? $value : gmdate('d/m/Y', $time);
};
$kpiValue = static fn (array $kpi): string => match ((string) ($kpi['format'] ?? 'number')) {
    'money' => $money((int) ($kpi['value'] ?? 0)),
    'percent' => $percent(isset($kpi['value']) ? (int) $kpi['value'] : null),
    default => $number((int) ($kpi['value'] ?? 0)),
};
$tone = static fn (string $key): string => match ($key) {
    'failed', 'cancelled', 'expired', 'unpaid' => ' is-alert',
    'pending', 'soon' => ' is-warn',
    default => '',
};

$delta = $revenue['delta'] ?? null;
$revenueAmount = (int) ($revenue['amount'] ?? 0);
if ($delta === null) {
    $deltaText = $revenueAmount > 0 ? 'Aucun encaissement sur la période précédente' : 'Aucun encaissement sur les deux périodes';
    $deltaClass = 'is-flat';
} elseif ($delta > 0) {
    $deltaText = '+' . $delta . ' % par rapport à la période précédente';
    $deltaClass = 'is-up';
} elseif ($delta < 0) {
    $deltaText = '−' . abs($delta) . ' % par rapport à la période précédente';
    $deltaClass = 'is-down';
} else {
    $deltaText = 'Stable par rapport à la période précédente';
    $deltaClass = 'is-flat';
}
$periodLabel = (string) ($period['label'] ?? '');
$trendTotal = array_sum(array_map(static fn ($row): int => (int) ($row['amount'] ?? 0), $trend));
$moduleLink = (array) ($module['link'] ?? []);
$showModuleLink = $moduleLink !== [] && $actor instanceof User && $actor->can((string) ($moduleLink['permission'] ?? ''));
$breakdown = (array) ($module['breakdown'] ?? []);
$top = is_array($module['top'] ?? null) ? $module['top'] : null;

?>
<section class="dash-premium stats">
    <header class="dash-hero console-hero stats-hero">
        <div>
            <p class="console-eyebrow">Statistiques<?php if ($tenant?->displayName()): ?> · <?= Html::e($tenant->displayName()) ?><?php endif; ?></p>
            <h1>Vos chiffres sur <?= Html::e($periodLabel) ?></h1>
            <p>Du <?= Html::e($date((string) ($period['from'] ?? ''))) ?> au <?= Html::e($date((string) ($period['to'] ?? ''))) ?>, comparés aux <?= Html::e($periodLabel) ?> précédents.</p>
        </div>
        <nav class="stats-periods" aria-label="Période analysée">
            <?php foreach ($periods as $key => $label): ?>
                <a href="<?= Html::url('/statistiques') ?>?periode=<?= Html::e((string) $key) ?>"<?= $key === ($period['key'] ?? '') ? ' aria-current="page"' : '' ?>><?= Html::e((string) $label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="stats-kpis">
        <article class="stats-kpi stats-kpi-lead">
            <p>Chiffre d'affaires encaissé</p>
            <strong><?= Html::e($money($revenueAmount)) ?></strong>
            <span class="stats-delta <?= $deltaClass ?>"><?= Html::e($deltaText) ?></span>
        </article>
        <article class="stats-kpi">
            <p>Taux de paiement</p>
            <strong><?= Html::e($percent(isset($payments['rate']) ? (int) $payments['rate'] : null)) ?></strong>
            <small><?= Html::e($number((int) ($payments['succeeded'] ?? 0))) ?> payés sur <?= Html::e($number((int) ($payments['initiated'] ?? 0))) ?> demandés</small>
        </article>
        <article class="stats-kpi">
            <p>Panier moyen</p>
            <strong><?= Html::e($money((int) ($revenue['average'] ?? 0))) ?></strong>
            <small>sur <?= Html::e($number((int) ($revenue['count'] ?? 0))) ?> paiement<?= (int) ($revenue['count'] ?? 0) > 1 ? 's' : '' ?> encaissé<?= (int) ($revenue['count'] ?? 0) > 1 ? 's' : '' ?></small>
        </article>
        <article class="stats-kpi stats-kpi-unpaid">
            <p><?= Html::e((string) ($unpaid['label'] ?? 'Impayés')) ?></p>
            <strong><?= Html::e($money((int) ($unpaid['amount'] ?? 0))) ?></strong>
            <small><?= Html::e((string) ($unpaid['hint'] ?? '')) ?> · à ce jour</small>
        </article>
    </div>

    <div class="stats-grid">
        <section class="dash-card stats-trend" aria-labelledby="stats-trend-title">
            <header class="dash-card-head">
                <h2 id="stats-trend-title">Encaissements par mois</h2>
                <span class="muted"><?= Html::e($money($trendTotal)) ?> sur <?= count($trend) ?> mois</span>
            </header>
            <ol class="stats-chart<?= count($trend) > 6 ? ' stats-chart-dense' : '' ?>">
                <?php foreach ($trend as $row): ?>
                    <?php $amount = (int) ($row['amount'] ?? 0); $count = (int) ($row['count'] ?? 0); ?>
                    <li<?= $amount === 0 ? ' class="is-empty"' : '' ?>>
                        <span class="stats-chart-value" aria-hidden="true"><?= $amount > 0 ? Html::e($compact($amount)) : '' ?></span>
                        <span class="stats-chart-bar" aria-hidden="true"><span style="height: <?= max($amount > 0 ? 4 : 0, (int) ($row['ratio'] ?? 0)) ?>%"></span></span>
                        <span class="stats-chart-label" aria-hidden="true"><span class="stats-chart-long"><?= Html::e((string) ($row['label'] ?? '')) ?><?php if ((string) ($row['year'] ?? '') !== ''): ?><span class="stats-chart-year"> <?= Html::e((string) $row['year']) ?></span><?php endif; ?></span><span class="stats-chart-short"><?= Html::e((string) ($row['short'] ?? '')) ?></span></span>
                        <span class="sr-only"><?= Html::e((string) ($row['full'] ?? '')) ?> : <?= Html::e($money($amount)) ?>, <?= Html::e($number($count)) ?> paiement<?= $count > 1 ? 's' : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="dash-card stats-clients" aria-labelledby="stats-clients-title">
            <header class="dash-card-head">
                <h2 id="stats-clients-title">Clients</h2>
                <?php if ($actor instanceof User && $actor->can(Permission::CLIENTS_VIEW)): ?>
                    <a class="stats-link" href="<?= Html::url('/clients') ?>">Voir les clients</a>
                <?php endif; ?>
            </header>
            <dl class="stats-figures">
                <div><dt>Nouveaux</dt><dd><?= Html::e($number((int) ($clients['new'] ?? 0))) ?></dd><dd class="muted">fiches créées</dd></div>
                <div><dt>Actifs</dt><dd><?= Html::e($number((int) ($clients['active'] ?? 0))) ?></dd><dd class="muted">à ce jour</dd></div>
                <div><dt>Payeurs</dt><dd><?= Html::e($number((int) ($clients['paying'] ?? 0))) ?></dd><dd class="muted">au moins un paiement</dd></div>
                <div><dt>Fidèles</dt><dd><?= Html::e($number((int) ($clients['returning'] ?? 0))) ?></dd><dd class="muted">deux paiements ou plus</dd></div>
            </dl>
        </section>
    </div>

    <section class="dash-card stats-module" aria-labelledby="stats-module-title">
        <header class="dash-card-head">
            <h2 id="stats-module-title"><?= Html::e((string) ($module['title'] ?? 'Activité')) ?></h2>
            <?php if ($showModuleLink): ?>
                <a class="stats-link" href="<?= Html::url((string) $moduleLink['href']) ?>"><?= Html::e((string) ($moduleLink['label'] ?? '')) ?></a>
            <?php endif; ?>
        </header>
        <dl class="stats-figures stats-figures-wide">
            <?php foreach ((array) ($module['kpis'] ?? []) as $kpi): ?>
                <?php if (!is_array($kpi)) { continue; } ?>
                <div>
                    <dt><?= Html::e((string) ($kpi['label'] ?? '')) ?></dt>
                    <dd><?= Html::e($kpiValue($kpi)) ?></dd>
                    <dd class="muted"><?= Html::e((string) ($kpi['hint'] ?? '')) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
        <div class="stats-module-body">
            <div>
                <h3><?= Html::e((string) ($breakdown['title'] ?? '')) ?></h3>
                <ul class="stats-bars">
                    <?php foreach ((array) ($breakdown['rows'] ?? []) as $row): ?>
                        <li class="<?= trim('stats-bars-item' . $tone((string) ($row['key'] ?? ''))) ?>">
                            <span class="console-bar-label"><?= Html::e((string) ($row['label'] ?? '')) ?></span>
                            <span class="console-bar-count"><?= Html::e($number((int) ($row['count'] ?? 0))) ?></span>
                            <span class="console-bar" aria-hidden="true"><span style="width: <?= (int) ($row['ratio'] ?? 0) ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if ($top !== null): ?>
                <div>
                    <h3><?= Html::e((string) ($top['title'] ?? '')) ?></h3>
                    <?php if (($top['rows'] ?? []) === []): ?>
                        <p class="muted stats-empty">Rien à classer sur cette période.</p>
                    <?php else: ?>
                        <ol class="stats-top">
                            <?php foreach ((array) $top['rows'] as $row): ?>
                                <li>
                                    <span class="stats-top-label"><?= Html::e((string) ($row['label'] ?? '')) ?></span>
                                    <span class="stats-top-count"><?= Html::e($number((int) ($row['count'] ?? 0))) ?> <?= Html::e((string) ($top['unit'] ?? '')) ?></span>
                                    <span class="console-bar" aria-hidden="true"><span style="width: <?= (int) ($row['ratio'] ?? 0) ?>%"></span></span>
                                    <?php if ((string) ($top['amountHint'] ?? '') !== '' && (int) ($row['amount'] ?? 0) > 0): ?>
                                        <span class="stats-top-amount muted"><?= Html::e($money((int) $row['amount'])) ?> <?= Html::e((string) $top['amountHint']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="stats-grid stats-grid-even">
        <section class="dash-card" aria-labelledby="stats-payments-title">
            <header class="dash-card-head">
                <h2 id="stats-payments-title">Paiements demandés</h2>
                <?php if ($actor instanceof User && $actor->can(Permission::PAYMENTS_VIEW)): ?>
                    <a class="stats-link" href="<?= Html::url('/paiements') ?>">Voir les paiements</a>
                <?php endif; ?>
            </header>
            <ul class="stats-bars">
                <?php foreach ($paymentMix as $row): ?>
                    <li class="<?= trim('stats-bars-item' . $tone((string) ($row['key'] ?? ''))) ?>">
                        <span class="console-bar-label"><?= Html::e((string) ($row['label'] ?? '')) ?></span>
                        <span class="console-bar-count"><?= Html::e($number((int) ($row['count'] ?? 0))) ?></span>
                        <span class="console-bar" aria-hidden="true"><span style="width: <?= (int) ($row['ratio'] ?? 0) ?>%"></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="dash-card" aria-labelledby="stats-activity-title">
            <header class="dash-card-head">
                <h2 id="stats-activity-title">Activité clients</h2>
            </header>
            <?php if ($activity === []): ?>
                <p class="muted stats-empty">Aucune action sur les fiches clients pendant cette période.</p>
            <?php else: ?>
                <ul class="stats-bars">
                    <?php foreach ($activity as $row): ?>
                        <li class="stats-bars-item">
                            <span class="console-bar-label"><?= Html::e((string) ($row['label'] ?? '')) ?></span>
                            <span class="console-bar-count"><?= Html::e($number((int) ($row['count'] ?? 0))) ?></span>
                            <span class="console-bar" aria-hidden="true"><span style="width: <?= (int) ($row['ratio'] ?? 0) ?>%"></span></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <p class="muted stats-note">Encaissements comptés à leur date de paiement, tous moyens confondus, y compris les paiements simulés du bac à sable. Les montants « à ce jour » ne dépendent pas de la période.</p>
</section>
