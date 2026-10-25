<?php

declare(strict_types=1);

use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationAppointment;
use Sesam\Models\ReservationTeamMember;
use Sesam\Services\Reservation\BookingService;

$agenda = is_array($agenda ?? null) ? $agenda : [];
$canManage = (bool) ($canManage ?? false);
$view = (string) ($agenda['view'] ?? 'jour');
$date = (string) ($agenda['date'] ?? gmdate('Y-m-d'));
$days = is_array($agenda['days'] ?? null) ? $agenda['days'] : [$date];
$team = is_array($agenda['team'] ?? null) ? $agenda['team'] : [];
$byDay = is_array($agenda['byDay'] ?? null) ? $agenda['byDay'] : [];
$staffId = $agenda['staffId'] ?? null;
$open = (int) ($agenda['open'] ?? 8);
$close = (int) ($agenda['close'] ?? 20);
$closures = is_array($agenda['closures'] ?? null) ? $agenda['closures'] : [];
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$pxPerHour = 64;
$months = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$dayLabel = static function (string $day) use ($months): string {
    $ts = (int) strtotime($day . ' 00:00:00 UTC');

    return BookingService::weekdayLabel((int) gmdate('N', $ts)) . ' ' . (int) gmdate('j', $ts) . ' ' . $months[(int) gmdate('n', $ts)];
};
$link = static function (array $params) use ($view, $date, $staffId): string {
    $query = array_filter($params + ['date' => $date, 'vue' => $view, 'personne' => $staffId ? (string) $staffId : '']);

    return '/reservation/agenda?' . http_build_query($query);
};
$closedOn = static function (string $day, ?int $who) use ($closures): ?string {
    foreach ($closures as $closure) {
        if ($closure['starts_on'] <= $day && $closure['ends_on'] >= $day && ($closure['staff_id'] === null || $closure['staff_id'] === $who)) {
            return $closure['staff_id'] === null ? ($closure['reason'] !== '' ? $closure['reason'] : 'Salon fermé') : 'Absent' . ($closure['reason'] !== '' ? ' — ' . $closure['reason'] : '');
        }
    }

    return null;
};

