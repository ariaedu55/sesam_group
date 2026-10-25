<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\ReservationTeamMember;

$items = is_array($items ?? null) ? $items : [];
$prestations = is_array($prestations ?? null) ? $prestations : [];
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Équipe</h1>
            <p class="muted">Qui travaille, quand, et sur quelles prestations. L'agenda et la réservation en ligne en tiennent compte.</p>
        </div>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/reservation/equipe/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Ajouter une personne</a>
        <?php endif; ?>
    </header>

    <?php if ($items === []): ?>
        <div class="empty btq-card">
            <p>Vous travaillez seul(e) ? Pas besoin d'équipe : les horaires du salon suffisent. Sinon, ajoutez chaque coiffeuse, esthéticienne ou praticien.</p>
            <?php if ($canManage): ?><p><a class="btn btn-primary" href="<?= Html::url('/reservation/equipe/nouveau') ?>">Ajouter une personne</a></p><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="btq-products">
            <?php foreach ($items as $member): ?>
                <?php if (!$member instanceof ReservationTeamMember) { continue; } ?>
                <article class="btq-product<?= $member->isActive() ? '' : ' is-hidden' ?>">
                    <div class="btq-product-body">
                        <span class="tenant-avatar" aria-hidden="true"><?= Html::e(Html::initials($member->name())) ?></span>
                        <h3><?= Html::e($member->name()) ?><?= $member->isActive() ? '' : ' (inactive)' ?></h3>
                        <?php if ($member->roleLabel() !== ''): ?><p class="muted"><?= Html::e($member->roleLabel()) ?></p><?php endif; ?>
                        <p class="muted">
                            <?php if ($member->serviceIds() === []): ?>
                                Toutes les prestations
                            <?php else: ?>
                                <?= Html::e(implode(', ', array_filter(array_map(static fn (int $id): string => (string) ($prestations[$id] ?? ''), $member->serviceIds())))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="btq-product-foot">
                            <a class="btn btn-secondary btn-sm" href="<?= Html::url('/reservation/equipe/' . $member->id() . '/disponibilites') ?>"><?php $name = 'edit'; include $icon; ?>Fiche, horaires, absences</a>
                            <a class="btn btn-ghost btn-sm" href="<?= Html::url('/reservation/agenda?personne=' . $member->id()) ?>"><?php $name = 'calendar'; include $icon; ?>Agenda</a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
