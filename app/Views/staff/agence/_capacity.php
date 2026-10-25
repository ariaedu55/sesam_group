<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\AgencyProject;

$capacity = is_array($capacity ?? null) ? $capacity : ['month' => gmdate('Y-m'), 'used' => 0, 'limit' => 5, 'projects' => []];
$used = (int) $capacity['used'];
$limit = max(1, (int) $capacity['limit']);
$started = array_values(array_filter(is_array($capacity['projects']) ? $capacity['projects'] : [], static fn ($p): bool => $p instanceof AgencyProject));
$full = $used >= $limit;

?>
<article class="dash-card agency-capacity<?= $full ? ' is-full' : '' ?>" aria-labelledby="capacity-title">
    <div class="dash-card-head">
        <h2 id="capacity-title">Capacité · <?= Html::e($monthLabel((string) $capacity['month'])) ?></h2>
        <span class="chip <?= $full ? 'chip-off' : 'chip-ok' ?>"><?= $full ? 'Complet' : ($limit - $used) . ' place' . ($limit - $used > 1 ? 's' : '') . ' libre' . ($limit - $used > 1 ? 's' : '') ?></span>
    </div>
    <p class="agency-capacity-figure"><strong><?= $used ?></strong> / <?= $limit ?> <span>projets web démarrés ce mois</span></p>
    <ol class="agency-slots" aria-label="<?= $used ?> projets démarrés sur <?= $limit ?>">
        <?php for ($slot = 0; $slot < $limit; $slot++): ?>
            <?php $project = $started[$slot] ?? null; ?>
            <li class="<?= $project !== null ? 'is-used' : '' ?>">
                <?php if ($project !== null): ?>
                    <a href="<?= Html::url('/agence/projets/' . $project->id()) ?>" title="<?= Html::e($project->title()) ?>">
                        <span class="agency-slot-index"><?= $slot + 1 ?></span>
                        <span class="agency-slot-name"><?= Html::e($project->clientName()) ?></span>
                    </a>
                <?php else: ?>
                    <span class="agency-slot-index"><?= $slot + 1 ?></span>
                    <span class="agency-slot-name">Libre</span>
                <?php endif; ?>
            </li>
        <?php endfor; ?>
    </ol>
</article>
