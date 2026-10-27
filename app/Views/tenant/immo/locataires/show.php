<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Boutique\WhatsApp;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$file = is_array($file ?? null) ? $file : [];
$client = is_array($file['client'] ?? null) ? $file['client'] : [];
$profile = is_array($file['profile'] ?? null) ? $file['profile'] : [];
$leases = is_array($file['leases'] ?? null) ? $file['leases'] : [];
$dues = is_array($file['dues'] ?? null) ? $file['dues'] : [];
$reminders = is_array($file['reminders'] ?? null) ? $file['reminders'] : [];
$canManage = (bool) ($canManage ?? false);
if ($client === []) {
    return;
}
$phone = WhatsApp::normalize((string) ($client['phone'] ?? ''));
$badge = ['paid' => ['success', 'Payé'], 'partial' => ['info', 'Partiel'], 'pending' => ['warning', 'À payer'], 'late' => ['danger', 'En retard'], 'cancelled' => ['muted', 'Annulé']];
$facts = array_filter([
    'Pièce d\'identité' => ($profile['id_type'] ?? null) ? (ImmoCatalog::ID_TYPES[$profile['id_type']] ?? $profile['id_type']) . ' ' . ($profile['id_number'] ?? '') : null,
    'Profession' => trim(($profile['profession'] ?? '') . (($profile['employer'] ?? '') !== '' ? ' — ' . $profile['employer'] : '')),
    'Contact d\'urgence' => trim(($profile['emergency_name'] ?? '') . ' ' . ($profile['emergency_phone'] ?? '')),
    'Garant' => trim(($profile['guarantor_name'] ?? '') . ' ' . ($profile['guarantor_phone'] ?? '')),
]);

ob_start();
if (WhatsApp::isValid($phone)): ?>
    <a class="btn btn-secondary" href="<?= Html::e(WhatsApp::link($phone)) ?>" target="_blank" rel="noopener">WhatsApp</a>
<?php endif;
if ($canManage): ?>
    <a class="btn btn-secondary" href="<?= Html::url('/immo/locataires/' . $client['id'] . '/modifier') ?>">Modifier</a>
    <a class="btn btn-primary" href="<?= Html::url('/immo/baux/nouveau?locataire=' . $client['id']) ?>">Nouveau bail</a>
<?php endif;
$heroActions = ob_get_clean() ?: '';

$heroBackHref = '/immo/locataires';
$heroBackLabel = 'Locataires';
$heroTitle = (string) $client['full_name'];
$heroSubtitle = trim((string) ($client['phone'] ?? '') . (($client['email'] ?? '') !== '' && $client['email'] !== null ? ' · ' . $client['email'] : ''));
$heroTone = 'emerald';
$heroInitials = Html::initials((string) $client['full_name']);
$heroStatusText = (int) $file['balance'] > 0 ? 'Impayés' : 'À jour';
$heroStatusTone = (int) $file['balance'] > 0 ? 'danger' : 'success';
$heroBadgesHtml = '';
$heroStats = [
    ['label' => 'Impayés', 'value' => Money::format((int) $file['balance']), 'alert' => (int) $file['balance'] > 0],
    ['label' => 'Total payé', 'value' => Money::format((int) $file['paidTotal'])],
    ['label' => 'Baux', 'value' => (string) count($leases)],
    ['label' => 'Depuis', 'value' => ImmoCatalog::date($leases !== [] ? (string) end($leases)['starts_on'] : (string) $client['created_at'])],
];

?>
<section class="record-detail btq immo">
    <?php require dirname(__DIR__, 3) . '/partials/record-hero.php'; ?>

    <div class="record-body is-two btq-two">
        <div class="btq">
            <section class="btq-card record-panel">
                <?php $panelTitle = 'Loyers'; $panelIcon = 'coin'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <?php if ($dues === []): ?>
                    <p class="muted">Aucun loyer pour l'instant.</p>
                <?php else: ?>
                    <ul class="btq-list">
                        <?php foreach ($dues as $due): ?>
                            <?php [$tone, $label] = $badge[$due['state']] ?? ['muted', $due['state']]; ?>
                            <li><a href="<?= Html::url('/immo/loyers/' . $due['id']) ?>">
                                <span><strong><?= Html::e(ucfirst(ImmoCatalog::monthLabel($due['due_date']))) ?></strong><small><?= Html::e($due['unit_label']) ?> · <?= Html::e(Money::format((int) $due['amount_minor'])) ?><?= $due['remaining'] > 0 && $due['status'] !== 'pending' ? ' · reste ' . Html::e(Money::format($due['remaining'])) : '' ?></small></span>
                                <span class="btq-status btq-status-<?= Html::e($tone) ?>"><?= Html::e($label) ?></span>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
        <div class="btq">
            <section class="btq-card record-panel">
                <?php $panelTitle = 'Fiche locataire'; $panelIcon = 'file'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <?php if ($facts === [] && ($profile['notes'] ?? '') === ''): ?>
                    <p class="muted">Pièce d'identité, profession, contact d'urgence et garant ne sont pas encore renseignés.</p>
                <?php else: ?>
                    <dl class="btq-dl">
                        <?php foreach ($facts as $label => $value): ?><dt><?= Html::e($label) ?></dt><dd><?= Html::e($value) ?></dd><?php endforeach; ?>
                    </dl>
                    <?php if (($profile['notes'] ?? '') !== '' && $profile['notes'] !== null): ?><p class="immo-note"><?= nl2br(Html::e($profile['notes'])) ?></p><?php endif; ?>
                <?php endif; ?>
            </section>
            <section class="btq-card record-panel">
                <?php $panelTitle = 'Contrats de bail'; $panelIcon = 'book'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                <ul class="btq-list">
                    <?php foreach ($leases as $lease): ?>
                        <li><a href="<?= Html::url('/immo/baux/' . $lease['id']) ?>">
                            <span><strong><?= Html::e($lease['property_name'] . ' — ' . $lease['unit_label']) ?></strong><small>depuis le <?= Html::e(ImmoCatalog::date($lease['starts_on'])) ?> · <?= Html::e(Money::format((int) $lease['rent_minor'] + (int) $lease['charges_minor'])) ?> / mois</small></span>
                            <span class="btq-status btq-status-<?= $lease['status'] === 'active' ? 'success' : 'muted' ?>"><?= $lease['status'] === 'active' ? 'En cours' : 'Terminé' ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($leases === []): ?><p class="muted">Aucun contrat de bail.</p><?php endif; ?>
            </section>
            <?php if ($reminders !== []): ?>
                <section class="btq-card record-panel">
                    <?php $panelTitle = 'Dernières relances'; $panelIcon = 'bell'; $panelActions = ''; require dirname(__DIR__, 3) . '/partials/record-panel-head.php'; ?>
                    <ul class="pay-timeline">
                        <?php foreach ($reminders as $reminder): ?>
                            <li class="is-<?= $reminder['status'] === 'sent' ? 'success' : 'pending' ?>"><strong><?= Html::e(ImmoCatalog::REMINDER_KINDS[$reminder['kind']] ?? $reminder['kind']) ?></strong><span class="muted"><?= Html::e(ImmoCatalog::date($reminder['created_at'])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>
</section>
