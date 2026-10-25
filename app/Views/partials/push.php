<?php

declare(strict_types=1);

/*
 * Notifications push : configuration publique Firebase pour push.js et bandeau « Activer les notifications ».
 * Inclus par layouts/tenant et layouts/staff, juste avant </body>.
 */

use Sesam\Helpers\Html;

/** @var array{web: array<string, string>, vapid_key: string} $pushConfig */
$pushConfig = require dirname(__DIR__, 3) . '/config/firebase.php';
$pushScope = rtrim(html_entity_decode(Html::url('/')), '/') . '/';

?>
<div hidden data-push
     data-config="<?= Html::e((string) json_encode($pushConfig['web'], JSON_UNESCAPED_SLASHES)) ?>"
     data-vapid="<?= Html::e($pushConfig['vapid_key']) ?>"
     data-sw="<?= Html::url('/firebase-messaging-sw.js') ?>"
     data-scope="<?= Html::e($pushScope) ?>"
     data-register="<?= Html::url('/notifications/appareils') ?>"
     data-unregister="<?= Html::url('/notifications/appareils/oublier') ?>"
     data-csrf="<?= Html::e($csrf ?? '') ?>"></div>
<aside class="push-prompt" data-push-prompt hidden aria-label="Notifications">
    <span class="push-prompt-icon" aria-hidden="true"><?php $name = 'bell'; include __DIR__ . '/ui-icon.php'; ?></span>
    <span class="push-prompt-text"><strong>Recevoir les alertes sur cet appareil ?</strong><small>Commandes, rendez-vous, loyers, paiements : vous êtes prévenu même l'application fermée.</small></span>
    <span class="push-prompt-actions">
        <button class="btn btn-primary btn-sm" type="button" data-push-enable>Activer</button>
        <button class="btn btn-ghost btn-sm" type="button" data-push-dismiss>Plus tard</button>
    </span>
</aside>
<script src="<?= Html::asset('assets/vendor/firebase-10.12.2/firebase-app-compat.js') ?>" defer></script>
<script src="<?= Html::asset('assets/vendor/firebase-10.12.2/firebase-messaging-compat.js') ?>" defer></script>
<script src="<?= Html::asset('assets/js/push.js') ?>" defer></script>
