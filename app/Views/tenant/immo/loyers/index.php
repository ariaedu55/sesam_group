<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$view = (string) ($data['view'] ?? 'mois');
$month = (string) ($data['month'] ?? gmdate('Y-m'));
$counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
$totals = is_array($data['totals'] ?? null) ? $data['totals'] : [];
$q = (string) ($data['q'] ?? '');
$prev = gmdate('Y-m', (int) strtotime($month . '-01 -1 month'));
$next = gmdate('Y-m', (int) strtotime($month . '-01 +1 month'));
$tabs = [
    'mois' => ['Par mois', null],
    'a-encaisser' => ['À encaisser', $counts['a_encaisser'] ?? 0],
    'retards' => ['En retard', $counts['retards'] ?? 0],
    'partiels' => ['Partiels', $counts['partiels'] ?? 0],
    'payes' => ['Payés', null],
];
$late = (int) ($counts['retards'] ?? 0);
$remaining = (int) ($totals['remaining'] ?? 0);

$heroBackHref = '';
$heroTitle = 'Loyers';
$heroSubtitle = 'Chaque ligne est le loyer d\'un mois — encaissement, relance ou quittance depuis la fiche.';
$heroTone = 'teal';
$heroStatusText = $late > 0 ? $late . ' en retard' : ($remaining > 0 ? Money::format($remaining) . ' à encaisser' : null);
$heroStatusTone = $late > 0 ? 'danger' : 'warning';
$heroBadgesHtml = '';
$heroActions = '';
$heroStats = [
    ['label' => 'Total dû', 'value' => Money::format((int) ($totals['amount'] ?? 0))],
    ['label' => 'Encaissé', 'value' => Money::format((int) ($totals['paid'] ?? 0))],
    ['label' => 'Reste', 'value' => Money::format($remaining), 'alert' => $remaining > 0],
    ['label' => 'Retards', 'value' => (string) $late, 'alert' => $late > 0],
];
$recordFilterPartial = dirname(__DIR__, 3) . '/partials/record-filter-select.php';
$recordFilterFieldId = 'filtre-vue-loyers';
$recordFilterFieldLabel = 'Vue';
$recordFilterFieldName = 'vue';
$recordFilterOptions = [];
foreach ($tabs as $key => [$label, $count]) {
    $optionLabel = $label;
    if ($count !== null && (int) $count > 0) {
        $optionLabel .= ' (' . (int) $count . ')';
    }
    $recordFilterOptions[] = [
        'value' => $key,
        'label' => $optionLabel,
        'selected' => $view === $key,
    ];
}

?>
<section class="record-index btq immo">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <div class="record-index-toolbar">
        <div class="immo-toolbar">
            <?php if ($view === 'mois'): ?>
                <div class="immo-month">
                    <a class="btn btn-secondary btn-sm" href="<?= Html::url('/immo/loyers?mois=' . $prev) ?>" aria-label="Mois précédent">←</a>
                    <strong><?= Html::e(ucfirst(ImmoCatalog::monthLabel($month . '-01'))) ?></strong>
                    <a class="btn btn-secondary btn-sm" href="<?= Html::url('/immo/loyers?mois=' . $next) ?>" aria-label="Mois suivant">→</a>
                </div>
            <?php endif; ?>
            <form
                method="get"
                action="<?= Html::url('/immo/loyers') ?>"
                class="immo-search"
                role="search"
                data-live-filter
                data-live-filter-target="#loyers-results"
                data-live-filter-delay="280"
            >
                <?php require $recordFilterPartial; ?>
                <?php if ($view === 'mois'): ?><input type="hidden" name="mois" value="<?= Html::e($month) ?>"><?php endif; ?>
                <label class="sr-only" for="loyer-q">Rechercher</label>
                <input class="input" id="loyer-q" type="search" name="q" value="<?= Html::e($q) ?>" placeholder="Locataire, logement ou bien" autocomplete="off">
            </form>
        </div>
    </div>

    <div id="loyers-results" class="record-index-results" data-crm-clients-results aria-live="polite">
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
