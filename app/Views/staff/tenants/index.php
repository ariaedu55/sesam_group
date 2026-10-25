<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;
use Sesam\Models\TenantSubscription;

$tenants = is_array($tenants ?? null) ? $tenants : [];
$overview = is_array($overview ?? null) ? $overview : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'sector' => '', 'status' => '', 'subscription' => ''];
$sectors = is_array($sectors ?? null) ? $sectors : [];
$today = is_string($today ?? null) ? $today : gmdate('Y-m-d');
$canCreate = (bool) ($canCreate ?? false);
$canSeeSubscriptions = (bool) ($canSeeSubscriptions ?? false);
$subscriptionStats = is_array($overview['subscriptions'] ?? null) ? $overview['subscriptions'] : [];
$sectorCounts = is_array($overview['sectors'] ?? null) ? $overview['sectors'] : [];
$total = (int) ($overview['total'] ?? 0);
$filtered = $filters['q'] !== '' || $filters['sector'] !== '' || $filters['status'] !== '' || $filters['subscription'] !== '' || ($filters['source'] ?? '') !== '';
$statusLabels = ['active' => 'Actif', 'suspended' => 'Suspendu'];
$chip = ['active' => 'chip-ok', 'overdue' => 'chip-warn', 'suspended' => 'chip-off', 'none' => 'chip-muted', 'trial' => 'chip-ok', 'trial_over' => 'chip-warn'];
$date = static fn (?string $value): string => $value === null || $value === '' ? '' : date('d/m/Y', (int) strtotime($value . ' UTC'));

