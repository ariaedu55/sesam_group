<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$canManage = (bool) ($canManage ?? false);

?>
    <?php if ($rows === []): ?>
        <div class="btq-card empty"><p class="muted">Aucun locataire ici. <?= $canManage ? 'Ajoutez-en un, ou créez directement un contrat de bail : le locataire est enregistré en même temps.' : '' ?></p></div>
    <?php else: ?>
        <ul class="btq-list record-list-rich immo-people">
            <?php foreach ($rows as $row): ?>
                <li><a href="<?= Html::url('/immo/locataires/' . $row['id']) ?>">
                    <span class="record-list-leading">
                    <span class="record-list-avatar immo-avatar" data-tone="tenant" aria-hidden="true"><?= Html::e(Html::initials((string) $row['full_name'])) ?></span>
                    <span class="immo-person">
                        <strong><?= Html::e($row['full_name']) ?></strong>
                        <small><?= $row['unit_label'] !== null ? Html::e($row['property_name'] . ' — ' . $row['unit_label']) : 'Plus de contrat de bail en cours' ?></small>
                        <small><?= Html::e((string) ($row['phone'] ?? '')) ?></small>
                    </span>
                    </span>
                    <span class="record-list-meta btq-list-end">
                        <?php if ((int) $row['balance'] > 0): ?>
                            <span class="btq-status btq-status-danger">Doit <?= Html::e(Money::format((int) $row['balance'])) ?></span>
                        <?php elseif ($row['unit_label'] !== null): ?>
                            <span class="btq-status btq-status-success">À jour</span>
                        <?php endif; ?>
                        <?php if ($row['monthly'] !== null): ?><small><?= Html::e(Money::format((int) $row['monthly'])) ?> / mois</small><?php endif; ?>
                    </span>
                </a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
