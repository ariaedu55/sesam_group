<?php

declare(strict_types=1);

/*
 * Choix du lien public du commerce (/t/…), sous la carte « Lien » des réglages Boutique et Salon.
 * Variables : $publicUrl, $tenant (Tenant), $csrf.
 */

use Sesam\Core\Tenant\Slug;
use Sesam\Helpers\Html;
use Sesam\Models\Tenant;

$linkTenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
if ($linkTenant === null) {
    return;
}
$linkBase = preg_replace('#[^/]+$#', '', (string) ($publicUrl ?? '')) ?: '/t/';
$linkPrefix = (string) preg_replace('#^https?://#', '', $linkBase);
$fromName = Slug::from($linkTenant->displayName());

?>
<details class="btq-link-edit" id="lien">
    <summary>Changer le lien</summary>
    <form method="post" action="<?= Html::url('/lien-public') ?>" class="btq-link-form">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <label for="public-slug">Votre lien</label>
        <div class="btq-link-field">
            <span aria-hidden="true"><?= Html::e($linkPrefix) ?></span>
            <input id="public-slug" name="slug" type="text" required minlength="3" maxlength="60" autocomplete="off" spellcheck="false" value="<?= Html::e($linkTenant->slug()) ?>" aria-describedby="public-slug-hint">
        </div>
        <p class="muted" id="public-slug-hint">Lettres, chiffres et tirets. Les liens déjà partagés continueront de fonctionner : ils redirigent vers le nouveau.</p>
        <div class="btq-actions">
            <button class="btn btn-primary" type="submit">Enregistrer le lien</button>
            <?php if ($fromName !== '' && $fromName !== $linkTenant->slug()): ?>
                <button class="btn" type="submit" name="slug" value="<?= Html::e($fromName) ?>">Utiliser « <?= Html::e($fromName) ?> »</button>
            <?php endif; ?>
        </div>
    </form>
</details>
