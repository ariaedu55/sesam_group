<?php

declare(strict_types=1);

use Sesam\Core\Auth\Permission;
use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;
use Sesam\Models\User;

$actor = ($actor ?? null) instanceof User ? $actor : null;
$board = is_array($board ?? null) ? $board : [];
$greeting = is_string($greeting ?? null) ? $greeting : '';
$kpis = is_array($board['kpis'] ?? null) ? $board['kpis'] : [];
$alerts = is_array($board['alerts'] ?? null) ? $board['alerts'] : [];
$upcoming = is_array($board['upcoming'] ?? null) ? $board['upcoming'] : [];
$payments = is_array($board['recentPayments'] ?? null) ? $board['recentPayments'] : [];
$months = is_array($board['months'] ?? null) ? $board['months'] : [];
$can = static fn (string $permission): bool => $actor instanceof User && $actor->can($permission);
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$max = max(1, ...array_values(array_map(static fn (array $m): int => $m['expected'], $months ?: [['expected' => 0]])));
$monthName = ImmoCatalog::monthLabel(gmdate('Y-m-d'));

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Bonjour <?= Html::e($greeting) ?></h1>
            <p class="muted">Vos loyers de <?= Html::e($monthName) ?> et ce qui demande votre attention aujourd'hui.</p>
        </div>
        <div class="btq-actions">
            <a class="btn btn-primary" href="<?= Html::url('/immo/loyers?vue=a-encaisser') ?>"><?php $name = 'card'; include $icon; ?>Encaisser un loyer</a>
            <?php if ($can(Permission::LOYER_LEASES)): ?><a class="btn btn-secondary" href="<?= Html::url('/immo/baux/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau contrat de bail</a><?php endif; ?>
        </div>
    </header>

    <?php if ((int) ($board['remindersSent'] ?? 0) > 0): ?>
        <p class="alert" role="status"><?= (int) $board['remindersSent'] ?> relance(s) WhatsApp envoyée(s) automatiquement ce matin. <a href="<?= Html::url('/immo/relances') ?>">Voir le journal</a></p>
    <?php endif; ?>

    <div class="btq-kpis">
        <article class="btq-kpi">
            <p>Encaissé en <?= Html::e(explode(' ', $monthName)[0]) ?></p>
            <strong><?= Html::e(Money::format((int) ($kpis['paid'] ?? 0))) ?></strong>
            <span class="muted">sur <?= Html::e(Money::format((int) ($kpis['expected'] ?? 0))) ?> attendus</span>
            <span class="immo-meter" aria-hidden="true"><i style="width: <?= min(100, (int) ($kpis['rate'] ?? 0)) ?>%"></i></span>
        </article>
        <article class="btq-kpi<?= (int) ($kpis['arrears_amount'] ?? 0) > 0 ? ' is-alert' : '' ?>">
            <p>Impayés</p>
            <strong><?= Html::e(Money::format((int) ($kpis['arrears_amount'] ?? 0))) ?></strong>
            <a href="<?= Html::url('/immo/loyers?vue=retards') ?>"><?= (int) ($kpis['arrears_count'] ?? 0) ?> loyer(s) en retard</a>
        </article>
        <article class="btq-kpi">
            <p>Occupation</p>
            <strong><?= (int) ($kpis['occupancy'] ?? 0) ?> %</strong>
            <span class="muted"><?= (int) ($kpis['occupied'] ?? 0) ?> loués / <?= (int) ($kpis['units'] ?? 0) ?> logements</span>
        </article>
        <article class="btq-kpi">
            <p>Contrats de bail en cours</p>
            <strong><?= (int) ($kpis['leases'] ?? 0) ?></strong>
            <a href="<?= Html::url('/immo/baux') ?>">Voir les contrats</a>
        </article>
    </div>

    <div class="btq-two">
        <div class="btq">
            <section class="btq-card">
                <header class="btq-card-head"><h2>À traiter</h2><?php if ($alerts !== []): ?><span class="btq-status btq-status-warning"><?= count($alerts) ?></span><?php endif; ?></header>
                <?php if ($alerts === []): ?>
                    <p class="muted">Rien d'urgent : tous les loyers sont à jour et aucun contrat de bail ne se termine bientôt.</p>
                <?php else: ?>
                    <ul class="immo-alerts">
                        <?php foreach ($alerts as $alert): ?>
                            <li class="is-<?= Html::e($alert['level']) ?>">
                                <a href="<?= Html::url($alert['href']) ?>">
                                    <span class="immo-alert-dot" aria-hidden="true"></span>
                                    <span><strong><?= Html::e($alert['title']) ?></strong><small><?= Html::e($alert['detail']) ?></small></span>
                                    <span class="immo-alert-go" aria-hidden="true">›</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="btq-card">
                <header class="btq-card-head"><h2>Échéances des 10 prochains jours</h2><a class="muted" href="<?= Html::url('/immo/loyers') ?>">Tous les loyers</a></header>
                <?php if ($upcoming === []): ?>
                    <p class="muted">Aucune échéance à venir dans les 10 jours.</p>
                <?php else: ?>
                    <ul class="btq-list">
                        <?php foreach ($upcoming as $due): ?>
                            <li><a href="<?= Html::url('/immo/loyers/' . $due['id']) ?>">
                                <span><strong><?= Html::e($due['full_name']) ?></strong><small><?= Html::e($due['property_name'] . ' — ' . $due['unit_label']) ?> · le <?= Html::e(ImmoCatalog::date($due['due_date'])) ?></small></span>
                                <span class="btq-list-end"><strong><?= Html::e(Money::format($due['remaining'])) ?></strong><?php if ($due['status'] === 'partial'): ?><small>reste</small><?php endif; ?></span>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <div class="btq">
            <section class="btq-card">
                <header class="btq-card-head"><h2>Encaissement sur 6 mois</h2></header>
                <div class="immo-bars" role="img" aria-label="Loyers attendus et encaissés par mois">
                    <?php foreach ($months as $month): ?>
                        <div class="immo-bar" title="<?= Html::e($month['label'] . ' : ' . Money::format($month['paid']) . ' / ' . Money::format($month['expected'])) ?>">
                            <span class="immo-bar-track" style="height: <?= (int) round($month['expected'] * 100 / $max) ?>%"><i style="height: <?= $month['expected'] > 0 ? min(100, (int) round($month['paid'] * 100 / $month['expected'])) : 0 ?>%"></i></span>
                            <small><?= Html::e($month['label']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="immo-legend"><span class="is-paid">Encaissé</span><span class="is-expected">Attendu</span></p>
            </section>

            <section class="btq-card">
                <header class="btq-card-head"><h2>Derniers encaissements</h2></header>
                <?php if ($payments === []): ?>
                    <p class="muted">Aucun loyer encaissé pour l'instant.</p>
                <?php else: ?>
                    <ul class="btq-list">
                        <?php foreach ($payments as $payment): ?>
                            <li><a href="<?= Html::url('/immo/quittances/' . $payment['id']) ?>">
                                <span><strong><?= Html::e($payment['full_name']) ?></strong><small><?= Html::e(ImmoCatalog::monthLabel($payment['due_date'])) ?> · <?= Html::e(ImmoCatalog::METHODS[$payment['method']] ?? $payment['method']) ?> · <?= Html::e(ImmoCatalog::date($payment['paid_on'])) ?></small></span>
                                <strong><?= Html::e(Money::format((int) $payment['amount_minor'])) ?></strong>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
