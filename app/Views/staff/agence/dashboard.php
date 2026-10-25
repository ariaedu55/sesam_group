<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Services\Agency\AgencyCatalog;

require __DIR__ . '/_helpers.php';

$stageCounts = is_array($stageCounts ?? null) ? $stageCounts : [];
$pipeline = is_array($pipeline ?? null) ? $pipeline : ['count' => 0, 'amount' => 0];
$projectCounts = is_array($projectCounts ?? null) ? $projectCounts : [];
$invoiceTotals = is_array($invoiceTotals ?? null) ? $invoiceTotals : [];
$requests = is_array($requests ?? null) ? $requests : [];
$pendingRequests = (int) ($pendingRequests ?? 0);
$dueActions = (int) ($dueActions ?? 0);
$canProspects = (bool) ($canProspects ?? false);
$activities = AgencyCatalog::activities();
$funnel = array_diff_key(AgencyCatalog::STAGES, ['perdu' => true]);
$funnelMax = max(1, ...array_map(static fn (string $stage): int => (int) ($stageCounts[$stage] ?? 0), array_keys($funnel)));
$agencyTab = 'dashboard';

?>
<section class="console agency">
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">SESAM Digital · agence</p>
            <h1>Pipeline commercial</h1>
            <p>Des premiers contacts jusqu'à la livraison du site : prospects, devis, projets et encaissements.</p>
        </div>
        <div class="dash-hero-actions">
            <?php if ($canProspects): ?>
                <a class="dash-cta dash-cta-solid" href="<?= Html::url('/agence/prospects/nouveau') ?>" data-modal="Agence">Nouveau prospect</a>
            <?php endif; ?>
            <a class="dash-cta dash-cta-ghost" href="<?= Html::url('/agence/projets') ?>">Voir les projets</a>
        </div>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="console-kpis agency-kpis">
        <a class="dash-kpi console-kpi-link" href="<?= Html::url('/agence/prospects') ?>?etape=devis-envoye">
            <span class="dash-kpi-icon dash-kpi-icon-alert" aria-hidden="true"></span>
            <div><p>Devis en attente</p><strong><?= (int) $pipeline['count'] ?></strong><small><?= $money((int) $pipeline['amount']) ?></small></div>
        </a>
        <a class="dash-kpi console-kpi-link" href="<?= Html::url('/agence/projets') ?>?statut=in_progress">
            <span class="dash-kpi-icon dash-kpi-icon-cyan" aria-hidden="true"></span>
            <div><p>Projets en cours</p><strong><?= (int) ($projectCounts['in_progress'] ?? 0) ?></strong><small><?= (int) ($projectCounts['planned'] ?? 0) ?> à démarrer</small></div>
        </a>
        <a class="dash-kpi console-kpi-link" href="<?= Html::url('/agence/factures') ?>?statut=issued">
            <span class="dash-kpi-icon dash-kpi-icon-violet" aria-hidden="true"></span>
            <div><p>Reste à encaisser</p><strong class="agency-kpi-money"><?= $money((int) ($invoiceTotals['outstanding'] ?? 0)) ?></strong><small><?= (int) ($invoiceTotals['overdue_count'] ?? 0) ?> facture<?= (int) ($invoiceTotals['overdue_count'] ?? 0) > 1 ? 's' : '' ?> en retard</small></div>
        </a>
        <a class="dash-kpi console-kpi-link" href="<?= Html::url('/agence/factures') ?>?statut=paid">
            <span class="dash-kpi-icon dash-kpi-icon-cyan" aria-hidden="true"></span>
            <div><p>Encaissé ce mois</p><strong class="agency-kpi-money"><?= $money((int) ($invoiceTotals['paid_month'] ?? 0)) ?></strong></div>
        </a>
    </div>

    <div class="agency-grid">
        <article class="dash-card agency-funnel-card" aria-labelledby="funnel-title">
            <div class="dash-card-head">
                <h2 id="funnel-title">Pipeline</h2>
                <a class="agency-more" href="<?= Html::url('/agence/prospects') ?>">Tous les prospects</a>
            </div>
            <ol class="agency-funnel">
                <?php foreach ($funnel as $stage => $label): ?>
                    <?php $count = (int) ($stageCounts[$stage] ?? 0); ?>
                    <li>
                        <a href="<?= Html::url('/agence/prospects') ?>?etape=<?= Html::e(rawurlencode($stage)) ?>">
                            <span class="agency-funnel-label"><?= Html::e($label) ?></span>
                            <span class="agency-funnel-count"><?= $count ?></span>
                            <span class="console-bar" aria-hidden="true"><span style="width: <?= (int) round($count * 100 / $funnelMax) ?>%"></span></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="muted agency-funnel-foot">
                <a href="<?= Html::url('/agence/prospects') ?>?etape=perdu">Perdus : <?= (int) ($stageCounts['perdu'] ?? 0) ?></a>
                <?php if ($dueActions > 0): ?>
                    · <strong class="agency-due"><?= $dueActions ?> relance<?= $dueActions > 1 ? 's' : '' ?> à faire</strong>
                <?php endif; ?>
            </p>
        </article>

        <div class="agency-side">
            <?php require __DIR__ . '/_capacity.php'; ?>

            <article class="dash-card" aria-labelledby="requests-title">
                <div class="dash-card-head">
                    <h2 id="requests-title">Demandes du site</h2>
                    <span class="chip <?= $pendingRequests > 0 ? 'chip-warn' : 'chip-muted' ?>"><?= $pendingRequests ?> à traiter</span>
                </div>
                <?php if ($requests === []): ?>
                    <p class="muted dash-empty">Toutes les demandes du formulaire /devis sont suivies.</p>
                <?php else: ?>
                    <ul class="agency-requests">
                        <?php foreach ($requests as $request): ?>
                            <li>
                                <div>
                                    <strong><?= Html::e((string) $request['full_name']) ?></strong>
                                    <span class="muted"><?= Html::e((string) ($activities[(string) $request['activity']] ?? $request['activity'])) ?> · <?= $date((string) $request['created_at']) ?></span>
                                </div>
                                <?php if ($canProspects): ?>
                                    <form method="post" action="<?= Html::url('/agence/demandes/' . (int) $request['id'] . '/convertir') ?>">
                                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                        <button class="btn btn-secondary btn-sm" type="submit">Suivre</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($pendingRequests > count($requests)): ?>
                        <a class="agency-more" href="<?= Html::url('/agence/prospects') ?>#demandes">Voir les <?= $pendingRequests ?> demandes</a>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        </div>
    </div>
</section>