?>
<section class="console">
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">Console interne SESAM</p>
            <h1>Commerces abonnés</h1>
            <p>Tous les comptes, tous secteurs confondus. Activez, suspendez et suivez chaque abonnement.</p>
        </div>
        <?php if ($canCreate): ?>
            <div class="dash-hero-actions">
                <a class="dash-cta dash-cta-solid" href="<?= Html::url('/commerces/nouveau') ?>" data-modal="Commerces">Nouveau commerce</a>
            </div>
        <?php endif; ?>
    </header>

    <div class="console-kpis">
        <article class="dash-kpi">
            <span class="dash-kpi-icon dash-kpi-icon-violet" aria-hidden="true"></span>
            <div><p>Commerces</p><strong><?= $total ?></strong></div>
        </article>
        <article class="dash-kpi">
            <span class="dash-kpi-icon dash-kpi-icon-cyan" aria-hidden="true"></span>
            <div><p>Accès actifs</p><strong><?= (int) ($overview['active'] ?? 0) ?></strong></div>
        </article>
        <article class="dash-kpi">
            <span class="dash-kpi-icon console-kpi-off" aria-hidden="true"></span>
            <div><p>Accès suspendus</p><strong><?= (int) ($overview['suspended'] ?? 0) ?></strong></div>
        </article>
        <?php if ($canSeeSubscriptions): ?>
            <a class="dash-kpi console-kpi-link" href="<?= Html::url('/commerces') ?>?abonnement=overdue">
                <span class="dash-kpi-icon dash-kpi-icon-alert" aria-hidden="true"></span>
                <div><p>Abonnements échus</p><strong><?= (int) ($subscriptionStats['overdue'] ?? 0) ?></strong></div>
            </a>
            <article class="dash-kpi">
                <span class="dash-kpi-icon dash-kpi-icon-cyan" aria-hidden="true"></span>
                <div><p>À renouveler sous 7 jours</p><strong><?= (int) ($subscriptionStats['soon'] ?? 0) ?></strong></div>
            </article>
            <a class="dash-kpi console-kpi-link" href="<?= Html::url('/commerces') ?>?abonnement=none">
                <span class="dash-kpi-icon console-kpi-muted" aria-hidden="true"></span>
                <div><p>Sans abonnement</p><strong><?= (int) ($subscriptionStats['none'] ?? 0) ?></strong></div>
            </a>
        <?php endif; ?>
    </div>

    <div class="console-layout">
        <aside class="dash-card console-sectors" aria-labelledby="sectors-title">
            <div class="dash-card-head"><h2 id="sectors-title">Par secteur</h2></div>
            <ul class="console-bars">
                <?php foreach ($sectors as $slug => $label): ?>
                    <?php
                    if (!is_string($slug) || !is_string($label)) { continue; }
                    $count = (int) ($sectorCounts[$slug] ?? 0);
                    $share = $total > 0 ? (int) round($count * 100 / $total) : 0;
                    ?>
                    <li>
                        <a href="<?= Html::url('/commerces') ?>?secteur=<?= Html::e(rawurlencode($slug)) ?>"<?= $filters['sector'] === $slug ? ' aria-current="true"' : '' ?>>
                            <span class="console-bar-label"><?= Html::e($label) ?></span>
                            <span class="console-bar-count"><?= $count ?></span>
                            <span class="console-bar" aria-hidden="true"><span style="width: <?= $share ?>%"></span></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <div class="dash-card console-list">
            <form class="console-filters" method="get" action="<?= Html::url('/commerces') ?>" role="search">
                <div class="field console-filter-q">
                    <label for="q"><span>Rechercher</span></label>
                    <input class="input" id="q" name="q" type="search" maxlength="80" placeholder="Nom ou slug" value="<?= Html::e($filters['q']) ?>">
                </div>
                <div class="field">
                    <label for="secteur"><span>Secteur</span></label>
                    <select class="select" id="secteur" name="secteur">
                        <option value="">Tous</option>
                        <?php foreach ($sectors as $slug => $label): ?>
                            <?php if (!is_string($slug) || !is_string($label)) { continue; } ?>
                            <option value="<?= Html::e($slug) ?>"<?= $filters['sector'] === $slug ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="etat"><span>Accès</span></label>
                    <select class="select" id="etat" name="etat">
                        <option value="">Tous</option>
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= $value ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($canSeeSubscriptions): ?>
                    <div class="field">
                        <label for="abonnement"><span>Abonnement</span></label>
                        <select class="select" id="abonnement" name="abonnement">
                            <option value="">Tous</option>
                            <?php foreach (TenantSubscription::LABELS as $value => $label): ?>
                                <option value="<?= $value ?>"<?= $filters['subscription'] === $value ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="source"><span>Origine</span></label>
                        <select class="select" id="source" name="source">
                            <option value="">Toutes</option>
                            <option value="site"<?= ($filters['source'] ?? '') === 'site' ? ' selected' : '' ?>>Inscrits depuis le site</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="console-filter-actions">
                    <button class="btn btn-primary" type="submit">Filtrer</button>
                    <?php if ($filtered): ?>
                        <a class="btn btn-ghost" href="<?= Html::url('/commerces') ?>">Réinitialiser</a>
                    <?php endif; ?>
                </div>
            </form>

            <p class="muted console-count"><?= count($tenants) ?> commerce<?= count($tenants) > 1 ? 's' : '' ?><?= $filtered ? ' trouvé' . (count($tenants) > 1 ? 's' : '') : '' ?></p>

            <?php if ($tenants === []): ?>
                <div class="dash-empty console-empty">
                    <p><?= $filtered ? 'Aucun commerce ne correspond à ces filtres.' : 'Aucun commerce n\'est encore enregistré.' ?></p>
                    <?php if ($canCreate && !$filtered): ?>
                        <a class="btn btn-primary" href="<?= Html::url('/commerces/nouveau') ?>" data-modal="Commerces">Créer un commerce</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="console-rows">
                    <?php foreach ($tenants as $tenant): ?>
                        <?php
                        if (!$tenant instanceof Tenant) { continue; }
                        $subStatus = $tenant->get('subscription_status');
                        $subEnd = $tenant->get('subscription_period_end');
                        $state = TenantSubscription::stateOf(is_string($subStatus) ? $subStatus : null, is_string($subEnd) ? $subEnd : null, $today);
                        $owner = $tenant->get('owner_email');
                        $initials = mb_strtoupper(mb_substr($tenant->displayName(), 0, 2));
                        ?>
                        <li>
                            <a class="console-row" href="<?= Html::url('/commerces/' . $tenant->id()) ?>">
                                <span class="console-avatar" aria-hidden="true"><?= Html::e($initials) ?></span>
                                <span class="console-row-main">
                                    <strong><?= Html::e($tenant->displayName()) ?></strong>
                                    <span class="muted"><?= Html::e($tenant->sectorLabel()) ?> · /t/<?= Html::e($tenant->slug()) ?></span>
                                    <?php if (is_string($owner) && $owner !== ''): ?>
                                        <span class="muted console-row-owner"><?= Html::e($owner) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="console-row-chips">
                                    <span class="chip <?= $tenant->isActive() ? 'chip-ok' : 'chip-off' ?>">Accès <?= Html::e(mb_strtolower($statusLabels[$tenant->status()] ?? $tenant->status())) ?></span>
                                    <?php if ($canSeeSubscriptions): ?>
                                        <span class="chip <?= $chip[$state] ?? 'chip-muted' ?>"><?= Html::e(TenantSubscription::label($state)) ?><?= in_array($state, ['active', 'overdue', 'trial', 'trial_over'], true) ? ' · ' . Html::e($date(is_string($subEnd) ? $subEnd : null)) : '' ?></span>
                                    <?php endif; ?>
                                    <?php if ($tenant->signupSource() === 'site'): ?><span class="chip chip-muted">Inscrit seul</span><?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>
