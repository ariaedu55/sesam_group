<?php

declare(strict_types=1);

use Sesam\Core\Boutique\BoutiqueTheme;
use Sesam\Helpers\Html;
use Sesam\Models\BoutiqueSettings;
use Sesam\Models\Tenant;

$settings = ($settings ?? null) instanceof BoutiqueSettings ? $settings : new BoutiqueSettings();
$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$publicUrl = is_string($publicUrl ?? null) ? $publicUrl : '';
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canUpdate = (bool) ($canUpdate ?? false);
$slug = $tenant?->slug() ?? '';
$version = rawurlencode($settings->updatedAt());
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
$val = static fn (string $key): string => is_scalar($old[$key] ?? null) ? (string) $old[$key] : '';
$checked = static fn (string $key): string => $val($key) !== '' ? ' checked' : '';
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$readonly = $canUpdate ? '' : ' disabled';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Ma boutique</h1>
            <p class="muted">Tout ce que vos clients voient sur votre page. Modifiez, enregistrez : c'est en ligne tout de suite.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::e($publicUrl) ?>" target="_blank" rel="noopener"><?php $name = 'eye'; include $icon; ?>Voir ma boutique</a>
    </header>

    <section class="btq-card btq-share">
        <h2>Lien public de votre boutique</h2>
        <div class="btq-share-url">
            <label class="sr-only" for="public-url">Lien de la boutique</label>
            <input id="public-url" type="text" readonly value="<?= Html::e($publicUrl) ?>">
        </div>
        <div class="btq-actions">
            <button class="btn btn-primary" type="button" data-copy="public-url"><?php $name = 'link'; include $icon; ?><span data-copy-label>Copier le lien</span></button>
            <a class="btn" href="<?= Html::e('https://wa.me/?text=' . rawurlencode(($tenant?->displayName() ?? '') . ' — découvrez notre boutique en ligne : ' . $publicUrl)) ?>" target="_blank" rel="noopener"><?php $name = 'chat'; include $icon; ?>Envoyer sur WhatsApp</a>
        </div>
    </section>

    <?php if ($errors !== []): ?>
        <p class="alert alert-danger" role="alert">Vérifiez les champs en rouge.</p>
    <?php endif; ?>

    <form class="btq-form" method="post" action="<?= Html::url('/boutique/parametres') ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

        <fieldset>
            <legend>Identité</legend>
            <div class="field">
                <label for="display_name"><span>Nom de la boutique</span></label>
                <input class="input<?= isset($errors['display_name']) ? ' is-invalid' : '' ?>" id="display_name" name="display_name" required maxlength="120" value="<?= Html::e($val('display_name')) ?>"<?= $readonly ?>>
                <?php $err('display_name'); ?>
            </div>
            <div class="field">
                <label for="tagline"><span>Phrase d'accroche</span></label>
                <input class="input" id="tagline" name="tagline" maxlength="160" placeholder="Ex. : Mode wax faite main, livrée partout à Dakar" value="<?= Html::e($val('tagline')) ?>"<?= $readonly ?>>
                <?php $err('tagline'); ?>
            </div>
            <div class="btq-upload">
                <?php if ($settings->logoPath() !== null): ?>
                    <img class="btq-upload-preview" id="logo-preview" src="<?= Html::url('/t/' . $slug . '/logo') ?>?v=<?= Html::e($version) ?>" alt="">
                <?php else: ?>
                    <span class="btq-upload-preview" id="logo-preview"><?php $name = 'store'; include $icon; ?></span>
                <?php endif; ?>
                <div class="field">
                    <label for="logo"><span>Logo</span></label>
                    <input class="input" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="logo-preview"<?= $readonly ?>>
                    <p class="btq-help">Une image carrée de préférence.</p>
                    <?php $err('logo'); ?>
                    <?php if ($settings->logoPath() !== null && $canUpdate): ?>
                        <label class="btq-switch"><input type="checkbox" name="remove_logo" value="1"><span>Retirer le logo</span></label>
                    <?php endif; ?>
                </div>
            </div>
            <div class="btq-upload">
                <?php if ($settings->coverPath() !== null): ?>
                    <img class="btq-upload-preview is-wide" id="cover-preview" src="<?= Html::url('/t/' . $slug . '/couverture') ?>?v=<?= Html::e($version) ?>" alt="">
                <?php else: ?>
                    <span class="btq-upload-preview is-wide" id="cover-preview"><?php $name = 'image'; include $icon; ?></span>
                <?php endif; ?>
                <div class="field">
                    <label for="cover"><span>Image de couverture</span></label>
                    <input class="input" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="cover-preview"<?= $readonly ?>>
                    <p class="btq-help">La grande photo en haut de votre boutique : votre vitrine, vos produits, votre équipe.</p>
                    <?php $err('cover'); ?>
                    <?php if ($settings->coverPath() !== null && $canUpdate): ?>
                        <label class="btq-switch"><input type="checkbox" name="remove_cover" value="1"><span>Retirer l'image de couverture</span></label>
                    <?php endif; ?>
                </div>
            </div>
            <fieldset>
                <legend>Couleur de la boutique</legend>
                <div class="btq-themes">
                    <?php foreach (BoutiqueTheme::THEMES as $key => $theme): ?>
                        <label class="btq-theme">
                            <input type="radio" name="theme" value="<?= Html::e($key) ?>"<?= ($val('theme') !== '' ? $val('theme') : BoutiqueTheme::DEFAULT) === $key ? ' checked' : '' ?><?= $readonly ?>>
                            <span style="background: <?= Html::e($theme['accent']) ?>"></span>
                            <span><?= Html::e($theme['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </fieldset>

        <fieldset>
            <legend>Commandes WhatsApp</legend>
            <div class="field">
                <label for="whatsapp_phone"><span>Numéro WhatsApp qui reçoit les commandes</span></label>
                <input class="input<?= isset($errors['whatsapp_phone']) ? ' is-invalid' : '' ?>" id="whatsapp_phone" name="whatsapp_phone" type="tel" maxlength="30" placeholder="77 123 45 67" value="<?= Html::e($val('whatsapp_phone')) ?>"<?= $readonly ?>>
                <p class="btq-help">Quand un client clique sur « Commander », WhatsApp s'ouvre sur ce numéro avec sa commande déjà écrite. Indicatif +221 ajouté si besoin.</p>
                <?php $err('whatsapp_phone'); ?>
            </div>
            <label class="btq-switch">
                <input type="checkbox" name="is_open" value="1"<?= $checked('is_open') ?><?= $readonly ?>>
                <span><strong>Boutique ouverte aux commandes</strong><span class="btq-help">Décochez pendant vos congés : les clients voient les produits mais ne peuvent pas commander.</span></span>
            </label>
            <label class="btq-switch">
                <input type="checkbox" name="show_stock" value="1"<?= $checked('show_stock') ?><?= $readonly ?>>
                <span><strong>Afficher les quantités en stock</strong><span class="btq-help">Ex. : « 4 en stock » sous chaque produit.</span></span>
            </label>
        </fieldset>

        <fieldset>
            <legend>Informations pratiques</legend>
            <div class="field">
                <label for="description"><span>Présentation de la boutique</span></label>
                <textarea class="input" id="description" name="description" rows="4" maxlength="1000" placeholder="Qui vous êtes, ce que vous vendez, ce qui vous rend unique."<?= $readonly ?>><?= Html::e($val('description')) ?></textarea>
                <?php $err('description'); ?>
            </div>
            <div class="btq-grid-2">
                <div class="field">
                    <label for="address"><span>Adresse ou quartier</span></label>
                    <input class="input" id="address" name="address" maxlength="190" placeholder="Ex. : Sacré-Cœur 3, Dakar" value="<?= Html::e($val('address')) ?>"<?= $readonly ?>>
                    <?php $err('address'); ?>
                </div>
                <div class="field">
                    <label for="opening_hours"><span>Horaires</span></label>
                    <input class="input" id="opening_hours" name="opening_hours" maxlength="190" placeholder="Ex. : Lun–Sam, 9 h – 20 h" value="<?= Html::e($val('opening_hours')) ?>"<?= $readonly ?>>
                    <?php $err('opening_hours'); ?>
                </div>
            </div>
            <div class="field">
                <label for="delivery_info"><span>Livraison</span></label>
                <input class="input" id="delivery_info" name="delivery_info" maxlength="300" placeholder="Ex. : Livraison à Dakar en 24 h — 1 500 FCFA" value="<?= Html::e($val('delivery_info')) ?>"<?= $readonly ?>>
                <?php $err('delivery_info'); ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Réseaux sociaux (facultatif)</legend>
            <div class="btq-grid-2">
                <?php foreach (['instagram_url' => 'Instagram', 'facebook_url' => 'Facebook', 'tiktok_url' => 'TikTok'] as $key => $label): ?>
                    <div class="field">
                        <label for="<?= Html::e($key) ?>"><span><?= Html::e($label) ?></span></label>
                        <input class="input<?= isset($errors[$key]) ? ' is-invalid' : '' ?>" id="<?= Html::e($key) ?>" name="<?= Html::e($key) ?>" type="text" inputmode="url" maxlength="255" placeholder="https://" value="<?= Html::e($val($key)) ?>"<?= $readonly ?>>
                        <?php $err($key); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <?php if ($canUpdate): ?>
            <div class="btq-sticky-save">
                <button class="btn btn-primary" type="submit">Enregistrer ma boutique</button>
            </div>
        <?php endif; ?>
    </form>
</section>