// Colonnes : une par personne (jour) ou une par jour (semaine).
$columns = [];
if ($view === 'jour') {
    $items = $byDay[$date] ?? [];
    $people = $staffId !== null
        ? array_values(array_filter($team, static fn (ReservationTeamMember $m): bool => $m->id() === $staffId))
        : $team;
    foreach ($people as $member) {
        $columns[] = ['title' => $member->name(), 'sub' => $member->roleLabel(), 'items' => array_values(array_filter($items, static fn (ReservationAppointment $a): bool => $a->staffId() === $member->id())), 'closed' => $closedOn($date, $member->id()), 'day' => $date, 'staff' => $member->id()];
    }
    $unassigned = array_values(array_filter($items, static fn (ReservationAppointment $a): bool => $a->staffId() === null));
    if ($columns === [] || $unassigned !== []) {
        $columns[] = ['title' => $team === [] ? 'Salon' : 'Sans attribution', 'sub' => '', 'items' => $team === [] ? $items : $unassigned, 'closed' => $closedOn($date, null), 'day' => $date, 'staff' => null];
    }
} else {
    foreach ($days as $day) {
        $columns[] = ['title' => $dayLabel($day), 'sub' => '', 'items' => $byDay[$day] ?? [], 'closed' => $closedOn($day, $staffId), 'day' => $day, 'staff' => $staffId];
    }
}
$height = ($close - $open) * $pxPerHour;

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Agenda</h1>
            <p class="muted"><?= $view === 'jour' ? Html::e($dayLabel($date)) : 'Semaine du ' . Html::e($dayLabel($days[0])) ?></p>
        </div>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/reservation/rendez-vous/nouveau?date=' . $date) ?>"><?php $name = 'plus'; include $icon; ?>Nouveau rendez-vous</a>
        <?php endif; ?>
    </header>

    <div class="ag-bar">
        <div class="btq-actions">
            <a class="btn btn-secondary btn-sm" href="<?= Html::url($link(['date' => (string) $agenda['prev']])) ?>" aria-label="Période précédente">←</a>
            <a class="btn btn-secondary btn-sm" href="<?= Html::url($link(['date' => gmdate('Y-m-d')])) ?>">Aujourd'hui</a>
            <a class="btn btn-secondary btn-sm" href="<?= Html::url($link(['date' => (string) $agenda['next']])) ?>" aria-label="Période suivante">→</a>
        </div>
        <nav class="btq-tabs" aria-label="Vue">
            <a href="<?= Html::url($link(['vue' => 'jour'])) ?>"<?= $view === 'jour' ? ' aria-current="page"' : '' ?>>Jour</a>
            <a href="<?= Html::url($link(['vue' => 'semaine'])) ?>"<?= $view === 'semaine' ? ' aria-current="page"' : '' ?>>Semaine</a>
        </nav>
        <?php if ($team !== []): ?>
            <form class="ag-filter" method="get" action="<?= Html::url('/reservation/agenda') ?>">
                <input type="hidden" name="date" value="<?= Html::e($date) ?>">
                <input type="hidden" name="vue" value="<?= Html::e($view) ?>">
                <label class="sr-only" for="ag-personne">Personne</label>
                <select class="input" id="ag-personne" name="personne" data-autosubmit>
                    <option value="">Toute l'équipe</option>
                    <?php foreach ($team as $member): ?>
                        <option value="<?= $member->id() ?>"<?= $staffId === $member->id() ? ' selected' : '' ?>><?= Html::e($member->name()) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-secondary btn-sm" type="submit">Filtrer</button></noscript>
            </form>
        <?php endif; ?>
    </div>

    <div class="ag" style="--ag-cols: <?= count($columns) ?>; --ag-h: <?= $height ?>px;">
        <div class="ag-head">
            <span></span>
            <?php foreach ($columns as $column): ?>
                <span class="ag-col-title<?= $column['day'] === gmdate('Y-m-d') && $view === 'semaine' ? ' is-today' : '' ?>">
                    <?php if ($view === 'semaine'): ?>
                        <a href="<?= Html::url($link(['vue' => 'jour', 'date' => $column['day']])) ?>"><?= Html::e($column['title']) ?></a>
                    <?php else: ?>
                        <?= Html::e($column['title']) ?>
                    <?php endif; ?>
                    <?php if ($column['sub'] !== ''): ?><small><?= Html::e($column['sub']) ?></small><?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="ag-body">
            <div class="ag-hours" aria-hidden="true">
                <?php for ($h = $open; $h < $close; $h++): ?>
                    <span><?= sprintf('%02d:00', $h) ?></span>
                <?php endfor; ?>
            </div>
            <?php foreach ($columns as $column): ?>
                <div class="ag-col<?= $column['closed'] !== null ? ' is-closed' : '' ?>">
                    <?php if ($column['closed'] !== null): ?>
                        <p class="ag-closed"><?= Html::e($column['closed']) ?></p>
                    <?php endif; ?>
                    <?php if ($canManage && $column['closed'] === null): ?>
                        <a class="ag-add" href="<?= Html::url('/reservation/rendez-vous/nouveau?date=' . $column['day'] . ($column['staff'] ? '&personne=' . $column['staff'] : '')) ?>" aria-label="Ajouter un rendez-vous le <?= Html::e($dayLabel($column['day'])) ?>">+</a>
                    <?php endif; ?>
                    <?php foreach ($column['items'] as $item): ?>
                        <?php
                        if (!$item instanceof ReservationAppointment) {
                            continue;
                        }
                        $start = (int) substr($item->startsAt(), 11, 2) * 60 + (int) substr($item->startsAt(), 14, 2);
                        $top = (int) round(($start - $open * 60) * $pxPerHour / 60);
                        $h = max(26, (int) round($item->durationMinutes() * $pxPerHour / 60) - 3);
                        ?>
                        <a class="ag-item is-<?= Html::e($item->status()) ?>" style="top: <?= $top ?>px; height: <?= $h ?>px" href="<?= Html::url('/reservation/rendez-vous/' . $item->id()) ?>">
                            <strong><?= Html::e(substr($item->startsAt(), 11, 5)) ?> · <?= Html::e($item->clientName()) ?></strong>
                            <span><?= Html::e($item->serviceName()) ?><?= $view === 'semaine' && $item->staffName() !== '' ? ' · ' . Html::e($item->staffName()) : '' ?></span>
                            <em><?= Html::e(AppointmentStatus::label($item->status())) ?></em>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <p class="ag-legend">
        <?php foreach ([AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED, AppointmentStatus::COMPLETED, AppointmentStatus::NO_SHOW, AppointmentStatus::CANCELLED] as $status): ?>
            <span class="ag-dot is-<?= Html::e($status) ?>"><?= Html::e(AppointmentStatus::label($status)) ?></span>
        <?php endforeach; ?>
    </p>
</section>
