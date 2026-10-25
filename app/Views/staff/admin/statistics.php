<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Tenant\Sector;
use Sesam\Helpers\Html;

$stats = is_array($stats ?? null) ? $stats : [];
$series = is_array($stats['series'] ?? null) ? $stats['series'] : [];
$mrr = is_array($stats['mrr'] ?? null) ? $stats['mrr'] : ['total' => 0, 'count' => 0, 'by_sector' => []];
$quotes = is_array($stats['quoteStats'] ?? null) ? $stats['quoteStats'] : [];
$bySector = [];
foreach ((array) ($stats['by_sector'] ?? []) as $row) {
    $bySector[(string) $row['sector']] = $row;
}
$online = ['console' => 0, 'en-ligne' => 0];
foreach ((array) ($stats['online_share'] ?? []) as $row) {
    $online[(string) $row['channel']] = (int) $row['amount'];
}
$maxMoney = max(1, ...array_values(array_map(static fn (array $m): int => $m['saas'] + $m['agency'], $series ?: [['saas' => 0, 'agency' => 0]])));
$maxSignups = max(1, ...array_values(array_map(static fn (array $m): int => $m['site'] + $m['console'], $series ?: [['site' => 0, 'console' => 0]])));
$yearSaas = (int) ($stats['yearSaas'] ?? 0);
$date = static fn (string $value): string => gmdate('d/m/Y', (int) strtotime($value . ' UTC'));

