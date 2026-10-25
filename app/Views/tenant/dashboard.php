<?php

declare(strict_types=1);

use Sesam\Core\Auth\Permission;
use Sesam\Helpers\Html;
use Sesam\Models\Tenant;
use Sesam\Models\User;

$actor = $actor instanceof User ? $actor : null;
$tenant = $tenant instanceof Tenant ? $tenant : null;
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$config = is_array($dashboard['config'] ?? null) ? $dashboard['config'] : [];
$counts = is_array($dashboard['counts'] ?? null) ? $dashboard['counts'] : [];
$kpis = is_array($config['kpis'] ?? null) ? $config['kpis'] : [];
$quickActions = is_array($config['quickActions'] ?? null) ? $config['quickActions'] : [];
$calendar = is_array($dashboard['calendar'] ?? null) ? $dashboard['calendar'] : ['title' => '', 'weekdays' => [], 'cells' => []];
$upcoming = is_array($dashboard['upcoming'] ?? null) ? $dashboard['upcoming'] : [];
$work = is_array($dashboard['work'] ?? null) ? $dashboard['work'] : [];
$queue = is_array($dashboard['queue'] ?? null) ? $dashboard['queue'] : [];
$people = is_array($dashboard['people'] ?? null) ? $dashboard['people'] : [];
$greeting = is_string($greeting ?? null) ? $greeting : ($actor?->fullName() ?? '');
$kpiTones = ['cyan', 'alert', 'violet'];

$heroActions = [];
foreach ($quickActions as $action) {
    if (!is_array($action)) {
        continue;
    }
    $href = (string) ($action['href'] ?? '');
    $permission = (string) ($action['permission'] ?? '');
    if ($href === '' || $permission === '' || !$actor instanceof User || !$actor->can($permission)) {
        continue;
    }
    $heroActions[] = $action;
    if (count($heroActions) === 2) {
        break;
    }
}

