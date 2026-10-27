<?php

declare(strict_types=1);

use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationAppointment;
use Sesam\Services\Reservation\BookingService;

$list = is_array($list ?? null) ? $list : [];
$appointments = is_array($list['appointments'] ?? null) ? $list['appointments'] : [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$filters = is_array($filters ?? null) ? $filters : [];
$canManage = (bool) ($canManage ?? false);
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
    <p class="muted btq-filter-count"><?= Html::e((string) ($list['total'] ?? 0)) ?> rendez-vous pour ce filtre.</p>
    <?php if ($appointments === []): ?>
        <div class="empty btq-card"><p>Aucun rendez-vous ici. <?php if ($canManage): ?><a href="<?= Html::url('/reservation/rendez-vous/nouveau') ?>">Ajouter un rendez-vous</a><?php endif; ?></p></div>
    <?php else: ?>
        <ul class="btq-list record-list-rich">
            <?php foreach ($appointments as $row): ?>
                <?php
                if (!$row instanceof ReservationAppointment) {
                    continue;
                }
                $ts = (int) strtotime($row->startsAt() . ' UTC');
                ?>
                <li>
                    <a href="<?= Html::url('/reservation/rendez-vous/' . $row->id()) ?>">
                        <span class="record-list-leading">
                            <span class="record-list-avatar" data-tone="calendar" aria-hidden="true"><?= Html::e(Html::initials($row->clientName())) ?></span>
                            <span>
                                <strong><?= Html::e($days[(int) gmdate('N', $ts)] . ' ' . gmdate('d/m', $ts) . ' · ' . gmdate('H:i', $ts)) ?> — <?= Html::e($row->clientName()) ?></strong>
                                <small><?= Html::e($row->serviceName()) ?><?= $row->staffName() !== '' ? ' · avec ' . Html::e($row->staffName()) : '' ?><?= $row->source() === 'online' ? ' · réservé en ligne' : '' ?></small>
                            </span>
                        </span>
                        <span class="record-list-meta btq-list-end">
                            <strong><?= Html::e(BookingService::money($row->priceMinor())) ?></strong>
                            <span class="btq-status btq-status-<?= Html::e(AppointmentStatus::tone($row->status())) ?>"><?= Html::e(AppointmentStatus::label($row->status())) ?></span>
                            <?php if ($row->isPaid()): ?><span class="btq-status btq-status-success">Payé</span><?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($pages > 1): ?>
            <nav class="btq-actions" data-live-filter-pages aria-label="Pages">
                <?php if ($page > 1): ?><a class="btn btn-secondary" href="<?= Html::url($link(['page' => (string) ($page - 1)])) ?>">Précédent</a><?php endif; ?>
                <span class="muted">Page <?= Html::e((string) $page) ?> sur <?= Html::e((string) $pages) ?></span>
                <?php if ($page < $pages): ?><a class="btn btn-secondary" href="<?= Html::url($link(['page' => (string) ($page + 1)])) ?>">Suivant</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
