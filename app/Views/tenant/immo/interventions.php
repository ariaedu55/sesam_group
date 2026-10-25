<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$units = is_array($data['units'] ?? null) ? $data['units'] : [];
$properties = is_array($data['properties'] ?? null) ? $data['properties'] : [];
$view = (string) ($data['view'] ?? 'en-cours');
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$val = static fn (string $key): string => Html::e(is_scalar($old[$key] ?? null) ? (string) $old[$key] : '');
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$tone = ['urgente' => 'danger', 'normale' => 'warning', 'basse' => 'muted'];

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Interventions</h1>
            <p class="muted">Pannes, réparations et travaux : qui s'en occupe, où en est-on, combien ça coûte. Dépensé en <?= gmdate('Y') ?> : <strong><?= Html::e(Money::format((int) ($data['costYear'] ?? 0))) ?></strong>.</p>
        </div>
    </header>

    <nav class="btq-tabs" aria-label="Filtrer les interventions">
        <a href="<?= Html::url('/immo/interventions') ?>"<?= $view === 'en-cours' ? ' aria-current="page"' : '' ?>>À faire et en cours</a>
        <a href="<?= Html::url('/immo/interventions?vue=terminees') ?>"<?= $view === 'terminees' ? ' aria-current="page"' : '' ?>>Terminées</a>
    </nav>

    <div class="btq-two">
        <section class="btq-card">
            <?php if ($rows === []): ?>
                <p class="muted"><?= $view === 'terminees' ? 'Aucune intervention terminée.' : 'Aucune intervention en cours.' ?></p>
            <?php else: ?>
                <ul class="immo-tasks">
                    <?php foreach ($rows as $item): ?>
                        <li>
                            <div class="immo-task-head">
                                <strong><?= Html::e($item['title']) ?></strong>
                                <span class="btq-status btq-status-<?= $item['status'] === 'terminee' ? 'success' : Html::e($tone[$item['priority']] ?? 'muted') ?>"><?= Html::e($item['status'] === 'terminee' ? 'Terminée' : ImmoCatalog::PRIORITIES[$item['priority']] ?? $item['priority']) ?></span>
                            </div>
                            <small class="muted"><?= Html::e($item['property_name']) ?><?= $item['unit_label'] ? ' — ' . Html::e($item['unit_label']) : ' — parties communes' ?> · signalée le <?= Html::e(ImmoCatalog::date($item['reported_on'])) ?><?= $item['provider'] ? ' · ' . Html::e($item['provider']) : '' ?><?= (int) $item['cost_minor'] > 0 ? ' · ' . Html::e(Money::format((int) $item['cost_minor'])) : '' ?></small>
                            <?php if ($item['description']): ?><p><?= Html::e($item['description']) ?></p><?php endif; ?>
                            <?php if ($canManage && $item['status'] !== 'terminee'): ?>
                                <form method="post" action="<?= Html::url('/immo/interventions/' . $item['id']) ?>" class="immo-task-form">
                                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                    <label class="sr-only" for="status-<?= (int) $item['id'] ?>">Statut</label>
                                    <select class="input" id="status-<?= (int) $item['id'] ?>" name="status">
                                        <?php foreach (ImmoCatalog::INTERVENTION_STATUSES as $key => $label): ?><option value="<?= Html::e($key) ?>"<?= $item['status'] === $key ? ' selected' : '' ?>><?= Html::e($label) ?></option><?php endforeach; ?>
                                    </select>
                                    <label class="sr-only" for="cost-<?= (int) $item['id'] ?>">Coût</label>
                                    <input class="input" id="cost-<?= (int) $item['id'] ?>" name="cost_minor" inputmode="numeric" placeholder="Coût (FCFA)">
                                    <button class="btn btn-secondary btn-sm" type="submit">Mettre à jour</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($canManage): ?>
            <section class="btq-card">
                <h2>Signaler une intervention</h2>
                <?php if ($properties === []): ?>
                    <p class="muted">Ajoutez d'abord un bien.</p>
                <?php else: ?>
                    <form class="btq-form" method="post" action="<?= Html::url('/immo/interventions') ?>">
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <div class="field">
                            <label for="unit_id"><span>Où ?</span></label>
                            <select class="input<?= isset($errors['property_id']) ? ' is-invalid' : '' ?>" id="unit_id" name="unit_id">
                                <?php foreach ($properties as $property): ?>
                                    <optgroup label="<?= Html::e($property['name']) ?>">
                                        <?php foreach ($units as $unit): ?>
                                            <?php if ((int) $unit['property_id'] === (int) $property['id']): ?><option value="<?= (int) $unit['id'] ?>"<?= ($old['unit_id'] ?? '') === (string) $unit['id'] ? ' selected' : '' ?>><?= Html::e($unit['label']) ?></option><?php endif; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php $err('property_id'); ?>
                        </div>
                        <div class="field"><label for="title"><span>Problème</span></label><input class="input<?= isset($errors['title']) ? ' is-invalid' : '' ?>" id="title" name="title" maxlength="160" placeholder="Fuite d'eau salle de bain" value="<?= $val('title') ?>"><?php $err('title'); ?></div>
                        <div class="field"><label for="description"><span>Détails</span></label><textarea class="input" id="description" name="description" rows="3" maxlength="1000"><?= $val('description') ?></textarea></div>
                        <div class="btq-grid-2">
                            <div class="field"><label for="priority"><span>Priorité</span></label><select class="input" id="priority" name="priority"><?php foreach (ImmoCatalog::PRIORITIES as $key => $label): ?><option value="<?= Html::e($key) ?>"<?= ($old['priority'] ?? 'normale') === $key ? ' selected' : '' ?>><?= Html::e($label) ?></option><?php endforeach; ?></select></div>
                            <div class="field"><label for="provider"><span>Artisan / prestataire</span></label><input class="input" id="provider" name="provider" maxlength="120" value="<?= $val('provider') ?>"></div>
                            <div class="field"><label for="cost_minor"><span>Coût estimé (FCFA)</span></label><input class="input" id="cost_minor" name="cost_minor" inputmode="numeric" value="<?= $val('cost_minor') ?>"></div>
                        </div>
                        <div><button class="btn btn-primary" type="submit">Ajouter</button></div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>
