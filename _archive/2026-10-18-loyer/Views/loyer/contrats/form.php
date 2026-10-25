<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;
use Sesam\Models\LoyerUnit;

$catalog = is_array($catalog ?? null) ? $catalog : ['clients' => [], 'units' => []];
$clients = is_array($catalog['clients'] ?? null) ? $catalog['clients'] : [];
$units = is_array($catalog['units'] ?? null) ? $catalog['units'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];

?>
<section>
    <h1>Nouveau contrat</h1>
    <form class="stack" method="post" action="<?= Html::url('/loyer/contrats') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="client_id"><span>Locataire (client CRM)</span></label>
            <select class="input" id="client_id" name="client_id" required>
                <option value="">— Choisir —</option>
                <?php foreach ($clients as $client): ?>
                    <?php if (!$client instanceof Client) { continue; } ?>
                    <option value="<?= Html::e((string) $client->id()) ?>"><?= Html::e($client->fullName()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['client_id'])): ?><p class="field-error"><?= Html::e($errors['client_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="unit_id"><span>Unité</span></label>
            <select class="input" id="unit_id" name="unit_id" required>
                <option value="">— Choisir —</option>
                <?php foreach ($units as $unit): ?>
                    <?php if (!$unit instanceof LoyerUnit) { continue; } ?>
                    <option value="<?= Html::e((string) $unit->id()) ?>" data-rent="<?= Html::e((string) $unit->defaultRentMinor()) ?>">
                        <?= Html::e($unit->label()) ?> (<?= Html::e(number_format($unit->defaultRentMinor(), 0, ',', ' ')) ?> FCFA)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['unit_id'])): ?><p class="field-error"><?= Html::e($errors['unit_id']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="rent_minor"><span>Loyer mensuel (FCFA)</span></label>
            <input class="input" id="rent_minor" name="rent_minor" type="number" min="100" required value="<?= Html::e((string) ($old['rent_minor'] ?? '')) ?>">
            <?php if (isset($errors['rent_minor'])): ?><p class="field-error"><?= Html::e($errors['rent_minor']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="due_day"><span>Jour d'échéance (1–28)</span></label>
            <input class="input" id="due_day" name="due_day" type="number" min="1" max="28" value="<?= Html::e((string) ($old['due_day'] ?? '5')) ?>">
            <?php if (isset($errors['due_day'])): ?><p class="field-error"><?= Html::e($errors['due_day']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="starts_on"><span>Début du contrat</span></label>
            <input class="input" id="starts_on" name="starts_on" type="date" required value="<?= Html::e((string) ($old['starts_on'] ?? '')) ?>">
            <?php if (isset($errors['starts_on'])): ?><p class="field-error"><?= Html::e($errors['starts_on']) ?></p><?php endif; ?>
        </div>
        <p class="muted">12 échéances mensuelles seront générées automatiquement.</p>
        <p class="row">
            <button class="btn btn-primary" type="submit">Créer</button>
            <a class="btn btn-secondary" href="<?= Html::url('/loyer/contrats') ?>">Annuler</a>
        </p>
    </form>
</section>
