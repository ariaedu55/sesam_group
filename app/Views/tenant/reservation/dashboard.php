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
$week = is_array($board['week'] ?? null) ? $board['week'] : [];
$topServices = is_array($board['topServices'] ?? null) ? $board['topServices'] : [];
$next = ($board['next'] ?? null) instanceof ReservationAppointment ? $board['next'] : null;
$doneSteps = count(array_filter($checklist, static fn ($step): bool => is_array($step) && !empty($step['done'])));
$remaining = count($checklist) - $doneSteps;
$can = static fn (string $permission): bool => $actor instanceof User && $actor->can($permission);
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$publicUrl = (string) ($board['publicUrl'] ?? '');
$settings = $board['settings'] ?? null;
$money = static fn (int $amount): string => BookingService::money($amount);

$countToday = (int) ($board['countToday'] ?? 0);
$countPending = (int) ($board['countPending'] ?? 0);
$revenueWeek = (int) ($board['revenueWeek'] ?? 0);
$revenueLastWeek = (int) ($board['revenueLastWeek'] ?? 0);
$trend = $revenueLastWeek > 0 ? (int) round(($revenueWeek - $revenueLastWeek) * 100 / $revenueLastWeek) : null;
$noShowRate = (int) ($board['noShowRate'] ?? 0);
$weekMax = max(1, ...array_map(static fn (array $day): int => (int) $day['total'], $week ?: [['total' => 0]]));
$weekTotal = array_sum(array_map(static fn (array $day): int => (int) $day['total'], $week));
$dayLetters = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
$time = static fn (string $at): string => substr($at, 11, 5);

