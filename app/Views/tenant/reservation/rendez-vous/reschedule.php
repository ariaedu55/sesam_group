<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ReservationAppointment;
use Sesam\Models\ReservationTeamMember;

$appointment = ($appointment ?? null) instanceof ReservationAppointment ? $appointment : null;
$catalog = is_array($catalog ?? null) ? $catalog : [];
$team = is_array($catalog['team'] ?? null) ? $catalog['team'] : [];
$errors = is_array($errors ?? null) ? $errors : [];
if ($appointment === null) {
    return;
}

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Déplacer le rendez-vous</h1>
            <p class="muted"><?= Html::e($appointment->clientName()) ?> · <?= Html::e($appointment->serviceName()) ?> · actuellement le <?= Html::e(gmdate('d/m/Y à H:i', (int) strtotime($appointment->startsAt() . ' UTC'))) ?></p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/reservation/rendez-vous/' . $appointment->id()) ?>">Retour</a>
    </header>
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert"><?php foreach ($errors as $message): ?><p><?= Html::e((string) $message) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form class="btq-form" method="post" action="<?= Html::url('/reservation/rendez-vous/' . $appointment->id() . '/reprogrammer') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <fieldset>
            <legend>Nouveau créneau</legend>
            <?php if ($team !== []): ?>
                <div class="field">
                    <label for="staff_id"><span>Avec</span></label>
                    <select class="input" id="staff_id" name="staff_id">
                        <option value="">Peu importe</option>
                        <?php foreach ($team as $member): ?>
                            <?php if (!$member instanceof ReservationTeamMember) { continue; } ?>
                            <option value="<?= $member->id() ?>"<?= $appointment->staffId() === $member->id() ? ' selected' : '' ?>><?= Html::e($member->name()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="slot-picker" data-slot-picker="<?= Html::url('/reservation/creneaux') ?>" data-service="<?= $appointment->serviceId() ?>">
                <div class="field">
                    <label for="slot-date"><span>Jour</span></label>
                    <input class="input" id="slot-date" type="date" data-slot-date value="<?= Html::e(substr($appointment->startsAt(), 0, 10)) ?>">
                </div>
                <div class="slot-grid" data-slot-grid aria-live="polite"></div>
                <div class="field">
                    <label for="starts_at"><span>Date et heure</span></label>
                    <input class="input" id="starts_at" name="starts_at" type="datetime-local" required value="<?= Html::e(str_replace(' ', 'T', substr($appointment->startsAt(), 0, 16))) ?>">
                    <?php if (isset($errors['starts_at'])): ?><p class="field-error"><?= Html::e($errors['starts_at']) ?></p><?php endif; ?>
                </div>
            </div>
        </fieldset>
        <div class="btq-sticky-save">
            <button class="btn btn-primary" type="submit">Déplacer</button>
        </div>
    </form>
</section>
