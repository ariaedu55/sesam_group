<?php

declare(strict_types=1);

use Sesam\Core\Boutique\OrderStatus;
use Sesam\Helpers\Html;

$page = is_array($page ?? null) ? $page : [];
$counts = is_array($page['counts'] ?? null) ? $page['counts'] : [];
$status = is_string($status ?? null) ? $status : '';
$query = is_string($query ?? null) ? $query : '';
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$all = array_sum(array_map('intval', $counts));
$newCount = (int) ($counts[OrderStatus::NEW] ?? 0);
$recordFilterPartial = dirname(__DIR__, 3) . '/partials/record-filter-select.php';
$recordFilterFieldId = 'filtre-statut-commandes';
$recordFilterFieldLabel = 'Étape';
$recordFilterFieldName = 'statut';
$recordFilterOptions = [
    ['value' => '', 'label' => 'Toutes (' . $all . ')', 'selected' => $status === ''],
];
foreach (OrderStatus::ALL as $key) {
    $recordFilterOptions[] = [
        'value' => $key,
        'label' => OrderStatus::label($key) . ' (' . (int) ($counts[$key] ?? 0) . ')',
        'selected' => $status === $key,
    ];
}

ob_start();
if ($canManage): ?>
    <a class="btn btn-primary" href="<?= Html::url('/boutique/commandes/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Noter une vente</a>
<?php endif;
$heroActions = ob_get_clean() ?: '';

$heroBackHref = '';
$heroBackLabel = '';
$heroTitle = 'Commandes';
$heroSubtitle = 'Suivez les ventes en ligne et les saisies manuelles. Chaque commande ouvre une fiche détaillée.';
$heroTone = 'electric';
$heroInitials = null;
$heroStatusText = $newCount > 0 ? $newCount . ' à traiter' : null;
$heroStatusTone = 'warning';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Total', 'value' => (string) $all],
    ['label' => 'Nouvelles', 'value' => (string) $newCount, 'alert' => $newCount > 0],
    ['label' => 'Confirmées', 'value' => (string) ((int) ($counts[OrderStatus::CONFIRMED] ?? 0))],
    ['label' => 'Livrées', 'value' => (string) ((int) ($counts[OrderStatus::DELIVERED] ?? 0))],
];

?>
<section class="record-index btq">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <div class="record-index-toolbar">
        <form
            class="btq-filters"
            method="get"
            action="<?= Html::url('/boutique/commandes') ?>"
            role="search"
            data-live-filter
            data-live-filter-target="#commandes-results"
            data-live-filter-delay="280"
        >
            <?php require $recordFilterPartial; ?>
            <label class="sr-only" for="q">Rechercher</label>
            <input class="input" id="q" name="q" type="search" placeholder="Nom, téléphone ou numéro de commande" value="<?= Html::e($query) ?>" autocomplete="off">
            <button class="btn btn-secondary" type="submit">Chercher</button>
        </form>
    </div>

    <div id="commandes-results" class="record-index-results" data-crm-clients-results aria-live="polite">
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
