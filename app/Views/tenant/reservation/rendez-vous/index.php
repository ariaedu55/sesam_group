<?php

declare(strict_types=1);

use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationAppointment;
use Sesam\Models\ReservationTeamMember;
use Sesam\Services\Reservation\BookingService;

$list = is_array($list ?? null) ? $list : [];
$appointments = is_array($list['appointments'] ?? null) ? $list['appointments'] : [];
$counts = is_array($list['counts'] ?? null) ? $list['counts'] : [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$filters = is_array($filters ?? null) ? $filters : [];
$team = is_array($team ?? null) ? $team : [];
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$status = (string) ($filters['status'] ?? '');
$period = (string) ($filters['period'] ?? 'a-venir');
$link = static function (array $params) use ($filters): string {
    $query = array_filter($params + [
        'statut' => (string) ($filters['status'] ?? ''),
        'periode' => (string) ($filters['period'] ?? ''),
        'q' => (string) ($filters['q'] ?? ''),
        'personne' => (int) ($filters['staff_id'] ?? 0) > 0 ? (string) $filters['staff_id'] : '',
    ], static fn ($v): bool => $v !== '' && $v !== null);

    return '/reservation/rendez-vous' . ($query !== [] ? '?' . http_build_query($query) : '');
};
$days = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Rendez-vous</h1>
            <p class="muted"><?= Html::e((string) ($list['total'] ?? 0)) ?> rendez-vous pour ce filtre.</p>
        </div>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/reservation/rendez-vous/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau rendez-vous</a>
        <?php endif; ?>
    </header>

    <nav class="btq-tabs" aria-label="Période">
        <?php foreach (['aujourdhui' => 'Aujourd\'hui', 'semaine' => '7 prochains jours', 'a-venir' => 'À venir', 'passes' => 'Passés', 'tous' => 'Tous'] as $key => $label): ?>
            <a href="<?= Html::url($link(['periode' => $key, 'page' => ''])) ?>"<?= $period === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <form class="btq-filters" method="get" action="<?= Html::url('/reservation/rendez-vous') ?>" role="search">
        <input type="hidden" name="periode" value="<?= Html::e($period) ?>">
        <label class="sr-only" for="q">Rechercher</label>
        <input class="input" id="q" name="q" type="search" placeholder="Nom ou téléphone de la cliente" value="<?= Html::e((string) ($filters['q'] ?? '')) ?>">
        <label class="sr-only" for="statut">Statut</label>
        <select class="input" id="statut" name="statut" data-autosubmit>
            <option value="">Tous les statuts</option>
            <?php foreach (AppointmentStatus::all() as $key): ?>
                <option value="<?= Html::e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= Html::e(AppointmentStatus::label($key)) ?> (<?= Html::e((string) ($counts[$key] ?? 0)) ?>)</option>
            <?php endforeach; ?>
        </select>
        <?php if ($team !== []): ?>
            <label class="sr-only" for="personne">Personne</label>
            <select class="input" id="personne" name="personne" data-autosubmit>
                <option value="">Toute l'équipe</option>
                <?php foreach ($team as $member): ?>
                    <?php if (!$member instanceof ReservationTeamMember) { continue; } ?>
                    <option value="<?= $member->id() ?>"<?= (int) ($filters['staff_id'] ?? 0) === $member->id() ? ' selected' : '' ?>><?= Html::e($member->name()) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="btn btn-secondary" type="submit">Chercher</button>
    </form>

    <?php if ($appointments === []): ?>
        <div class="empty btq-card"><p>Aucun rendez-vous ici. <?php if ($canManage): ?><a href="<?= Html::url('/reservation/rendez-vous/nouveau') ?>">Ajouter un rendez-vous</a><?php endif; ?></p></div>
    <?php else: ?>
        <ul class="btq-list">
            <?php foreach ($appointments as $row): ?>
                <?php
                if (!$row instanceof ReservationAppointment) {
                    continue;
                }
                $ts = (int) strtotime($row->startsAt() . ' UTC');
                ?>
                <li>
                    <a href="<?= Html::url('/reservation/rendez-vous/' . $row->id()) ?>">
                        <span>
                            <strong><?= Html::e($days[(int) gmdate('N', $ts)] . ' ' . gmdate('d/m', $ts) . ' · ' . gmdate('H:i', $ts)) ?> — <?= Html::e($row->clientName()) ?></strong>
                            <small><?= Html::e($row->serviceName()) ?><?= $row->staffName() !== '' ? ' · avec ' . Html::e($row->staffName()) : '' ?><?= $row->source() === 'online' ? ' · réservé en ligne' : '' ?></small>
                        </span>
                        <span class="btq-list-end">
                            <strong><?= Html::e(BookingService::money($row->priceMinor())) ?></strong>
                            <span class="btq-status btq-status-<?= Html::e(AppointmentStatus::tone($row->status())) ?>"><?= Html::e(AppointmentStatus::label($row->status())) ?></span>
                            <?php if ($row->isPaid()): ?><span class="btq-status btq-status-success">Payé</span><?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($pages > 1): ?>
            <nav class="btq-actions" aria-label="Pages">
                <?php if ($page > 1): ?><a class="btn btn-secondary" href="<?= Html::url($link(['page' => (string) ($page - 1)])) ?>">Précédent</a><?php endif; ?>
                <span class="muted">Page <?= Html::e((string) $page) ?> sur <?= Html::e((string) $pages) ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-secondary" href="<?= Html::url($link(['page' => (string) ($page + 1)])) ?>">Suivant</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
