<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;
use Sesam\Models\ClientTag;

$edited = ($edited ?? null) instanceof Client ? $edited : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$allTags = is_array($allTags ?? null) ? $allTags : [];
$canTags = (bool) ($canTags ?? false);
$selectedTags = is_array($old['tag_ids'] ?? null) ? array_map('intval', $old['tag_ids']) : [];

?>
<section>
    <h1><?= $edited === null ? 'Nouveau client' : 'Modifier le client' ?></h1>
    <form class="stack" method="post" action="<?= Html::url($edited === null ? '/clients' : '/clients/' . $edited->id()) ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="full_name"><span>Nom complet</span></label>
            <input class="input" id="full_name" name="full_name" required maxlength="120" value="<?= Html::e((string) ($old['full_name'] ?? '')) ?>">
            <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= Html::e($errors['full_name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="email"><span>E-mail</span></label>
            <input class="input" id="email" name="email" type="email" maxlength="190" value="<?= Html::e((string) ($old['email'] ?? '')) ?>">
            <?php if (isset($errors['email'])): ?><p class="field-error"><?= Html::e($errors['email']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="phone"><span>Téléphone</span></label>
            <input class="input" id="phone" name="phone" type="tel" maxlength="40" value="<?= Html::e((string) ($old['phone'] ?? '')) ?>">
            <?php if (isset($errors['phone'])): ?><p class="field-error"><?= Html::e($errors['phone']) ?></p><?php endif; ?>
        </div>
        <?php if ($canTags): ?>
            <fieldset class="field">
                <legend>Tags</legend>
                <?php if ($allTags === []): ?>
                    <p class="muted">Aucun tag pour l'instant. Ajoutez-en ci-dessous.</p>
                <?php else: ?>
                    <ul class="tag-checklist">
                        <?php foreach ($allTags as $tag): ?>
                            <?php if (!$tag instanceof ClientTag) { continue; } ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="tag_ids[]" value="<?= Html::e((string) $tag->id()) ?>"<?= in_array($tag->id(), $selectedTags, true) ? ' checked' : '' ?>>
                                    <?= Html::e($tag->name()) ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <label for="new_tags"><span>Nouveaux tags</span></label>
                <input class="input" id="new_tags" name="new_tags" maxlength="200" placeholder="VIP, Particulier…" value="<?= Html::e((string) ($old['new_tags'] ?? '')) ?>">
                <p class="muted">Séparez plusieurs tags par une virgule.</p>
                <?php if (isset($errors['new_tags'])): ?><p class="field-error"><?= Html::e($errors['new_tags']) ?></p><?php endif; ?>
            </fieldset>
        <?php endif; ?>
        <div class="row">
            <button class="btn btn-primary" type="submit">Enregistrer</button>
            <?php if ($edited !== null): ?>
                <a class="btn btn-secondary" href="<?= Html::url('/clients/' . $edited->id()) ?>">Annuler</a>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?= Html::url('/clients') ?>">Annuler</a>
            <?php endif; ?>
        </div>
    </form>
</section>
