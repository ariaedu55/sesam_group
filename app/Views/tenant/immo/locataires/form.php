<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$id = isset($id) && is_int($id) ? $id : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$idTypes = is_array($idTypes ?? null) ? $idTypes : [];
$val = static fn (string $key): string => Html::e(is_scalar($old[$key] ?? null) ? (string) $old[$key] : '');
$field = static function (string $key, string $label, string $type = 'text', string $extra = '') use ($val, $errors): void {
    echo '<div class="field"><label for="' . $key . '"><span>' . Html::e($label) . '</span></label>';
    echo '<input class="input' . (isset($errors[$key]) ? ' is-invalid' : '') . '" id="' . $key . '" name="' . $key . '" type="' . $type . '" value="' . $val($key) . '"' . $extra . '>';
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
    echo '</div>';
};

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1><?= $id === null ? 'Nouveau locataire' : 'Modifier le locataire' ?></h1>
            <p class="muted">Seuls le nom et le téléphone sont obligatoires. Le reste sert en cas de litige ou d'urgence.</p>
        </div>
    </header>
    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert">Vérifiez les informations en rouge.</p><?php endif; ?>
    <form class="btq-form" method="post" action="<?= Html::url('/immo/locataires' . ($id !== null ? '/' . $id : '')) ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <fieldset>
            <legend>Coordonnées</legend>
            <?php $field('full_name', 'Nom complet', 'text', ' required maxlength="120"'); ?>
            <div class="btq-grid-2">
                <?php $field('phone', 'Téléphone (WhatsApp)', 'tel', ' required maxlength="40" placeholder="77 123 45 67"'); ?>
                <?php $field('email', 'E-mail (facultatif)', 'email', ' maxlength="190"'); ?>
            </div>
        </fieldset>
        <fieldset>
            <legend>Identité et situation</legend>
            <div class="btq-grid-2">
                <div class="field">
                    <label for="id_type"><span>Pièce d'identité</span></label>
                    <select class="input" id="id_type" name="id_type">
                        <option value="">—</option>
                        <?php foreach ($idTypes as $key => $label): ?><option value="<?= Html::e($key) ?>"<?= ($old['id_type'] ?? '') === $key ? ' selected' : '' ?>><?= Html::e($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <?php $field('id_number', 'Numéro de la pièce', 'text', ' maxlength="60"'); ?>
                <?php $field('profession', 'Profession', 'text', ' maxlength="120"'); ?>
                <?php $field('employer', 'Employeur', 'text', ' maxlength="120"'); ?>
            </div>
        </fieldset>
        <fieldset>
            <legend>Contacts de confiance</legend>
            <div class="btq-grid-2">
                <?php $field('emergency_name', 'Contact d\'urgence', 'text', ' maxlength="120"'); ?>
                <?php $field('emergency_phone', 'Téléphone du contact', 'tel', ' maxlength="40"'); ?>
                <?php $field('guarantor_name', 'Garant', 'text', ' maxlength="120"'); ?>
                <?php $field('guarantor_phone', 'Téléphone du garant', 'tel', ' maxlength="40"'); ?>
            </div>
            <div class="field">
                <label for="notes"><span>Notes</span></label>
                <textarea class="input" id="notes" name="notes" rows="3" maxlength="1000"><?= $val('notes') ?></textarea>
            </div>
        </fieldset>
        <div class="btq-sticky-save">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <a class="btn btn-ghost" href="<?= Html::url($id !== null ? '/immo/locataires/' . $id : '/immo/locataires') ?>">Annuler</a>
        </div>
    </form>
</section>