// « Dans 2 h 15 », « Dans 25 min », « Demain à 10:00 »…
$when = static function (string $startsAt): string {
    $minutes = (int) floor((strtotime($startsAt . ' UTC') - time()) / 60);
    $day = substr($startsAt, 0, 10);
    if ($day === gmdate('Y-m-d')) {
        if ($minutes < 60) {
            return 'Dans ' . max(1, $minutes) . ' min';
        }

        return 'Dans ' . intdiv($minutes, 60) . ' h' . ($minutes % 60 > 0 ? ' ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) : '');
    }
    if ($day === gmdate('Y-m-d', strtotime('+1 day'))) {
        return 'Demain à ' . substr($startsAt, 11, 5);
    }

    return 'Le ' . gmdate('d/m', (int) strtotime($startsAt . ' UTC')) . ' à ' . substr($startsAt, 11, 5);
};

?>
<section class="rd">
    <header class="rd-hero">
        <div class="rd-hero-main">
            <p class="rd-hero-date"><?php $name = 'calendar'; include $icon; ?><?= Html::e(BookingService::weekdayLabel((int) gmdate('N'))) ?> <?= Html::e(gmdate('d/m/Y')) ?></p>
            <h1>Bonjour <?= Html::e($greeting) ?></h1>
            <p class="rd-hero-summary">
                <?php if ($countToday === 0): ?>
                    Aucun rendez-vous prévu aujourd'hui à <?= Html::e($tenant?->displayName() ?? 'votre salon') ?>. Partagez votre lien pour remplir l'agenda.
                <?php else: ?>
                    <strong><?= $countToday ?> rendez-vous</strong> aujourd'hui à <?= Html::e($tenant?->displayName() ?? 'votre salon') ?><?= (int) ($board['revenueToday'] ?? 0) > 0 ? ', pour <strong>' . Html::e($money((int) $board['revenueToday'])) . '</strong> prévus' : '' ?>.
                <?php endif; ?>
                <?php if ($countPending > 0): ?> <span class="rd-hero-flag"><?= $countPending ?> demande<?= $countPending > 1 ? 's' : '' ?> à confirmer</span><?php endif; ?>
            </p>
            <div class="rd-hero-actions">
                <?php if ($can(Permission::RESERVATION_APPOINTMENTS)): ?>
                    <a class="btn rd-btn-light" href="<?= Html::url('/reservation/rendez-vous/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau rendez-vous</a>
                <?php endif; ?>
                <a class="btn rd-btn-outline" href="<?= Html::url('/reservation/agenda') ?>"><?php $name = 'calendar'; include $icon; ?>Ouvrir l'agenda</a>
            </div>
        </div>
        <aside class="rd-next" aria-label="Prochain rendez-vous">
            <p class="rd-next-label">Prochain rendez-vous</p>
            <?php if ($next !== null): ?>
                <a class="rd-next-card" href="<?= Html::url('/reservation/rendez-vous/' . $next->id()) ?>">
                    <span class="rd-next-time"><?= Html::e($time($next->startsAt())) ?></span>
                    <span class="rd-next-when"><?= Html::e($when($next->startsAt())) ?></span>
                    <strong><?= Html::e($next->clientName()) ?></strong>
                    <small><?= Html::e($next->serviceName()) ?><?= $next->staffName() !== '' ? ' · avec ' . Html::e($next->staffName()) : '' ?></small>
                    <span class="rd-chip rd-chip-<?= Html::e(AppointmentStatus::tone($next->status())) ?>"><?= Html::e(AppointmentStatus::label($next->status())) ?></span>
                </a>
            <?php else: ?>
                <div class="rd-next-card is-empty">
                    <strong>Rien de prévu pour l'instant</strong>
                    <small>Les nouvelles réservations apparaîtront ici.</small>
                </div>
            <?php endif; ?>
        </aside>
    </header>

    <?php if ($settings instanceof \Sesam\Models\ReservationSettings && !$settings->isOpen()): ?>
        <p class="alert alert-strong" role="status">La réservation en ligne est fermée : vos clientes ne peuvent pas réserver. <a href="<?= Html::url('/reservation/salon') ?>">La rouvrir</a></p>
    <?php endif; ?>

    <div class="rd-kpis">
        <a class="rd-kpi" data-tone="blue" href="<?= Html::url('/reservation/agenda') ?>">
            <span class="rd-kpi-icon"><?php $name = 'calendar'; include $icon; ?></span>
            <span class="rd-kpi-label">Rendez-vous aujourd'hui</span>
            <strong><?= $countToday ?></strong>
            <small>Voir le programme du jour</small>
        </a>
        <a class="rd-kpi<?= $countPending > 0 ? ' is-urgent' : '' ?>" data-tone="amber" href="<?= Html::url('/reservation/rendez-vous?statut=pending&periode=a-venir') ?>">
            <span class="rd-kpi-icon"><?php $name = 'bell'; include $icon; ?></span>
            <span class="rd-kpi-label">À confirmer</span>
            <strong><?= $countPending ?></strong>
            <small><?= $countPending > 0 ? 'Répondez vite : la cliente attend' : 'Tout est traité' ?></small>
        </a>
        <article class="rd-kpi" data-tone="green">
            <span class="rd-kpi-icon"><?php $name = 'chart'; include $icon; ?></span>
            <span class="rd-kpi-label">Chiffre de la semaine</span>
            <strong><?= Html::e($money($revenueWeek)) ?></strong>
            <small>
                <?php if ($trend === null): ?>Prestations confirmées et réalisées
                <?php else: ?><span class="rd-trend <?= $trend >= 0 ? 'is-up' : 'is-down' ?>"><?= $trend >= 0 ? '▲ +' : '▼ ' ?><?= $trend ?> %</span> par rapport à la semaine dernière<?php endif; ?>
            </small>
        </article>
        <article class="rd-kpi" data-tone="violet">
            <span class="rd-kpi-icon"><?php $name = 'coin'; include $icon; ?></span>
            <span class="rd-kpi-label">Acomptes reçus ce mois</span>
            <strong><?= Html::e($money((int) ($board['depositsMonth'] ?? 0))) ?></strong>
            <small>Payés en ligne par Wave ou Orange Money</small>
        </article>
    </div>

    <div class="rd-grid">
        <div class="rd-col">
            <section class="rd-card" aria-labelledby="rd-today">
                <header class="rd-card-head">
                    <div>
                        <h2 id="rd-today">Programme du jour</h2>
                        <p><?= $countToday ?> rendez-vous · <?= Html::e(gmdate('d/m')) ?></p>
                    </div>
                    <a class="rd-link" href="<?= Html::url('/reservation/agenda') ?>">Agenda complet<?php $name = 'arrow'; include $icon; ?></a>
                </header>
                <?php if ($today === []): ?>
                    <div class="rd-empty">
                        <span class="rd-empty-icon" aria-hidden="true"><?php $name = 'calendar'; include $icon; ?></span>
                        <strong>Journée libre</strong>
                        <p>Aucun rendez-vous aujourd'hui. Envoyez votre lien de réservation à vos clientes sur WhatsApp.</p>
                        <a class="btn btn-primary btn-sm" href="#partager">Partager mon lien</a>
                    </div>
                <?php else: ?>
                    <ol class="rd-timeline">
                        <?php foreach ($today as $item): ?>
                            <?php if (!$item instanceof ReservationAppointment) { continue; } ?>
                            <?php $past = $item->startsAt() < gmdate('Y-m-d H:i:s'); ?>
                            <li class="is-<?= Html::e($item->status()) ?><?= $past ? ' is-past' : '' ?>">
                                <time datetime="<?= Html::e(str_replace(' ', 'T', $item->startsAt())) ?>"><?= Html::e($time($item->startsAt())) ?><small><?= (int) $item->durationMinutes() ?> min</small></time>
                                <a href="<?= Html::url('/reservation/rendez-vous/' . $item->id()) ?>">
                                    <strong><?= Html::e($item->clientName()) ?></strong>
                                    <small><?= Html::e($item->serviceName()) ?><?= $item->staffName() !== '' ? ' · ' . Html::e($item->staffName()) : '' ?></small>
                                </a>
                                <span class="rd-chip rd-chip-<?= Html::e(AppointmentStatus::tone($item->status())) ?>"><?= Html::e(AppointmentStatus::label($item->status())) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <?php if ($pending !== []): ?>
                <section class="rd-card rd-pending" aria-labelledby="rd-pending">
                    <header class="rd-card-head">
                        <div>
                            <h2 id="rd-pending">Demandes à confirmer</h2>
                            <p>La cliente reçoit un message dès que vous confirmez.</p>
                        </div>
                        <a class="rd-link" href="<?= Html::url('/reservation/rendez-vous?statut=pending&periode=a-venir') ?>">Tout voir<?php $name = 'arrow'; include $icon; ?></a>
                    </header>
                    <ul class="rd-requests">
                        <?php foreach ($pending as $item): ?>
                            <?php if (!$item instanceof ReservationAppointment) { continue; } ?>
                            <li>
                                <span class="rd-avatar" aria-hidden="true"><?= Html::e(mb_strtoupper(mb_substr($item->clientName(), 0, 1))) ?></span>
                                <a href="<?= Html::url('/reservation/rendez-vous/' . $item->id()) ?>">
                                    <strong><?= Html::e($item->clientName()) ?></strong>
                                    <small><?= Html::e($item->serviceName()) ?> · <?= Html::e(BookingService::weekdayLabel((int) gmdate('N', (int) strtotime($item->startsAt() . ' UTC')))) ?> <?= Html::e(gmdate('d/m à H:i', (int) strtotime($item->startsAt() . ' UTC'))) ?></small>
                                </a>
                                <?php if ($can(Permission::RESERVATION_APPOINTMENTS)): ?>
                                    <form method="post" action="<?= Html::url('/reservation/rendez-vous/' . $item->id() . '/confirmer') ?>">
                                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                        <input type="hidden" name="retour" value="/espace">
                                        <button class="btn btn-primary btn-sm" type="submit"><?php $name = 'check'; include $icon; ?>Confirmer</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="rd-card" aria-labelledby="rd-week">
                <header class="rd-card-head">
                    <div>
                        <h2 id="rd-week">Votre semaine</h2>
                        <p><?= $weekTotal ?> rendez-vous du lundi au dimanche</p>
                    </div>
                </header>
                <div class="rd-bars" role="img" aria-label="Rendez-vous par jour cette semaine : <?= Html::e(implode(', ', array_map(static fn (array $day): string => $dayLetters[(int) gmdate('N', (int) strtotime($day['date'] . ' UTC'))] . ' ' . (int) $day['total'], $week))) ?>">
                    <?php foreach ($week as $day): ?>
                        <?php $n = (int) $day['total']; $label = $dayLetters[(int) gmdate('N', (int) strtotime($day['date'] . ' UTC'))]; ?>
                        <div class="rd-bar<?= !empty($day['today']) ? ' is-today' : '' ?>" title="<?= Html::e($label . ' ' . gmdate('d/m', (int) strtotime($day['date'] . ' UTC')) . ' : ' . $n . ' rendez-vous') ?>">
                            <span class="rd-bar-value"><?= $n > 0 ? $n : '' ?></span>
                            <span class="rd-bar-track"><span class="rd-bar-fill" style="height: <?= $n > 0 ? max(6, (int) round($n * 100 / $weekMax)) : 0 ?>%"></span></span>
                            <span class="rd-bar-day"><?= Html::e(!empty($day['today']) ? 'Auj.' : $label) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="rd-col">
            <section class="rd-card rd-share" id="partager" aria-labelledby="rd-share">
                <span class="rd-share-icon" aria-hidden="true"><?php $name = 'link'; include $icon; ?></span>
                <h2 id="rd-share">Votre lien de réservation</h2>
                <p>Vos clientes choisissent la prestation et un créneau libre, sans vous appeler. Mettez-le dans votre statut WhatsApp et votre bio Instagram.</p>
                <div class="rd-share-url">
                    <label class="sr-only" for="public-url">Lien de réservation</label>
                    <input id="public-url" type="text" readonly value="<?= Html::e($publicUrl) ?>">
                    <button class="btn btn-primary btn-sm" type="button" data-copy="public-url"><?php $name = 'link'; include $icon; ?><span data-copy-label>Copier</span></button>
                </div>
                <div class="rd-share-actions">
                    <a class="btn rd-btn-whatsapp" href="<?= Html::e((string) ($board['shareLink'] ?? '')) ?>" target="_blank" rel="noopener"><?php $name = 'chat'; include $icon; ?>Envoyer sur WhatsApp</a>
                    <a class="btn btn-ghost" href="<?= Html::e($publicUrl) ?>" target="_blank" rel="noopener"><?php $name = 'eye'; include $icon; ?>Voir ma page</a>
                </div>
            </section>

            <section class="rd-card" aria-labelledby="rd-month">
                <header class="rd-card-head">
                    <div>
                        <h2 id="rd-month">Ce mois-ci</h2>
                        <p><?= Html::e([1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'][(int) gmdate('n')] . ' ' . gmdate('Y')) ?></p>
                    </div>
                </header>
                <dl class="rd-month">
                    <div><dt>Rendez-vous</dt><dd><?= (int) ($board['monthTotal'] ?? 0) ?></dd></div>
                    <div class="<?= $noShowRate >= 15 ? 'is-alert' : '' ?>"><dt>Absences</dt><dd><?= $noShowRate ?> %</dd></div>
                </dl>
                <p class="rd-hint"><?= $noShowRate >= 15 ? 'Beaucoup de clientes ne viennent pas : demandez un acompte sur vos prestations les plus longues.' : 'Clientes non venues parmi les rendez-vous passés du mois.' ?></p>
                <?php if ($topServices !== []): ?>
                    <h3 class="rd-subtitle">Prestations les plus demandées</h3>
                    <ol class="rd-top">
                        <?php $topMax = max(1, (int) $topServices[0]['total']); ?>
                        <?php foreach ($topServices as $service): ?>
                            <li>
                                <span><?= Html::e((string) $service['name']) ?></span>
                                <strong><?= (int) $service['total'] ?></strong>
                                <span class="rd-top-track" aria-hidden="true"><span style="width: <?= (int) round((int) $service['total'] * 100 / $topMax) ?>%"></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <?php if ($remaining > 0): ?>
                <section class="rd-card rd-start" aria-labelledby="rd-start">
                    <header class="rd-card-head">
                        <div>
                            <h2 id="rd-start">Pour bien démarrer</h2>
                            <p><?= $doneSteps ?> étape<?= $doneSteps > 1 ? 's' : '' ?> sur <?= count($checklist) ?> terminée<?= $doneSteps > 1 ? 's' : '' ?></p>
                        </div>
                    </header>
                    <span class="rd-progress" aria-hidden="true"><span style="width: <?= (int) round($doneSteps * 100 / max(1, count($checklist))) ?>%"></span></span>
                    <ol class="rd-steps">
                        <?php foreach ($checklist as $step): ?>
                            <?php if (!is_array($step)) { continue; } ?>
                            <li class="<?= !empty($step['done']) ? 'is-done' : '' ?>">
                                <a href="<?= Html::url((string) $step['href']) ?>">
                                    <span class="rd-check" aria-hidden="true"><?php if (!empty($step['done'])) { $name = 'check'; include $icon; } ?></span>
                                    <span><?= Html::e((string) $step['label']) ?><?= !empty($step['done']) ? ' (fait)' : '' ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <nav class="rd-tiles" aria-label="Raccourcis">
        <a class="rd-tile" data-tone="blue" href="<?= Html::url('/reservation/agenda?vue=semaine') ?>"><span class="rd-tile-icon"><?php $name = 'calendar'; include $icon; ?></span><span><strong>Agenda de la semaine</strong><small>Qui fait quoi, et quand</small></span></a>
        <a class="rd-tile" data-tone="amber" href="<?= Html::url('/reservation/prestations') ?>"><span class="rd-tile-icon"><?php $name = 'spark'; include $icon; ?></span><span><strong>Prestations</strong><small>Prix, durées, acomptes</small></span></a>
        <a class="rd-tile" data-tone="green" href="<?= Html::url('/reservation/equipe') ?>"><span class="rd-tile-icon"><?php $name = 'team'; include $icon; ?></span><span><strong>Équipe</strong><small>Horaires et absences</small></span></a>
        <a class="rd-tile" data-tone="violet" href="<?= Html::url('/reservation/salon') ?>"><span class="rd-tile-icon"><?php $name = 'store'; include $icon; ?></span><span><strong>Mon salon</strong><small>Horaires, fermetures, page</small></span></a>
    </nav>
</section>