?>
<section class="btq console-board">
    <header class="btq-head">
        <div>
            <h1>Statistiques</h1>
            <p class="muted">Les 12 derniers mois : croissance des abonnés, conversion des essais, revenus par module et activité de l'agence.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/revenus') ?>">Détail des revenus</a>
    </header>

    <h2 class="console-section">Abonnements SaaS</h2>
    <div class="btq-kpis">
        <article class="btq-kpi"><p>Revenu mensuel récurrent</p><strong><?= Html::e(Money::format((int) $mrr['total'])) ?></strong><span class="muted"><?= (int) $mrr['count'] ?> abonné(s) payant(s)</span></article>
        <article class="btq-kpi"><p>Revenu moyen par abonné</p><strong><?= Html::e(Money::format((int) ($stats['arpu'] ?? 0))) ?></strong><span class="muted">par mois</span></article>
        <article class="btq-kpi"><p>Essais convertis</p><strong><?= (int) ($stats['conversion'] ?? 0) ?> %</strong><span class="muted"><?= (int) ($stats['converted'] ?? 0) ?> sur <?= (int) ($stats['trials'] ?? 0) ?> essai(s) · <?= (int) ($stats['trial_running'] ?? 0) ?> en cours</span></article>
        <article class="btq-kpi<?= (int) ($stats['lost'] ?? 0) > 0 ? ' is-alert' : '' ?>"><p>Commerces perdus</p><strong><?= (int) ($stats['lost'] ?? 0) ?></strong><span class="muted">suspendus ou sans paiement depuis 30 j</span></article>
    </div>

    <div class="btq-two">
        <section class="btq-card">
            <header class="btq-card-head"><h2>Encaissements par mois</h2><span class="muted"><?= Html::e(Money::format($yearSaas + (int) ($stats['yearAgency'] ?? 0))) ?> sur 12 mois</span></header>
            <div class="console-chart">
                <?php foreach ($series as $item): ?>
                    <?php $total = $item['saas'] + $item['agency']; ?>
                    <div class="console-month" title="<?= Html::e($item['label'] . ' : ' . Money::format($total)) ?>">
                        <span class="console-month-stack" style="height: <?= (int) round($total * 100 / $maxMoney) ?>%">
                            <i class="is-agency" style="height: <?= $total > 0 ? (int) round($item['agency'] * 100 / $total) : 0 ?>%"></i>
                            <i class="is-saas" style="height: <?= $total > 0 ? (int) round($item['saas'] * 100 / $total) : 0 ?>%"></i>
                        </span>
                        <small><?= Html::e($item['label']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="console-legend"><span class="is-saas">Abonnements</span><span class="is-agency">Agence</span></p>
        </section>
        <section class="btq-card">
            <header class="btq-card-head"><h2>Nouveaux commerces par mois</h2></header>
            <div class="console-chart">
                <?php foreach ($series as $item): ?>
                    <?php $total = $item['site'] + $item['console']; ?>
                    <div class="console-month" title="<?= Html::e($item['label'] . ' : ' . $total . ' commerce(s)') ?>">
                        <span class="console-month-stack" style="height: <?= (int) round($total * 100 / $maxSignups) ?>%">
                            <i class="is-agency" style="height: <?= $total > 0 ? (int) round($item['console'] * 100 / $total) : 0 ?>%"></i>
                            <i class="is-saas" style="height: <?= $total > 0 ? (int) round($item['site'] * 100 / $total) : 0 ?>%"></i>
                        </span>
                        <small><?= Html::e($item['label']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="console-legend"><span class="is-saas">Inscrits seuls (site)</span><span class="is-agency">Créés par SESAM</span></p>
        </section>
    </div>

    <div class="btq-two">
        <section class="btq-card">
            <h2>Revenus par module (12 mois)</h2>
            <ul class="btq-list">
                <?php foreach (Sector::LABELS as $sector => $label): ?>
                    <?php $row = $bySector[$sector] ?? ['amount' => 0, 'payers' => 0]; $mrrSector = $mrr['by_sector'][$sector] ?? ['amount' => 0, 'count' => 0]; ?>
                    <li><div>
                        <span><strong>Sesam <?= Html::e($label) ?></strong><small><?= (int) $row['payers'] ?> commerce(s) payant(s) · MRR <?= Html::e(Money::format((int) $mrrSector['amount'])) ?></small></span>
                        <span class="btq-list-end"><strong><?= Html::e(Money::format((int) $row['amount'])) ?></strong><small><?= $yearSaas > 0 ? (int) round((int) $row['amount'] * 100 / $yearSaas) : 0 ?> %</small></span>
                    </div></li>
                <?php endforeach; ?>
            </ul>
            <p class="muted" style="margin-top: 10px">Payé en ligne par les commerces : <?= Html::e(Money::format($online['en-ligne'])) ?> · saisi par SESAM : <?= Html::e(Money::format($online['console'])) ?>.</p>
        </section>

        <section class="btq-card">
            <h2>Essais qui se terminent</h2>
            <?php $ending = (array) ($stats['trial_ending'] ?? []); ?>
            <?php if ($ending === []): ?>
                <p class="muted">Aucun essai ne se termine cette semaine.</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($ending as $item): ?>
                        <li><a href="<?= Html::url('/commerces/' . (int) $item['id']) ?>">
                            <span><strong><?= Html::e((string) $item['name']) ?></strong><small>Sesam <?= Html::e(Sector::label((string) $item['sector'])) ?><?= $item['phone'] ? ' · ' . Html::e((string) $item['phone']) : '' ?></small></span>
                            <span class="btq-status btq-status-<?= (string) $item['period_end'] < gmdate('Y-m-d') ? 'danger' : 'warning' ?>"><?= (string) $item['period_end'] < gmdate('Y-m-d') ? 'Fini le ' : 'Fin le ' ?><?= Html::e($date((string) $item['period_end'])) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <p class="muted" style="margin-top: 10px">Appelez-les pour les aider à s'abonner : c'est le meilleur moment.</p>
            <?php endif; ?>
        </section>
    </div>

    <h2 class="console-section">Agence (sites web)</h2>
    <div class="btq-kpis">
        <article class="btq-kpi"><p>Demandes de devis</p><strong><?= (int) ($stats['requests'] ?? 0) ?></strong><a href="<?= Html::url('/agence/demandes') ?>">Voir les demandes</a></article>
        <article class="btq-kpi"><p>Devis acceptés</p><strong><?= (int) ($quotes['win_rate'] ?? 0) ?> %</strong><span class="muted"><?= (int) ($quotes['accepted'] ?? 0) ?> accepté(s), <?= (int) ($quotes['refused'] ?? 0) ?> refusé(s)</span></article>
        <article class="btq-kpi"><p>Montant signé</p><strong><?= Html::e(Money::format((int) ($quotes['accepted_amount'] ?? 0))) ?></strong><span class="muted"><?= (int) ($quotes['sent'] ?? 0) ?> devis en attente</span></article>
        <article class="btq-kpi"><p>Sites livrés</p><strong><?= (int) ($stats['projects_delivered'] ?? 0) ?></strong><span class="muted">sur 12 mois</span></article>
    </div>

    <?php $top = (array) ($stats['top'] ?? []); ?>
    <?php if ($top !== []): ?>
        <section class="btq-card">
            <h2>Meilleurs abonnés (12 mois)</h2>
            <ul class="btq-list">
                <?php foreach ($top as $item): ?>
                    <li><a href="<?= Html::url('/commerces/' . (int) $item['id']) ?>"><span><strong><?= Html::e((string) $item['name']) ?></strong><small>Sesam <?= Html::e(Sector::label((string) $item['sector'])) ?></small></span><strong><?= Html::e(Money::format((int) $item['amount'])) ?></strong></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
