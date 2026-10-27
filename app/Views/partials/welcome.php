<?php

declare(strict_types=1);

/*
 * Popup de bienvenue affiché une fois, juste après la connexion (préparé par WelcomeService) :
 *  - first : première connexion d'un commerce, visite guidée en 3 écrans ;
 *  - back  : retour d'un commerce, salutation du moment et chiffres du jour ;
 *  - team  : équipe SESAM, aperçu de la console.
 * Illustrations en SVG animées en CSS (welcome.css) ; ouverture, écrans et compteurs gérés par welcome.js.
 */

use Sesam\Helpers\Html;

$welcome = is_array($welcome ?? null) ? $welcome : null;
if ($welcome === null) {
    return;
}
$type = in_array($welcome['type'] ?? '', ['first', 'back', 'team'], true) ? (string) $welcome['type'] : 'back';
$firstName = (string) ($welcome['firstName'] ?? '');
$greeting = (string) ($welcome['greeting'] ?? 'Bonjour');
$moment = (string) ($welcome['moment'] ?? 'day');
$business = (string) ($welcome['business'] ?? '');
$sector = (string) ($welcome['sector'] ?? '');
$tiles = is_array($welcome['tiles'] ?? null) ? $welcome['tiles'] : [];
$slides = is_array($welcome['slides'] ?? null) ? $welcome['slides'] : [];
$unread = (int) ($welcome['unread'] ?? 0);
$tileIcon = static function (string $icon): string {
    return match ($icon) {
        'calendar' => '<path d="M5 7h14v12H5z"/><path d="M5 11h14M9 4v4M15 4v4"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
        'bag' => '<path d="M6 8h12l-1 11H7z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'box' => '<path d="M4 8l8-4 8 4v8l-8 4-8-4z"/><path d="M4 8l8 4 8-4M12 12v8"/>',
        'home' => '<path d="M4 11l8-7 8 7v9H4z"/><path d="M10 20v-5h4v5"/>',
        default => '<path d="M12 3l2.2 5.8L20 11l-5.8 2.2L12 19l-2.2-5.8L4 11l5.8-2.2z"/>',
    };
};
$sectorIcon = $sector === 'boutique' ? 'bag' : ($sector === 'reservation' ? 'calendar' : 'home');

