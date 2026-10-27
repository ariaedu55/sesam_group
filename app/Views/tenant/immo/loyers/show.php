<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$detail = is_array($detail ?? null) ? $detail : [];
$due = is_array($detail['due'] ?? null) ? $detail['due'] : [];
$settings = is_array($detail['settings'] ?? null) ? $detail['settings'] : [];
$payments = is_array($detail['payments'] ?? null) ? $detail['payments'] : [];
$reminders = is_array($detail['reminders'] ?? null) ? $detail['reminders'] : [];
$methods = is_array($detail['methods'] ?? null) ? $detail['methods'] : [];
$online = is_array($detail['online'] ?? null) ? $detail['online'] : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canCollect = (bool) ($canCollect ?? false);
$canOnline = (bool) ($canOnline ?? false);
if ($due === []) {
    return;
}
$remaining = (int) $due['remaining'];
$val = static fn (string $key, string $default = ''): string => Html::e(is_scalar($old[$key] ?? null) ? (string) $old[$key] : $default);
$err = static function (string $key) use ($errors): void {
    if (isset($errors[$key])) {
        echo '<p class="field-error">' . Html::e($errors[$key]) . '</p>';
    }
};
$badge = ['paid' => ['success', 'Payé'], 'partial' => ['info', 'Payé en partie'], 'pending' => ['warning', 'À payer'], 'late' => ['danger', 'En retard'], 'cancelled' => ['muted', 'Annulé']];
[$tone, $label] = $due['state'] === 'late' && $due['status'] === 'partial' ? ['danger', 'Partiel · en retard'] : ($badge[$due['state']] ?? ['muted', $due['state']]);
$percent = (int) $due['amount_minor'] > 0 ? (int) round((int) $due['paid_minor'] * 100 / (int) $due['amount_minor']) : 0;
$onlineLabels = ['pending' => 'en attente de paiement', 'success' => 'payé', 'cancelled' => 'annulé', 'failed' => 'échoué'];

$heroBackHref = '/immo/loyers';
$heroBackLabel = 'Loyers';
$heroTitle = 'Loyer · ' . ucfirst(ImmoCatalog::monthLabel($due['due_date']));
$heroSubtitle = $due['full_name'] . ' · ' . $due['property_name'] . ' — ' . $due['unit_label'];
$heroTone = 'emerald';
$heroInitials = Html::initials((string) $due['full_name']);
$heroStatusText = $label . ($due['days_late'] > 0 ? ' · ' . (int) $due['days_late'] . ' j' : '');
$heroStatusTone = $tone;
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Montant', 'value' => Money::format((int) $due['amount_minor'])],
    ['label' => 'Payé', 'value' => Money::format((int) $due['paid_minor'])],
    ['label' => 'Reste', 'value' => Money::format($remaining), 'alert' => $remaining > 0],
    ['label' => 'Échéance', 'value' => ImmoCatalog::date($due['due_date'])],
];
$heroActions = '';

