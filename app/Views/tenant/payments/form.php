<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$clients = is_array($clients ?? null) ? $clients : [];
$sandboxNotice = (bool) ($sandboxNotice ?? false);

?>
<section>
    <h1>Nouveau paiement</h1>
    <?php if ($sandboxNotice): ?>
        <p class="alert" role="status">NabooPay est en <strong>mode provisoire</strong> : sans clé API, vous serez redirigé vers un bac à sable local. Aucun débit réel.</p>
    <?php endif; ?>
    <form class="stack" method="post" action="<?= Html::url('/paiements') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="amount_minor"><span>Montant (FCFA)</span></label>
            <input class="input" id="amount_minor" name="amount_minor" type="number" min="100" step="1" required value="<?= Html::e((string) ($old['amount_minor'] ?? '')) ?>">
            <?php if (isset($errors['amount_minor'])): ?><p class="field-error"><?= Html::e($errors['amount_minor']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="description"><span>Libellé</span></label>
            <input class="input" id="description" name="description" maxlength="255" required value="<?= Html::e((string) ($old['description'] ?? '')) ?>">
            <?php if (isset($errors['description'])): ?><p class="field-error"><?= Html::e($errors['description']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="client_id"><span>Client (optionnel)</span></label>
            <select class="input" id="client_id" name="client_id">
                <option value="">— Aucun —</option>
                <?php foreach ($clients as $client): ?>
                    <?php if (!$client instanceof Client) { continue; } ?>
                    <option value="<?= Html::e((string) $client->id()) ?>"<?= (string) ($old['client_id'] ?? '') === (string) $client->id() ? ' selected' : '' ?>><?= Html::e($client->fullName()) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['client_id'])): ?><p class="field-error"><?= Html::e($errors['client_id']) ?></p><?php endif; ?>
        </div>
        <div class="row">
            <button class="btn btn-primary" type="submit">Continuer vers NabooPay</button>
            <a class="btn btn-secondary" href="<?= Html::url('/paiements') ?>">Annuler</a>
        </div>
    </form>
</section>
