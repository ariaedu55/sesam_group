<?php

declare(strict_types=1);

use Sesam\Core\Messaging\DeliveryStatus;
use Sesam\Helpers\Html;
use Sesam\Models\ReservationSettings;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$settings = ($settings ?? null) instanceof ReservationSettings ? $settings : new ReservationSettings();
$heading = is_string($heading ?? null) ? $heading : 'Demande enregistrée';
$summary = is_string($summary ?? null) ? $summary : '';
$whatsapp = is_string($whatsapp ?? null) ? $whatsapp : '';
$sent = ($whatsapp_status ?? '') === DeliveryStatus::SENT;
$slug = $tenant?->slug() ?? '';
$name = $tenant?->displayName() ?? 'Salon';
$base = '/t/' . $slug;
$version = rawurlencode($settings->updatedAt());
$confirmed = str_contains(mb_strtolower($heading), 'confirmé');
$pending = str_contains($summary, 'le salon confirme');
$summary = trim(str_replace(' — le salon confirme très vite.', '', $summary));
[$what, $when] = array_pad(array_map('trim', explode(' — ', $summary, 2)), 2, '');

?>
<header class="sl-top">
    <a class="sl-brand" href="<?= Html::url($base) ?>">
        <?php if ($settings->logoPath() !== null): ?>
            <img class="sl-brand-logo" src="<?= Html::url($base . '/logo') ?>?v=<?= Html::e($version) ?>" alt="" width="40" height="40">
        <?php else: ?>
            <span class="sl-brand-logo is-text" aria-hidden="true"><?= Html::e(Html::initials($name)) ?></span>
        <?php endif; ?>
        <span><?= Html::e($name) ?></span>
    </a>
</header>

<main class="sl-done">
    <section class="sl-done-card" aria-labelledby="done-title">
        <span class="sl-done-icon<?= $pending ? ' is-pending' : '' ?>" aria-hidden="true">
            <?php if ($pending): ?>
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
            <?php else: ?>
                <svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            <?php endif; ?>
        </span>
        <h1 id="done-title"><?= Html::e($heading) ?></h1>
        <p class="sl-done-lead"><?= $pending
            ? 'Merci ! ' . Html::e($name) . ' a bien reçu votre demande et vous confirme le rendez-vous très vite.'
            : ($confirmed ? 'C\'est noté, ' . Html::e($name) . ' vous attend.' : 'Merci, votre demande est bien enregistrée.') ?></p>

        <?php if ($what !== ''): ?>
            <dl class="sl-done-recap">
                <div><dt>Prestation</dt><dd><?= Html::e($what) ?></dd></div>
                <?php if ($when !== ''): ?><div><dt>Quand</dt><dd><?= Html::e(ucfirst($when)) ?></dd></div><?php endif; ?>
                <?php if ($settings->address() !== ''): ?><div><dt>Où</dt><dd><?= Html::e($settings->address()) ?></dd></div><?php endif; ?>
            </dl>
        <?php endif; ?>

        <h2 class="sl-done-next-title">Et maintenant ?</h2>
        <ol class="sl-done-next">
            <?php if ($pending): ?>
                <li><b>Le salon valide votre demande</b><span>Vous recevez un message dès que c'est confirmé.</span></li>
            <?php endif; ?>
            <li><b><?= $sent ? 'Confirmation envoyée sur WhatsApp' : 'Confirmation par message' ?></b><span>Gardez votre téléphone à portée de main<?= $sent ? '' : ' (et regardez vos e-mails si vous en avez donné un)' ?>.</span></li>
            <li><b>Un rappel la veille</b><span>Pour ne pas oublier votre rendez-vous.</span></li>
        </ol>

        <div class="sl-done-actions">
            <?php if ($whatsapp !== ''): ?>
                <a class="sl-btn sl-btn-whatsapp" href="<?= Html::e($whatsapp) ?>" target="_blank" rel="noopener">Une question ? Écrire sur WhatsApp</a>
            <?php endif; ?>
            <a class="sl-btn sl-btn-ghost" href="<?= Html::url($base) ?>">Retour au salon</a>
        </div>
        <?php if ($settings->cancellationPolicy() !== ''): ?>
            <p class="sl-done-policy"><?= Html::e($settings->cancellationPolicy()) ?></p>
        <?php endif; ?>
    </section>
</main>

<footer class="sl-foot">
    <p>Réservation en ligne avec <a href="<?= Html::url('/services/reservation') ?>">Sesam Réservation</a></p>
</footer>
