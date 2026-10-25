<?php

declare(strict_types=1);

use Sesam\Core\Auth\Permission;
use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationAppointment;
use Sesam\Models\Tenant;
use Sesam\Models\User;
use Sesam\Services\Reservation\BookingService;

$actor = ($actor ?? null) instanceof User ? $actor : null;
$board = is_array($board ?? null) ? $board : [];
$greeting = is_string($greeting ?? null) ? $greeting : '';
$tenant = ($board['tenant'] ?? null) instanceof Tenant ? $board['tenant'] : null;
$today = is_array($board['today'] ?? null) ? $board['today'] : [];
$pending = is_array($board['pending'] ?? null) ? $board['pending'] : [];
$checklist = is_array($board['checklist'] ?? null) ? $board['checklist'] : [];
$remaining = count(array_filter($checklist, static fn ($step): bool => is_array($step) && empty($step['done'])));
$can = static fn (string $permission): bool => $actor instanceof User && $actor->can($permission);
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$publicUrl = (string) ($board['publicUrl'] ?? '');
$settings = $board['settings'] ?? null;

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Bonjour <?= Html::e($greeting) ?></h1>
            <p class="muted">Voici la journée de <?= Html::e($tenant?->displayName() ?? 'votre salon') ?>, <?= Html::e(strtolower(BookingService::weekdayLabel((int) gmdate('N')))) ?> <?= Html::e(gmdate('d/m')) ?>.</p>
        </div>
        <?php if ($can(Permission::RESERVATION_APPOINTMENTS)): ?>
            <a class="btn btn-primary" href="<?= Html::url('/reservation/rendez-vous/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau rendez-vous</a>
        <?php endif; ?>
    </header>

    <?php if ($settings instanceof \Sesam\Models\ReservationSettings && !$settings->isOpen()): ?>
        <p class="alert alert-strong" role="status">La réservation en ligne est fermée. <a href="<?= Html::url('/reservation/salon') ?>">La rouvrir</a></p>
    <?php endif; ?>

    <div class="btq-kpis">
        <article class="btq-kpi">
            <p>Rendez-vous aujourd'hui</p>
            <strong><?= Html::e((string) ($board['countToday'] ?? 0)) ?></strong>
            <a href="<?= Html::url('/reservation/agenda') ?>">Voir l'agenda</a>
        </article>
        <article class="btq-kpi<?= (int) ($board['countPending'] ?? 0) > 0 ? ' is-alert' : '' ?>">
            <p>À confirmer</p>
            <strong><?= Html::e((string) ($board['countPending'] ?? 0)) ?></strong>
            <a href="<?= Html::url('/reservation/rendez-vous?statut=pending&periode=a-venir') ?>">Traiter</a>
        </article>
        <article class="btq-kpi">
            <p>Chiffre de la semaine</p>
            <strong><?= Html::e(BookingService::money((int) ($board['revenueWeek'] ?? 0))) ?></strong>
        </article>
        <article class="btq-kpi<?= (int) ($board['noShowRate'] ?? 0) >= 15 ? ' is-alert' : '' ?>">
            <p>Absences ce mois</p>
            <strong><?= Html::e((string) ($board['noShowRate'] ?? 0)) ?> %</strong>
        </article>
    </div>

    <div class="btq-two">
        <section class="btq-card">
            <header class="btq-card-head">
                <h2>Aujourd'hui</h2>
                <a class="muted" href="<?= Html::url('/reservation/agenda') ?>">Agenda</a>
            </header>
            <?php if ($today === []): ?>
                <p class="muted">Aucun rendez-vous aujourd'hui. Partagez votre lien de réservation pour remplir l'agenda.</p>
            <?php else: ?>
                <ol class="rv-timeline">
                    <?php foreach ($today as $item): ?>
                        <?php if (!$item instanceof ReservationAppointment) { continue; } ?>
                        <li class="is-<?= Html::e($item->status()) ?>">
                            <time><?= Html::e(substr($item->startsAt(), 11, 5)) ?></time>
                            <a href="<?= Html::url('/reservation/rendez-vous/' . $item->id()) ?>">
                                <strong><?= Html::e($item->clientName()) ?></strong>
                                <small><?= Html::e($item->serviceName()) ?><?= $item->staffName() !== '' ? ' · ' . Html::e($item->staffName()) : '' ?></small>
                            </a>
                            <span class="btq-status btq-status-<?= Html::e(AppointmentStatus::tone($item->status())) ?>"><?= Html::e(AppointmentStatus::label($item->status())) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <div class="btq">
            <section class="btq-card btq-share" id="partager">
                <h2>Votre lien de réservation</h2>
                <p>Vos clientes choisissent la prestation et un créneau libre, sans vous appeler.</p>
                <div class="btq-share-url">
                    <label class="sr-only" for="public-url">Lien de réservation</label>
                    <input id="public-url" type="text" readonly value="<?= Html::e($publicUrl) ?>">
                </div>
                <div class="btq-actions">
                    <button class="btn btn-primary" type="button" data-copy="public-url"><?php $name = 'link'; include $icon; ?><span data-copy-label>Copier</span></button>
                    <a class="btn" href="<?= Html::e((string) ($board['shareLink'] ?? '')) ?>" target="_blank" rel="noopener"><?php $name = 'chat'; include $icon; ?>WhatsApp</a>
                    <a class="btn" href="<?= Html::e($publicUrl) ?>" target="_blank" rel="noopener"><?php $name = 'eye'; include $icon; ?>Voir</a>
                </div>
            </section>

            <?php if ($pending !== []): ?>
                <section class="btq-card">
                    <header class="btq-card-head"><h2>Demandes à confirmer</h2></header>
                    <ul class="btq-list">
                        <?php foreach ($pending as $item): ?>
                            <?php if (!$item instanceof ReservationAppointment) { continue; } ?>
                            <li>
                                <div>
                                    <span>
                                        <a href="<?= Html::url('/reservation/rendez-vous/' . $item->id()) ?>"><strong><?= Html::e($item->clientName()) ?></strong></a>
                                        <small><?= Html::e($item->serviceName()) ?> · <?= Html::e(gmdate('d/m H:i', (int) strtotime($item->startsAt() . ' UTC'))) ?></small>
                                    </span>
                                    <?php if ($can(Permission::RESERVATION_APPOINTMENTS)): ?>
                                        <form method="post" action="<?= Html::url('/reservation/rendez-vous/' . $item->id() . '/confirmer') ?>">
                                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                            <input type="hidden" name="retour" value="/espace">
                                            <button class="btn btn-primary btn-sm" type="submit">Confirmer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php if ($remaining > 0): ?>
                <section class="btq-card">
                    <h2>Pour bien démarrer</h2>
                    <ol class="btq-steps">
                        <?php foreach ($checklist as $step): ?>
                            <?php if (!is_array($step)) { continue; } ?>
                            <li class="<?= !empty($step['done']) ? 'is-done' : '' ?>">
                                <a href="<?= Html::url((string) $step['href']) ?>">
                                    <span class="btq-check" aria-hidden="true"><?php if (!empty($step['done'])) { $name = 'check'; include $icon; } ?></span>
                                    <span><?= Html::e((string) $step['label']) ?><?= !empty($step['done']) ? ' (fait)' : '' ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <nav class="btq-tiles" aria-label="Raccourcis">
        <a class="btq-tile" href="<?= Html::url('/reservation/agenda?vue=semaine') ?>"><span class="btq-tile-icon"><?php $name = 'calendar'; include $icon; ?></span>Agenda de la semaine<small>Qui fait quoi, et quand</small></a>
        <a class="btq-tile" data-tone="blue" href="<?= Html::url('/reservation/prestations') ?>"><span class="btq-tile-icon"><?php $name = 'spark'; include $icon; ?></span>Prestations<small>Prix, durées, acomptes</small></a>
        <a class="btq-tile" data-tone="amber" href="<?= Html::url('/reservation/equipe') ?>"><span class="btq-tile-icon"><?php $name = 'team'; include $icon; ?></span>Équipe<small>Horaires et absences</small></a>
        <a class="btq-tile" data-tone="violet" href="<?= Html::url('/reservation/salon') ?>"><span class="btq-tile-icon"><?php $name = 'store'; include $icon; ?></span>Mon salon<small>Horaires, fermetures, page</small></a>
    </nav>
</section>
