<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;
use Sesam\Models\ReservationPrestation;
use Sesam\Models\ReservationTeamMember;
use Sesam\Services\Reservation\BookingService;

$catalog = is_array($catalog ?? null) ? $catalog : [];
$prestations = is_array($catalog['prestations'] ?? null) ? $catalog['prestations'] : [];
$team = is_array($catalog['team'] ?? null) ? $catalog['team'] : [];
$clients = is_array($catalog['clients'] ?? null) ? $catalog['clients'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$val = static fn (string $key): string => is_scalar($old[$key] ?? null) ? (string) $old[$key] : '';
$pickedId = $val('client_id');
$pickedName = $val('client_name');
$pickedPhone = $val('client_phone');
if ($pickedId !== '') {
    $picked = null;
    foreach ($clients as $client) {
        if ($client instanceof Client && (string) $client->id() === $pickedId) {
            $picked = $client;
        }
    }
    if ($picked === null) {
        $pickedId = '';
    } else {
        $pickedName = $pickedName !== '' ? $pickedName : $picked->fullName();
        $pickedPhone = $pickedPhone !== '' ? $pickedPhone : (string) $picked->phone();
    }
}
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Nouveau rendez-vous</h1>
            <p class="muted">Pour une cliente qui appelle, écrit ou passe au salon. Les réservations en ligne arrivent toutes seules.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::url('/reservation/agenda') ?>">Agenda</a>
    </header>

    <?php if ($prestations === []): ?>
        <div class="empty btq-card">
            <p>Ajoutez d'abord une prestation (nom, durée, prix).</p>
            <p><a class="btn btn-primary" href="<?= Html::url('/reservation/prestations/nouveau') ?>">Ajouter une prestation</a></p>
        </div>
    <?php else: ?>
        <?php if ($errors !== []): ?>
            <p class="alert alert-danger" role="alert">Vérifiez les informations en rouge.</p>
        <?php endif; ?>
        <form class="btq-form" method="post" action="<?= Html::url('/reservation/rendez-vous') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

            <fieldset>
                <legend>1. La prestation</legend>
                <div class="btq-grid-2">
                    <div class="field">
                        <label for="service_id"><span>Prestation</span></label>
                        <select class="input" id="service_id" name="service_id" required>
                            <option value="">Choisir…</option>
                            <?php foreach ($prestations as $item): ?>
                                <?php if (!$item instanceof ReservationPrestation) { continue; } ?>
                                <option value="<?= $item->id() ?>"<?= $val('service_id') === (string) $item->id() ? ' selected' : '' ?>><?= Html::e($item->name()) ?> — <?= Html::e($item->durationLabel()) ?> — <?= Html::e(BookingService::money($item->priceMinor())) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php $err('service_id'); ?>
                    </div>
                    <div class="field">
                        <label for="staff_id"><span>Avec</span></label>
                        <select class="input" id="staff_id" name="staff_id">
                            <option value="">Peu importe</option>
                            <?php foreach ($team as $member): ?>
                                <?php if (!$member instanceof ReservationTeamMember) { continue; } ?>
                                <option value="<?= $member->id() ?>"<?= $val('staff_id') === (string) $member->id() ? ' selected' : '' ?>><?= Html::e($member->name()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php $err('staff_id'); ?>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Le créneau</legend>
                <div class="slot-picker" data-slot-picker="<?= Html::url('/reservation/creneaux') ?>">
                    <div class="field">
                        <label for="slot-date"><span>Jour</span></label>
                        <input class="input" id="slot-date" type="date" data-slot-date value="<?= Html::e($val('date') !== '' ? $val('date') : gmdate('Y-m-d')) ?>">
                    </div>
                    <div class="slot-grid" data-slot-grid aria-live="polite"></div>
                    <div class="field">
                        <label for="starts_at"><span>Date et heure retenues</span></label>
                        <input class="input" id="starts_at" name="starts_at" type="datetime-local" required value="<?= Html::e(str_replace(' ', 'T', substr($val('starts_at'), 0, 16))) ?>">
                        <p class="btq-help">Cliquez sur un créneau libre, ou saisissez l'heure vous-même.</p>
                        <?php $err('starts_at'); ?>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>3. La cliente</legend>
                <div class="client-pick" data-client-picker="<?= Html::url('/reservation/clientes') ?>">
                    <input type="hidden" name="client_id" value="<?= Html::e($pickedId) ?>" data-client-id>
                    <div class="btq-grid-2">
                        <div class="field client-pick-name">
                            <label for="client_name"><span>Nom de la cliente</span></label>
                            <input class="input<?= isset($errors['client_name']) ? ' is-invalid' : '' ?>" id="client_name" name="client_name" maxlength="120" required autocomplete="off"
                                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="client-suggest" aria-describedby="client-help"
                                   placeholder="Tapez le nom ou le numéro…" value="<?= Html::e($pickedName) ?>" data-client-name>
                            <ul class="client-suggest" id="client-suggest" role="listbox" aria-label="Clientes déjà enregistrées" hidden data-client-list></ul>
                            <?php $err('client_name'); ?>
                        </div>
                        <div class="field">
                            <label for="client_phone"><span>Numéro (WhatsApp)</span></label>
                            <input class="input<?= isset($errors['client_phone']) ? ' is-invalid' : '' ?>" id="client_phone" name="client_phone" type="tel" inputmode="tel" maxlength="40" required
                                   placeholder="Ex. 77 123 45 67" value="<?= Html::e($pickedPhone) ?>" data-client-phone>
                            <?php $err('client_phone'); ?>
                        </div>
                    </div>
                    <p class="client-picked" data-client-picked<?= $pickedId === '' ? ' hidden' : '' ?>>
                        <span>Cliente déjà enregistrée : sa fiche sera utilisée.</span>
                        <button class="btn btn-ghost btn-sm" type="button" data-client-clear>Nouvelle cliente</button>
                    </p>
                    <p class="btq-help" id="client-help">Commencez à taper : les clientes déjà enregistrées apparaissent, cliquez pour remplir. Sinon, la fiche de la cliente est créée à l'enregistrement.</p>
                </div>
                <div class="field">
                    <label for="notes"><span>Note (facultatif)</span></label>
                    <textarea class="input" id="notes" name="notes" rows="2" maxlength="500" placeholder="Ex. : cheveux longs, première visite…"><?= Html::e($val('notes')) ?></textarea>
                </div>
                <label class="btq-switch">
                    <input type="checkbox" name="confirm_now" value="1"<?= $val('confirm_now') !== '' || $old === [] ? ' checked' : '' ?>>
                    <span><strong>Confirmer tout de suite</strong><span class="btq-help">Le rappel WhatsApp sera envoyé la veille.</span></span>
                </label>
            </fieldset>

            <div class="btq-sticky-save">
                <button class="btn btn-primary" type="submit">Enregistrer le rendez-vous</button>
                <a class="btn btn-ghost" href="<?= Html::url('/reservation/rendez-vous') ?>">Annuler</a>
            </div>
        </form>
    <?php endif; ?>
</section>