?>
<section class="dash-premium">
    <header class="dash-hero">
        <div>
            <h1>Bonjour <?= Html::e($greeting) ?></h1>
            <p><?= Html::e((string) ($config['activityTitle'] ?? 'Votre activité aujourd\'hui')) ?><?php if ($tenant?->displayName()): ?> — <?= Html::e($tenant->displayName()) ?><?php endif; ?></p>
        </div>
        <?php if ($heroActions !== []): ?>
            <div class="dash-hero-actions" aria-label="Actions rapides">
                <span class="sr-only">Actions rapides</span>
                <?php foreach ($heroActions as $index => $action): ?>
                    <?php $isForm = str_ends_with((string) parse_url((string) $action['href'], PHP_URL_PATH), '/nouveau'); ?>
                    <a class="<?= $index === 0 ? 'dash-cta dash-cta-ghost' : 'dash-cta dash-cta-solid' ?>" href="<?= Html::url((string) $action['href']) ?>"<?= $isForm ? ' data-modal="Action rapide"' : '' ?>>
                        <?= Html::e((string) ($action['label'] ?? '')) ?> +
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>

    <div class="dash-kpis">
        <?php foreach ($kpis as $index => $kpi): ?>
            <?php
            if (!is_array($kpi)) {
                continue;
            }
            $key = (string) ($kpi['key'] ?? '');
            $label = (string) ($kpi['label'] ?? '');
            $value = (int) ($counts[$key] ?? 0);
            $tone = $kpiTones[$index] ?? 'cyan';
            ?>
            <article class="dash-kpi">
                <span class="dash-kpi-icon dash-kpi-icon-<?= Html::e($tone) ?>" aria-hidden="true"></span>
                <div>
                    <p class="muted"><?= Html::e($label) ?></p>
                    <strong><?= Html::e((string) $value) ?></strong>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="dash-mid">
        <section class="dash-card">
            <header class="dash-card-head">
                <h2><?= Html::e((string) ($config['upcomingTitle'] ?? 'À venir')) ?></h2>
                <span class="muted">Aujourd'hui</span>
            </header>
            <div class="dash-agenda">
                <div class="dash-cal" aria-label="<?= Html::e((string) ($calendar['title'] ?? '')) ?>">
                    <p class="dash-cal-title"><?= Html::e((string) ($calendar['title'] ?? '')) ?></p>
                    <div class="calendar dash-cal-grid">
                        <?php foreach ((array) ($calendar['weekdays'] ?? []) as $day): ?>
                            <span class="dash-cal-wd"><?= Html::e((string) $day) ?></span>
                        <?php endforeach; ?>
                        <?php foreach ((array) ($calendar['cells'] ?? []) as $cell): ?>
                            <?php if (!is_array($cell)) { continue; } ?>
                            <?php
                            $calClasses = [];
                            if (!empty($cell['today'])) {
                                $calClasses[] = 'is-today';
                            }
                            ?>
                            <span<?= $calClasses !== [] ? ' class="' . Html::e(implode(' ', $calClasses)) . '"' : '' ?>><?= Html::e((string) ($cell['label'] ?? '')) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($upcoming === []): ?>
                    <p class="muted dash-empty">Aucun élément à venir.</p>
                <?php else: ?>
                    <ul class="dash-upcoming">
                        <?php foreach ($upcoming as $row): ?>
                            <?php if (!is_array($row)) { continue; } ?>
                            <li>
                                <span class="dash-dot" aria-hidden="true"></span>
                                <div>
                                    <a href="<?= Html::url((string) ($row['href'] ?? '#')) ?>"><?= Html::e((string) ($row['title'] ?? '')) ?></a>
                                    <p class="muted"><?= Html::e((string) ($row['meta'] ?? '')) ?> · <?= Html::e((string) ($row['when'] ?? '')) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="dash-card">
            <header class="dash-card-head">
                <h2><?= Html::e((string) ($config['workTitle'] ?? 'Activité récente')) ?></h2>
            </header>
            <?php if ($work === []): ?>
                <div class="empty">
                    <p><?= Html::e((string) ($config['activityEmpty'] ?? 'Aucune activité pour le moment.')) ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrap dash-table">
                    <table>
                        <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Date</th>
                            <th scope="col">Détail</th>
                            <th scope="col">Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($work as $row): ?>
                            <?php if (!is_array($row)) { continue; } ?>
                            <tr>
                                <td><a href="<?= Html::url((string) ($row['href'] ?? '#')) ?>"><?= Html::e((string) ($row['name'] ?? '')) ?></a></td>
                                <td><?= Html::e((string) ($row['date'] ?? '')) ?></td>
                                <td><?= Html::e((string) ($row['condition'] ?? '')) ?></td>
                                <td><span class="badge"><?= Html::e((string) ($row['status'] ?? '')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="dash-low">
        <section class="dash-card">
            <header class="dash-card-head">
                <h2><?= Html::e((string) ($config['queueTitle'] ?? 'Paiements')) ?></h2>
                <?php
                $queueAll = (string) ($config['queueHref'] ?? '');
                if ($queueAll === '' && $actor instanceof User && $actor->can(Permission::PAYMENTS_VIEW)) {
                    $queueAll = '/paiements';
                }
                ?>
                <?php if ($queueAll !== ''): ?>
                    <a class="muted" href="<?= Html::url($queueAll) ?>">Voir tout</a>
                <?php endif; ?>
            </header>
            <?php if ($queue === []): ?>
                <p class="muted dash-empty"><?= Html::e((string) ($config['queueEmpty'] ?? 'Aucun paiement pour le moment.')) ?></p>
            <?php else: ?>
                <div class="table-wrap dash-table">
                    <table>
                        <thead>
                        <tr>
                            <th scope="col">Libellé</th>
                            <th scope="col">Montant</th>
                            <th scope="col">Date</th>
                            <th scope="col">Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($queue as $row): ?>
                            <?php if (!is_array($row)) { continue; } ?>
                            <tr>
                                <td><a href="<?= Html::url((string) ($row['href'] ?? '#')) ?>"><?= Html::e((string) ($row['label'] ?? '')) ?></a></td>
                                <td><?= Html::e((string) ($row['qty'] ?? '')) ?></td>
                                <td><?= Html::e((string) ($row['when'] ?? '')) ?></td>
                                <td><span class="badge"><?= Html::e((string) ($row['status'] ?? '')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="dash-card">
            <header class="dash-card-head">
                <h2><?= Html::e((string) ($config['peopleTitle'] ?? 'Clients')) ?></h2>
                <?php
                $peopleAll = (string) ($config['peopleHref'] ?? '');
                if ($peopleAll === '' && $actor instanceof User && $actor->can(Permission::CLIENTS_VIEW)) {
                    $peopleAll = '/clients';
                }
                ?>
                <?php if ($peopleAll !== ''): ?>
                    <a class="muted" href="<?= Html::url($peopleAll) ?>">Voir tout</a>
                <?php endif; ?>
            </header>
            <?php if ($people === []): ?>
                <p class="muted dash-empty"><?= Html::e((string) ($config['peopleEmpty'] ?? 'Aucun contact pour le moment.')) ?></p>
            <?php else: ?>
                <div class="table-wrap dash-table">
                    <table>
                        <thead>
                        <tr>
                            <th scope="col">Nom</th>
                            <th scope="col">Détail</th>
                            <th scope="col">Statut</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($people as $row): ?>
                            <?php if (!is_array($row)) { continue; } ?>
                            <tr>
                                <td>
                                    <span class="dash-person">
                                        <span class="tenant-avatar tenant-avatar-sm" aria-hidden="true"><?= Html::e(Html::initials((string) ($row['name'] ?? ''))) ?></span>
                                        <a href="<?= Html::url((string) ($row['href'] ?? '#')) ?>"><?= Html::e((string) ($row['name'] ?? '')) ?></a>
                                    </span>
                                </td>
                                <td><?= Html::e((string) ($row['role'] ?? '')) ?></td>
                                <td><span class="badge badge-accent"><?= Html::e((string) ($row['status'] ?? '')) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
