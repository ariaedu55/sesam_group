<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$lease = is_array($data['lease'] ?? null) ? $data['lease'] : [];
$dues = is_array($data['dues'] ?? null) ? $data['dues'] : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
if ($lease === []) {
    return;
}
$active = $lease['status'] === 'active';
$badge = ['paid' => ['success', 'Payé'], 'partial' => ['info', 'Partiel'], 'pending' => ['warning', 'À payer'], 'late' => ['danger', 'En retard'], 'cancelled' => ['muted', 'Annulé']];

$heroBackHref = '/immo/baux';
$heroBackLabel = 'Contrats de bail';
$heroTitle = 'Bail ' . (string) $lease['reference'];
$heroSubtitle = $lease['full_name'] . ' · ' . $lease['property_name'] . ' — ' . $lease['unit_label'];
$heroTone = 'emerald';
$heroInitials = Html::initials((string) $lease['full_name']);
$heroStatusText = $active ? 'En cours' : 'Terminé';
$heroStatusTone = $active ? 'success' : 'muted';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Loyer + charges', 'value' => Money::format((int) $lease['rent_minor'] + (int) $lease['charges_minor']), 'hint' => 'Loyer ' . Money::format((int) $lease['rent_minor'])],
    ['label' => 'Impayés', 'value' => Money::format((int) $lease['balance']), 'alert' => (int) $lease['balance'] > 0],
    ['label' => 'Encaissé', 'value' => Money::format((int) $data['paidTotal'])],
    ['label' => 'Caution', 'value' => (int) $lease['deposit_minor'] === 0 ? '—' : Money::format((int) $lease['deposit_minor'])],
];
$heroActions = '';

?>
<section class="record-detail btq immo">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>
    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert"><?= Html::e(implode(' ', $errors)) ?></p><?php endif; ?>

    <div class="record-body is-two btq-two">
        <section class="btq-card record-panel">
            <?php $panelTitle = 'Échéancier'; $panelIcon = 'calendar'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
            <?php if ($dues === []): ?>
                <p class="muted">Aucune échéance.</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($dues as $due): ?>
                        <?php [$tone, $label] = $badge[$due['state']] ?? ['muted', $due['state']]; ?>
                        <li><a href="<?= Html::url('/immo/loyers/' . $due['id']) ?>">
                            <span><strong><?= Html::e(ucfirst(ImmoCatalog::monthLabel($due['due_date']))) ?></strong><small>le <?= Html::e(ImmoCatalog::date($due['due_date'])) ?> · <?= Html::e(Money::format((int) $due['amount_minor'])) ?><?= $due['status'] === 'partial' ? ' · reste ' . Html::e(Money::format($due['remaining'])) : '' ?></small></span>
                            <span class="btq-status btq-status-<?= Html::e($tone) ?>"><?= Html::e($label) ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="btq">
            <section class="btq-card record-panel">
                <?php $panelTitle = 'Conditions'; $panelIcon = 'file'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <dl class="record-facts is-grid">
                    <div><dt>Entrée</dt><dd><?= Html::e(ImmoCatalog::date($lease['starts_on'])) ?></dd></div>
                    <div><dt>Fin prévue</dt><dd><?= $lease['ends_on'] ? Html::e(ImmoCatalog::date($lease['ends_on'])) : 'Sans date de fin' ?></dd></div>
                    <div><dt>Échéance</dt><dd>le <?= (int) $lease['due_day'] ?> de chaque mois</dd></div>
                    <div><dt>Préavis</dt><dd><?= (int) $lease['notice_months'] ?> mois</dd></div>
                    <?php if ($lease['end_reason']): ?><div><dt>Motif de sortie</dt><dd><?= Html::e($lease['end_reason']) ?></dd></div><?php endif; ?>
                </dl>
                <?php if ($lease['notes']): ?><p class="immo-note"><?= nl2br(Html::e($lease['notes'])) ?></p><?php endif; ?>
            </section>

            <?php if ($canManage && $active): ?>
                <?php if ((int) $lease['deposit_minor'] > 0 && $lease['deposit_paid_on'] === null): ?>
                    <section class="btq-card">
                        <h2>Caution reçue ?</h2>
                        <form method="post" action="<?= Html::url('/immo/baux/' . $lease['id'] . '/caution') ?>" class="immo-inline">
                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                            <div class="field"><label for="deposit_paid_on"><span>Date de versement</span></label><input class="input" id="deposit_paid_on" name="deposit_paid_on" type="date" max="<?= gmdate('Y-m-d') ?>" value="<?= gmdate('Y-m-d') ?>"></div>
                            <button class="btn btn-primary" type="submit">Caution versée</button>
                        </form>
                    </section>
                <?php endif; ?>
                <section class="btq-card">
                    <details>
                        <summary class="immo-danger-summary">Terminer le contrat de bail (départ du locataire)</summary>
                        <form method="post" action="<?= Html::url('/immo/baux/' . $lease['id'] . '/terminer') ?>">
                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                            <div class="btq-grid-2">
                                <div class="field"><label for="ended_on"><span>Date de sortie</span></label><input class="input" id="ended_on" name="ended_on" type="date" value="<?= gmdate('Y-m-d') ?>"></div>
                                <div class="field"><label for="end_reason"><span>Motif</span></label><input class="input" id="end_reason" name="end_reason" maxlength="255" placeholder="Fin de bail, déménagement…"></div>
                            </div>
                            <p class="btq-help">Les loyers après la sortie sont annulés ; ceux déjà dus restent à encaisser. Le logement redevient libre.</p>
                            <button class="btn btn-secondary" type="submit">Terminer le contrat</button>
                        </form>
                    </details>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>
