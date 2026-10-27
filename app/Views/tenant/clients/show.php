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
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';

if ($client === null) {
    return;
}

$spent = 0;
foreach ($boutiqueOrders as $order) {
    if ($order instanceof BoutiqueOrder && OrderStatus::consumesStock($order->status())) {
        $spent += $order->totalMinor();
    }
}
$visits = count(array_filter($appointments, static fn ($a): bool => $a instanceof \Sesam\Models\ReservationAppointment && $a->status() === \Sesam\Core\Reservation\AppointmentStatus::COMPLETED));

ob_start();
if ($canUpdate): ?>
    <a class="btn btn-secondary" href="<?= Html::url('/clients/' . $client->id() . '/modifier') ?>" data-modal="Clients"><?php $name = 'edit'; include $icon; ?>Modifier</a>
<?php endif;
if ($canArchive && !$client->isArchived()): ?>
    <form method="post" action="<?= Html::url('/clients/' . $client->id() . '/archiver') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <button class="btn btn-ghost" type="submit">Archiver</button>
    </form>
<?php elseif ($canArchive && $client->isArchived()): ?>
    <form method="post" action="<?= Html::url('/clients/' . $client->id() . '/restaurer') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <button class="btn btn-primary" type="submit">Restaurer</button>
    </form>
<?php endif;
$heroActions = ob_get_clean() ?: '';

$heroStats = [
    ['label' => 'Notes', 'value' => (string) count($notes)],
    ['label' => 'Activités', 'value' => (string) count($activities)],
];
if ($isBoutique) {
    $heroStats[] = ['label' => 'Commandes', 'value' => (string) count($boutiqueOrders), 'hint' => $spent > 0 ? Money::format($spent) . ' confirmés' : ''];
}
if ($isSalon) {
    $heroStats[] = ['label' => 'Visites', 'value' => (string) $visits, 'hint' => count($appointments) . ' RDV au total'];
}

$contact = array_filter([
    $client->phone(),
    $client->email(),
], static fn ($v) => $v !== null && $v !== '');
$createdLabel = $client->createdAt() !== '' ? gmdate('d/m/Y', (int) strtotime($client->createdAt()) ?: time()) : '—';
$heroSubtitle = ($contact !== [] ? implode(' · ', $contact) : 'Coordonnées à compléter') . ' — Client depuis le ' . $createdLabel;
$heroSubtitle = Html::e($heroSubtitle);

ob_start();
foreach ($client->tags() as $tag) {
    echo '<span class="badge">' . Html::e($tag->name()) . '</span>';
}
$heroBadgesHtml = ob_get_clean() ?: '';

$heroBackHref = '/clients';
$heroBackLabel = 'Clients';
$heroTitle = $client->fullName();
$heroTone = $isBoutique ? 'electric' : ($isSalon ? 'coral' : 'teal');
$heroInitials = Html::initials($client->fullName());
$heroStatusText = $client->isArchived() ? 'Archivé' : 'Actif';
$heroStatusTone = $client->isArchived() ? 'muted' : 'success';

?>
<section class="record-detail crm-detail">
    <?php require dirname(__DIR__, 2) . '/partials/record-hero.php'; ?>

    <div class="record-body is-split">
        <article class="card record-panel">
            <?php $panelTitle = 'Coordonnées'; $panelIcon = 'person'; require dirname(__DIR__, 2) . '/partials/record-panel-head.php'; ?>
            <dl class="record-facts is-grid">
                <div>
                    <dt>E-mail</dt>
                    <dd><?= $client->email() !== null ? Html::e($client->email()) : '—' ?></dd>
                </div>
                <div>
                    <dt>Téléphone</dt>
                    <dd><?= $client->phone() !== null ? Html::e($client->phone()) : '—' ?></dd>
                </div>
                <div>
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
                </div>
                <div>
                    <dt>Statut</dt>
                    <dd><?= $client->isArchived() ? 'Archivé' : 'Actif' ?></dd>
                </div>
            </dl>
        </article>

        <article class="card record-panel">
            <?php $panelTitle = 'Notes'; $panelIcon = 'chat'; require dirname(__DIR__, 2) . '/partials/record-panel-head.php'; ?>
            <?php if ($notes === []): ?>
                <p class="muted">Aucune note pour l'instant. Notez préférences, tailles ou relances importantes.</p>
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
                        <textarea class="input" id="body" name="body" rows="4" maxlength="5000" required placeholder="Ex. préfère être contactée le matin…"></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Publier</button>
                </form>
            <?php endif; ?>
        </article>
    </div>

    <?php if ($isBoutique): ?>
        <article class="card record-panel">
            <?php
            ob_start();
            if ($canOrder): ?>
                <a class="btn btn-secondary btn-sm" href="<?= Html::url('/boutique/commandes/nouveau?client=' . $client->id()) ?>"><?php $name = 'plus'; include $icon; ?>Noter une vente</a>
            <?php endif;
            $panelActions = ob_get_clean() ?: '';
            $panelTitle = 'Commandes boutique';
            $panelIcon = 'bag';
            require dirname(__DIR__, 2) . '/partials/record-panel-head.php';
            ?>
            <?php if ($boutiqueOrders === []): ?>
                <p class="muted">Aucune commande. Partagez votre lien boutique pour recevoir des commandes en ligne.</p>
            <?php else: ?>
                <ul class="record-timeline">
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
        <article class="card record-panel">
            <?php
            ob_start();
            if ($canBook): ?>
                <a class="btn btn-secondary btn-sm" href="<?= Html::url('/reservation/rendez-vous/nouveau?client=' . $client->id()) ?>"><?php $name = 'plus'; include $icon; ?>Nouveau RDV</a>
            <?php endif;
            $panelActions = ob_get_clean() ?: '';
            $panelTitle = 'Rendez-vous';
            $panelIcon = 'calendar';
            require dirname(__DIR__, 2) . '/partials/record-panel-head.php';
            ?>
            <?php if ($appointments === []): ?>
                <p class="muted">Aucun rendez-vous enregistré pour cette cliente.</p>
            <?php else: ?>
                <ul class="record-timeline">
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

    <article class="card record-panel">
        <?php $panelTitle = 'Historique'; $panelIcon = 'clock'; $panelActions = ''; require dirname(__DIR__, 2) . '/partials/record-panel-head.php'; ?>
        <?php if ($activities === []): ?>
            <p class="muted">Aucune activité enregistrée.</p>
        <?php else: ?>
            <ul class="record-timeline">
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
</section>
