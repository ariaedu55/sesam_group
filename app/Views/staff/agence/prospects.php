<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\AgencyProspect;
use Sesam\Services\Agency\AgencyCatalog;

require __DIR__ . '/_helpers.php';

$prospects = is_array($prospects ?? null) ? $prospects : [];
$stageCounts = is_array($stageCounts ?? null) ? $stageCounts : [];
$requests = is_array($requests ?? null) ? $requests : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'stage' => '', 'clients' => false];
$today = is_string($today ?? null) ? $today : gmdate('Y-m-d');
$canProspects = (bool) ($canProspects ?? false);
$activities = AgencyCatalog::activities();
$filtered = $filters['q'] !== '' || $filters['stage'] !== '';
$base = Html::url('/agence/prospects');
$clientCount = array_sum(array_map(static fn (string $stage): int => (int) ($stageCounts[$stage] ?? 0), AgencyCatalog::CLIENT_STAGES));
$agencyTab = 'prospects';

?>
<section class="console agency">
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">SESAM Digital · agence</p>
            <h1><?= $filters['clients'] ? 'Clients' : 'Prospects' ?></h1>
            <p><?= $filters['clients'] ? 'Les prospects gagnés : projets à démarrer, en cours ou livrés.' : 'Chaque contact avance d\'étape en étape jusqu\'au devis signé.' ?></p>
        </div>
        <?php if ($canProspects): ?>
            <div class="dash-hero-actions">
                <a class="dash-cta dash-cta-solid" href="<?= Html::url('/agence/prospects/nouveau') ?>" data-modal="Agence">Nouveau prospect</a>
            </div>
        <?php endif; ?>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="agency-segment" role="group" aria-label="Affichage">
        <a href="<?= $base ?>"<?= !$filters['clients'] ? ' aria-current="true"' : '' ?>>Tous <span><?= array_sum($stageCounts) ?></span></a>
        <a href="<?= $base ?>?vue=clients"<?= $filters['clients'] ? ' aria-current="true"' : '' ?>>Clients <span><?= $clientCount ?></span></a>
    </div>

    <div class="console-layout agency-list-layout">
        <div class="dash-card console-list">
            <form class="console-filters agency-filters" method="get" action="<?= $base ?>" role="search">
                <?php if ($filters['clients']): ?><input type="hidden" name="vue" value="clients"><?php endif; ?>
                <div class="field console-filter-q">
                    <label for="q"><span>Rechercher</span></label>
                    <input class="input" id="q" name="q" type="search" maxlength="80" placeholder="Nom, commerce, téléphone ou e-mail" value="<?= Html::e($filters['q']) ?>">
                </div>
                <div class="field">
                    <label for="etape"><span>Étape</span></label>
                    <select class="select" id="etape" name="etape">
                        <option value="">Toutes</option>
                        <?php foreach (AgencyCatalog::STAGES as $value => $label): ?>
                            <option value="<?= Html::e($value) ?>"<?= $filters['stage'] === $value ? ' selected' : '' ?>><?= Html::e($label) ?> (<?= (int) ($stageCounts[$value] ?? 0) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="console-filter-actions">
                    <button class="btn btn-primary" type="submit">Filtrer</button>
                    <?php if ($filtered): ?>
                        <a class="btn btn-ghost" href="<?= $base ?><?= $filters['clients'] ? '?vue=clients' : '' ?>">Réinitialiser</a>
                    <?php endif; ?>
                </div>
            </form>

            <p class="muted console-count"><?= count($prospects) ?> fiche<?= count($prospects) > 1 ? 's' : '' ?></p>

            <?php if ($prospects === []): ?>
                <div class="dash-empty console-empty">
                    <p><?= $filtered ? 'Aucune fiche ne correspond à ces filtres.' : ($filters['clients'] ? 'Aucun client pour l\'instant : un devis accepté transforme le prospect en client.' : 'Aucun prospect. Ajoutez-en un ou suivez une demande du site.') ?></p>
                    <?php if ($canProspects && !$filtered && !$filters['clients']): ?>
                        <a class="btn btn-primary" href="<?= Html::url('/agence/prospects/nouveau') ?>" data-modal="Agence">Ajouter un prospect</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <ul class="console-rows">
                    <?php foreach ($prospects as $prospect): ?>
                        <?php
                        if (!$prospect instanceof AgencyProspect) { continue; }
                        $next = $prospect->nextActionOn();
                        $late = $next !== null && $next <= $today && !in_array($prospect->stage(), ['livre', 'perdu'], true);
                        $contact = $prospect->phone() !== '' ? $prospect->phone() : $prospect->email();
                        ?>
                        <li>
                            <a class="console-row" href="<?= Html::url('/agence/prospects/' . $prospect->id()) ?>">
                                <span class="console-avatar" aria-hidden="true"><?= $initials($prospect->displayName()) ?></span>
                                <span class="console-row-main">
                                    <strong><?= Html::e($prospect->displayName()) ?></strong>
                                    <span class="muted"><?= Html::e($prospect->activityLabel()) ?><?= $prospect->businessName() !== '' ? ' · ' . Html::e($prospect->fullName()) : '' ?></span>
                                    <?php if ($contact !== ''): ?><span class="muted console-row-owner"><?= Html::e($contact) ?></span><?php endif; ?>
                                </span>
                                <span class="console-row-chips">
                                    <span class="chip <?= $stageChip($prospect->stage()) ?>"><?= Html::e($prospect->stageLabel()) ?></span>
                                    <?php if ($prospect->source() === 'site'): ?><span class="chip chip-muted">Site</span><?php endif; ?>
                                    <?php if ($next !== null && !in_array($prospect->stage(), ['livre', 'perdu'], true)): ?>
                                        <span class="chip <?= $late ? 'chip-warn' : 'chip-muted' ?>">Relance <?= $date($next) ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <aside class="dash-card agency-requests-card" id="demandes" aria-labelledby="requests-title">
            <div class="dash-card-head">
                <h2 id="requests-title">Demandes du site</h2>
                <span class="chip <?= $requests !== [] ? 'chip-warn' : 'chip-muted' ?>"><?= count($requests) ?></span>
            </div>
            <?php if ($requests === []): ?>
                <p class="muted dash-empty">Aucune demande en attente.</p>
            <?php else: ?>
                <ul class="agency-requests agency-requests-full">
                    <?php foreach ($requests as $request): ?>
                        <li>
                            <div>
                                <strong><?= Html::e((string) $request['full_name']) ?></strong>
                                <span class="muted"><?= Html::e((string) ($activities[(string) $request['activity']] ?? $request['activity'])) ?> · <?= $date((string) $request['created_at']) ?></span>
                                <p class="agency-request-message"><?= Html::e(mb_strimwidth((string) $request['message'], 0, 160, '…')) ?></p>
                            </div>
                            <?php if ($canProspects): ?>
                                <form method="post" action="<?= Html::url('/agence/demandes/' . (int) $request['id'] . '/convertir') ?>">
                                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                    <button class="btn btn-secondary btn-sm" type="submit">Suivre comme prospect</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </div>
</section>
