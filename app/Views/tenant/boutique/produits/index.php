<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$page = is_array($page ?? null) ? $page : [];
$summary = is_array($page['summary'] ?? null) ? $page['summary'] : [];
$categories = is_array($page['categories'] ?? null) ? $page['categories'] : [];
$query = is_string($query ?? null) ? $query : '';
$category = is_string($category ?? null) ? $category : '';
$filter = is_string($filter ?? null) ? $filter : '';
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$tabs = [
    '' => ['Tous', (int) ($summary['total'] ?? 0)],
    'faible' => ['Bientôt épuisés', (int) ($summary['low'] ?? 0)],
    'rupture' => ['Épuisés', (int) ($summary['out'] ?? 0)],
    'masques' => ['Masqués', (int) ($summary['total'] ?? 0) - (int) ($summary['published'] ?? 0)],
];
$low = (int) ($summary['low'] ?? 0);
$out = (int) ($summary['out'] ?? 0);

ob_start();
if ($canManage): ?>
    <a class="btn btn-primary" href="<?= Html::url('/boutique/produits/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Ajouter un produit</a>
<?php endif;
$heroActions = ob_get_clean() ?: '';

$heroBackHref = '';
$heroTitle = 'Mes produits';
$heroSubtitle = (string) ($summary['published'] ?? 0) . ' produit(s) visibles dans votre boutique en ligne.';
$heroTone = 'amber';
$heroStatusText = $out > 0 ? $out . ' épuisé(s)' : ($low > 0 ? $low . ' stock bas' : null);
$heroStatusTone = $out > 0 ? 'danger' : 'warning';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Catalogue', 'value' => (string) ((int) ($summary['total'] ?? 0))],
    ['label' => 'En ligne', 'value' => (string) ((int) ($summary['published'] ?? 0))],
    ['label' => 'Stock bas', 'value' => (string) $low, 'alert' => $low > 0],
    ['label' => 'Épuisés', 'value' => (string) $out, 'alert' => $out > 0],
];
$recordFilterPartial = dirname(__DIR__, 3) . '/partials/record-filter-select.php';
$recordFilterFieldId = 'filtre-produits';
$recordFilterFieldLabel = 'Affichage';
$recordFilterFieldName = 'filtre';
$recordFilterOptions = [];
foreach ($tabs as $key => [$label, $count]) {
    $recordFilterOptions[] = [
        'value' => $key,
        'label' => $label . ' (' . max(0, $count) . ')',
        'selected' => $filter === $key,
    ];
}

?>
<section class="record-index btq">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <div class="record-index-toolbar">
        <form
            class="btq-filters"
            method="get"
            action="<?= Html::url('/boutique/produits') ?>"
            role="search"
            data-live-filter
            data-live-filter-target="#produits-results"
            data-live-filter-delay="280"
        >
            <?php require $recordFilterPartial; ?>
            <label class="sr-only" for="q">Rechercher</label>
            <input class="input" id="q" name="q" type="search" placeholder="Rechercher un produit" value="<?= Html::e($query) ?>" autocomplete="off">
            <?php if ($categories !== []): ?>
                <label class="sr-only" for="categorie">Catégorie</label>
                <select class="input" id="categorie" name="categorie">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $item): ?>
                        <option value="<?= Html::e((string) $item) ?>"<?= $category === $item ? ' selected' : '' ?>><?= Html::e((string) $item) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button class="btn btn-secondary" type="submit">Chercher</button>
        </form>
    </div>

    <div id="produits-results" class="record-index-results" data-crm-clients-results aria-live="polite">
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
