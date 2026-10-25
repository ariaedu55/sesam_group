<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

$catalog = is_array($catalog ?? null) ? $catalog : [];
$units = is_array($catalog['units'] ?? null) ? $catalog['units'] : [];
$tenants = is_array($catalog['tenants'] ?? null) ? $catalog['tenants'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$val = static fn (string $key, string $default = ''): string => Html::e(is_scalar($old[$key] ?? null) && (string) $old[$key] !== '' ? (string) $old[$key] : $default);
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$selectedUnit = null;
foreach ($units as $unit) {
    if ((string) $unit['id'] === (string) ($old['unit_id'] ?? '')) {
        $selectedUnit = $unit;
    }
}

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Nouveau contrat de bail</h1>
            <p class="muted">Choisissez le logement et le locataire : les loyers de chaque mois sont créés automatiquement.</p>
        </div>
    </header>

    <?php if ($units === []): ?>
        <div class="btq-card empty"><p>Aucun logement libre. Ajoutez un logement à un bien, ou terminez un bail.</p><p><a class="btn btn-primary" href="<?= Html::url('/immo/biens') ?>">Voir les biens</a></p></div>
    <?php else: ?>
        <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert">Vérifiez les informations en rouge.</p><?php endif; ?>
        <form class="btq-form" method="post" action="<?= Html::url('/immo/baux') ?>" data-lease-form>
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <fieldset>
                <legend>1. Le logement</legend>
                <div class="field">
                    <label for="unit_id"><span>Logement libre</span></label>
                    <select class="input<?= isset($errors['unit_id']) ? ' is-invalid' : '' ?>" id="unit_id" name="unit_id" required data-lease-unit>
                        <option value="">Choisir…</option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>" data-rent="<?= (int) $unit['default_rent_minor'] ?>" data-charges="<?= (int) $unit['default_charges_minor'] ?>" data-deposit="<?= (int) $unit['default_deposit_minor'] ?>"<?= (string) ($old['unit_id'] ?? '') === (string) $unit['id'] ? ' selected' : '' ?>><?= Html::e($unit['property_name'] . ' — ' . $unit['label']) ?> (<?= Html::e(Money::format((int) $unit['default_rent_minor'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php $err('unit_id'); ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Le locataire</legend>
                <div class="field">
                    <label for="client_id"><span>Locataire déjà enregistré</span></label>
                    <select class="input<?= isset($errors['client_id']) ? ' is-invalid' : '' ?>" id="client_id" name="client_id" data-lease-client>
                        <option value="">Nouveau locataire (saisir ci-dessous)</option>
                        <?php foreach ($tenants as $tenant): ?>
                            <option value="<?= (int) $tenant['id'] ?>"<?= (string) ($old['client_id'] ?? '') === (string) $tenant['id'] ? ' selected' : '' ?>><?= Html::e($tenant['full_name']) ?><?= $tenant['phone'] ? ' — ' . Html::e($tenant['phone']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php $err('client_id'); ?>
                </div>
                <div class="btq-grid-2" data-lease-new>
                    <div class="field"><label for="full_name"><span>Nom du nouveau locataire</span></label><input class="input<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>" id="full_name" name="full_name" maxlength="120" value="<?= $val('full_name') ?>"><?php $err('full_name'); ?></div>
                    <div class="field"><label for="phone"><span>Téléphone (WhatsApp)</span></label><input class="input<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="phone" name="phone" type="tel" maxlength="40" value="<?= $val('phone') ?>"><?php $err('phone'); ?></div>
                    <div class="field"><label for="email"><span>E-mail (facultatif)</span></label><input class="input" id="email" name="email" type="email" maxlength="190" value="<?= $val('email') ?>"></div>
                </div>
                <p class="btq-help">Pièce d'identité, garant et contact d'urgence se complètent ensuite sur la fiche du locataire.</p>
            </fieldset>

            <fieldset>
                <legend>3. Les conditions</legend>
                <div class="btq-grid-2">
                    <div class="field"><label for="rent_minor"><span>Loyer mensuel (FCFA)</span></label><input class="input<?= isset($errors['rent_minor']) ? ' is-invalid' : '' ?>" id="rent_minor" name="rent_minor" inputmode="numeric" required value="<?= $val('rent_minor', (string) ($selectedUnit['default_rent_minor'] ?? '')) ?>" data-lease-rent><?php $err('rent_minor'); ?></div>
                    <div class="field"><label for="charges_minor"><span>Charges mensuelles (FCFA)</span></label><input class="input" id="charges_minor" name="charges_minor" inputmode="numeric" value="<?= $val('charges_minor', (string) ($selectedUnit['default_charges_minor'] ?? '0')) ?>" data-lease-charges></div>
                    <div class="field"><label for="deposit_minor"><span>Caution (FCFA)</span></label><input class="input" id="deposit_minor" name="deposit_minor" inputmode="numeric" value="<?= $val('deposit_minor', (string) ($selectedUnit['default_deposit_minor'] ?? '0')) ?>" data-lease-deposit></div>
                    <div class="field"><label for="due_day"><span>Jour d'échéance</span></label><input class="input<?= isset($errors['due_day']) ? ' is-invalid' : '' ?>" id="due_day" name="due_day" type="number" min="1" max="28" value="<?= $val('due_day', '5') ?>"><?php $err('due_day'); ?></div>
                    <div class="field"><label for="starts_on"><span>Date d'entrée</span></label><input class="input<?= isset($errors['starts_on']) ? ' is-invalid' : '' ?>" id="starts_on" name="starts_on" type="date" required value="<?= $val('starts_on') ?>"><?php $err('starts_on'); ?></div>
                    <div class="field"><label for="duration_months"><span>Durée (mois, 0 = sans fin)</span></label><input class="input<?= isset($errors['duration_months']) ? ' is-invalid' : '' ?>" id="duration_months" name="duration_months" type="number" min="0" max="120" value="<?= $val('duration_months', '12') ?>"><?php $err('duration_months'); ?></div>
                    <div class="field"><label for="notice_months"><span>Préavis (mois)</span></label><input class="input" id="notice_months" name="notice_months" type="number" min="0" max="12" value="<?= $val('notice_months', '1') ?>"></div>
                </div>
                <label class="btq-switch"><input type="checkbox" name="deposit_paid" value="1"<?= ($old['deposit_paid'] ?? '') !== '' ? ' checked' : '' ?>><span><strong>La caution est déjà versée</strong><span class="btq-help">Sinon, une alerte vous le rappellera.</span></span></label>
                <div class="field"><label for="notes"><span>Notes (état des lieux, clauses…)</span></label><textarea class="input" id="notes" name="notes" rows="3" maxlength="1000"><?= $val('notes') ?></textarea></div>
            </fieldset>

            <div class="btq-sticky-save">
                <button class="btn btn-primary" type="submit">Créer le contrat de bail</button>
                <a class="btn btn-ghost" href="<?= Html::url('/immo/baux') ?>">Annuler</a>
            </div>
        </form>
    <?php endif; ?>
</section>
