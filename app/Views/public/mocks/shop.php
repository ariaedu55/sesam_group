<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** Maquette de téléphone : une boutique Sesam avec ses produits et le bouton WhatsApp. */
$mockShop = [
    'name' => 'Awa Couture',
    // Nom, prix et vraie photo du produit (public/assets/images/boutique/).
    'products' => [
        ['Robe wax', '25 000 F', 'robe.jpg'],
        ['Sac raphia', '12 000 F', 'sac.jpg'],
        ['Bijoux dorés', '7 500 F', 'bijoux.jpg'],
        ['Chemise lin', '9 000 F', 'chemise.jpg'],
    ],
];
$mockIcon = dirname(__DIR__, 2) . '/partials/ui-icon.php';

?>
<div class="mk-phone" aria-hidden="true">
    <div class="mk-screen">
        <p class="mk-screen-top"><i>AC</i><?= Html::e($mockShop['name']) ?><span>2</span></p>
        <div class="mk-chips"><span>Tout</span><span>Robes</span><span>Sacs</span></div>
        <div class="mk-products">
            <?php foreach ($mockShop['products'] as [$mockName, $mockPrice, $mockPhoto]): ?>
                <div class="mk-product"><i><img src="<?= Html::asset('assets/images/boutique/' . $mockPhoto) ?>" alt="" width="240" height="240" loading="lazy" decoding="async"></i><b><?= Html::e($mockName) ?></b><em><?= Html::e($mockPrice) ?></em></div>
            <?php endforeach; ?>
        </div>
        <p class="mk-wa"><?php $name = 'chat'; include $mockIcon; ?>Commander sur WhatsApp</p>
    </div>
</div>
