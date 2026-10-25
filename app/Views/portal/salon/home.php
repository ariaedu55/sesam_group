<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ReservationPrestation;
use Sesam\Models\ReservationSettings;
use Sesam\Models\ReservationTeamMember;
use Sesam\Models\Tenant;
use Sesam\Services\Reservation\BookingService;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$settings = ($settings ?? null) instanceof ReservationSettings ? $settings : new ReservationSettings();
$catalog = is_array($catalog ?? null) ? $catalog : [];
$prestations = is_array($catalog['prestations'] ?? null) ? $catalog['prestations'] : [];
$team = is_array($catalog['team'] ?? null) ? $catalog['team'] : [];
$closures = is_array($closures ?? null) ? $closures : [];
$whatsapp = is_string($whatsapp ?? null) ? $whatsapp : '';
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$slug = $tenant?->slug() ?? '';
$name = $tenant?->displayName() ?? 'Salon';
$base = '/t/' . $slug;
$version = rawurlencode($settings->updatedAt());
$val = static function (string $key) use ($old): string {
    $value = $old[$key] ?? '';

    return is_scalar($value) ? (string) $value : '';
};
$hours = $settings->hours();
$todayIndex = (int) gmdate('N');
$todayHours = $hours[$todayIndex] ?? [];
$nowTime = gmdate('H:i:s');
$openNow = false;
foreach ($todayHours as [$from, $to]) {
    if ($nowTime >= $from && $nowTime < $to) {
        $openNow = true;
    }
}
$range = static fn (array $ranges): string => implode(' · ', array_map(static fn (array $r): string => substr($r[0], 0, 5) . '–' . substr($r[1], 0, 5), $ranges));
$groups = [];
foreach ($prestations as $item) {
    if ($item instanceof ReservationPrestation) {
        $groups[$item->category() !== '' ? $item->category() : 'Prestations'][] = $item;
    }
}
$canBook = $settings->isOpen() && $prestations !== [];
$days = [];
$start = (int) strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
$count = min(21, $settings->maxAdvanceDays() + 1);
for ($i = 0; $i < $count; $i++) {
    $days[] = $start + $i * 86400;
}
$short = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$months = ['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
$hasDeposit = array_filter($prestations, static fn ($p): bool => $p instanceof ReservationPrestation && $p->depositMinor() >= 100) !== [];
$step = 1;
$anchor = static fn (string $text): string => 'cat-' . substr(md5($text), 0, 8);

?>
<header class="sl-top" data-sl-top>
    <a class="sl-brand" href="<?= Html::url($base) ?>">
        <?php if ($settings->logoPath() !== null): ?>
            <img class="sl-brand-logo" src="<?= Html::url($base . '/logo') ?>?v=<?= Html::e($version) ?>" alt="" width="40" height="40">
        <?php else: ?>
            <span class="sl-brand-logo is-text" aria-hidden="true"><?= Html::e(Html::initials($name)) ?></span>
        <?php endif; ?>
        <span><?= Html::e($name) ?></span>
    </a>
    <?php if ($canBook): ?><a class="sl-btn sl-btn-sm" href="#reserver">Réserver</a><?php endif; ?>
</header>

<section class="sl-hero<?= $settings->coverPath() !== null ? ' has-cover' : '' ?>">
    <div class="sl-hero-media" aria-hidden="true">
        <?php if ($settings->coverPath() !== null): ?>
            <img src="<?= Html::url($base . '/couverture') ?>?v=<?= Html::e($version) ?>" alt="" width="1600" height="700">
        <?php endif; ?>
    </div>
    <div class="sl-hero-inner">
        <div class="sl-identity">
            <?php if ($settings->logoPath() !== null): ?>
                <img class="sl-avatar" src="<?= Html::url($base . '/logo') ?>?v=<?= Html::e($version) ?>" alt="Logo de <?= Html::e($name) ?>" width="112" height="112">
            <?php else: ?>
                <span class="sl-avatar is-text" aria-hidden="true"><?= Html::e(Html::initials($name)) ?></span>
            <?php endif; ?>
            <div>
                <?php if ($hours !== []): ?>
                    <p class="sl-open <?= $openNow ? 'is-open' : 'is-closed' ?>"><i aria-hidden="true"></i><?= $openNow ? 'Ouvert maintenant' : ($todayHours === [] ? 'Fermé aujourd\'hui' : 'Fermé pour le moment') ?><?= $todayHours !== [] ? ' · ' . Html::e($range($todayHours)) : '' ?></p>
                <?php endif; ?>
                <h1><?= Html::e($name) ?></h1>
                <?php if ($settings->tagline() !== ''): ?><p class="sl-tagline"><?= Html::e($settings->tagline()) ?></p><?php endif; ?>
            </div>
        </div>
        <ul class="sl-chips">
            <?php if ($settings->address() !== ''): ?><li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg><?= Html::e($settings->address()) ?></li><?php endif; ?>
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l4 4 10-10"/></svg>Réservation en 1 minute, sans compte</li>
            <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z"/></svg>Confirmation sur WhatsApp</li>
        </ul>
        <div class="sl-hero-actions">
            <?php if ($canBook): ?><a class="sl-btn sl-btn-light" href="#reserver">Prendre rendez-vous</a><?php endif; ?>
            <?php if ($whatsapp !== ''): ?><a class="sl-btn sl-btn-outline" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">Écrire sur WhatsApp</a><?php endif; ?>
        </div>
    </div>
</section>

<main class="sl-main">
    <?php if (!$settings->isOpen()): ?>
        <p class="sl-notice" role="status">La réservation en ligne est fermée pour le moment.<?= $whatsapp !== '' ? ' Vous pouvez écrire au salon sur WhatsApp.' : '' ?></p>
    <?php endif; ?>
    <?php foreach (array_slice($closures, 0, 2) as $closure): ?>
        <p class="sl-notice" role="status">Fermé <?= $closure['ends_on'] !== $closure['starts_on'] ? 'du ' . Html::e(gmdate('d/m', (int) strtotime($closure['starts_on']))) . ' au ' . Html::e(gmdate('d/m', (int) strtotime($closure['ends_on']))) : 'le ' . Html::e(gmdate('d/m', (int) strtotime($closure['starts_on']))) ?><?= $closure['reason'] !== '' ? ' — ' . Html::e($closure['reason']) : '' ?>.</p>
    <?php endforeach; ?>

    <ul class="sl-perks" aria-label="Pourquoi réserver en ligne">
        <li><b>1 min</b><span>pour réserver en ligne</span></li>
        <li><b>WhatsApp</b><span>confirmation et rappel</span></li>
        <li><b><?= $hasDeposit ? 'Wave · OM' : '0 compte' ?></b><span><?= $hasDeposit ? 'acompte sécurisé' : 'sans inscription' ?></span></li>
    </ul>

    <?php if ($settings->description() !== ''): ?>
        <section class="sl-about">
            <h2>Bienvenue chez <?= Html::e($name) ?></h2>
            <p><?= nl2br(Html::e($settings->description())) ?></p>
        </section>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="sl-alert" role="alert" id="erreurs"><?php foreach ($errors as $message): ?><p><?= Html::e((string) $message) ?></p><?php endforeach; ?></div>
    <?php endif; ?>

    <?php if ($prestations === []): ?>
        <div class="sl-empty"><p>Les prestations arrivent bientôt.</p></div>
    <?php else: ?>
        <form class="sl-book" id="reserver" method="post" action="<?= Html::url($base) ?>" data-salon="<?= Html::url($base . '/creneaux') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <div class="sl-book-steps">
                <section class="sl-step" aria-labelledby="step-<?= $step ?>">
                    <header class="sl-step-head">
                        <span class="sl-step-num"><?= $step ?></span>
                        <h2 id="step-<?= $step++ ?>">Nos prestations</h2>
                    </header>
                    <?php if (count($groups) > 1): ?>
                        <nav class="sl-cats" aria-label="Catégories">
                            <?php foreach (array_keys($groups) as $category): ?>
                                <a href="#<?= Html::e($anchor((string) $category)) ?>"><?= Html::e((string) $category) ?></a>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>
                    <?php foreach ($groups as $category => $items): ?>
                        <h3 class="sl-cat" id="<?= Html::e($anchor((string) $category)) ?>"><?= Html::e((string) $category) ?></h3>
                        <div class="sl-services">
                            <?php foreach ($items as $item): ?>
                                <label class="sl-service<?= $item->imagePath() !== null ? ' has-photo' : '' ?>">
                                    <input type="radio" name="service_id" value="<?= $item->id() ?>" required data-name="<?= Html::e($item->name()) ?>" data-price="<?= Html::e(BookingService::money($item->priceMinor())) ?>" data-deposit="<?= $item->depositMinor() ?>" data-duration="<?= Html::e($item->durationLabel()) ?>"<?= $val('service_id') === (string) $item->id() ? ' checked' : '' ?><?= $canBook ? '' : ' disabled' ?>>
                                    <span class="sl-service-media" aria-hidden="true">
                                        <?php if ($item->imagePath() !== null): ?>
                                            <img src="<?= Html::url($base . '/prestation/' . $item->id() . '/image') ?>?v=<?= Html::e($item->imageVersion()) ?>" alt="" width="480" height="360" loading="lazy" decoding="async">
                                        <?php else: ?>
                                            <span class="sl-service-initials"><?= Html::e(Html::initials($item->name())) ?></span>
                                        <?php endif; ?>
                                        <span class="sl-service-check">Choisie</span>
                                    </span>
                                    <span class="sl-service-body">
                                        <strong><?= Html::e($item->name()) ?></strong>
                                        <?php if ($item->description() !== ''): ?><small><?= Html::e($item->description()) ?></small><?php endif; ?>
                                        <span class="sl-service-meta">
                                            <span class="sl-duration"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg><?= Html::e($item->durationLabel()) ?></span>
                                            <b><?= Html::e(BookingService::money($item->priceMinor())) ?></b>
                                        </span>
                                        <?php if ($item->depositMinor() >= 100): ?><em>Acompte <?= Html::e(BookingService::money($item->depositMinor())) ?> à la réservation</em><?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </section>

                <?php if ($canBook): ?>
                    <?php if ($team !== []): ?>
                        <section class="sl-step" aria-labelledby="step-<?= $step ?>">
                            <header class="sl-step-head">
                                <span class="sl-step-num"><?= $step ?></span>
                                <h2 id="step-<?= $step++ ?>">Avec qui ?</h2>
                            </header>
                            <div class="sl-people">
                                <label class="sl-person"><input type="radio" name="staff_id" value=""<?= $val('staff_id') === '' ? ' checked' : '' ?>><span><i>✦</i><strong>Peu importe</strong><small>La première personne libre</small></span></label>
                                <?php foreach ($team as $member): ?>
                                    <?php if (!$member instanceof ReservationTeamMember) { continue; } ?>
                                    <label class="sl-person" data-services="<?= Html::e(implode(',', $member->serviceIds())) ?>"><input type="radio" name="staff_id" value="<?= $member->id() ?>"<?= $val('staff_id') === (string) $member->id() ? ' checked' : '' ?>><span><i><?= Html::e(Html::initials($member->name())) ?></i><strong><?= Html::e($member->name()) ?></strong><?php if ($member->roleLabel() !== ''): ?><small><?= Html::e($member->roleLabel()) ?></small><?php endif; ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="sl-step" aria-labelledby="step-<?= $step ?>">
                        <header class="sl-step-head">
                            <span class="sl-step-num"><?= $step ?></span>
                            <h2 id="step-<?= $step++ ?>">Choisissez le jour et l'heure</h2>
                        </header>
                        <div class="sl-days" data-days hidden>
                            <?php foreach ($days as $index => $ts): ?>
                                <button type="button" data-day="<?= Html::e(gmdate('Y-m-d', $ts)) ?>"<?= $index === 0 ? ' aria-pressed="true"' : ' aria-pressed="false"' ?>><small><?= $index === 0 ? 'Auj.' : ($index === 1 ? 'Dem.' : Html::e($short[(int) gmdate('N', $ts)])) ?></small><strong><?= Html::e(gmdate('j', $ts)) ?></strong><em><?= Html::e($months[(int) gmdate('n', $ts)]) ?></em></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="sl-slots" data-slots aria-live="polite" hidden></div>
                        <div class="sl-manual" data-manual>
                            <label for="starts_at">Date et heure souhaitées</label>
                            <input id="starts_at" name="starts_at" type="datetime-local" required value="<?= Html::e(str_replace(' ', 'T', substr($val('starts_at'), 0, 16))) ?>">
                        </div>
                    </section>

                    <section class="sl-step" aria-labelledby="step-<?= $step ?>">
                        <header class="sl-step-head">
                            <span class="sl-step-num"><?= $step ?></span>
                            <h2 id="step-<?= $step++ ?>">Vos coordonnées</h2>
                        </header>
                        <div class="sl-fields">
                            <div class="sl-field"><label for="full_name">Votre nom</label><input id="full_name" name="full_name" required maxlength="120" autocomplete="name" value="<?= Html::e($val('full_name')) ?>"<?= isset($errors['full_name']) ? ' aria-invalid="true"' : '' ?>></div>
                            <div class="sl-field"><label for="phone">Votre téléphone (WhatsApp)</label><input id="phone" name="phone" type="tel" required maxlength="40" autocomplete="tel" placeholder="77 123 45 67" value="<?= Html::e($val('phone')) ?>"<?= isset($errors['phone']) ? ' aria-invalid="true"' : '' ?>></div>
                            <div class="sl-field"><label for="email">E-mail <span>(facultatif)</span></label><input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= Html::e($val('email')) ?>"></div>
                            <div class="sl-field is-wide"><label for="notes">Un message pour le salon <span>(facultatif)</span></label><textarea id="notes" name="notes" rows="2" maxlength="500"><?= Html::e($val('notes')) ?></textarea></div>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <?php if ($canBook): ?>
                <aside class="sl-summary" aria-label="Votre rendez-vous">
                    <p class="sl-summary-title">Votre rendez-vous</p>
                    <p class="sl-summary-text" data-summary>Choisissez une prestation et un créneau.</p>
                    <button class="sl-btn sl-btn-block" type="submit" data-submit>Réserver mon rendez-vous</button>
                    <p class="sl-summary-hint">Confirmation sur WhatsApp, puis un rappel la veille.<?= $settings->cancellationPolicy() !== '' ? ' ' . Html::e($settings->cancellationPolicy()) : '' ?></p>
                </aside>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <section class="sl-info">
        <?php if ($hours !== []): ?>
            <div class="sl-card">
                <h2>Horaires</h2>
                <ul class="sl-hours">
                    <?php for ($day = 1; $day <= 7; $day++): ?>
                        <li<?= $day === $todayIndex ? ' class="is-today"' : '' ?>><span><?= Html::e(BookingService::weekdayLabel($day)) ?></span><strong><?= isset($hours[$day]) ? Html::e($range($hours[$day])) : 'Fermé' ?></strong></li>
                    <?php endfor; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="sl-card sl-contact">
            <h2>Nous trouver</h2>
            <?php if ($settings->address() !== ''): ?><p class="sl-address"><?= Html::e($settings->address()) ?></p><?php endif; ?>
            <?php if ($team !== []): ?>
                <p class="sl-team-line">
                    <?php foreach (array_slice($team, 0, 6) as $member): ?>
                        <?php if ($member instanceof ReservationTeamMember): ?><span class="sl-team-avatar" title="<?= Html::e($member->name()) ?>"><?= Html::e(Html::initials($member->name())) ?></span><?php endif; ?>
                    <?php endforeach; ?>
                    <span><?= count($team) ?> personne(s) pour vous accueillir</span>
                </p>
            <?php endif; ?>
            <?php if ($whatsapp !== ''): ?><a class="sl-btn sl-btn-whatsapp" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">Une question ? WhatsApp</a><?php endif; ?>
            <?php if ($settings->cancellationPolicy() !== ''): ?><p class="sl-policy"><?= Html::e($settings->cancellationPolicy()) ?></p><?php endif; ?>
        </div>
    </section>
</main>

<footer class="sl-foot">
    <p>Réservation en ligne avec <a href="<?= Html::url('/services/reservation') ?>">Sesam Réservation</a></p>
</footer>
