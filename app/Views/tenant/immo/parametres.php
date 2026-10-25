<?php

declare(strict_types=1);

use Sesam\Core\Boutique\WhatsApp;
use Sesam\Helpers\Html;

$values = is_array($values ?? null) ? $values : [];
$errors = is_array($errors ?? null) ? $errors : [];
$val = static fn (string $key): string => Html::e(is_scalar($values[$key] ?? null) ? (string) $values[$key] : '');
$on = static fn (string $key): string => (string) ($values[$key] ?? '') === '1' || ($values[$key] ?? null) === 1 ? ' checked' : '';
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$phone = (string) ($values['whatsapp_phone'] ?? '');
$phoneShown = preg_match('/^\d{11,15}$/', $phone) === 1 ? WhatsApp::display($phone) : $phone;

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Réglages</h1>
            <p class="muted">Votre identité sur les quittances, les échéances, les relances automatiques et les paiements partiels.</p>
        </div>
    </header>
    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert">Vérifiez les informations en rouge.</p><?php endif; ?>
    <form class="btq-form" method="post" action="<?= Html::url('/immo/parametres') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <fieldset>
            <legend>Votre agence</legend>
            <div class="btq-grid-2">
                <div class="field"><label for="display_name"><span>Nom affiché</span></label><input class="input" id="display_name" name="display_name" maxlength="120" value="<?= $val('display_name') ?>"></div>
                <div class="field"><label for="whatsapp_phone"><span>WhatsApp de l'agence</span></label><input class="input<?= isset($errors['whatsapp_phone']) ? ' is-invalid' : '' ?>" id="whatsapp_phone" name="whatsapp_phone" type="tel" maxlength="40" value="<?= Html::e($phoneShown) ?>"><?php $err('whatsapp_phone'); ?></div>
                <div class="field"><label for="email"><span>E-mail</span></label><input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" maxlength="190" value="<?= $val('email') ?>"><?php $err('email'); ?></div>
                <div class="field"><label for="address"><span>Adresse</span></label><input class="input" id="address" name="address" maxlength="255" value="<?= $val('address') ?>"></div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Quittances</legend>
            <div class="btq-grid-2">
                <div class="field"><label for="signature_name"><span>Signataire</span></label><input class="input" id="signature_name" name="signature_name" maxlength="120" placeholder="Nom du gérant" value="<?= $val('signature_name') ?>"></div>
            </div>
            <div class="field"><label for="receipt_footer"><span>Mention en bas des quittances</span></label><input class="input" id="receipt_footer" name="receipt_footer" maxlength="500" placeholder="Ex. NINEA, RC, conditions…" value="<?= $val('receipt_footer') ?>"></div>
        </fieldset>

        <fieldset>
            <legend>Échéances et alertes</legend>
            <div class="btq-grid-2">
                <div class="field"><label for="default_due_day"><span>Jour d'échéance par défaut</span></label><input class="input<?= isset($errors['default_due_day']) ? ' is-invalid' : '' ?>" id="default_due_day" name="default_due_day" type="number" min="1" max="28" value="<?= $val('default_due_day') ?>"><?php $err('default_due_day'); ?></div>
                <div class="field"><label for="grace_days"><span>Délai de grâce (jours)</span></label><input class="input" id="grace_days" name="grace_days" type="number" min="0" max="30" value="<?= $val('grace_days') ?>"><p class="btq-help">Après ce délai, le loyer passe « en retard ».</p></div>
                <div class="field"><label for="lease_end_alert_days"><span>Alerte de fin de bail (jours avant)</span></label><input class="input" id="lease_end_alert_days" name="lease_end_alert_days" type="number" min="7" max="365" value="<?= $val('lease_end_alert_days') ?>"></div>
            </div>
        </fieldset>

        <fieldset id="relances">
            <legend>Relances automatiques (WhatsApp)</legend>
            <label class="btq-switch"><input type="checkbox" name="auto_reminders" value="1"<?= $on('auto_reminders') ?>><span><strong>Envoyer les relances automatiquement</strong><span class="btq-help">Chaque rappel n'est envoyé qu'une fois par jour et par loyer.</span></span></label>
            <div class="btq-grid-2">
                <div class="field"><label for="remind_before_days"><span>Rappel avant l'échéance (jours)</span></label><input class="input" id="remind_before_days" name="remind_before_days" type="number" min="0" max="30" value="<?= $val('remind_before_days') ?>"><p class="btq-help">0 = pas de rappel avant.</p></div>
                <div class="field"><label for="remind_after_days"><span>Relances après l'échéance (jours)</span></label><input class="input" id="remind_after_days" name="remind_after_days" maxlength="40" placeholder="3, 7, 15" value="<?= $val('remind_after_days') ?>"><p class="btq-help">Jusqu'à 5 relances, séparées par des virgules.</p></div>
            </div>
            <label class="btq-switch"><input type="checkbox" name="remind_on_due" value="1"<?= $on('remind_on_due') ?>><span><strong>Rappel le jour de l'échéance</strong></span></label>
            <div class="field">
                <label for="reminder_message"><span>Message de relance</span></label>
                <textarea class="input<?= isset($errors['reminder_message']) ? ' is-invalid' : '' ?>" id="reminder_message" name="reminder_message" rows="4" maxlength="600"><?= $val('reminder_message') ?></textarea>
                <?php $err('reminder_message'); ?>
                <p class="btq-help">Mots remplacés automatiquement : {locataire} {mois} {logement} {montant} {reste} {echeance} {agence}</p>
            </div>
        </fieldset>

        <fieldset>
            <legend>Paiements partiels</legend>
            <label class="btq-switch"><input type="checkbox" name="allow_partial" value="1"<?= $on('allow_partial') ?>><span><strong>Accepter les paiements en plusieurs fois</strong><span class="btq-help">Le reste reste dû et continue d'être relancé.</span></span></label>
            <div class="btq-grid-2">
                <div class="field"><label for="min_partial_minor"><span>Montant minimum d'un paiement partiel (FCFA)</span></label><input class="input" id="min_partial_minor" name="min_partial_minor" inputmode="numeric" value="<?= $val('min_partial_minor') ?>"></div>
            </div>
        </fieldset>

        <div class="btq-sticky-save"><button class="btn btn-primary" type="submit">Enregistrer les réglages</button></div>
    </form>
</section>
