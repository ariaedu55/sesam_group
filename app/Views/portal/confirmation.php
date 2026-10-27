<?php

declare(strict_types=1);

use Sesam\Core\Messaging\DeliveryStatus;
use Sesam\Helpers\Html;
use Sesam\Models\Tenant;

$tenant = ($tenant ?? null) instanceof Tenant ? $tenant : null;
$title = is_string($title ?? null) ? $title : 'Demande enregistrée';
$summary = is_string($summary ?? null) ? $summary : '';
$status = is_string($whatsapp_status ?? null) ? $whatsapp_status : DeliveryStatus::FAILED;
$mock = (bool) ($whatsapp_mock ?? true);

?>
<section class="portal-confirm">
    <p class="eyebrow">Confirmation</p>
    <h1><?= Html::e($title) ?></h1>
    <?php if ($summary !== ''): ?>
        <p class="portal-lead"><?= Html::e($summary) ?></p>
    <?php endif; ?>
    <article class="portal-confirm-card">
        <?php if ($status === DeliveryStatus::MOCK || $mock): ?>
            <p>Votre demande est bien enregistrée auprès de <?= Html::e($tenant?->displayName() ?? 'ce commerce') ?>. Vous recevrez une confirmation par message.</p>
        <?php elseif ($status === DeliveryStatus::SENT): ?>
            <p>Une confirmation vient de vous être envoyée sur WhatsApp.</p>
        <?php else: ?>
            <p>WhatsApp n’a pas pu partir. Votre demande est tout de même enregistrée auprès de <?= Html::e($tenant?->displayName() ?? 'ce commerce') ?>.</p>
        <?php endif; ?>
    </article>
    <p><a class="btn btn-primary" href="<?= Html::url('/t/' . ($tenant?->slug() ?? '')) ?>">Retour</a></p>
</section>