?>
<link rel="stylesheet" href="<?= Html::asset('assets/css/welcome.css') ?>">
<div class="wl wl-<?= Html::e($type) ?> wl-<?= Html::e($moment) ?>" data-welcome hidden>
    <div class="wl-backdrop" data-welcome-close></div>
    <section class="wl-card" role="dialog" aria-modal="true" aria-labelledby="wl-title">
        <button class="wl-close" type="button" data-welcome-close aria-label="Fermer">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>

        <div class="wl-art" aria-hidden="true">
            <span class="wl-dots"></span>
            <?php if ($type === 'first'): ?>
                <!-- Fusée qui décolle, orbite, étoiles et cartes du métier qui flottent -->
                <svg class="wl-scene" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid slice">
                    <g class="wl-stars">
                        <circle cx="40" cy="40" r="2"/><circle cx="96" cy="26" r="1.5"/><circle cx="330" cy="34" r="2"/>
                        <circle cx="362" cy="96" r="1.5"/><circle cx="58" cy="150" r="1.5"/><circle cx="300" cy="176" r="2"/>
                        <circle cx="150" cy="40" r="1.2"/><circle cx="252" cy="22" r="1.4"/>
                    </g>
                    <circle class="wl-orbit" cx="200" cy="112" r="82"/>
                    <circle class="wl-planet" cx="200" cy="112" r="46"/>
                    <ellipse class="wl-ring" cx="200" cy="116" rx="70" ry="14"/>
                    <circle class="wl-moonlet" cx="282" cy="112" r="7"/>
                    <g class="wl-rocket">
                        <g class="wl-flame"><path d="M193 168 L200 192 L207 168 Z"/></g>
                        <path class="wl-rocket-body" d="M200 70 C214 84 218 112 214 164 L186 164 C182 112 186 84 200 70 Z"/>
                        <circle class="wl-rocket-window" cx="200" cy="112" r="8"/>
                        <path class="wl-rocket-fin" d="M186 140 L172 168 L186 162 Z M214 140 L228 168 L214 162 Z"/>
                    </g>
                    <g class="wl-float wl-float-a"><rect x="40" y="76" width="76" height="52" rx="12"/><path class="wl-float-line" d="M54 96h48M54 108h30"/></g>
                    <g class="wl-float wl-float-b"><rect x="286" y="132" width="78" height="46" rx="12"/><circle class="wl-float-dot" cx="304" cy="155" r="7"/><path class="wl-float-line" d="M318 150h32M318 161h22"/></g>
                </svg>
                <div class="wl-confetti"><?php for ($i = 0; $i < 18; $i++): ?><i></i><?php endfor; ?></div>
            <?php elseif ($type === 'team'): ?>
                <!-- Console : tableau de bord qui s'anime (barres, courbe, points) -->
                <svg class="wl-scene" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid slice">
                    <g class="wl-stars"><circle cx="34" cy="30" r="1.5"/><circle cx="368" cy="40" r="2"/><circle cx="350" cy="190" r="1.5"/><circle cx="48" cy="186" r="2"/></g>
                    <g class="wl-window">
                        <rect class="wl-window-frame" x="70" y="34" width="260" height="160" rx="16"/>
                        <path class="wl-window-bar" d="M70 50 a16 16 0 0 1 16 -16 h228 a16 16 0 0 1 16 16 v10 H70 Z"/>
                        <circle class="wl-light" cx="88" cy="47" r="4"/><circle class="wl-light" cx="102" cy="47" r="4"/><circle class="wl-light" cx="116" cy="47" r="4"/>
                        <g class="wl-bars">
                            <rect x="96" y="120" width="18" height="56" rx="5"/><rect x="122" y="104" width="18" height="72" rx="5"/>
                            <rect x="148" y="132" width="18" height="44" rx="5"/><rect x="174" y="92" width="18" height="84" rx="5"/>
                        </g>
                        <path class="wl-line" d="M212 160 L236 136 L258 148 L282 108 L306 118"/>
                        <circle class="wl-pulse" cx="306" cy="118" r="6"/>
                        <path class="wl-window-rule" d="M210 176h100"/>
                    </g>
                    <g class="wl-float wl-float-a"><circle cx="58" cy="96" r="22"/><path class="wl-float-check" d="M48 96l7 7 13-14"/></g>
                    <g class="wl-float wl-float-b"><rect x="318" y="140" width="64" height="40" rx="12"/><path class="wl-float-line" d="M330 156h40M330 166h26"/></g>
                </svg>
            <?php else: ?>
                <!-- Retour : soleil du matin, soleil de l'après-midi ou lune du soir -->
                <svg class="wl-scene" viewBox="0 0 400 220" preserveAspectRatio="xMidYMid slice">
                    <g class="wl-stars"><circle cx="50" cy="36" r="2"/><circle cx="110" cy="70" r="1.4"/><circle cx="320" cy="30" r="1.8"/><circle cx="356" cy="88" r="1.4"/><circle cx="270" cy="60" r="1.2"/><circle cx="80" cy="120" r="1.4"/></g>
                    <?php if ($moment === 'night'): ?>
                        <g class="wl-moon"><circle cx="200" cy="96" r="44"/><circle class="wl-moon-cut" cx="222" cy="80" r="40"/></g>
                    <?php else: ?>
                        <g class="wl-sun<?= $moment === 'morning' ? ' is-rising' : '' ?>">
                            <g class="wl-rays"><path d="M200 22v20M200 150v20M126 96h20M254 96h20M148 44l14 14M238 134l14 14M148 148l14-14M238 58l14-14"/></g>
                            <circle cx="200" cy="96" r="40"/>
                        </g>
                    <?php endif; ?>
                    <g class="wl-cloud wl-cloud-a"><path d="M60 150 a18 18 0 0 1 34 -8 a14 14 0 0 1 26 8 a12 12 0 0 1 -2 24 h-56 a12 12 0 0 1 -2 -24 z"/></g>
                    <g class="wl-cloud wl-cloud-b"><path d="M282 120 a16 16 0 0 1 30 -6 a12 12 0 0 1 22 8 a10 10 0 0 1 -2 20 h-48 a10 10 0 0 1 -2 -22 z"/></g>
                    <path class="wl-hills" d="M0 200 Q100 150 200 182 T400 172 V220 H0 Z"/>
                    <g class="wl-float wl-float-a wl-badge"><circle cx="330" cy="176" r="22"/><g transform="translate(318 164)"><svg viewBox="0 0 24 24" width="24" height="24" class="wl-badge-icon"><?= $tileIcon($sectorIcon) ?></svg></g></g>
                </svg>
            <?php endif; ?>
        </div>

        <div class="wl-body">
            <?php if ($type === 'first'): ?>
                <p class="wl-eyebrow">Bienvenue sur SESAM, <?= Html::e($firstName) ?> !</p>
                <div class="wl-slides" data-welcome-slides>
                    <?php foreach ($slides as $index => $slide): ?>
                        <div class="wl-slide" data-welcome-slide<?= $index > 0 ? ' hidden' : '' ?>>
                            <h2 <?= $index === 0 ? 'id="wl-title"' : '' ?>><?= Html::e((string) $slide['title']) ?></h2>
                            <p class="wl-text"><?= Html::e((string) $slide['text']) ?></p>
                            <?php if (!empty($slide['steps']) && is_array($slide['steps'])): ?>
                                <ol class="wl-steps">
                                    <?php foreach ($slide['steps'] as $n => $item): ?>
                                        <li><a href="<?= Html::url((string) $item['href']) ?>"><span><?= $n + 1 ?></span><?= Html::e((string) $item['label']) ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></a></li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="wl-footer">
                    <div class="wl-pager" aria-hidden="true"><?php foreach ($slides as $index => $slide): ?><i<?= $index === 0 ? ' class="is-on"' : '' ?>></i><?php endforeach; ?></div>
                    <div class="wl-actions">
                        <button class="wl-btn wl-btn-ghost" type="button" data-welcome-skip>Passer</button>
                        <button class="wl-btn" type="button" data-welcome-next data-last-label="Commencer">Suivant</button>
                    </div>
                </div>
            <?php else: ?>
                <p class="wl-eyebrow"><?= $type === 'team' ? 'Console SESAM · ' : '' ?><?= Html::e((string) ($welcome['date'] ?? '')) ?></p>
                <h2 id="wl-title"><?= Html::e($greeting . ', ' . $firstName) ?> <span class="wl-wave" aria-hidden="true">👋</span></h2>
                <p class="wl-text"><?= $type === 'team'
                    ? 'Quel plaisir de vous revoir dans la console. Voici l\'essentiel avant de commencer.'
                    : 'Quel plaisir de vous revoir ! Voici votre journée chez ' . Html::e($business) . '.' ?></p>
                <?php if ($tiles !== []): ?>
                    <ul class="wl-tiles">
                        <?php foreach ($tiles as $tile): ?>
                            <li>
                                <a href="<?= Html::url((string) $tile['href']) ?>"<?= (int) $tile['value'] > 0 ? ' class="has-value"' : '' ?>>
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><?= $tileIcon((string) $tile['icon']) ?></svg>
                                    <b data-count="<?= (int) $tile['value'] ?>"><?= (int) $tile['value'] ?></b>
                                    <span><?= Html::e((string) $tile['label']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($unread > 0): ?>
                    <a class="wl-unread" href="<?= Html::url('/notifications') ?>"><i></i><?= $unread ?> notification<?= $unread > 1 ? 's' : '' ?> non lue<?= $unread > 1 ? 's' : '' ?></a>
                <?php endif; ?>
                <div class="wl-footer">
                    <div class="wl-actions">
                        <button class="wl-btn wl-btn-wide" type="button" data-welcome-close data-welcome-main><?= $type === 'team' ? 'Ouvrir la console' : 'C\'est parti' ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>
<script src="<?= Html::asset('assets/js/welcome.js') ?>" defer></script>
