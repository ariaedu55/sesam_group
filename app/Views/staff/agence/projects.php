<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\AgencyProject;
use Sesam\Services\Agency\AgencyCatalog;

require __DIR__ . '/_helpers.php';

$projects = is_array($projects ?? null) ? $projects : [];
$counts = is_array($counts ?? null) ? $counts : [];
$statusFilter = is_string($statusFilter ?? null) ? $statusFilter : '';
$base = Html::url('/agence/projets');
$statusChip = ['planned' => 'chip-muted', 'in_progress' => 'chip-warn', 'delivered' => 'chip-ok'];
$agencyTab = 'projets';

?>
<section class="console agency">
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">SESAM Digital · agence</p>
            <h1>Projets web</h1>
            <p>Un devis accepté crée le projet. Au démarrage, il prend une des <?= AgencyCatalog::MONTHLY_CAPACITY ?> places du mois.</p>
        </div>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="agency-grid agency-grid-reverse">
        <div class="dash-card console-list">
            <div class="agency-segment" role="group" aria-label="Statut">
                <a href="<?= $base ?>"<?= $statusFilter === '' ? ' aria-current="true"' : '' ?>>Tous <span><?= array_sum($counts) ?></span></a>
                <?php foreach (AgencyCatalog::PROJECT_STATUSES as $value => $label): ?>
                    <a href="<?= $base ?>?statut=<?= Html::e($value) ?>"<?= $statusFilter === $value ? ' aria-current="true"' : '' ?>><?= Html::e($label) ?> <span><?= (int) ($counts[$value] ?? 0) ?></span></a>
                <?php endforeach; ?>
            </div>

            <?php if ($projects === []): ?>
                <div class="dash-empty console-empty">
                    <p><?= $statusFilter === '' ? 'Aucun projet. Faites accepter un devis depuis une fiche prospect.' : 'Aucun projet avec ce statut.' ?></p>
                </div>
            <?php else: ?>
                <ul class="console-rows agency-project-rows">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        if (!$project instanceof AgencyProject) { continue; }
                        $paidShare = $project->amount() > 0 ? min(100, (int) round($project->paidAmount() * 100 / $project->amount())) : 0;
                        ?>
                        <li>
                            <a class="console-row" href="<?= Html::url('/agence/projets/' . $project->id()) ?>">
                                <span class="console-avatar" aria-hidden="true"><?= $initials($project->clientName()) ?></span>
                                <span class="console-row-main">
                                    <strong><?= Html::e($project->title()) ?></strong>
                                    <span class="muted"><?= $money($project->amount()) ?> · <?= $paidShare ?>&nbsp;% encaissé</span>
                                    <span class="console-bar agency-row-bar" aria-hidden="true"><span style="width: <?= $paidShare ?>%"></span></span>
                                </span>
                                <span class="console-row-chips">
                                    <span class="chip <?= $statusChip[$project->status()] ?? 'chip-muted' ?>"><?= Html::e($project->statusLabel()) ?></span>
                                    <?php if ($project->startMonth() !== null): ?>
                                        <span class="chip chip-muted"><?= Html::e($monthLabel($project->startMonth())) ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="agency-side">
            <?php require __DIR__ . '/_capacity.php'; ?>
        </div>
    </div>
</section>
