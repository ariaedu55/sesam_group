<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;
use Sesam\Models\LoyerProperty;
use Sesam\Models\LoyerUnit;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$agency = is_array($agency ?? null) ? $agency : [];
$catalog = is_array($catalog ?? null) ? $catalog : [];
$listings = is_array($catalog['listings'] ?? null) ? $catalog['listings'] : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$whatsapp = is_string($whatsapp ?? null) ? $whatsapp : '';
$lead = is_string($lead ?? null) ? $lead : '';
$cta = is_string($cta ?? null) ? $cta : 'Envoyer ma demande';
$slug = $tenant?->slug() ?? '';
$base = '/t/' . $slug;
$name = is_string($agency['display_name'] ?? null) && $agency['display_name'] !== ''
    ? (string) $agency['display_name']
    : ($tenant?->displayName() ?? 'Agence');
$address = is_string($agency['address'] ?? null) ? (string) $agency['address'] : '';
$phone = is_string($agency['whatsapp_phone'] ?? null) ? (string) $agency['whatsapp_phone'] : '';
$suggestions = [];
$available = 0;
foreach ($listings as $row) {
    if (!is_array($row)) {
        continue;
    }
    $units = is_array($row['units'] ?? null) ? $row['units'] : [];
    $property = ($row['property'] ?? null) instanceof LoyerProperty ? $row['property'] : null;
    foreach ($units as $unit) {
        if (!$unit instanceof LoyerUnit) {
            continue;
        }
        $available++;
        if ($property instanceof LoyerProperty) {
            $suggestions[] = $property->name() . ' — ' . $unit->label();
        }
    }
}
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
<header class="im-top">
    <a class="im-brand" href="<?= Html::url($base) ?>">
        <span class="im-mark" aria-hidden="true"><?= Html::e(Html::initials($name)) ?></span>
        <span>
            <strong><?= Html::e($name) ?></strong>
            <em>Biens à louer</em>
        </span>
    </a>
    <?php if ($listings !== []): ?>
        <a class="im-btn im-btn-sm" href="#biens">Voir les biens</a>
    <?php endif; ?>
</header>

