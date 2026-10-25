<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/**
 * Scène du héros d'accueil : une vue par mot rotatif (boutique, réservations, loyers, site).
 *
 * @var string $icon chemin vers partials/ui-icon.php
 * @var string $mocks chemin vers mocks/
 */

$scenes = [
    [
        'key' => 'boutique',
        'image' => 'sesam-hero-boutique.jpg',
        'alt' => 'Commerçante tenant un téléphone qui affiche l\'application Sesam Boutique',
        'mock' => 'shop.php',
        'device' => 'phone',
        'toast_icon' => 'bag',
        'toast_tone' => 'order',
        'toast_title' => 'Nouvelle commande',
        'toast_meta' => '2 articles · 37 000 FCFA',
        'chip' => 'Sesam Boutique',
    ],
    [
        'key' => 'reservation',
        'image' => 'sesam-hero-reservation.jpg',
        'alt' => 'Personne qui réserve un créneau sur tablette, avec notification RDV confirmé sur téléphone',
        'mock' => 'booking.php',
        'device' => 'panel',
        'toast_icon' => 'calendar',
        'toast_tone' => 'rdv',
        'toast_title' => 'RDV confirmé',
        'toast_meta' => 'Mercredi · 14:00',
        'chip' => 'Sesam Réservation',
    ],
    [
        'key' => 'immo',
        'image' => 'sesam-hero-immo.jpg',
        'alt' => 'Gestionnaire immobilier avec clés et tableau de bord des loyers sur tablette',
        'mock' => 'rent.php',
        'device' => 'panel',
        'toast_icon' => 'building',
        'toast_tone' => 'rent',
        'toast_title' => 'Relance envoyée',
        'toast_meta' => 'Loyer octobre · WhatsApp',
        'chip' => 'Sesam Immo',
    ],
    [
        'key' => 'agence',
        'image' => 'sesam-hero-web.jpg',
        'alt' => 'Créateur web avec outils de conception, site sur desktop, application sur tablette et mobile',
        'mock' => 'site.php',
        'device' => 'browser',
        'toast_icon' => 'briefcase',
        'toast_tone' => 'site',
        'toast_title' => 'Site en ligne',
        'toast_meta' => 'votre-commerce.sn',
        'chip' => 'Sesam Digital',
    ],
];

?>
<div class="hx-stage" data-hx-stage aria-hidden="true">
    <div class="hx-scenes">
        <?php foreach ($scenes as $index => $scene): ?>
            <article class="hx-scene<?= $index === 0 ? ' is-on' : '' ?>" data-hx-scene="<?= (int) $index ?>" data-service="<?= Html::e($scene['key']) ?>">
                <figure class="hx-shot">
                    <?= Html::picture($scene['image'], $scene['alt'], 960, 720, $index > 0) ?>
                    <span class="hx-shot-veil"></span>
                    <span class="hx-shot-chip"><?= Html::e($scene['chip']) ?></span>
                </figure>
                <div class="hx-device hx-device--<?= Html::e($scene['device']) ?>">
                    <?php require $mocks . $scene['mock']; ?>
                </div>
                <p class="hx-toast hx-toast--<?= Html::e($scene['toast_tone']) ?>">
                    <i><?php $name = $scene['toast_icon']; include $icon; ?></i>
                    <span><strong><?= Html::e($scene['toast_title']) ?></strong><small><?= Html::e($scene['toast_meta']) ?></small></span>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="hx-sticker">
        <svg viewBox="0 0 120 120">
            <defs><path id="hx-circle" d="M60 60m-44 0a44 44 0 1 1 88 0a44 44 0 1 1 -88 0"/></defs>
            <text font-size="11.5" font-weight="800" letter-spacing="2.4" fill="#071a3d"><textPath href="#hx-circle">SESAM · DAKAR · NEW YORK · SESAM · </textPath></text>
        </svg>
        <b>✺</b>
    </div>
</div>
