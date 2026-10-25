<?php

declare(strict_types=1);

use Sesam\Core\Tenant\Sector;
use Sesam\Helpers\Html;
use Sesam\Models\LoyerProperty;
use Sesam\Models\LoyerUnit;
use Sesam\Models\ReservationPrestation;
use Sesam\Models\ReservationTeamMember;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$sector = is_string($sector ?? null) ? $sector : ($tenant?->sector() ?? '');
$lead = is_string($lead ?? null) ? $lead : '';
$cta = is_string($cta ?? null) ? $cta : 'Envoyer';
$catalog = is_array($catalog ?? null) ? $catalog : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$slug = $tenant?->slug() ?? '';

$val = static function (string $key) use ($old): string {
    $value = $old[$key] ?? '';

    return is_scalar($value) ? (string) $value : '';
};

$invalid = static function (string $key) use ($errors): string {
    return isset($errors[$key]) ? ' is-invalid' : '';
};

$err = static function (string $key) use ($errors): void {
    if (!isset($errors[$key])) {
        return;
    }
    echo '<p class="field-error" id="' . Html::e($key) . '-error">' . Html::e($errors[$key]) . '</p>';
};

?>
<section class="portal-hero">
    <p class="eyebrow"><?= Html::e($tenant?->sectorLabel() ?? '') ?></p>
    <h1><?= Html::e($tenant?->displayName() ?? 'Commerce') ?></h1>
    <p class="portal-lead"><?= Html::e($lead) ?></p>
</section>

<?php if ($errors !== []): ?>
    <div class="alert" role="alert">Vérifiez les champs indiqués, puis renvoyez.</div>
<?php endif; ?>

<?php if ($sector === Sector::RESERVATION): ?>
    <?php
    $prestations = is_array($catalog['prestations'] ?? null) ? $catalog['prestations'] : [];
    $team = is_array($catalog['team'] ?? null) ? $catalog['team'] : [];
    ?>
    <?php if ($prestations === []): ?>
        <p class="portal-empty">Aucune prestation n’est ouverte pour le moment.</p>
    <?php else: ?>
        <form class="portal-form" method="post" action="<?= Html::url('/t/' . $slug) ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <fieldset class="portal-choose">
                <legend>1. Choisissez</legend>
                <div class="portal-grid">
                    <?php foreach ($prestations as $item): ?>
                        <?php if (!$item instanceof ReservationPrestation) { continue; } ?>
                        <label class="portal-card">
                            <input type="radio" name="service_id" value="<?= $item->id() ?>" required<?= $val('service_id') === (string) $item->id() ? ' checked' : '' ?>>
                            <span>
                                <strong><?= Html::e($item->name()) ?></strong>
                                <em><?= Html::e((string) $item->durationMinutes()) ?> min · <?= Html::e(number_format($item->priceMinor(), 0, ',', ' ')) ?> FCFA</em>
                                <?php if ($item->depositMinor() >= 100): ?>
                                    <small>Acompte <?= Html::e(number_format($item->depositMinor(), 0, ',', ' ')) ?> FCFA</small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php $err('service_id'); ?>
            </fieldset>
            <fieldset>
                <legend>2. Créneau</legend>
                <?php if ($team !== []): ?>
                    <div class="field">
                        <label for="staff_id"><span>Avec qui</span></label>
                        <select class="input<?= $invalid('staff_id') ?>" id="staff_id" name="staff_id">
                            <option value="">Sans préférence</option>
                            <?php foreach ($team as $member): ?>
                                <?php if (!$member instanceof ReservationTeamMember) { continue; } ?>
                                <option value="<?= $member->id() ?>"<?= $val('staff_id') === (string) $member->id() ? ' selected' : '' ?>><?= Html::e($member->name()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php $err('staff_id'); ?>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="starts_at"><span>Jour et heure</span></label>
                    <input class="input<?= $invalid('starts_at') ?>" id="starts_at" name="starts_at" type="datetime-local" required value="<?= Html::e($val('starts_at')) ?>">
                    <?php $err('starts_at'); ?>
                </div>
            </fieldset>
            <?php require __DIR__ . '/_client.php'; ?>
            <button class="btn btn-primary portal-submit" type="submit"><?= Html::e($cta) ?></button>
        </form>
    <?php endif; ?>

<?php elseif ($sector === Sector::LOYER): ?>
    <?php $listings = is_array($catalog['listings'] ?? null) ? $catalog['listings'] : []; ?>
    <?php if ($listings === []): ?>
        <p class="portal-empty">Aucun bien n’est publié pour le moment.</p>
    <?php else: ?>
        <div class="portal-listings">
            <?php foreach ($listings as $row): ?>
                <?php
                if (!is_array($row)) {
                    continue;
                }
                $property = ($row['property'] ?? null) instanceof LoyerProperty ? $row['property'] : null;
                $units = is_array($row['units'] ?? null) ? $row['units'] : [];
                if ($property === null) {
                    continue;
                }
                ?>
                <article class="portal-listing">
                    <h2><?= Html::e($property->name()) ?></h2>
                    <?php if ($property->address() !== ''): ?>
                        <p class="muted"><?= Html::e($property->address()) ?></p>
                    <?php endif; ?>
                    <?php if ($units === []): ?>
                        <p>Aucune unité libre indiquée.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($units as $unit): ?>
                                <?php if (!$unit instanceof LoyerUnit) { continue; } ?>
                                <li>
                                    <strong><?= Html::e($unit->label()) ?></strong>
                                    <span><?= Html::e(number_format($unit->defaultRentMinor(), 0, ',', ' ')) ?> FCFA / mois</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <form class="portal-form" method="post" action="<?= Html::url('/t/' . $slug) ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <div class="field">
                <label for="unit_label"><span>Bien ou unité qui vous intéresse</span></label>
                <input class="input" id="unit_label" name="unit_label" maxlength="120" value="<?= Html::e($val('unit_label')) ?>">
            </div>
            <div class="field">
                <label for="message"><span>Message</span></label>
                <textarea class="input<?= $invalid('message') ?>" id="message" name="message" rows="4" maxlength="500"><?= Html::e($val('message')) ?></textarea>
                <?php $err('message'); ?>
            </div>
            <?php require __DIR__ . '/_client.php'; ?>
            <button class="btn btn-primary portal-submit" type="submit"><?= Html::e($cta) ?></button>
        </form>
    <?php endif; ?>

<?php endif; ?>
