<?php

declare(strict_types=1);

use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationTeamMember;

$list = is_array($list ?? null) ? $list : [];
$counts = is_array($list['counts'] ?? null) ? $list['counts'] : [];
$filters = is_array($filters ?? null) ? $filters : [];
$team = is_array($team ?? null) ? $team : [];
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$status = (string) ($filters['status'] ?? '');
$period = (string) ($filters['period'] ?? 'a-venir');
$total = (int) ($list['total'] ?? 0);
$pending = (int) ($counts[AppointmentStatus::PENDING] ?? 0);
ob_start();
if ($canManage): ?>
    <a class="btn btn-primary" href="<?= Html::url('/reservation/rendez-vous/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau rendez-vous</a>
<?php endif;
$heroActions = ob_get_clean() ?: '';

$heroBackHref = '';
$heroTitle = 'Rendez-vous';
$heroSubtitle = 'Agenda, confirmations et suivis clientes — cliquez sur une ligne pour ouvrir la fiche.';
$heroTone = 'coral';
$heroStatusText = $pending > 0 ? $pending . ' en attente' : null;
$heroStatusTone = 'warning';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Ce filtre', 'value' => (string) $total],
    ['label' => 'En attente', 'value' => (string) $pending, 'alert' => $pending > 0],
    ['label' => 'Confirmés', 'value' => (string) ((int) ($counts[AppointmentStatus::CONFIRMED] ?? 0))],
    ['label' => 'Réalisés', 'value' => (string) ((int) ($counts[AppointmentStatus::COMPLETED] ?? 0))],
];
$recordFilterPartial = dirname(__DIR__, 3) . '/partials/record-filter-select.php';
$recordFilterFieldId = 'filtre-periode-rdv';
$recordFilterFieldLabel = 'Période';
$recordFilterFieldName = 'periode';
$recordFilterOptions = [];
foreach (['aujourdhui' => 'Aujourd\'hui', 'semaine' => '7 prochains jours', 'a-venir' => 'À venir', 'passes' => 'Passés', 'tous' => 'Tous'] as $key => $label) {
    $recordFilterOptions[] = [
        'value' => $key,
        'label' => $label,
        'selected' => $period === $key,
    ];
}

?>
<section class="record-index btq">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <div class="record-index-toolbar">
        <form
            class="btq-filters"
            method="get"
            action="<?= Html::url('/reservation/rendez-vous') ?>"
            role="search"
            data-live-filter
            data-live-filter-target="#rdv-results"
            data-live-filter-delay="280"
        >
            <?php require $recordFilterPartial; ?>
            <label class="sr-only" for="q">Rechercher</label>
            <input class="input" id="q" name="q" type="search" placeholder="Nom ou téléphone de la cliente" value="<?= Html::e((string) ($filters['q'] ?? '')) ?>" autocomplete="off">
            <label class="sr-only" for="statut">Statut</label>
            <select class="input" id="statut" name="statut">
                <option value="">Tous les statuts</option>
                <?php foreach (AppointmentStatus::all() as $key): ?>
                    <option value="<?= Html::e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= Html::e(AppointmentStatus::label($key)) ?> (<?= Html::e((string) ($counts[$key] ?? 0)) ?>)</option>
                <?php endforeach; ?>
            </select>
            <?php if ($team !== []): ?>
                <label class="sr-only" for="personne">Personne</label>
                <select class="input" id="personne" name="personne">
                    <option value="">Toute l'équipe</option>
                    <?php foreach ($team as $member): ?>
                        <?php if (!$member instanceof ReservationTeamMember) { continue; } ?>
                        <option value="<?= $member->id() ?>"<?= (int) ($filters['staff_id'] ?? 0) === $member->id() ? ' selected' : '' ?>><?= Html::e($member->name()) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button class="btn btn-secondary" type="submit">Chercher</button>
        </form>
    </div>

    <div id="rdv-results" class="record-index-results" data-crm-clients-results aria-live="polite">
        <?php require __DIR__ . '/_results.php'; ?>
    </div>
</section>
