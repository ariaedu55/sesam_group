<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$agencyTab = is_string($agencyTab ?? null) ? $agencyTab : '';
$tabs = [
    'demandes' => ['/agence/demandes', 'Demandes de devis'],
    'dashboard' => ['/agence', 'Synthèse'],
    'prospects' => ['/agence/prospects', 'Prospects'],
    'projets' => ['/agence/projets', 'Projets'],
    'factures' => ['/agence/factures', 'Factures'],
];

?>
<nav class="agency-tabs" aria-label="Agence">
    <?php foreach ($tabs as $key => [$path, $label]): ?>
        <a href="<?= Html::url($path) ?>"<?= $agencyTab === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?></a>
    <?php endforeach; ?>
</nav>
