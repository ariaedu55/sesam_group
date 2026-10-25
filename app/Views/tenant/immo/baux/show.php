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

?>
<section class="btq immo">
    <p><a class="muted" href="<?= Html::url('/immo/baux') ?>">← Tous les contrats de bail</a></p>
    <header class="btq-head">
        <div>
            <h1>Contrat de bail <?= Html::e((string) $lease['reference']) ?></h1>
            <p class="muted"><a href="<?= Html::url('/immo/locataires/' . $lease['client_id']) ?>"><?= Html::e($lease['full_name']) ?></a> · <a href="<?= Html::url('/immo/biens/' . $lease['property_id']) ?>"><?= Html::e($lease['property_name']) ?></a> — <?= Html::e($lease['unit_label']) ?></p>
        </div>
        <span class="btq-status btq-status-<?= $active ? 'success' : 'muted' ?> immo-big-status"><?= $active ? 'En cours' : 'Terminé le ' . Html::e(ImmoCatalog::date($lease['ended_on'])) ?></span>
    </header>
    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert"><?= Html::e(implode(' ', $errors)) ?></p><?php endif; ?>

    <div class="btq-kpis">
        <article class="btq-kpi"><p>Loyer + charges</p><strong><?= Html::e(Money::format((int) $lease['rent_minor'] + (int) $lease['charges_minor'])) ?></strong><span class="muted">loyer <?= Html::e(Money::format((int) $lease['rent_minor'])) ?></span></article>
        <article class="btq-kpi<?= (int) $lease['balance'] > 0 ? ' is-alert' : '' ?>"><p>Impayés</p><strong><?= Html::e(Money::format((int) $lease['balance'])) ?></strong></article>
        <article class="btq-kpi"><p>Total encaissé</p><strong><?= Html::e(Money::format((int) $data['paidTotal'])) ?></strong></article>
        <article class="btq-kpi<?= (int) $lease['deposit_minor'] > 0 && $lease['deposit_paid_on'] === null ? ' is-alert' : '' ?>"><p>Caution</p><strong><?= Html::e(Money::format((int) $lease['deposit_minor'])) ?></strong><span class="muted"><?= (int) $lease['deposit_minor'] === 0 ? 'aucune' : ($lease['deposit_paid_on'] !== null ? 'versée le ' . Html::e(ImmoCatalog::date($lease['deposit_paid_on'])) : 'pas encore versée') ?></span></article>
    </div>

    <div class="btq-two">
        <section class="btq-card">
            <h2>Échéancier</h2>
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
            <section class="btq-card">
                <h2>Conditions</h2>
                <dl class="btq-dl">
                    <dt>Entrée</dt><dd><?= Html::e(ImmoCatalog::date($lease['starts_on'])) ?></dd>
                    <dt>Fin prévue</dt><dd><?= $lease['ends_on'] ? Html::e(ImmoCatalog::date($lease['ends_on'])) : 'Sans date de fin' ?></dd>
                    <dt>Échéance</dt><dd>le <?= (int) $lease['due_day'] ?> de chaque mois</dd>
                    <dt>Préavis</dt><dd><?= (int) $lease['notice_months'] ?> mois</dd>
                    <?php if ($lease['end_reason']): ?><dt>Motif de sortie</dt><dd><?= Html::e($lease['end_reason']) ?></dd><?php endif; ?>
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
