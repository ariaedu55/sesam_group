<?php

declare(strict_types=1);

use Sesam\Core\Messaging\DeliveryStatus;
use Sesam\Core\Messaging\MessageChannel;
use Sesam\Helpers\Html;
use Sesam\Models\MessageDelivery;

$list = is_array($list ?? null) ? $list : ['deliveries' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$deliveries = $list['deliveries'] ?? [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$total = (int) ($list['total'] ?? 0);
$mockMode = (bool) ($mockMode ?? true);

$statusLabel = static function (string $status): string {
    return match ($status) {
        DeliveryStatus::SENT => 'Envoyé',
        DeliveryStatus::FAILED => 'Échec',
        DeliveryStatus::MOCK => 'Mock (non expédié)',
        DeliveryStatus::CANCELLED => 'Annulé',
        default => 'En attente',
    };
};

$channelLabel = static function (string $channel): string {
    return match ($channel) {
        MessageChannel::EMAIL => 'E-mail',
        MessageChannel::WHATSAPP => 'WhatsApp',
        default => 'Interne',
    };
};

?>
<section>
    <h1>Historique des envois</h1>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($mockMode): ?>
        <p class="alert" role="status">Mode <strong>mock</strong> actif (`NOTIFICATIONS_MODE=mock`) : e-mail et WhatsApp ne partent pas réellement.</p>
    <?php endif; ?>
    <?php if ($deliveries === []): ?>
        <div class="empty">
            <p>Aucun envoi enregistré.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Canal</th>
                        <th>Destinataire</th>
                        <th>Modèle</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $delivery): ?>
                        <?php if (!$delivery instanceof MessageDelivery) { continue; } ?>
                        <tr>
                            <td><?= Html::e($delivery->createdAt()) ?></td>
                            <td><?= Html::e($channelLabel($delivery->channel())) ?></td>
                            <td><?= Html::e($delivery->recipient()) ?></td>
                            <td><?= Html::e($delivery->templateSlug()) ?></td>
                            <td>
                                <?= Html::e($statusLabel($delivery->status())) ?>
                                <?php if ($delivery->errorMessage() !== null): ?>
                                    <span class="muted"> — <?= Html::e($delivery->errorMessage()) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted"><?= Html::e((string) $total) ?> envoi(s), page <?= Html::e((string) $page) ?> / <?= Html::e((string) $pages) ?></p>
    <?php endif; ?>
</section>