?>
<section class="record-detail btq immo">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert"><?= Html::e(implode(' ', $errors)) ?></p><?php endif; ?>

    <article class="btq-card record-panel immo-summary">
        <?php $panelTitle = 'Synthèse du mois'; $panelIcon = 'coin'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
        <dl>
            <div><dt>Montant du mois</dt><dd><?= Html::e(Money::format((int) $due['amount_minor'])) ?></dd></div>
            <div><dt>Déjà payé</dt><dd><?= Html::e(Money::format((int) $due['paid_minor'])) ?></dd></div>
            <div class="<?= $remaining > 0 ? 'is-alert' : 'is-ok' ?>"><dt>Reste à payer</dt><dd><?= Html::e(Money::format($remaining)) ?></dd></div>
            <div><dt>Échéance</dt><dd><?= Html::e(ImmoCatalog::date($due['due_date'])) ?></dd></div>
        </dl>
        <span class="immo-meter is-large" aria-hidden="true"><i style="width: <?= min(100, $percent) ?>%"></i></span>
        <p class="muted">Loyer <?= Html::e(Money::format((int) $due['rent_part_minor'])) ?><?= (int) $due['charges_part_minor'] > 0 ? ' + charges ' . Html::e(Money::format((int) $due['charges_part_minor'])) : '' ?>.</p>
    </article>

    <div class="btq-two">
        <div class="btq">
            <?php if ($canCollect && $remaining > 0): ?>
                <section class="btq-card" id="encaisser">
                    <h2>Encaisser</h2>
                    <form class="console-pay" method="post" action="<?= Html::url('/immo/loyers/' . $due['id'] . '/paiement') ?>">
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <div class="btq-grid-2">
                            <div class="field">
                                <label for="amount"><span>Montant reçu (FCFA)</span></label>
                                <input class="input<?= isset($errors['amount']) ? ' is-invalid' : '' ?>" id="amount" name="amount" inputmode="numeric" required value="<?= $val('amount', (string) $remaining) ?>">
                                <?php $err('amount'); ?>
                                <p class="btq-help"><?= (int) $settings['allow_partial'] === 1 ? 'Paiement partiel accepté (minimum ' . Html::e(Money::format((int) $settings['min_partial_minor'])) . ') : le reste restera à encaisser.' : 'Paiements partiels désactivés dans les réglages.' ?></p>
                            </div>
                            <div class="field">
                                <label for="method"><span>Moyen de paiement</span></label>
                                <select class="input" id="method" name="method">
                                    <?php foreach ($methods as $key => $name): ?>
                                        <option value="<?= Html::e($key) ?>"<?= $val('method', 'especes') === $key ? ' selected' : '' ?>><?= Html::e($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php $err('method'); ?>
                            </div>
                            <div class="field">
                                <label for="paid_on"><span>Date du paiement</span></label>
                                <input class="input" id="paid_on" name="paid_on" type="date" max="<?= gmdate('Y-m-d') ?>" value="<?= $val('paid_on', gmdate('Y-m-d')) ?>">
                                <?php $err('paid_on'); ?>
                            </div>
                            <div class="field">
                                <label for="reference"><span>Référence (facultatif)</span></label>
                                <input class="input" id="reference" name="reference" maxlength="80" placeholder="N° de transaction, de chèque…" value="<?= $val('reference') ?>">
                            </div>
                        </div>
                        <div class="field">
                            <label for="note"><span>Note (facultatif)</span></label>
                            <input class="input" id="note" name="note" maxlength="255" value="<?= $val('note') ?>">
                        </div>
                        <div><button class="btn btn-primary" type="submit">Enregistrer le paiement</button></div>
                    </form>
                </section>

                <?php if ($canOnline): ?>
                    <section class="btq-card">
                        <h2>Faire payer en ligne</h2>
                        <?php if ($online !== null && $online['status'] === 'pending'): ?>
                            <p>Un lien de <?= Html::e(Money::format((int) $online['amount_minor'])) ?> est en attente.</p>
                            <p><a class="btn btn-secondary" href="<?= Html::url('/paiements/' . $online['id']) ?>">Voir le lien / simuler le paiement</a></p>
                        <?php else: ?>
                            <?php if ($online !== null): ?><p class="muted">Dernier lien : <?= Html::e($onlineLabels[$online['status']] ?? $online['status']) ?>.</p><?php endif; ?>
                            <form method="post" action="<?= Html::url('/immo/loyers/' . $due['id'] . '/lien') ?>" class="immo-inline">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <div class="field">
                                    <label for="link_amount"><span>Montant du lien (FCFA)</span></label>
                                    <input class="input" id="link_amount" name="link_amount" inputmode="numeric" value="<?= Html::e((string) $remaining) ?>">
                                </div>
                                <button class="btn btn-secondary" type="submit">Créer un lien Wave / Orange Money</button>
                            </form>
                            <p class="btq-help">Le locataire paie depuis son téléphone ; le paiement s'ajoute tout seul à ce loyer.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>

            <section class="btq-card">
                <h2>Paiements reçus</h2>
                <?php if ($payments === []): ?>
                    <p class="muted">Aucun paiement pour ce loyer.</p>
                <?php else: ?>
                    <ul class="btq-list">
                        <?php foreach ($payments as $payment): ?>
                            <li><div>
                                <span><strong><?= Html::e(Money::format((int) $payment['amount_minor'])) ?></strong><small><?= Html::e(ImmoCatalog::date($payment['paid_on'])) ?> · <?= Html::e(ImmoCatalog::METHODS[$payment['method']] ?? $payment['method']) ?><?= $payment['reference'] ? ' · ' . Html::e($payment['reference']) : '' ?></small></span>
                                <a class="btn btn-secondary btn-sm" href="<?= Html::url('/immo/quittances/' . $payment['id']) ?>" target="_blank" rel="noopener"><?= $due['status'] === 'paid' ? 'Quittance' : 'Reçu' ?></a>
                            </div></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <div class="btq">
            <section class="btq-card" id="relances">
                <h2>Relancer le locataire</h2>
                <?php if ($remaining <= 0): ?>
                    <p class="muted">Loyer payé : aucune relance nécessaire.</p>
                <?php elseif ($canCollect): ?>
                    <form method="post" action="<?= Html::url('/immo/loyers/' . $due['id'] . '/relance') ?>">
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <div class="field">
                            <label for="message"><span>Message WhatsApp</span></label>
                            <textarea class="input" id="message" name="message" rows="5" maxlength="800"><?= Html::e((string) ($detail['message'] ?? '')) ?></textarea>
                            <?php $err('message'); ?>
                        </div>
                        <div class="btq-actions">
                            <button class="btn btn-primary" type="submit">Envoyer la relance</button>
                            <?php if (($detail['whatsapp'] ?? '') !== ''): ?><a class="btn btn-secondary" href="<?= Html::e($detail['whatsapp']) ?>" target="_blank" rel="noopener">Ouvrir WhatsApp</a><?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>
                <?php if ($reminders !== []): ?>
                    <h3 class="console-subhead">Relances envoyées</h3>
                    <ul class="pay-timeline">
                        <?php foreach ($reminders as $reminder): ?>
                            <li class="is-<?= $reminder['status'] === 'sent' ? 'success' : 'pending' ?>">
                                <strong><?= Html::e(ImmoCatalog::REMINDER_KINDS[$reminder['kind']] ?? $reminder['kind']) ?></strong>
                                <span class="muted"><?= Html::e(ImmoCatalog::date($reminder['created_at'])) ?> · <?= $reminder['status'] === 'sent' ? 'envoyée' : 'non envoyée (pas de numéro)' ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">Aucune relance pour l'instant. Les relances automatiques suivent vos <a href="<?= Html::url('/immo/parametres') ?>">réglages</a>.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
