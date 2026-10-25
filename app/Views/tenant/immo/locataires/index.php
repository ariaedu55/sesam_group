<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$view = (string) ($data['view'] ?? 'actifs');
$canManage = (bool) ($canManage ?? false);
$icon = dirname(__DIR__, 3) . '/partials/ui-icon.php';
$tabs = ['actifs' => 'En place', 'retards' => 'Avec impayés', 'anciens' => 'Anciens locataires', 'tous' => 'Tous'];

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Locataires</h1>
            <p class="muted">Coordonnées, logement occupé, loyer et impayés de chaque locataire.</p>
        </div>
        <?php if ($canManage): ?><a class="btn btn-primary" href="<?= Html::url('/immo/locataires/nouveau') ?>"><?php $name = 'plus'; include $icon; ?>Nouveau locataire</a><?php endif; ?>
    </header>

    <nav class="btq-tabs" aria-label="Filtrer les locataires">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= Html::url('/immo/locataires' . ($key === 'actifs' ? '' : '?vue=' . $key)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="<?= Html::url('/immo/locataires') ?>" class="immo-search" role="search">
        <?php if ($view !== 'actifs'): ?><input type="hidden" name="vue" value="<?= Html::e($view) ?>"><?php endif; ?>
        <label class="sr-only" for="locataire-q">Rechercher</label>
        <input class="input" id="locataire-q" type="search" name="q" value="<?= Html::e((string) ($data['q'] ?? '')) ?>" placeholder="Nom, téléphone ou e-mail">
    </form>

    <?php if ($rows === []): ?>
        <div class="btq-card empty"><p class="muted">Aucun locataire ici. <?= $canManage ? 'Ajoutez-en un, ou créez directement un contrat de bail : le locataire est enregistré en même temps.' : '' ?></p></div>
    <?php else: ?>
        <ul class="btq-list immo-people">
            <?php foreach ($rows as $row): ?>
                <li><a href="<?= Html::url('/immo/locataires/' . $row['id']) ?>">
                    <span class="immo-avatar" aria-hidden="true"><?= Html::e(Html::initials((string) $row['full_name'])) ?></span>
                    <span class="immo-person">
                        <strong><?= Html::e($row['full_name']) ?></strong>
                        <small><?= $row['unit_label'] !== null ? Html::e($row['property_name'] . ' — ' . $row['unit_label']) : 'Plus de contrat de bail en cours' ?></small>
                        <small><?= Html::e((string) ($row['phone'] ?? '')) ?></small>
                    </span>
                    <span class="btq-list-end">
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
</section>
