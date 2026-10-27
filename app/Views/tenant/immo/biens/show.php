<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$property = is_array($data['property'] ?? null) ? $data['property'] : [];
$units = is_array($data['units'] ?? null) ? $data['units'] : [];
$interventions = is_array($data['interventions'] ?? null) ? $data['interventions'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$canLease = (bool) ($canLease ?? false);
if ($property === []) {
    return;
}
$val = static fn (string $key, string $default = ''): string => Html::e(is_scalar($old[$key] ?? null) && (string) $old[$key] !== '' ? (string) $old[$key] : $default);
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$active = array_filter($units, static fn (array $u): bool => (int) $u['is_active'] === 1);
$occupied = count(array_filter($active, static fn (array $u): bool => $u['lease_id'] !== null));
$monthly = array_sum(array_map(static fn (array $u): int => (int) ($u['rent_minor'] ?? 0) + (int) ($u['charges_minor'] ?? 0), $active));

$heroImageSrc = null;
if (!empty($property['image_path']) && ($tenant ?? null) instanceof \Sesam\Models\Tenant) {
    $heroImageSrc = Html::url('/t/' . $tenant->slug() . '/bien/' . $property['id'] . '/image') . '?v=' . rawurlencode((string) ($property['updated_at'] ?? ''));
}
ob_start();
if ($canManage): ?>
    <a class="btn btn-secondary" href="<?= Html::url('/immo/biens/' . $property['id'] . '/modifier') ?>">Modifier</a>
<?php endif;
$heroActions = ob_get_clean() ?: '';

$heroBackHref = '/immo/biens';
$heroBackLabel = 'Biens';
$heroTitle = (string) $property['name'];
$heroSubtitle = (ImmoCatalog::PROPERTY_KINDS[$property['kind']] ?? $property['kind']) . ($property['address'] ? ' · ' . $property['address'] : '') . ($property['city'] ? ', ' . $property['city'] : '');
$heroTone = 'emerald';
$heroInitials = Html::initials((string) $property['name']);
$heroImageAlt = (string) $property['name'];
$heroStatusText = $occupied . ' / ' . count($active) . ' loués';
$heroStatusTone = count($active) - $occupied > 0 ? 'warning' : 'success';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Logements', 'value' => (string) count($active), 'hint' => $occupied . ' loué(s)'],
    ['label' => 'Libres', 'value' => (string) (count($active) - $occupied), 'alert' => count($active) - $occupied > 0],
    ['label' => 'Loyers / mois', 'value' => Money::format($monthly)],
    ['label' => 'Encaissé ' . gmdate('Y'), 'value' => Money::format((int) $data['collected'])],
];

