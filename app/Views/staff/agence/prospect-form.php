<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

require __DIR__ . '/_helpers.php';

$prospectId = is_int($prospectId ?? null) ? $prospectId : null;
$old = is_array($old ?? null) ? $old : [];
$activities = is_array($activities ?? null) ? $activities : [];
$value = static function (string $key) use ($old): string {
    $item = $old[$key] ?? '';

    return Html::e(is_scalar($item) ? (string) $item : '');
};
$action = $prospectId === null ? Html::url('/agence/prospects') : Html::url('/agence/prospects/' . $prospectId);
$back = $prospectId === null ? Html::url('/agence/prospects') : Html::url('/agence/prospects/' . $prospectId);
$currentActivity = is_string($old['activity'] ?? null) ? $old['activity'] : '';

?>
<section class="console agency">
    <p class="console-back"><a href="<?= $back ?>">← <?= $prospectId === null ? 'Tous les prospects' : 'Retour à la fiche' ?></a></p>
    <div class="dash-card agency-form-card">
        <div class="dash-card-head">
            <h1 class="agency-form-title"><?= $prospectId === null ? 'Nouveau prospect' : 'Modifier le prospect' ?></h1>
        </div>
        <form class="agency-form" method="post" action="<?= $action ?>" novalidate>
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <div class="agency-form-grid">
                <div class="field">
                    <label for="full_name"><span>Nom du contact</span></label>
                    <input class="input<?= $invalid('full_name') ?>" id="full_name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?= $value('full_name') ?>">
                    <?= $fieldError('full_name') ?>
                </div>
                <div class="field">
                    <label for="business_name"><span>Commerce (facultatif)</span></label>
                    <input class="input<?= $invalid('business_name') ?>" id="business_name" name="business_name" type="text" maxlength="120" autocomplete="organization" value="<?= $value('business_name') ?>">
                    <?= $fieldError('business_name') ?>
                </div>
                <div class="field">
                    <label for="phone"><span>Téléphone</span></label>
                    <input class="input<?= $invalid('phone') ?>" id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="77 000 00 00" value="<?= $value('phone') ?>">
                    <?= $fieldError('phone') ?>
                </div>
                <div class="field">
                    <label for="email"><span>E-mail</span></label>
                    <input class="input<?= $invalid('email') ?>" id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= $value('email') ?>">
                    <?= $fieldError('email') ?>
                </div>
                <div class="field">
                    <label for="activity"><span>Activité</span></label>
                    <select class="select<?= $invalid('activity') ?>" id="activity" name="activity" required>
                        <option value="">Choisir…</option>
                        <?php foreach ($activities as $slug => $label): ?>
                            <option value="<?= Html::e((string) $slug) ?>"<?= $currentActivity === $slug ? ' selected' : '' ?>><?= Html::e((string) $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fieldError('activity') ?>
                </div>
                <div class="field">
                    <label for="next_action_on"><span>Prochaine relance (facultatif)</span></label>
                    <input class="input<?= $invalid('next_action_on') ?>" id="next_action_on" name="next_action_on" type="date" value="<?= $value('next_action_on') ?>">
                    <?= $fieldError('next_action_on') ?>
                </div>
            </div>
            <p class="muted agency-hint">Un téléphone ou un e-mail suffit. Rappel interne : les cliniques et établissements de santé ne sont pas démarchés.</p>
            <div class="agency-form-actions">
                <button class="btn btn-primary" type="submit"><?= $prospectId === null ? 'Ajouter au pipeline' : 'Enregistrer' ?></button>
                <a class="btn btn-ghost" href="<?= $back ?>">Annuler</a>
            </div>
        </form>
    </div>
</section>
