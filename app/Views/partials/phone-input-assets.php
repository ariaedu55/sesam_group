<?php

declare(strict_types=1);

/*
 * Sélecteur d'indicatif pays pour tous les champs téléphone (input type="tel") : chargé par chaque mise en page.
 */

use Sesam\Helpers\Html;

?>
    <link rel="stylesheet" href="<?= Html::asset('assets/lib/intl-tel-input-24.8.2/css/intlTelInput.min.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/phone-input.css') ?>">
    <script src="<?= Html::asset('assets/lib/intl-tel-input-24.8.2/js/intlTelInputWithUtils.min.js') ?>" defer></script>
    <script src="<?= Html::asset('assets/lib/intl-tel-input-24.8.2/js/i18n-fr.js') ?>" defer></script>
    <script src="<?= Html::asset('assets/js/phone-input.js') ?>" defer></script>