?>
<section class="record-detail btq immo">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <?php if ($property['owner_name'] || $property['notes']): ?>
        <section class="btq-card">
            <dl class="btq-dl">
                <?php if ($property['owner_name']): ?><dt>Propriétaire</dt><dd><?= Html::e($property['owner_name']) ?><?= $property['owner_phone'] ? ' · ' . Html::e($property['owner_phone']) : '' ?></dd><?php endif; ?>
            </dl>
            <?php if ($property['notes']): ?><p class="immo-note"><?= nl2br(Html::e($property['notes'])) ?></p><?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="btq-card" id="logements">
        <header class="btq-card-head"><h2>Logements</h2></header>
        <?php if ($units === []): ?>
            <p class="muted">Aucun logement : ajoutez le premier ci-dessous.</p>
        <?php else: ?>
            <ul class="btq-list">
                <?php foreach ($units as $unit): ?>
                    <li><div>
                        <span>
                            <strong><?= Html::e($unit['label']) ?></strong>
                            <small><?= Html::e(ImmoCatalog::UNIT_KINDS[$unit['kind']] ?? $unit['kind']) ?><?= $unit['rooms'] ? ' · ' . (int) $unit['rooms'] . ' pièce(s)' : '' ?><?= $unit['surface_m2'] ? ' · ' . (int) $unit['surface_m2'] . ' m²' : '' ?><?= $unit['floor_label'] ? ' · ' . Html::e($unit['floor_label']) : '' ?></small>
                            <small><?php if ($unit['lease_id'] !== null): ?>Loué à <a href="<?= Html::url('/immo/locataires/' . $unit['client_id']) ?>"><?= Html::e($unit['full_name']) ?></a> · <?= Html::e(Money::format((int) $unit['rent_minor'] + (int) $unit['charges_minor'])) ?>/mois<?php else: ?>Loyer prévu <?= Html::e(Money::format((int) $unit['default_rent_minor'] + (int) $unit['default_charges_minor'])) ?>/mois<?php endif; ?></small>
                        </span>
                        <span class="btq-list-end immo-unit-actions">
                            <?php if ((int) $unit['is_active'] !== 1): ?>
                                <span class="btq-status btq-status-muted">Hors location</span>
                            <?php elseif ($unit['lease_id'] !== null): ?>
                                <a class="btq-status btq-status-success" href="<?= Html::url('/immo/baux/' . $unit['lease_id']) ?>">Loué</a>
                            <?php elseif ($canLease): ?>
                                <a class="btn btn-primary btn-sm" href="<?= Html::url('/immo/baux/nouveau?logement=' . $unit['id']) ?>">Louer</a>
                            <?php else: ?>
                                <span class="btq-status btq-status-warning">Libre</span>
                            <?php endif; ?>
                            <?php if ($canManage): ?><a class="btn btn-ghost btn-sm" href="<?= Html::url('/immo/logements/' . $unit['id'] . '/modifier') ?>">Modifier</a><?php endif; ?>
                        </span>
                    </div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($canManage): ?>
            <details class="immo-add"<?= $errors !== [] || $units === [] ? ' open' : '' ?>>
                <summary>Ajouter un logement</summary>
                <form class="btq-form" method="post" action="<?= Html::url('/immo/biens/' . $property['id'] . '/logements') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <div class="btq-grid-2">
                        <div class="field"><label for="label"><span>Nom du logement</span></label><input class="input<?= isset($errors['label']) ? ' is-invalid' : '' ?>" id="label" name="label" maxlength="80" placeholder="Appartement A1" value="<?= $val('label') ?>"><?php $err('label'); ?></div>
                        <div class="field"><label for="kind"><span>Type</span></label><select class="input" id="kind" name="kind"><?php foreach (ImmoCatalog::UNIT_KINDS as $key => $label): ?><option value="<?= Html::e($key) ?>"<?= ($old['kind'] ?? 'appartement') === $key ? ' selected' : '' ?>><?= Html::e($label) ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="rooms"><span>Pièces</span></label><input class="input" id="rooms" name="rooms" inputmode="numeric" value="<?= $val('rooms') ?>"></div>
                        <div class="field"><label for="surface_m2"><span>Surface (m²)</span></label><input class="input" id="surface_m2" name="surface_m2" inputmode="numeric" value="<?= $val('surface_m2') ?>"></div>
                        <div class="field"><label for="floor_label"><span>Étage</span></label><input class="input" id="floor_label" name="floor_label" maxlength="30" placeholder="RDC, 1er…" value="<?= $val('floor_label') ?>"></div>
                        <div class="field"><label for="default_rent_minor"><span>Loyer mensuel (FCFA)</span></label><input class="input<?= isset($errors['default_rent_minor']) ? ' is-invalid' : '' ?>" id="default_rent_minor" name="default_rent_minor" inputmode="numeric" value="<?= $val('default_rent_minor') ?>"><?php $err('default_rent_minor'); ?></div>
                        <div class="field"><label for="default_charges_minor"><span>Charges mensuelles (FCFA)</span></label><input class="input" id="default_charges_minor" name="default_charges_minor" inputmode="numeric" value="<?= $val('default_charges_minor', '0') ?>"></div>
                        <div class="field"><label for="default_deposit_minor"><span>Caution (FCFA)</span></label><input class="input" id="default_deposit_minor" name="default_deposit_minor" inputmode="numeric" value="<?= $val('default_deposit_minor', '0') ?>"></div>
                    </div>
                    <div class="field"><label for="description"><span>Description (facultatif)</span></label><input class="input" id="description" name="description" maxlength="500" value="<?= $val('description') ?>"></div>
                    <div><button class="btn btn-primary" type="submit">Ajouter le logement</button></div>
                </form>
            </details>
        <?php endif; ?>
    </section>

    <section class="btq-card">
        <header class="btq-card-head"><h2>Interventions</h2><a class="muted" href="<?= Html::url('/immo/interventions') ?>">Toutes</a></header>
        <?php if ($interventions === []): ?>
            <p class="muted">Aucune intervention sur ce bien.</p>
        <?php else: ?>
            <ul class="btq-list">
                <?php foreach ($interventions as $item): ?>
                    <li><div><span><strong><?= Html::e($item['title']) ?></strong><small><?= Html::e($item['unit_label'] ?? 'Parties communes') ?> · <?= Html::e(ImmoCatalog::date($item['reported_on'])) ?><?= (int) $item['cost_minor'] > 0 ? ' · ' . Html::e(Money::format((int) $item['cost_minor'])) : '' ?></small></span>
                    <span class="btq-status btq-status-<?= $item['status'] === 'terminee' ? 'success' : ($item['priority'] === 'urgente' ? 'danger' : 'warning') ?>"><?= Html::e(ImmoCatalog::INTERVENTION_STATUSES[$item['status']] ?? $item['status']) ?></span></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</section>
