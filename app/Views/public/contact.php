<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$landing = $catalog['landing'];
/** @var array{phones: list<array{label: string, display: string, tel: string}>, address: string, map_embed: string} $contactInfo */
$contactInfo = $catalog['contact'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
$notes = [
    ['file', 'Décrivez votre besoin', "Vous décrivez le site, ou l'outil : Réservation, Boutique en ligne, Immo."],
    ['card', 'Pour vos clients', 'Wave, Orange Money et WhatsApp concernent vos clients, pas ce formulaire.'],
    ['folder', 'Discrétion', "Rien n'est publié tant qu'un projet n'est pas livré."],
];

?>
<main class="page-sheet page-contact">
    <header class="ph">
        <div class="s-wrap ph-grid">
            <div>
                <p class="s-kicker"><?= Html::e($landing['signature']) ?></p>
                <h1 class="s-display" style="margin-top: 1rem">Une question&nbsp;? <span class="s-mark">On vous répond.</span></h1>
            </div>
            <div style="display: grid; gap: 1.4rem">
                <p class="s-lead">L'équipe SESAM est joignable à Dakar et depuis New York. Appelez-nous, passez au bureau ou lancez votre essai en ligne — nous revenons vers vous rapidement.</p>
                <div class="s-actions">
                    <a class="s-btn" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
                    <a class="s-btn s-btn--ghost s-btn--plain" href="<?= Html::url('/devis') ?>">Projet de site web : demander un devis</a>
                </div>
            </div>
        </div>
    </header>

    <section class="s-wrap contact-coords" aria-labelledby="contact-reach">
        <h2 class="s-section-title" id="contact-reach">Nous joindre</h2>
        <div class="contact-coords-grid">
            <div class="contact-coords-card">
                <h3>WhatsApp et e-mail</h3>
                <ul class="contact-phone-list">
                    <?php foreach ($contactInfo['phones'] as $phone): ?>
                        <li>
                            <span class="contact-phone-label"><?= Html::e($phone['label']) ?></span>
                            <a class="contact-phone-link contact-wa" href="https://wa.me/<?= Html::e(ltrim($phone['tel'], '+')) ?>" target="_blank" rel="noopener noreferrer" aria-label="Écrire sur WhatsApp au <?= Html::e($phone['display']) ?> (<?= Html::e($phone['label']) ?>)">
                                <svg class="contact-wa-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg>
                                <?= Html::e($phone['display']) ?>
                            </a>
                            <?php if (($phone['email'] ?? '') !== ''): ?>
                                <a class="contact-mail" href="mailto:<?= Html::e($phone['email']) ?>"><?= Html::e($phone['email']) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="contact-address">
                    <span class="contact-phone-label">Adresse</span>
                    <?= Html::e($contactInfo['address']) ?>
                </p>
            </div>
            <div class="contact-map-wrap">
                <iframe
                    class="contact-map"
                    title="Carte — <?= Html::e($contactInfo['address']) ?>"
                    src="<?= Html::e($contactInfo['map_embed']) ?>"
                    width="600"
                    height="320"
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
                <p class="contact-map-caption">
                    <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($contactInfo['address']) ?>" rel="noopener noreferrer" target="_blank">Ouvrir dans Google Maps</a>
                </p>
            </div>
        </div>
    </section>

    <section class="s-wrap" style="padding-block: 24px 16px" aria-labelledby="contact-how">
        <h2 class="sr-only" id="contact-how">Comment nous contactons</h2>
        <div class="cards">
            <?php foreach ($notes as $index => [$noteIcon, $noteTitle, $noteText]): ?>
                <article class="card" data-reveal data-reveal-delay="<?= $index ?>">
                    <span class="chip-ico" aria-hidden="true"><?php $name = $noteIcon; include $icon; ?></span>
                    <h3><?= Html::e($noteTitle) ?></h3>
                    <p><?= Html::e($noteText) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>
