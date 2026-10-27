<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ClientTag;

$list = is_array($list ?? null) ? $list : [
    'clients' => [],
    'total' => 0,
    'page' => 1,
    'pages' => 1,
    'query' => '',
    'status' => '',
    'tagId' => null,
    'tags' => [],
];
$query = is_string($list['query'] ?? null) ? $list['query'] : '';
$status = is_string($list['status'] ?? null) ? $list['status'] : '';
$tagId = $list['tagId'] ?? null;
$tags = is_array($list['tags'] ?? null) ? $list['tags'] : [];
$canCreate = (bool) ($canCreate ?? false);
$canExport = (bool) ($canExport ?? false);
$total = (int) ($list['total'] ?? 0);
$pageNum = (int) ($list['page'] ?? 1);
$pageCount = (int) ($list['pages'] ?? 1);

$queryString = static function (array $extra = []) use ($query, $status, $tagId): string {
    $params = array_filter([
        'q' => $query !== '' ? $query : null,
        'status' => $status !== '' ? $status : null,
        'tag' => $tagId !== null ? (string) $tagId : null,
    ] + $extra, static fn ($value) => $value !== null && $value !== '');

    return $params === [] ? '' : '?' . http_build_query($params);
};

ob_start();
?>
<div class="row">
    <?php if ($canExport): ?>
        <a class="btn btn-secondary" href="<?= Html::url('/clients/export' . $queryString()) ?>">Exporter CSV</a>
    <?php endif; ?>
    <?php if ($canCreate): ?>
        <a class="btn btn-primary" href="<?= Html::url('/clients/nouveau') ?>" data-modal="Clients">Nouveau client</a>
    <?php endif; ?>
</div>
<?php
$heroActions = ob_get_clean() ?: '';

$heroBackHref = '';
$heroTitle = 'Clients';
$heroSubtitle = 'Fiches contact, tags et historique — cliquez sur une ligne pour ouvrir le dossier.';
$heroTone = 'blue';
$heroStatusText = $status === 'archived' ? 'Vue archivés' : ($query !== '' ? 'Recherche active' : null);
$heroStatusTone = 'muted';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Résultats', 'value' => (string) $total],
    ['label' => 'Page', 'value' => $pageCount > 1 ? $pageNum . ' / ' . $pageCount : '1'],
    ['label' => 'Tags', 'value' => (string) count($tags)],
    ['label' => 'Filtre', 'value' => $status === 'archived' ? 'Archivés' : ($status === 'active' ? 'Actifs' : 'Tous')],
];

?>
<section class="record-index">
    <?php require dirname(__DIR__, 2) . '/partials/record-hero.php'; ?>

    <div class="record-index-toolbar">
        <form
            class="row staff-search crm-filters"
            method="get"
            action="<?= Html::url('/clients') ?>"
            data-live-filter
            data-live-filter-target="#clients-results"
            data-live-filter-delay="280"
        >
            <div class="field">
                <label for="q"><span>Recherche</span></label>
                <input class="input" id="q" name="q" type="search" maxlength="120" value="<?= Html::e($query) ?>" placeholder="Nom, e-mail, téléphone" autocomplete="off">
            </div>
            <div class="field">
                <label for="status"><span>Statut</span></label>
                <select class="input" id="status" name="status">
                    <option value="">Tous</option>
                    <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Actifs</option>
                    <option value="archived"<?= $status === 'archived' ? ' selected' : '' ?>>Archivés</option>
                </select>
            </div>
            <div class="field">
                <label for="tag"><span>Tag</span></label>
                <select class="input" id="tag" name="tag">
                    <option value="">Tous</option>
                    <?php foreach ($tags as $tag): ?>
                        <?php if (!$tag instanceof ClientTag) { continue; } ?>
                        <option value="<?= Html::e((string) $tag->id()) ?>"<?= (int) $tagId === $tag->id() ? ' selected' : '' ?>><?= Html::e($tag->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-secondary" type="submit">Filtrer</button>
            <noscript><p class="muted crm-filters-hint">Les filtres s'appliquent en cliquant sur Filtrer.</p></noscript>
        </form>
    </div>

    <div id="clients-results" class="record-index-results" data-crm-clients-results aria-live="polite">
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
