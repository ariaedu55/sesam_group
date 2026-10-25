<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\GardeStaff;

$staff = is_array($staff ?? null) ? $staff : [];
$canManage = (bool) ($canManage ?? false);

?>
<section class="garde-page">
    <header class="garde-hero">
        <div>
            <p class="eyebrow">Équipe</p>
            <h1>Personnel de garde</h1>
            <p>Les personnes que vous pouvez placer sur le calendrier.</p>
        </div>
        <?php if ($canManage): ?>
            <div class="garde-hero-actions">
                <a class="btn btn-primary" href="<?= Html::url('/garde/personnel/nouveau') ?>" data-modal="Garde">Ajouter</a>
            </div>
        <?php endif; ?>
    </header>
    <?php if ($staff === []): ?>
        <div class="empty"><p>Aucun personnel. Ajoutez un préparateur ou un pharmacien pour planifier les tours.</p></div>
    <?php else: ?>
        <ul class="garde-people">
            <?php foreach ($staff as $member): ?>
                <?php if (!$member instanceof GardeStaff) { continue; } ?>
                <li class="card">
                    <span class="garde-avatar" aria-hidden="true"><?= Html::e(Html::initials($member->name())) ?></span>
                    <div>
                        <strong><?= Html::e($member->name()) ?></strong>
                        <p class="muted"><?= Html::e($member->roleLabel() !== '' ? $member->roleLabel() : 'Équipe') ?></p>
                    </div>
                    <span class="badge<?= $member->isActive() ? ' badge-accent' : '' ?>"><?= $member->isActive() ? 'Actif' : 'Inactif' ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
