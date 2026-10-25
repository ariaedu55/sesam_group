<?php

declare(strict_types=1);

use Sesam\Core\Loyer\LeaseStatus;
use Sesam\Helpers\Html;
use Sesam\Models\LoyerLease;

$lease = ($lease ?? null) instanceof LoyerLease ? $lease : null;
if ($lease === null) {
    return;
}

?>
<section class="crm-detail">
    <h1><?= Html::e($lease->clientName()) ?></h1>
    <p class="muted"><?= Html::e($lease->propertyName()) ?> — <?= Html::e($lease->unitLabel()) ?></p>
    <article class="card">
        <dl class="crm-dl">
            <dt>Loyer</dt>
            <dd><?= Html::e(number_format($lease->rentMinor(), 0, ',', ' ')) ?> FCFA / mois</dd>
            <dt>Jour d'échéance</dt>
            <dd><?= Html::e((string) $lease->dueDay()) ?></dd>
            <dt>Début</dt>
            <dd><?= Html::e($lease->startsOn()) ?></dd>
            <dt>Statut</dt>
            <dd><?= Html::e(LeaseStatus::label($lease->status())) ?></dd>
        </dl>
    </article>
    <p><a href="<?= Html::url('/loyer/echeances') ?>">Voir les échéances</a> · <a href="<?= Html::url('/loyer/contrats') ?>">← Contrats</a></p>
</section>
