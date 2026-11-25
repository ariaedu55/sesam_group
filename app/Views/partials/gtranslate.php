<?php

declare(strict_types=1);

/*
 * Traduction de la plateforme avec GTranslate (gratuit) : français par défaut, anglais au choix.
 * Le widget GTranslate reste caché ; nos boutons FR / EN (partials/language-switch.php) le pilotent.
 * Le choix est gardé par le cookie « googtrans » : il suit le visiteur sur toutes les pages.
 */

use Sesam\Helpers\Html;

?>
<div class="gtranslate_wrapper" aria-hidden="true"></div>
<script src="<?= Html::asset('assets/js/language-glossary.js') ?>"></script>
<script src="<?= Html::asset('assets/js/language.js') ?>"></script>
<script src="https://cdn.gtranslate.net/widgets/latest/dropdown.js" defer></script>
