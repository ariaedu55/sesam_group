<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$view = (string) ($data['view'] ?? 'actifs');
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$tabs = ['actifs' => 'En place', 'retards' => 'Avec impayés', 'anciens' => 'Anciens locataires', 'tous' => 'Tous'];
$shown = count($rows);
$withBalance = 0;
foreach ($rows as $row) {
    if ((int) ($row['balance'] ?? 0) > 0) {
        ++$withBalance;
    }
}

ob_start();
if ($canManage): ?>
    <a class="btn btn-primary" href="<?= Html::url('/immo/locataires/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau locataire</a>
<?php endif;
$heroActions = ob_get_clean() ?: '';

$heroBackHref = '';
$heroTitle = 'Locataires';
$heroSubtitle = 'Coordonnées, logement occupé, loyer et impayés — ouvrez une fiche en un clic.';
$heroTone = 'teal';
$heroStatusText = $withBalance > 0 && $view !== 'retards' ? $withBalance . ' avec impayés' : null;
$heroStatusTone = 'danger';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Affichés', 'value' => (string) $shown],
    ['label' => 'Impayés', 'value' => (string) $withBalance, 'alert' => $withBalance > 0],
    ['label' => 'Vue', 'value' => $tabs[$view] ?? $view],
    ['label' => 'Recherche', 'value' => ($data['q'] ?? '') !== '' ? 'Oui' : '—'],
];
$recordFilterPartial = dirname(__DIR__, 3) . '/partials/record-filter-select.php';
$recordFilterFieldId = 'filtre-vue-locataires';
$recordFilterFieldLabel = 'Vue';
$recordFilterFieldName = 'vue';
$recordFilterOptions = [];
foreach ($tabs as $key => $label) {
    $recordFilterOptions[] = [
        'value' => $key,
        'label' => $label,
        'selected' => $view === $key,
    ];
}

?>
<section class="record-index btq immo">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <div class="record-index-toolbar">
        <form
            method="get"
            action="<?= Html::url('/immo/locataires') ?>"
            class="immo-search"
            role="search"
            data-live-filter
            data-live-filter-target="#locataires-results"
            data-live-filter-delay="280"
        >
            <?php require $recordFilterPartial; ?>
            <label class="sr-only" for="locataire-q">Rechercher</label>
            <input class="input" id="locataire-q" type="search" name="q" value="<?= Html::e((string) ($data['q'] ?? '')) ?>" placeholder="Nom, téléphone ou e-mail" autocomplete="off">
        </form>
    </div>

    <div id="locataires-results" class="record-index-results" data-crm-clients-results aria-live="polite">
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
