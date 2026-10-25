<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$view = (string) ($data['view'] ?? 'mois');
$month = (string) ($data['month'] ?? gmdate('Y-m'));
$counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
$totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
$q = (string) ($data['q'] ?? '');
$prev = gmdate('Y-m', (int) strtotime($month . '-01 -1 month'));
$next = gmdate('Y-m', (int) strtotime($month . '-01 +1 month'));
$badge = [
    'paid' => ['success', 'Payé'],
    'partial' => ['info', 'Payé en partie'],
    'pending' => ['warning', 'À payer'],
    'late' => ['danger', 'En retard'],
    'cancelled' => ['muted', 'Annulé'],
];
$tabs = [
    'mois' => ['Par mois', null],
    'a-encaisser' => ['À encaisser', $counts['a_encaisser'] ?? 0],
    'retards' => ['En retard', $counts['retards'] ?? 0],
    'partiels' => ['Partiels', $counts['partiels'] ?? 0],
    'payes' => ['Payés', null],
];

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Loyers</h1>
            <p class="muted">Chaque ligne est le loyer d'un mois. Cliquez pour encaisser (tout ou une partie), relancer ou imprimer la quittance.</p>
        </div>
    </header>

    <nav class="btq-tabs" aria-label="Filtrer les loyers">
        <?php foreach ($tabs as $key => [$label, $count]): ?>
            <a href="<?= Html::url('/immo/loyers' . ($key === 'mois' ? '' : '?vue=' . $key)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?><?php if ((int) $count > 0): ?> <span class="immo-count<?= $key === 'retards' ? ' is-danger' : '' ?>"><?= (int) $count ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="immo-toolbar">
        <?php if ($view === 'mois'): ?>
            <div class="immo-month">
                <a class="btn btn-secondary btn-sm" href="<?= Html::url('/immo/loyers?mois=' . $prev) ?>" aria-label="Mois précédent">←</a>
                <strong><?= Html::e(ucfirst(ImmoCatalog::monthLabel($month . '-01'))) ?></strong>
                <a class="btn btn-secondary btn-sm" href="<?= Html::url('/immo/loyers?mois=' . $next) ?>" aria-label="Mois suivant">→</a>
            </div>
        <?php endif; ?>
        <form method="get" action="<?= Html::url('/immo/loyers') ?>" class="immo-search" role="search">
            <?php if ($view !== 'mois'): ?><input type="hidden" name="vue" value="<?= Html::e($view) ?>"><?php else: ?><input type="hidden" name="mois" value="<?= Html::e($month) ?>"><?php endif; ?>
            <label class="sr-only" for="loyer-q">Rechercher</label>
            <input class="input" id="loyer-q" type="search" name="q" value="<?= Html::e($q) ?>" placeholder="Locataire, logement ou bien">
        </form>
    </div>

    <div class="btq-kpis immo-totals">
        <article class="btq-kpi"><p>Total</p><strong><?= Html::e(Money::format((int) ($totals['amount'] ?? 0))) ?></strong></article>
        <article class="btq-kpi"><p>Encaissé</p><strong><?= Html::e(Money::format((int) ($totals['paid'] ?? 0))) ?></strong></article>
        <article class="btq-kpi<?= (int) ($totals['remaining'] ?? 0) > 0 ? ' is-alert' : '' ?>"><p>Reste à encaisser</p><strong><?= Html::e(Money::format((int) ($totals['remaining'] ?? 0))) ?></strong></article>
    </div>

    <?php if ($rows === []): ?>
        <div class="btq-card empty"><p class="muted"><?= $view === 'retards' ? 'Aucun loyer en retard. Bravo !' : 'Aucun loyer ici.' ?> <?= $view === 'mois' ? 'Les loyers apparaissent dès qu\'un bail est créé.' : '' ?></p></div>
    <?php else: ?>
        <ul class="immo-dues">
            <?php foreach ($rows as $due): ?>
                <?php [$tone, $label] = $due['state'] === 'late' && $due['status'] === 'partial' ? ['danger', 'Partiel · en retard'] : ($badge[$due['state']] ?? ['muted', $due['state']]); ?>
                <?php $percent = (int) $due['amount_minor'] > 0 ? (int) round((int) $due['paid_minor'] * 100 / (int) $due['amount_minor']) : 0; ?>
                <li>
                    <a href="<?= Html::url('/immo/loyers/' . $due['id']) ?>">
                        <span class="immo-due-who">
                            <strong><?= Html::e($due['full_name']) ?></strong>
                            <small><?= Html::e($due['property_name'] . ' — ' . $due['unit_label']) ?></small>
                            <small><?= Html::e(ucfirst(ImmoCatalog::monthLabel($due['due_date']))) ?> · échéance <?= Html::e(ImmoCatalog::date($due['due_date'])) ?><?= $due['days_late'] > 0 ? ' · ' . $due['days_late'] . ' j de retard' : '' ?></small>
                        </span>
                        <span class="immo-due-money">
                            <span class="btq-status btq-status-<?= Html::e($tone) ?>"><?= Html::e($label) ?></span>
                            <strong><?= Html::e(Money::format($due['state'] === 'paid' ? (int) $due['amount_minor'] : $due['remaining'])) ?></strong>
                            <?php if ($due['status'] === 'partial'): ?><small><?= Html::e(Money::format((int) $due['paid_minor'])) ?> payés sur <?= Html::e(Money::format((int) $due['amount_minor'])) ?></small><?php endif; ?>
                            <span class="immo-meter" aria-hidden="true"><i style="width: <?= min(100, $percent) ?>%"></i></span>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
