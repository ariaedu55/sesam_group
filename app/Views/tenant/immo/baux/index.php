<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$view = (string) ($data['view'] ?? 'actifs');
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$tabs = ['actifs' => 'En cours', 'fin-proche' => 'Fin proche', 'caution' => 'Caution à encaisser', 'termines' => 'Terminés'];
$today = gmdate('Y-m-d');

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Contrats de bail</h1>
            <p class="muted">Un contrat de bail lie un locataire à un logement : loyer, charges, caution, jour d'échéance et durée.</p>
        </div>
        <?php if ($canManage): ?><a class="btn btn-primary" href="<?= Html::url('/immo/baux/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau contrat de bail</a><?php endif; ?>
    </header>

    <nav class="btq-tabs" aria-label="Filtrer les contrats de bail">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= Html::url('/immo/baux' . ($key === 'actifs' ? '' : '?vue=' . $key)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($rows === []): ?>
        <div class="btq-card empty"><p class="muted">Aucun contrat de bail ici.</p></div>
    <?php else: ?>
        <ul class="btq-list">
            <?php foreach ($rows as $lease): ?>
                <li><a href="<?= Html::url('/immo/baux/' . $lease['id']) ?>">
                    <span>
                        <strong><?= Html::e($lease['full_name']) ?></strong>
                        <small><?= Html::e($lease['property_name'] . ' — ' . $lease['unit_label']) ?> · <?= Html::e((string) $lease['reference']) ?></small>
                        <small>Depuis le <?= Html::e(ImmoCatalog::date($lease['starts_on'])) ?><?= $lease['ends_on'] ? ' · fin le ' . Html::e(ImmoCatalog::date($lease['ends_on'])) : ' · sans date de fin' ?> · échéance le <?= (int) $lease['due_day'] ?></small>
                    </span>
                    <span class="btq-list-end">
                        <strong><?= Html::e(Money::format((int) $lease['rent_minor'] + (int) $lease['charges_minor'])) ?></strong>
                        <?php if ($lease['status'] !== 'active'): ?>
                            <span class="btq-status btq-status-muted">Terminé</span>
                        <?php elseif ((int) $lease['balance'] > 0): ?>
                            <span class="btq-status btq-status-danger">Doit <?= Html::e(Money::format((int) $lease['balance'])) ?></span>
                        <?php elseif ((int) $lease['deposit_minor'] > 0 && $lease['deposit_paid_on'] === null): ?>
                            <span class="btq-status btq-status-warning">Caution due</span>
                        <?php elseif ($lease['ends_on'] !== null && $lease['ends_on'] <= gmdate('Y-m-d', (int) strtotime('+60 days'))): ?>
                            <span class="btq-status btq-status-warning">Fin proche</span>
                        <?php else: ?>
                            <span class="btq-status btq-status-success">À jour</span>
                        <?php endif; ?>
                    </span>
                </a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
