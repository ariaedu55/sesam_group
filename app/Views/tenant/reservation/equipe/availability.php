<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ReservationPrestation;
use Sesam\Models\ReservationTeamMember;
use Sesam\Services\Reservation\BookingService;

$member = ($member ?? null) instanceof ReservationTeamMember ? $member : null;
$prestations = is_array($prestations ?? null) ? $prestations : [];
$slotsByDay = is_array($slotsByDay ?? null) ? $slotsByDay : [];
$absences = is_array($absences ?? null) ? $absences : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
if ($member === null) {
    return;
}
$selected = array_map('strval', is_array($old['service_ids'] ?? null) ? $old['service_ids'] : $member->serviceIds());
$val = static fn (string $key, string $fallback): string => is_scalar($old[$key] ?? null) ? (string) $old[$key] : $fallback;

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1><?= Html::e($member->name()) ?></h1>
            <p class="muted"><?= $member->roleLabel() !== '' ? Html::e($member->roleLabel()) . ' · ' : '' ?><?= $member->isActive() ? 'Active' : 'Inactive' ?></p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/reservation/equipe') ?>">Toute l'équipe</a>
    </header>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger" role="alert"><?php foreach ($errors as $message): ?><p><?= Html::e((string) $message) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <div class="btq-two">
        <form class="btq-card btq-form" method="post" action="<?= Html::url('/reservation/equipe/' . $member->id() . '/disponibilites') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <h2>Horaires de travail</h2>
            <p class="btq-help">Laissez une journée vide si elle ne travaille pas. Sans aucun horaire, elle suit les horaires du salon.</p>
            <div class="hours-table">
                <?php for ($day = 1; $day <= 7; ++$day): ?>
                    <?php $slot = $slotsByDay[$day] ?? ['start' => '', 'end' => '']; ?>
                    <div class="hours-row">
                        <strong><?= Html::e(BookingService::weekdayLabel($day)) ?></strong>
                        <div class="hours-ranges">
                            <label class="sr-only" for="day_<?= $day ?>_start">Début <?= Html::e(BookingService::weekdayLabel($day)) ?></label>
                            <input class="input" type="time" id="day_<?= $day ?>_start" name="day_<?= $day ?>_start" value="<?= Html::e($slot['start']) ?>">
                            <span class="muted">à</span>
                            <label class="sr-only" for="day_<?= $day ?>_end">Fin <?= Html::e(BookingService::weekdayLabel($day)) ?></label>
                            <input class="input" type="time" id="day_<?= $day ?>_end" name="day_<?= $day ?>_end" value="<?= Html::e($slot['end']) ?>">
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <button class="btn btn-primary" type="submit">Enregistrer les horaires</button>
        </form>

        <div class="btq">
            <form class="btq-card btq-form" method="post" action="<?= Html::url('/reservation/equipe/' . $member->id()) ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <h2>Fiche</h2>
                <div class="field">
                    <label for="name"><span>Prénom et nom</span></label>
                    <input class="input" id="name" name="name" required maxlength="120" value="<?= Html::e($val('name', $member->name())) ?>">
                </div>
                <div class="btq-grid-2">
                    <div class="field">
                        <label for="role_label"><span>Métier</span></label>
                        <input class="input" id="role_label" name="role_label" maxlength="80" value="<?= Html::e($val('role_label', $member->roleLabel())) ?>">
                    </div>
                    <div class="field">
                        <label for="phone"><span>Téléphone</span></label>
                        <input class="input" id="phone" name="phone" type="tel" maxlength="40" value="<?= Html::e($val('phone', $member->phone())) ?>">
                    </div>
                </div>
                <?php if ($prestations !== []): ?>
                    <div class="field">
                        <span>Prestations réalisées <small class="muted">(rien de coché = toutes)</small></span>
                        <div class="chip-list">
                            <?php foreach ($prestations as $item): ?>
                                <?php if (!$item instanceof ReservationPrestation) { continue; } ?>
                                <label><input type="checkbox" name="service_ids[]" value="<?= $item->id() ?>"<?= in_array((string) $item->id(), $selected, true) ? ' checked' : '' ?>><?= Html::e($item->name()) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <label class="btq-switch"><input type="checkbox" name="is_active" value="1"<?= $member->isActive() ? ' checked' : '' ?>><span><strong>Active</strong><span class="btq-help">Décochez si elle a quitté le salon : son historique est conservé.</span></span></label>
                <button class="btn btn-primary" type="submit">Enregistrer la fiche</button>
            </form>

            <section class="btq-card" id="absences">
                <h2>Congés et absences</h2>
                <?php if ($absences === []): ?>
                    <p class="muted">Aucune absence prévue.</p>
                <?php else: ?>
                    <ul class="btq-list">
                        <?php foreach ($absences as $absence): ?>
                            <li>
                                <div>
                                    <span>
                                        <strong><?= Html::e(gmdate('d/m/Y', (int) strtotime($absence['starts_on']))) ?><?= $absence['ends_on'] !== $absence['starts_on'] ? ' → ' . Html::e(gmdate('d/m/Y', (int) strtotime($absence['ends_on']))) : '' ?></strong>
                                        <?php if ($absence['reason'] !== ''): ?><small><?= Html::e($absence['reason']) ?></small><?php endif; ?>
                                    </span>
                                    <form method="post" action="<?= Html::url('/reservation/equipe/' . $member->id() . '/absences/' . $absence['id'] . '/supprimer') ?>">
                                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                        <button class="btn btn-ghost btn-sm" type="submit">Retirer</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form class="btq-form" method="post" action="<?= Html::url('/reservation/equipe/' . $member->id() . '/absences') ?>" style="margin-top: 14px">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <div class="btq-grid-2">
                        <div class="field"><label for="starts_on"><span>Du</span></label><input class="input" id="starts_on" name="starts_on" type="date" required min="<?= Html::e(gmdate('Y-m-d')) ?>"></div>
                        <div class="field"><label for="ends_on"><span>Au (inclus)</span></label><input class="input" id="ends_on" name="ends_on" type="date"></div>
                    </div>
                    <div class="field"><label for="reason"><span>Motif (facultatif)</span></label><input class="input" id="reason" name="reason" maxlength="160" placeholder="Congés, formation…"></div>
                    <button class="btn btn-secondary" type="submit">Ajouter l'absence</button>
                </form>
            </section>
        </div>
    </div>
</section>