<section class="im-hero">
    <div class="im-hero-inner">
        <p class="im-kicker">Sesam Immo</p>
        <h1><?= Html::e($name) ?></h1>
        <p class="im-lead"><?= Html::e($lead !== '' ? $lead : 'Parcourez les biens disponibles et laissez vos coordonnées. Aucun loyer n’est encaissé ici.') ?></p>
        <ul class="im-facts">
            <li><b><?= count($listings) ?></b><span>bien<?= count($listings) > 1 ? 's' : '' ?></span></li>
            <li><b><?= $available ?></b><span>logement<?= $available > 1 ? 's' : '' ?> proposé<?= $available > 1 ? 's' : '' ?></span></li>
            <li><b>Sans compte</b><span>demande en 1 minute</span></li>
        </ul>
        <div class="im-hero-actions">
            <?php if ($listings !== []): ?>
                <a class="im-btn im-btn-light" href="#biens">Parcourir les biens</a>
            <?php endif; ?>
            <a class="im-btn im-btn-ghost" href="#contact">Laisser une demande</a>
            <?php if ($whatsapp !== ''): ?>
                <a class="im-btn im-btn-wa" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<main class="im-main">
    <?php if ($errors !== []): ?>
        <div class="im-alert" role="alert">Vérifiez les champs indiqués, puis renvoyez.</div>
    <?php endif; ?>

    <div class="im-layout">
        <section class="im-listings" id="biens" aria-labelledby="biens-title">
            <header class="im-section-head">
                <h2 id="biens-title">Nos biens</h2>
                <p>Photos, adresses et loyers indicatifs. Écrivez-nous pour une visite.</p>
            </header>

            <?php if ($listings === []): ?>
                <div class="im-empty">
                    <p>Aucun bien n’est publié pour le moment.</p>
                    <?php if ($whatsapp !== ''): ?>
                        <p><a class="im-btn" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">Écrire sur WhatsApp</a></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="im-grid">
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
                        $rents = [];
                        foreach ($units as $unit) {
                            if ($unit instanceof LoyerUnit && $unit->defaultRentMinor() > 0) {
                                $rents[] = $unit->defaultRentMinor();
                            }
                        }
                        $from = $rents !== [] ? min($rents) : 0;
                        $kind = ImmoCatalog::PROPERTY_KINDS[$property->kind()] ?? $property->kind();
                        ?>
                        <article class="im-card">
                            <div class="im-card-media<?= $property->imagePath() !== null ? '' : ' is-empty' ?>">
                                <?php if ($property->imagePath() !== null): ?>
                                    <img src="<?= Html::url($base . '/bien/' . $property->id() . '/image') ?>?v=<?= Html::e($property->imageVersion()) ?>" alt="<?= Html::e($property->name()) ?>" width="960" height="600" loading="lazy" decoding="async">
                                <?php else: ?>
                                    <span aria-hidden="true"><?= Html::e(Html::initials($property->name())) ?></span>
                                <?php endif; ?>
                                <span class="im-badge"><?= Html::e((string) $kind) ?></span>
                            </div>
                            <div class="im-card-body">
                                <h3><?= Html::e($property->name()) ?></h3>
                                <?php if ($property->location() !== ''): ?>
                                    <p class="im-place"><?= Html::e($property->location()) ?></p>
                                <?php endif; ?>
                                <?php if ($from > 0): ?>
                                    <p class="im-price">À partir de <strong><?= Html::e(Money::format($from)) ?></strong><span>/ mois</span></p>
                                <?php endif; ?>
                                <?php if ($units === []): ?>
                                    <p class="im-muted">Logements à confirmer auprès de l’agence.</p>
                                <?php else: ?>
                                    <ul class="im-units">
                                        <?php foreach ($units as $unit): ?>
                                            <?php if (!$unit instanceof LoyerUnit) { continue; } ?>
                                            <li>
                                                <strong><?= Html::e($unit->label()) ?></strong>
                                                <?php if ($unit->defaultRentMinor() > 0): ?>
                                                    <span><?= Html::e(Money::format($unit->defaultRentMinor())) ?> / mois</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <a class="im-card-cta" href="#contact">Demander une visite</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside class="im-aside" id="contact" aria-labelledby="contact-title">
            <form class="im-form" method="post" action="<?= Html::url($base) ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <header class="im-form-head">
                    <h2 id="contact-title">Une visite ? Une question ?</h2>
                    <p>Indiquez le bien qui vous intéresse. L’agence vous recontacte, sans créer de compte.</p>
                </header>
                <div class="field">
                    <label for="unit_label"><span>Bien ou logement</span></label>
                    <input class="input" id="unit_label" name="unit_label" maxlength="120" list="unit-suggestions" placeholder="Ex. Immeuble Liberté 6 — A1" value="<?= Html::e($val('unit_label')) ?>" autocomplete="off">
                    <?php if ($suggestions !== []): ?>
                        <datalist id="unit-suggestions">
                            <?php foreach ($suggestions as $label): ?>
                                <option value="<?= Html::e($label) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label for="message"><span>Votre message</span></label>
                    <textarea class="input<?= $invalid('message') ?>" id="message" name="message" rows="4" maxlength="500" placeholder="Je souhaite visiter…"><?= Html::e($val('message')) ?></textarea>
                    <?php $err('message'); ?>
                </div>
                <?php require dirname(__DIR__) . '/_client.php'; ?>
                <button class="im-btn im-btn-block" type="submit"><?= Html::e($cta) ?></button>
                <?php if ($whatsapp !== ''): ?>
                    <p class="im-form-hint">Ou <a href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">écrivez directement sur WhatsApp</a>.</p>
                <?php endif; ?>
            </form>
            <?php if ($address !== '' || $phone !== ''): ?>
                <div class="im-agency">
                    <h3>L’agence</h3>
                    <?php if ($address !== ''): ?><p><?= Html::e($address) ?></p><?php endif; ?>
                    <?php if ($phone !== ''): ?><p><?= Html::e(\Sesam\Core\Boutique\WhatsApp::display($phone)) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<footer class="im-foot">
    <p>Page publique créée avec <a href="<?= Html::url('/services/immo') ?>">Sesam Immo</a></p>
</footer>
