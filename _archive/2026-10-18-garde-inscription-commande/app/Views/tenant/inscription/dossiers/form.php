<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;
use Sesam\Models\InscriptionClass;
use Sesam\Models\InscriptionFeeType;

$catalog = is_array($catalog ?? null) ? $catalog : [];
$classes = is_array($catalog['classes'] ?? null) ? $catalog['classes'] : [];
$feeTypes = is_array($catalog['feeTypes'] ?? null) ? $catalog['feeTypes'] : [];
$clients = is_array($catalog['clients'] ?? null) ? $catalog['clients'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
/** @var list<string|int> $selectedFees */
$selectedFees = is_array($old['fee_type_ids'] ?? null) ? $old['fee_type_ids'] : [];

?>
<section>
    <h1>Nouvelle inscription</h1>
    <form class="stack" method="post" action="<?= Html::url('/inscription/dossiers') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="student_name"><span>Nom de l'élève</span></label>
            <input class="input" id="student_name" name="student_name" required maxlength="120" value="<?= Html::e((string) ($old['student_name'] ?? '')) ?>">
            <?php if (isset($errors['student_name'])): ?><p class="field-error"><?= Html::e($errors['student_name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="class_id"><span>Classe</span></label>
            <select class="input" id="class_id" name="class_id" required>
                <option value="">— Choisir —</option>
                <?php foreach ($classes as $class): ?>
                    <?php if (!$class instanceof InscriptionClass) { continue; } ?>
                    <option value="<?= Html::e((string) $class->id()) ?>"<?= (string) ($old['class_id'] ?? '') === (string) $class->id() ? ' selected' : '' ?>><?= Html::e($class->name()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['class_id'])): ?><p class="field-error"><?= Html::e($errors['class_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="guardian_client_id"><span>Responsable (client CRM)</span></label>
            <select class="input" id="guardian_client_id" name="guardian_client_id" required>
                <option value="">— Choisir —</option>
                <?php foreach ($clients as $client): ?>
                    <?php if (!$client instanceof Client) { continue; } ?>
                    <option value="<?= Html::e((string) $client->id()) ?>"<?= (string) ($old['guardian_client_id'] ?? '') === (string) $client->id() ? ' selected' : '' ?>><?= Html::e($client->fullName()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['guardian_client_id'])): ?><p class="field-error"><?= Html::e($errors['guardian_client_id']) ?></p><?php endif; ?>
            <p class="muted"><a href="<?= Html::url('/clients/nouveau') ?>">Ajouter un responsable</a></p>
        </div>
        <div class="field">
            <label for="first_due_date"><span>Date de la 1<sup>re</sup> tranche</span></label>
            <input class="input" id="first_due_date" name="first_due_date" type="date" required value="<?= Html::e((string) ($old['first_due_date'] ?? '')) ?>">
            <?php if (isset($errors['first_due_date'])): ?><p class="field-error"><?= Html::e($errors['first_due_date']) ?></p><?php endif; ?>
        </div>
        <fieldset class="field">
            <legend>Frais à appliquer</legend>
            <?php if ($feeTypes === []): ?>
                <p class="muted">Créez d'abord un type de frais.</p>
            <?php else: ?>
                <?php foreach ($feeTypes as $fee): ?>
                    <?php if (!$fee instanceof InscriptionFeeType) { continue; } ?>
                    <?php $feeId = (string) $fee->id(); ?>
                    <label class="checkbox">
                        <input type="checkbox" name="fee_type_ids[]" value="<?= Html::e($feeId) ?>"<?= in_array($feeId, $selectedFees, true) || in_array((int) $feeId, array_map('intval', $selectedFees), true) ? ' checked' : '' ?>>
                        <?= Html::e($fee->name()) ?> — <?= Html::e(number_format($fee->totalMinor(), 0, ',', ' ')) ?> FCFA (<?= Html::e((string) $fee->installmentCount()) ?> tranches)
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (isset($errors['fee_type_ids'])): ?><p class="field-error"><?= Html::e($errors['fee_type_ids']) ?></p><?php endif; ?>
        </fieldset>
        <p class="row">
            <button class="btn btn-primary" type="submit">Inscrire</button>
            <a class="btn btn-secondary" href="<?= Html::url('/inscription/dossiers') ?>">Annuler</a>
        </p>
    </form>
</section>
