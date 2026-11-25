<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

?>
<div class="mk-panel" aria-hidden="true">
    <p class="mk-panel-head">Réserver chez Horizon <?= Html::en('Agenda', 'Schedule') ?></p>
    <div class="mk-days"><?= Html::en('Lun', 'Mon') ?><?= Html::en('Mar', 'Tue') ?><span class="is-on notranslate" translate="no" data-en="Wed">Mer</span><?= Html::en('Jeu', 'Thu') ?><?= Html::en('Ven', 'Fri') ?><?= Html::en('Sam', 'Sat') ?></div>
    <div class="mk-slots">
        <span>09:00 <small>libre</small></span>
        <span class="is-taken">10:30 <small>pris</small></span>
        <span class="is-on">14:00 <small>choisi</small></span>
        <span>16:30 <small>libre</small></span>
    </div>
    <div class="mk-pay"><span>Wave</span><span>Orange Money</span></div>
    <p class="mk-bubble">Rendez-vous confirmé mercredi à 14:00. Rappel la veille sur WhatsApp.</p>
</div>
