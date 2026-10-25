<?php

declare(strict_types=1);

use Sesam\Core\Boutique\BoutiqueTheme;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationSettings;
use Sesam\Models\Tenant;
use Sesam\Services\Reservation\BookingService;

$settings = ($settings ?? null) instanceof ReservationSettings ? $settings : new ReservationSettings();
$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$closures = is_array($closures ?? null) ? $closures : [];
$publicUrl = is_string($publicUrl ?? null) ? $publicUrl : '';
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canUpdate = (bool) ($canUpdate ?? false);
$suggestedHours = (bool) ($suggestedHours ?? false);
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
$ro = $canUpdate ? '' : ' disabled';
$select = static function (string $name, array $options) use ($val, $ro): void {
    echo '<select class="input" id="' . Html::e($name) . '" name="' . Html::e($name) . '"' . $ro . '>';
    foreach ($options as $value => $label) {
        echo '<option value="' . Html::e((string) $value) . '"' . ($val($name) === (string) $value ? ' selected' : '') . '>' . Html::e($label) . '</option>';
    }
    echo '</select>';
};

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Mon salon</h1>
            <p class="muted">Votre page de réservation, vos horaires et vos règles. Tout est pris en compte immédiatement.</p>
        </div>
        <a class="btn btn-secondary" href="<?= Html::e($publicUrl) ?>" target="_blank" rel="noopener"><?php $name = 'eye'; include $icon; ?>Voir ma page</a>
    </header>

    <section class="btq-card btq-share">
        <h2>Lien de réservation</h2>
        <div class="btq-share-url">
            <label class="sr-only" for="public-url">Lien de réservation</label>
            <input id="public-url" type="text" readonly value="<?= Html::e($publicUrl) ?>">
        </div>
        <div class="btq-actions">
            <button class="btn btn-primary" type="button" data-copy="public-url"><?php $name = 'link'; include $icon; ?><span data-copy-label>Copier le lien</span></button>
            <a class="btn" href="<?= Html::e('https://wa.me/?text=' . rawurlencode(($tenant?->displayName() ?? '') . ' — réservez en ligne : ' . $publicUrl)) ?>" target="_blank" rel="noopener"><?php $name = 'chat'; include $icon; ?>Envoyer sur WhatsApp</a>
        </div>
    </section>

    <?php if ($errors !== []): ?>
        <p class="alert alert-danger" role="alert">Vérifiez les champs en rouge.</p>
    <?php endif; ?>

    <form class="btq-form" method="post" action="<?= Html::url('/reservation/salon') ?>" enctype="multipart/form-data" style="max-width: 860px">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

        <fieldset id="photos">
            <legend>Photos et présentation de votre salon</legend>
            <p class="btq-help">Votre logo et votre photo de couverture s'affichent en haut de votre page de réservation publique.</p>
            <div class="btq-grid-2">
                <div class="field">
                    <label for="display_name"><span>Nom du salon</span></label>
                    <input class="input" id="display_name" name="display_name" required maxlength="120" value="<?= Html::e($val('display_name')) ?>"<?= $ro ?>>
                    <?php $err('display_name'); ?>
                </div>
                <div class="field">
                    <label for="whatsapp_phone"><span>Numéro WhatsApp du salon</span></label>
                    <input class="input" id="whatsapp_phone" name="whatsapp_phone" type="tel" maxlength="30" placeholder="77 123 45 67" value="<?= Html::e($val('whatsapp_phone')) ?>"<?= $ro ?>>
                    <?php $err('whatsapp_phone'); ?>
                </div>
            </div>
            <div class="field">
                <label for="tagline"><span>Phrase d'accroche</span></label>
                <input class="input" id="tagline" name="tagline" maxlength="160" placeholder="Ex. : Coiffure afro et soins, sur rendez-vous à Mermoz" value="<?= Html::e($val('tagline')) ?>"<?= $ro ?>>
                <?php $err('tagline'); ?>
            </div>
            <div class="field">
                <label for="description"><span>Présentation</span></label>
                <textarea class="input" id="description" name="description" rows="3" maxlength="1000"<?= $ro ?>><?= Html::e($val('description')) ?></textarea>
                <?php $err('description'); ?>
            </div>
            <div class="field">
                <label for="address"><span>Adresse ou quartier</span></label>
                <input class="input" id="address" name="address" maxlength="190" value="<?= Html::e($val('address')) ?>"<?= $ro ?>>
                <?php $err('address'); ?>
            </div>
            <div class="btq-upload">
                <?php if ($settings->logoPath() !== null): ?>
                    <img class="btq-upload-preview" id="logo-preview" src="<?= Html::url('/t/' . $slug . '/logo') ?>?v=<?= Html::e($version) ?>" alt="">
                <?php else: ?>
                    <span class="btq-upload-preview" id="logo-preview"><?php $name = 'store'; include $icon; ?></span>
                <?php endif; ?>
                <div class="field">
                    <label for="logo"><span>Logo ou photo de profil du salon</span></label>
                    <input class="input" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="logo-preview"<?= $ro ?>>
                    <?php $err('logo'); ?>
                    <?php if ($settings->logoPath() !== null && $canUpdate): ?><label class="btq-switch"><input type="checkbox" name="remove_logo" value="1"><span>Retirer le logo</span></label><?php endif; ?>
                </div>
            </div>
            <div class="btq-upload">
                <?php if ($settings->coverPath() !== null): ?>
                    <img class="btq-upload-preview is-wide" id="cover-preview" src="<?= Html::url('/t/' . $slug . '/couverture') ?>?v=<?= Html::e($version) ?>" alt="">
                <?php else: ?>
                    <span class="btq-upload-preview is-wide" id="cover-preview"><?php $name = 'image'; include $icon; ?></span>
                <?php endif; ?>
                <div class="field">
                    <label for="cover"><span>Photo de couverture (bannière en haut de la page)</span></label>
                    <input class="input" id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="cover-preview"<?= $ro ?>>
                    <?php $err('cover'); ?>
                    <?php if ($settings->coverPath() !== null && $canUpdate): ?><label class="btq-switch"><input type="checkbox" name="remove_cover" value="1"><span>Retirer la photo</span></label><?php endif; ?>
                </div>
            </div>
            <div class="btq-themes">
                <?php foreach (BoutiqueTheme::THEMES as $key => $theme): ?>
                    <label class="btq-theme">
                        <input type="radio" name="theme" value="<?= Html::e($key) ?>"<?= ($val('theme') !== '' ? $val('theme') : 'ocean') === $key ? ' checked' : '' ?><?= $ro ?>>
                        <span style="background: <?= Html::e($theme['accent']) ?>"></span>
                        <span><?= Html::e($theme['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset id="horaires">
            <legend>Horaires d'ouverture</legend>
            <?php if ($suggestedHours): ?>
                <p class="alert" role="status">Horaires proposés : du lundi au samedi, 9 h – 19 h. Ajustez-les puis enregistrez.</p>
            <?php endif; ?>
            <p class="btq-help">Ajoutez une deuxième plage si vous fermez à midi.</p>
            <div class="hours-table">
                <?php for ($day = 1; $day <= 7; ++$day): ?>
                    <div class="hours-row">
                        <label class="btq-switch"><input type="checkbox" name="day_<?= $day ?>_open" value="1"<?= $checked('day_' . $day . '_open') ?><?= $ro ?>><span><strong><?= Html::e(BookingService::weekdayLabel($day)) ?></strong></span></label>
                        <div class="hours-ranges">
                            <span><input class="input" type="time" name="day_<?= $day ?>_a_from" aria-label="Ouverture <?= Html::e(BookingService::weekdayLabel($day)) ?>" value="<?= Html::e($val('day_' . $day . '_a_from')) ?>"<?= $ro ?>> <span class="muted">à</span> <input class="input" type="time" name="day_<?= $day ?>_a_to" aria-label="Fermeture <?= Html::e(BookingService::weekdayLabel($day)) ?>" value="<?= Html::e($val('day_' . $day . '_a_to')) ?>"<?= $ro ?>></span>
                            <span><span class="muted">puis</span> <input class="input" type="time" name="day_<?= $day ?>_b_from" aria-label="Réouverture <?= Html::e(BookingService::weekdayLabel($day)) ?>" value="<?= Html::e($val('day_' . $day . '_b_from')) ?>"<?= $ro ?>> <span class="muted">à</span> <input class="input" type="time" name="day_<?= $day ?>_b_to" aria-label="Fermeture du soir <?= Html::e(BookingService::weekdayLabel($day)) ?>" value="<?= Html::e($val('day_' . $day . '_b_to')) ?>"<?= $ro ?>></span>
                        </div>
                    </div>
                    <?php $err('day_' . $day); ?>
                <?php endfor; ?>
            </div>
        </fieldset>

        <fieldset>
            <legend>Réservation en ligne</legend>
            <label class="btq-switch"><input type="checkbox" name="is_open" value="1"<?= $checked('is_open') ?><?= $ro ?>><span><strong>Ouverte</strong><span class="btq-help">Décochez pendant vos congés : la page reste visible, sans prise de rendez-vous.</span></span></label>
            <label class="btq-switch"><input type="checkbox" name="auto_confirm" value="1"<?= $checked('auto_confirm') ?><?= $ro ?>><span><strong>Confirmer automatiquement</strong><span class="btq-help">Sinon, chaque demande arrive « À confirmer » et vous validez d'un clic. Avec un acompte payé, c'est toujours confirmé.</span></span></label>
            <div class="btq-grid-2">
                <div class="field"><label for="slot_step_minutes"><span>Créneaux proposés toutes les</span></label><?php $select('slot_step_minutes', [15 => '15 minutes', 30 => '30 minutes', 45 => '45 minutes', 60 => '1 heure']); ?><?php $err('slot_step_minutes'); ?></div>
                <div class="field"><label for="buffer_minutes"><span>Pause entre deux clientes</span></label><?php $select('buffer_minutes', [0 => 'Aucune', 5 => '5 minutes', 10 => '10 minutes', 15 => '15 minutes', 30 => '30 minutes']); ?><?php $err('buffer_minutes'); ?></div>
                <div class="field"><label for="min_notice_hours"><span>Réserver au plus tard</span></label><?php $select('min_notice_hours', [0 => 'Jusqu\'au dernier moment', 1 => '1 h avant', 2 => '2 h avant', 4 => '4 h avant', 12 => '12 h avant', 24 => 'La veille (24 h)']); ?><?php $err('min_notice_hours'); ?></div>
                <div class="field"><label for="max_advance_days"><span>Réserver jusqu'à</span></label><?php $select('max_advance_days', [7 => '1 semaine à l\'avance', 14 => '2 semaines', 30 => '1 mois', 60 => '2 mois', 90 => '3 mois', 180 => '6 mois']); ?><?php $err('max_advance_days'); ?></div>
            </div>
            <div class="field">
                <label for="cancellation_policy"><span>Conditions d'annulation (affichées aux clientes)</span></label>
                <textarea class="input" id="cancellation_policy" name="cancellation_policy" rows="2" maxlength="500" placeholder="Ex. : Merci de prévenir 24 h à l'avance. L'acompte n'est pas remboursé en cas d'absence."<?= $ro ?>><?= Html::e($val('cancellation_policy')) ?></textarea>
                <?php $err('cancellation_policy'); ?>
            </div>
        </fieldset>


        <?php if ($canUpdate): ?>
            <div class="btq-sticky-save"><button class="btn btn-primary" type="submit">Enregistrer mon salon</button></div>
        <?php endif; ?>
    </form>

    <section class="btq-card" id="fermetures" style="max-width: 860px">
        <h2>Jours de fermeture</h2>
        <p class="btq-help">Fêtes, congés, travaux : personne ne pourra réserver ces jours-là.</p>
        <?php if ($closures === []): ?>
            <p class="muted">Aucune fermeture prévue.</p>
        <?php else: ?>
            <ul class="btq-list">
                <?php foreach ($closures as $closure): ?>
                    <li>
                        <div>
                            <span>
                                <strong><?= Html::e(gmdate('d/m/Y', (int) strtotime($closure['starts_on']))) ?><?= $closure['ends_on'] !== $closure['starts_on'] ? ' → ' . Html::e(gmdate('d/m/Y', (int) strtotime($closure['ends_on']))) : '' ?></strong>
                                <?php if ($closure['reason'] !== ''): ?><small><?= Html::e($closure['reason']) ?></small><?php endif; ?>
                            </span>
                            <?php if ($canUpdate): ?>
                                <form method="post" action="<?= Html::url('/reservation/salon/fermetures/' . $closure['id'] . '/supprimer') ?>">
                                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                    <button class="btn btn-ghost btn-sm" type="submit">Retirer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($canUpdate): ?>
            <form class="btq-form" method="post" action="<?= Html::url('/reservation/salon/fermetures') ?>" style="margin-top: 14px">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <div class="btq-grid-2">
                    <div class="field"><label for="starts_on"><span>Du</span></label><input class="input" id="starts_on" name="starts_on" type="date" required min="<?= Html::e(gmdate('Y-m-d')) ?>"></div>
                    <div class="field"><label for="ends_on"><span>Au (inclus)</span></label><input class="input" id="ends_on" name="ends_on" type="date"></div>
                </div>
                <div class="field"><label for="reason"><span>Motif (affiché aux clientes)</span></label><input class="input" id="reason" name="reason" maxlength="160" placeholder="Ex. : Tabaski"></div>
                <button class="btn btn-secondary" type="submit">Ajouter la fermeture</button>
            </form>
        <?php endif; ?>
    </section>
</section>
