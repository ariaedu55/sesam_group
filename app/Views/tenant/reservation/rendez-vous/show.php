<?php

declare(strict_types=1);

use Sesam\Core\Reservation\AppointmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationAppointment;
use Sesam\Services\Reservation\BookingService;

$detail = is_array($detail ?? null) ? $detail : [];
$appointment = ($appointment ?? null) instanceof ReservationAppointment ? $appointment : null;
$history = is_array($detail['history'] ?? null) ? $detail['history'] : [];
$whatsapp = (string) ($detail['whatsapp'] ?? '');
$payment = ($detail['payment'] ?? null) instanceof \Sesam\Models\Payment ? $detail['payment'] : null;
$paymentLabels = ['success' => 'payé', 'pending' => 'en attente de paiement', 'failed' => 'échoué', 'cancelled' => 'annulé'];
$when = is_string($when ?? null) ? $when : '';
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$canPay = (bool) ($canPay ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
if ($appointment === null) {
    return;
}
$status = $appointment->status();
$actions = match ($status) {
    AppointmentStatus::PENDING => [
        [AppointmentStatus::CONFIRMED, 'Confirmer', 'btn-primary', 'check'],
        [AppointmentStatus::CANCELLED, 'Annuler', 'btn-ghost', 'close'],
    ],
    AppointmentStatus::CONFIRMED => [
        [AppointmentStatus::COMPLETED, 'Prestation réalisée', 'btn-primary', 'check'],
        [AppointmentStatus::NO_SHOW, 'Cliente absente', 'btn-secondary', 'person'],
        [AppointmentStatus::CANCELLED, 'Annuler', 'btn-ghost', 'close'],
    ],
    AppointmentStatus::CANCELLED, AppointmentStatus::NO_SHOW => [[AppointmentStatus::PENDING, 'Réactiver', 'btn-secondary', 'clock']],
    AppointmentStatus::COMPLETED => [[AppointmentStatus::CONFIRMED, 'Annuler « réalisée »', 'btn-ghost', 'close']],
    default => [],
};
$active = in_array($status, AppointmentStatus::active(), true);

$heroBackHref = '/reservation/rendez-vous';
$heroBackLabel = 'Rendez-vous';
$heroTitle = $appointment->clientName();
$heroSubtitle = $appointment->serviceName() . ' · ' . ucfirst($when) . ($appointment->staffName() !== '' ? ' · avec ' . $appointment->staffName() : '');
$heroTone = 'coral';
$heroInitials = Html::initials($appointment->clientName());
$heroStatusText = AppointmentStatus::label($status);
$heroStatusTone = AppointmentStatus::tone($status);
$heroBadgesHtml = $appointment->isPaid() ? '<span class="btq-status btq-status-success">Payé</span>' : '';
$heroStats = [
    ['label' => 'Prix', 'value' => BookingService::money($appointment->priceMinor())],
    ['label' => 'Durée', 'value' => $appointment->durationMinutes() . ' min'],
    ['label' => 'Acompte', 'value' => $appointment->depositAmountMinor() >= 100 ? BookingService::money($appointment->depositAmountMinor()) : '—'],
    ['label' => 'Origine', 'value' => $appointment->source() === 'online' ? 'En ligne' : 'Salon'],
];
$heroActions = '';

?>
<section class="record-detail btq">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert"><?php foreach ($errors as $message): ?><p><?= Html::e((string) $message) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="record-body is-two btq-two">
        <div class="btq">
            <?php if ($canManage && ($actions !== [] || $active)): ?>
                <section class="btq-card record-panel">
                    <?php $panelTitle = 'Actions'; $panelIcon = 'spark'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                    <div class="btq-big-actions">
                        <?php foreach ($actions as [$next, $label, $class, $iconName]): ?>
                            <form method="post" action="<?= Html::url('/reservation/rendez-vous/' . $appointment->id() . '/statut') ?>">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <input type="hidden" name="status" value="<?= Html::e($next) ?>">
                                <button class="btn <?= Html::e($class) ?>" type="submit"><?php $name = $iconName; include $icon; ?><?= Html::e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                        <?php if ($active): ?>
                            <a class="btn btn-secondary" href="<?= Html::url('/reservation/rendez-vous/' . $appointment->id() . '/reprogrammer') ?>"><?php $name = 'calendar'; include $icon; ?>Déplacer</a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="btq-card record-panel">
                <?php $panelTitle = 'Détails du rendez-vous'; $panelIcon = 'calendar'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <dl class="record-facts is-grid">
                    <div><dt>Prestation</dt><dd><?= Html::e($appointment->serviceName()) ?></dd></div>
                    <div><dt>Quand</dt><dd><?= Html::e(ucfirst($when)) ?> (<?= Html::e((string) $appointment->durationMinutes()) ?> min)</dd></div>
                    <div><dt>Avec</dt><dd><?= $appointment->staffName() !== '' ? Html::e($appointment->staffName()) : 'Non attribué' ?></dd></div>
                    <div><dt>Prix</dt><dd><?= Html::e(BookingService::money($appointment->priceMinor())) ?></dd></div>
                    <?php if ($appointment->depositAmountMinor() >= 100): ?><div><dt>Acompte</dt><dd><?= Html::e(BookingService::money($appointment->depositAmountMinor())) ?><?php if ($payment !== null): ?> — <a href="<?= Html::url('/paiements/' . $payment->id()) ?>"><?= Html::e($paymentLabels[$payment->status()] ?? $payment->status()) ?><?= $payment->paidAt() !== null ? ' le ' . Html::e(gmdate('d/m à H:i', (int) strtotime($payment->paidAt() . ' UTC'))) : '' ?></a><?php endif; ?></dd></div><?php endif; ?>
                    <div><dt>Origine</dt><dd><?= $appointment->source() === 'online' ? 'Réservé en ligne' : 'Saisi au salon' ?></dd></div>
                    <?php if ($appointment->notes() !== ''): ?><div><dt>Note</dt><dd><?= nl2br(Html::e($appointment->notes())) ?></dd></div><?php endif; ?>
                </dl>
            </section>
        </div>

        <div class="btq">
            <section class="btq-card record-panel">
                <?php $panelTitle = 'Cliente'; $panelIcon = 'person'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <dl class="record-facts">
                    <div><dt>Nom</dt><dd><?= Html::e($appointment->clientName()) ?></dd></div>
                    <?php if ($appointment->clientPhone() !== ''): ?><div><dt>Téléphone</dt><dd><a href="tel:<?= Html::e(preg_replace('/[^\d+]/', '', $appointment->clientPhone()) ?? '') ?>"><?= Html::e($appointment->clientPhone()) ?></a></dd></div><?php endif; ?>
                </dl>
                <div class="btq-big-actions">
                    <?php if ($whatsapp !== ''): ?>
                        <a class="btn btq-whatsapp" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener"><?php $name = 'chat'; include $icon; ?>Écrire sur WhatsApp</a>
                    <?php endif; ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/clients/' . $appointment->clientId()) ?>"><?php $name = 'person'; include $icon; ?>Fiche cliente</a>
                </div>
                <?php if ($history !== []): ?>
                    <h3 style="margin: 16px 0 8px; font-size: 1rem">Ses autres rendez-vous</h3>
                    <ul class="btq-list">
                        <?php foreach ($history as $item): ?>
                            <?php if (!$item instanceof ReservationAppointment) { continue; } ?>
                            <li><a href="<?= Html::url('/reservation/rendez-vous/' . $item->id()) ?>"><span><strong><?= Html::e(gmdate('d/m/Y', (int) strtotime($item->startsAt() . ' UTC'))) ?></strong><small><?= Html::e($item->serviceName()) ?></small></span><span class="btq-status btq-status-<?= Html::e(AppointmentStatus::tone($item->status())) ?>"><?= Html::e(AppointmentStatus::label($item->status())) ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="btq-card record-panel">
                <?php $panelTitle = 'Paiement'; $panelIcon = 'coin'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <p><span class="btq-status btq-status-<?= $appointment->isPaid() ? 'success' : 'warning' ?>"><?= $appointment->isPaid() ? 'Payé' : 'Pas encore payé' ?></span></p>
                <div class="btq-big-actions">
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= Html::url('/reservation/rendez-vous/' . $appointment->id() . '/paiement') ?>">
                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                            <button class="btn btn-secondary" type="submit"><?= $appointment->isPaid() ? 'Marquer non payé' : 'Marquer payé' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canManage && $canPay && $active && $appointment->depositAmountMinor() >= 100 && $appointment->paymentId() === null): ?>
                        <form method="post" action="<?= Html::url('/reservation/rendez-vous/' . $appointment->id() . '/acompte') ?>">
                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                            <button class="btn btn-primary" type="submit">Demander l'acompte (Wave / Orange Money)</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</section>
