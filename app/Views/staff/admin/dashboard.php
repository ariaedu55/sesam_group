<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Tenant\Sector;
use Sesam\Helpers\Html;
use Sesam\Models\User;

$actor = ($actor ?? null) instanceof User ? $actor : null;
$board = is_array($board ?? null) ? $board : [];
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$seeMoney = !empty($board['seeMoney']);
$seeTenants = !empty($board['seeTenants']);
$seeAgency = !empty($board['seeAgency']);
$overview = is_array($board['overview'] ?? null) ? $board['overview'] : [];
$subs = is_array($overview['subscriptions'] ?? null) ? $overview['subscriptions'] : [];
$mrr = is_array($board['mrr'] ?? null) ? $board['mrr'] : ['total' => 0, 'count' => 0, 'by_sector' => []];
$series = is_array($board['series'] ?? null) ? $board['series'] : [];
$due = is_array($board['due'] ?? null) ? $board['due'] : [];
$unpaid = is_array($board['unpaid'] ?? null) ? $board['unpaid'] : [];
$agency = is_array($board['agency'] ?? null) ? $board['agency'] : [];
$month = (int) ($board['month'] ?? 0);
$previous = (int) ($board['monthPrevious'] ?? 0);
$trend = $previous > 0 ? (int) round(($month - $previous) * 100 / $previous) : null;
$max = max(1, ...array_values(array_map(static fn (array $m): int => $m['saas'] + $m['agency'], $series ?: [["saas" => 0, "agency" => 0]])));
$date = static fn (string $value): string => $value === '' ? '—' : gmdate('d/m/Y', (int) strtotime(substr($value, 0, 10) . ' UTC'));

