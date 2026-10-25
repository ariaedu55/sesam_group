<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueOrder;
use Sesam\Models\Client;
use Sesam\Models\ClientActivity;
use Sesam\Models\ClientNote;

$client = ($client ?? null) instanceof Client ? $client : null;
$notes = is_array($notes ?? null) ? $notes : [];
$activities = is_array($activities ?? null) ? $activities : [];
$canUpdate = (bool) ($canUpdate ?? false);
$canNotes = (bool) ($canNotes ?? false);
$canArchive = (bool) ($canArchive ?? false);
$boutiqueOrders = is_array($boutiqueOrders ?? null) ? $boutiqueOrders : [];
$canOrder = (bool) ($canOrder ?? false);
$isBoutique = (bool) ($showBoutiqueNav ?? false);
$appointments = is_array($appointments ?? null) ? $appointments : [];
$canBook = (bool) ($canBook ?? false);
$isSalon = (bool) ($showReservationNav ?? false);

if ($client === null) {
    return;
}

?>
<section class="crm-detail">
    <header class="row staff-toolbar">
        <div>
            <h1><?= Html::e($client->fullName()) ?></h1>
            <p class="muted">
                <?= $client->isArchived() ? 'Archivé' : 'Actif' ?>
                · Créé le <?= Html::e($client->createdAt()) ?>
            </p>
        </div>
        <div class="row">
            <?php if ($canUpdate): ?>
                <a class="btn btn-secondary" href="<?= Html::url('/clients/' . $client->id() . '/modifier') ?>" data-modal="Clients">Modifier</a>
            <?php endif; ?>
            <?php if ($canArchive && !$client->isArchived()): ?>
                <form method="post" action="<?= Html::url('/clients/' . $client->id() . '/archiver') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <button class="btn btn-ghost" type="submit">Archiver</button>
                </form>
            <?php elseif ($canArchive && $client->isArchived()): ?>
                <form method="post" action="<?= Html::url('/clients/' . $client->id() . '/restaurer') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <button class="btn btn-primary" type="submit">Restaurer</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <div class="crm-detail-grid">
        <article class="card">
            <h2>Coordonnées</h2>
            <dl class="crm-dl">
                <dt>E-mail</dt>
                <dd><?= $client->email() !== null ? Html::e($client->email()) : '—' ?></dd>
                <dt>Téléphone</dt>
                <dd><?= $client->phone() !== null ? Html::e($client->phone()) : '—' ?></dd>
                <dt>Tags</dt>
                <dd>
                    <?php if ($client->tags() === []): ?>
                        <span class="muted">Aucun tag</span>
                    <?php else: ?>
                        <?php foreach ($client->tags() as $tag): ?>
                            <span class="badge"><?= Html::e($tag->name()) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </dd>
            </dl>
        </article>

        <article class="card">
            <h2>Notes</h2>
            <?php if ($notes === []): ?>
                <p class="muted">Aucune note pour l'instant.</p>
            <?php else: ?>
                <ul class="crm-notes">
                    <?php foreach ($notes as $note): ?>
                        <?php if (!$note instanceof ClientNote) { continue; } ?>
                        <li>
                            <p><?= nl2br(Html::e($note->body())) ?></p>
                            <p class="muted"><?= Html::e($note->authorName()) ?> · <?= Html::e($note->createdAt()) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canNotes): ?>
                <form class="stack crm-note-form" data-modal-form="Ajouter une note" data-modal-title="Nouvelle note" data-modal-kicker="Clients" method="post" action="<?= Html::url('/clients/' . $client->id() . '/notes') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <div class="field">
                        <label for="body"><span>Ajouter une note</span></label>
                        <textarea class="input" id="body" name="body" rows="4" maxlength="5000" required></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Publier</button>
                </form>
            <?php endif; ?>
        </article>
    </div>

    <?php if ($isBoutique): ?>
        <article class="card">
            <div class="row staff-toolbar">
                <h2>Commandes de la boutique</h2>
                <?php if ($canOrder): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/boutique/commandes/nouveau?client=' . $client->id()) ?>">Noter une vente</a>
                <?php endif; ?>
            </div>
            <?php if ($boutiqueOrders === []): ?>
                <p class="muted">Aucune commande pour ce client.</p>
            <?php else: ?>
                <?php $spent = 0; foreach ($boutiqueOrders as $order) { if ($order instanceof BoutiqueOrder && OrderStatus::consumesStock($order->status())) { $spent += $order->totalMinor(); } } ?>
                <p class="muted"><?= Html::e((string) count($boutiqueOrders)) ?> commande(s) · <?= Html::e(Money::format($spent)) ?> d'achats confirmés</p>
                <ul class="crm-timeline">
                    <?php foreach ($boutiqueOrders as $order): ?>
                        <?php if (!$order instanceof BoutiqueOrder) { continue; } ?>
                        <li>
                            <p><a href="<?= Html::url('/boutique/commandes/' . $order->id()) ?>"><?= Html::e($order->orderNumber()) ?></a> — <?= Html::e(Money::format($order->totalMinor())) ?> · <?= Html::e(OrderStatus::label($order->status())) ?></p>
                            <p class="muted"><?= Html::e($order->createdAt()) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    <?php endif; ?>

    <?php if ($isSalon): ?>
        <article class="card">
            <div class="row staff-toolbar">
                <h2>Rendez-vous</h2>
                <?php if ($canBook): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/reservation/rendez-vous/nouveau?client=' . $client->id()) ?>">Nouveau rendez-vous</a>
                <?php endif; ?>
            </div>
            <?php if ($appointments === []): ?>
                <p class="muted">Aucun rendez-vous pour cette cliente.</p>
            <?php else: ?>
                <?php
                $visits = count(array_filter($appointments, static fn ($a): bool => $a instanceof \Sesam\Models\ReservationAppointment && $a->status() === \Sesam\Core\Reservation\AppointmentStatus::COMPLETED));
                $absent = count(array_filter($appointments, static fn ($a): bool => $a instanceof \Sesam\Models\ReservationAppointment && $a->status() === \Sesam\Core\Reservation\AppointmentStatus::NO_SHOW));
                ?>
                <p class="muted"><?= Html::e((string) $visits) ?> visite(s) réalisée(s)<?= $absent > 0 ? ' · ' . Html::e((string) $absent) . ' absence(s)' : '' ?></p>
                <ul class="crm-timeline">
                    <?php foreach ($appointments as $item): ?>
                        <?php if (!$item instanceof \Sesam\Models\ReservationAppointment) { continue; } ?>
                        <li>
                            <p><a href="<?= Html::url('/reservation/rendez-vous/' . $item->id()) ?>"><?= Html::e(gmdate('d/m/Y H:i', (int) strtotime($item->startsAt() . ' UTC'))) ?></a> — <?= Html::e($item->serviceName()) ?> · <?= Html::e(\Sesam\Core\Reservation\AppointmentStatus::label($item->status())) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    <?php endif; ?>

    <article class="card">
        <h2>Historique</h2>
        <?php if ($activities === []): ?>
            <p class="muted">Aucune activité enregistrée.</p>
        <?php else: ?>
            <ul class="crm-timeline">
                <?php foreach ($activities as $activity): ?>
                    <?php if (!$activity instanceof ClientActivity) { continue; } ?>
                    <li>
                        <p><?= Html::e($activity->summary()) ?></p>
                        <p class="muted">
                            <?php if ($activity->actorName() !== ''): ?>
                                <?= Html::e($activity->actorName()) ?> ·
                            <?php endif; ?>
                            <?= Html::e($activity->createdAt()) ?>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <p><a class="btn btn-ghost" href="<?= Html::url('/clients') ?>">← Retour à la liste</a></p>
</section>