?>
<section class="btq console-board">
    <header class="btq-head">
        <div>
            <h1>Bonjour <?= Html::e($actor?->fullName() ?? '') ?></h1>
            <p class="muted">Voici l'état de SESAM au <?= Html::e(gmdate('d/m/Y')) ?> : commerces, abonnements, revenus et agence.</p>
        </div>
        <div class="btq-actions">
            <?php if ($actor?->can('tenants.create')): ?><a class="btn btn-primary" href="<?= Html::url('/commerces/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau commerce</a><?php endif; ?>
            <?php if ($seeAgency && $actor?->can('agency.prospects')): ?><a class="btn btn-secondary" href="<?= Html::url('/agence/prospects/nouveau') ?>">Nouveau prospect</a><?php endif; ?>
        </div>
    </header>

    <?php if ($seeMoney && $due !== []): ?>
        <?php $late = array_filter($due, static fn (array $d): bool => $d['overdue']); ?>
        <?php if ($late !== []): ?>
            <p class="alert alert-strong" role="status"><?= count($late) ?> abonnement(s) échu(s), soit <?= Html::e(Money::format((int) ($board['dueAmount'] ?? 0))) ?> à encaisser. <a href="#echeances">Voir la liste</a></p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="btq-kpis">
        <?php if ($seeMoney): ?>
            <article class="btq-kpi">
                <p>Revenu mensuel récurrent</p>
                <strong><?= Html::e(Money::format((int) $mrr['total'])) ?></strong>
                <span class="muted"><?= Html::e((string) $mrr['count']) ?> abonnement(s) à jour</span>
            </article>
            <article class="btq-kpi">
                <p>Encaissé ce mois</p>
                <strong><?= Html::e(Money::format($month)) ?></strong>
                <?php if ($trend !== null): ?><span class="console-trend <?= $trend >= 0 ? 'is-up' : 'is-down' ?>"><?= $trend >= 0 ? '+' : '' ?><?= $trend ?> % vs mois dernier</span><?php endif; ?>
            </article>
            <article class="btq-kpi<?= (int) ($unpaid['amount'] ?? 0) + (int) ($board['dueAmount'] ?? 0) > 0 ? ' is-alert' : '' ?>">
                <p>Reste à encaisser</p>
                <strong><?= Html::e(Money::format((int) ($unpaid['amount'] ?? 0) + (int) ($board['dueAmount'] ?? 0))) ?></strong>
                <span class="muted">factures agence + abonnements échus</span>
            </article>
        <?php endif; ?>
        <?php if ($seeTenants): ?>
            <article class="btq-kpi">
                <p>Commerces actifs</p>
                <strong><?= Html::e((string) ($overview['active'] ?? 0)) ?></strong>
                <span class="muted">sur <?= Html::e((string) ($overview['total'] ?? 0)) ?> · +<?= Html::e((string) ($board['newTenants'] ?? 0)) ?> ce mois (<?= (int) ($board['selfSignups'] ?? 0) ?> inscrits seuls) · <?= (int) ($board['trialRunning'] ?? 0) ?> en essai</span>
            </article>
        <?php endif; ?>
    </div>

    <?php if ($seeMoney): ?>
        <section class="btq-card">
            <header class="btq-card-head">
                <h2>Encaissements sur 12 mois</h2>
                <a class="muted" href="<?= Html::url('/revenus') ?>">Détail des revenus</a>
            </header>
            <div class="console-chart" role="img" aria-label="Encaissements par mois : abonnements et agence">
                <?php foreach ($series as $item): ?>
                    <?php $total = $item['saas'] + $item['agency']; ?>
                    <div class="console-month" title="<?= Html::e($item['label'] . ' : ' . Money::format($total)) ?>">
                        <span class="console-month-stack" style="height: <?= (int) round($total * 100 / $max) ?>%">
                            <i class="is-agency" style="height: <?= $total > 0 ? (int) round($item['agency'] * 100 / $total) : 0 ?>%"></i>
                            <i class="is-saas" style="height: <?= $total > 0 ? (int) round($item['saas'] * 100 / $total) : 0 ?>%"></i>
                        </span>
                        <small><?= Html::e($item['label']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="console-legend"><span class="is-saas">Abonnements SaaS</span><span class="is-agency">Agence (sites web)</span><span class="muted">12 mois : <?= Html::e(Money::format((int) ($board['year'] ?? 0))) ?></span></p>
        </section>
    <?php endif; ?>

    <div class="btq-two">
        <div class="btq">
            <?php if ($seeMoney): ?>
                <section class="btq-card" id="echeances">
                    <header class="btq-card-head">
                        <h2>Abonnements à relancer</h2>
                        <span class="muted">échus ou dans les 7 jours</span>
                    </header>
                    <?php if ($due === []): ?>
                        <p class="muted">Rien à relancer : tous les abonnements sont à jour.</p>
                    <?php else: ?>
                        <ul class="btq-list">
                            <?php foreach ($due as $item): ?>
                                <li>
                                    <a href="<?= Html::url('/commerces/' . $item['id']) ?>">
                                        <span>
                                            <strong><?= Html::e($item['name']) ?></strong>
                                            <small>Sesam <?= Html::e(Sector::label($item['sector'])) ?> · <?= Html::e(Money::format($item['amount'])) ?>/mois<?= $item['email'] !== '' ? ' · ' . Html::e($item['email']) : '' ?></small>
                                        </span>
                                        <span class="btq-status btq-status-<?= $item['overdue'] ? 'danger' : 'warning' ?>"><?= $item['overdue'] ? 'Échu le ' : 'Échéance ' ?><?= Html::e($date($item['period_end'])) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($seeTenants): ?>
                <?php $ending = is_array($board['trialEnding'] ?? null) ? $board['trialEnding'] : []; ?>
                <section class="btq-card">
                    <header class="btq-card-head">
                        <h2>Essais à convertir</h2>
                        <a class="muted" href="<?= Html::url('/commerces?abonnement=trial') ?>"><?= (int) ($board['trialRunning'] ?? 0) ?> essai(s) en cours</a>
                    </header>
                    <?php if ($ending === []): ?>
                        <p class="muted">Aucun essai ne se termine cette semaine.</p>
                    <?php else: ?>
                        <ul class="btq-list">
                            <?php foreach ($ending as $item): ?>
                                <li><a href="<?= Html::url('/commerces/' . (int) $item['id']) ?>">
                                    <span><strong><?= Html::e((string) $item['name']) ?></strong><small>Sesam <?= Html::e(Sector::label((string) $item['sector'])) ?><?= $item['phone'] ? ' · ' . Html::e((string) $item['phone']) : '' ?></small></span>
                                    <span class="btq-status btq-status-<?= (string) $item['period_end'] < gmdate('Y-m-d') ? 'danger' : 'warning' ?>">Fin <?= Html::e($date((string) $item['period_end'])) ?></span>
                                </a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="btq-card">
                    <header class="btq-card-head">
                        <h2>Commerces par module</h2>
                        <a class="muted" href="<?= Html::url('/commerces') ?>">Console des commerces</a>
                    </header>
                    <ul class="console-modules">
                        <?php foreach (Sector::LABELS as $sector => $label): ?>
                            <?php $bySector = $mrr['by_sector'][$sector] ?? ['amount' => 0, 'count' => 0]; ?>
                            <li>
                                <a href="<?= Html::url('/commerces?secteur=' . $sector) ?>">
                                    <strong><?= Html::e((string) ($overview['sectors'][$sector] ?? 0)) ?></strong>
                                    <span>Sesam <?= Html::e($label) ?></span>
                                    <?php if ($seeMoney): ?><small><?= Html::e(Money::format($bySector['amount'])) ?>/mois</small><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <dl class="btq-dl" style="margin-top: 14px">
                        <dt>En essai</dt><dd><?= Html::e((string) ($subs['trial'] ?? 0)) ?></dd>
                        <dt>Essai terminé</dt><dd><?= Html::e((string) ($subs['trial_over'] ?? 0)) ?></dd>
                        <dt>Sans abonnement</dt><dd><?= Html::e((string) ($subs['none'] ?? 0)) ?></dd>
                        <dt>Suspendus</dt><dd><?= Html::e((string) ($subs['suspended'] ?? 0)) ?></dd>
                    </dl>
                </section>
            <?php endif; ?>
        </div>

        <div class="btq">
            <?php if ($seeAgency): ?>
                <section class="btq-card">
                    <header class="btq-card-head">
                        <h2>Agence</h2>
                        <a class="muted" href="<?= Html::url('/agence') ?>">Pipeline</a>
                    </header>
                    <ul class="console-pulse">
                        <li><strong><?= Html::e((string) ($agency['requests_new'] ?? 0)) ?></strong><span>demandes du site à traiter</span></li>
                        <li><strong><?= Html::e((string) ($agency['open_prospects'] ?? 0)) ?></strong><span>prospects en cours</span></li>
                        <li><strong><?= Html::e((string) ($agency['quotes_pending'] ?? 0)) ?></strong><span>devis envoyés (<?= Html::e(Money::format((int) ($agency['quotes_pending_amount'] ?? 0))) ?>)</span></li>
                        <li><strong><?= Html::e((string) ($agency['projects_running'] ?? 0)) ?></strong><span>projets à livrer</span></li>
                    </ul>
                    <?php $requests = is_array($board['requests'] ?? null) ? $board['requests'] : []; ?>
                    <?php if ($requests !== []): ?>
                        <h3 class="console-subhead">Dernières demandes de devis</h3>
                        <ul class="btq-list">
                            <?php foreach ($requests as $request): ?>
                                <li><a href="<?= Html::url('/agence') ?>"><span><strong><?= Html::e($request['full_name']) ?></strong><small><?= Html::e($request['activity']) ?> · <?= Html::e($request['phone']) ?></small></span><span class="muted"><?= Html::e($date($request['created_at'])) ?></span></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($seeMoney): ?>
                <?php $payments = is_array($board['lastPayments'] ?? null) ? $board['lastPayments'] : []; ?>
                <section class="btq-card">
                    <header class="btq-card-head"><h2>Derniers encaissements</h2><a class="muted" href="<?= Html::url('/revenus') ?>">Tout voir</a></header>
                    <?php if ($payments === []): ?>
                        <p class="muted">Aucun encaissement récent. Enregistrez les paiements d'abonnement depuis la fiche d'un commerce.</p>
                    <?php else: ?>
                        <ul class="btq-list">
                            <?php foreach ($payments as $payment): ?>
                                <li><a href="<?= Html::url($payment['link']) ?>"><span><strong><?= Html::e($payment['label']) ?></strong><small><?= Html::e($date($payment['date'])) ?></small></span><strong><?= Html::e(Money::format($payment['amount'])) ?></strong></a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($seeTenants): ?>
                <?php $recent = is_array($board['recentTenants'] ?? null) ? $board['recentTenants'] : []; ?>
                <section class="btq-card">
                    <header class="btq-card-head"><h2>Nouveaux commerces</h2></header>
                    <ul class="btq-list">
                        <?php foreach ($recent as $item): ?>
                            <li><a href="<?= Html::url('/commerces/' . $item['id']) ?>"><span><strong><?= Html::e($item['name']) ?></strong><small>Sesam <?= Html::e(Sector::label($item['sector'])) ?> · créé le <?= Html::e($date($item['created_at'])) ?></small></span><span class="btq-status btq-status-<?= $item['status'] === 'active' ? 'success' : 'muted' ?>"><?= $item['status'] === 'active' ? 'Actif' : 'Suspendu' ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$seeMoney && !$seeTenants && !$seeAgency): ?>
        <div class="empty btq-card"><p>Votre rôle n'a pas encore d'écran dédié. Utilisez la recherche ou votre profil.</p></div>
    <?php endif; ?>
</section>
