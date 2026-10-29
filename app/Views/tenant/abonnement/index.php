<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

$page = is_array($page ?? null) ? $page : [];
$access = is_array($page['access'] ?? null) ? $page['access'] : [];
$payments = is_array($page['payments'] ?? null) ? $page['payments'] : [];
$plans = is_array($page['plans'] ?? null) ? $page['plans'] : [];
$methods = is_array($page['methods'] ?? null) ? $page['methods'] : [];
$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$price = (int) ($page['price'] ?? 0);
$setupFee = (int) ($page['setupFee'] ?? 0);
$live = !empty($page['live']);
$state = (string) ($access['state'] ?? 'none');
$daysLeft = $access['days_left'] ?? null;
$end = $access['period_end'] ?? null;
$start = $page['periodStart'] ?? null;
$date = static fn (?string $value): string => $value === null || $value === '' ? '—' : gmdate('d/m/Y', (int) strtotime(substr($value, 0, 10) . ' UTC'));
$longDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $ts = (int) strtotime(substr($value, 0, 10) . ' UTC');

    return (int) gmdate('j', $ts) . ' ' . $months[(int) gmdate('n', $ts)] . ' ' . gmdate('Y', $ts);
};
$tone = match ($state) {
    'active' => 'success',
    'trial', 'pending' => 'info',
    'overdue', 'trial_over', 'suspended' => 'danger',
    default => 'muted',
};
$headline = match ($state) {
    'trial' => 'Essai gratuit en cours',
    'trial_over' => 'Votre essai gratuit est terminé',
    'pending' => 'Activez votre espace',
    'active' => 'Abonnement actif',
    'overdue' => 'Abonnement échu',
    'suspended' => 'Abonnement suspendu',
    default => 'Aucun abonnement en cours',
};
$lead = match ($state) {
    'trial' => 'Toutes les fonctions sont ouvertes. Abonnez-vous quand vous voulez : rien ne sera perdu.',
    'trial_over' => 'Votre espace est en pause. Abonnez-vous pour retrouver toutes vos données, intactes.',
    'pending' => 'Votre compte est créé. Réglez votre premier mois : votre espace s\'ouvre dès que le paiement est confirmé.',
    'active' => 'Tout fonctionne. Renouvelez à l\'avance : les mois payés s\'ajoutent à la suite de votre échéance.',
    'overdue' => !empty($access['blocked']) ? 'Votre espace est en pause. Renouvelez pour retrouver toutes vos données, intactes.' : 'Votre échéance est passée. Renouvelez maintenant pour ne pas être coupé.',
    'suspended' => 'Contactez SESAM ou renouvelez votre abonnement pour rouvrir votre espace.',
    default => 'Choisissez une formule et payez par Wave ou Orange Money.',
};
$cta = match ($state) {
    'active', 'overdue' => 'Renouveler mon abonnement',
    'pending' => 'Activer mon espace',
    'trial' => 'Choisir mon abonnement',
    default => 'M\'abonner maintenant',
};
// Avancement de la période en cours (barre de progression).
$progress = null;
if ($start !== null && $end !== null && in_array($state, ['active', 'trial'], true)) {
    $total = max(1, (int) ((strtotime($end . ' UTC') - strtotime(substr((string) $start, 0, 10) . ' UTC')) / 86400) + 1);
    $progress = (int) max(0, min(100, round(($total - max(0, (int) $daysLeft)) * 100 / $total)));
}
$daysText = match (true) {
    $daysLeft === null => '',
    $daysLeft > 1 => 'Il reste ' . $daysLeft . ' jours',
    $daysLeft === 1 => 'Il reste 1 jour',
    $daysLeft === 0 => 'Dernier jour aujourd\'hui',
    default => 'Échue depuis ' . abs((int) $daysLeft) . ' jour' . (abs((int) $daysLeft) > 1 ? 's' : ''),
};
$bestMonths = 0;
$bestDiscount = 0;
foreach ($plans as $count => $plan) {
    if ((int) $plan['discount'] > $bestDiscount) {
        $bestDiscount = (int) $plan['discount'];
        $bestMonths = (int) $count;
    }
}
$selected = isset($plans[(int) ($old['months'] ?? 0)]) ? (int) $old['months'] : (isset($plans[1]) ? 1 : (int) array_key_first($plans));
$openPayment = $errors !== [] || in_array($state, ['pending', 'trial_over'], true) || !empty($access['blocked']);
$lastPayment = $payments[0] ?? null;
$methodLabels = ['wave' => 'Wave', 'orange-money' => 'Orange Money', 'especes' => 'Espèces', 'virement' => 'Virement', 'cheque' => 'Chèque'];
$logos = ['wave' => 'assets/images/paiement/wave.png', 'orange-money' => 'assets/images/paiement/orange-money.png'];

?>
<link rel="stylesheet" href="<?= Html::asset('assets/css/abonnement.css') ?>">
<section class="sb">
    <header class="sb-head">
        <h1>Abonnement</h1>
        <p class="muted">Sesam <?= Html::e((string) ($page['sector'] ?? '')) ?> · paiement sécurisé par Wave ou Orange Money</p>
    </header>

    <article class="sb-status is-<?= Html::e($tone) ?>" aria-labelledby="sb-status-title">
        <div class="sb-status-main">
            <span class="sb-badge is-<?= Html::e($tone) ?>"><span class="sb-dot" aria-hidden="true"></span><?= Html::e((string) ($access['label'] ?? '')) ?></span>
            <h2 id="sb-status-title"><?= Html::e($headline) ?></h2>
            <p class="sb-lead"><?= Html::e($lead) ?></p>
            <?php if ($end !== null && in_array($state, ['active', 'trial', 'overdue'], true)): ?>
                <div class="sb-deadline">
                    <span class="sb-deadline-label"><?= $state === 'trial' ? 'Fin de l\'essai' : 'Échéance' ?></span>
                    <strong><?= Html::e($longDate($end)) ?></strong>
                    <span class="sb-deadline-days<?= $daysLeft !== null && $daysLeft <= 5 ? ' is-soon' : '' ?>"><?= Html::e($daysText) ?></span>
                </div>
                <?php if ($progress !== null): ?>
                    <div class="sb-progress" role="progressbar" aria-label="Période écoulée" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>">
                        <span style="width: <?= $progress ?>%"></span>
                    </div>
                    <p class="sb-progress-legend"><span>Du <?= Html::e($date((string) $start)) ?></span><span>au <?= Html::e($date($end)) ?></span></p>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($page['canPay']) && $plans !== []): ?>
                <a class="btn sb-cta" href="#payer" data-sub-open><?= Html::e($cta) ?></a>
            <?php endif; ?>
        </div>
        <dl class="sb-facts">
            <div><dt>Mensualité</dt><dd><?= Html::e(Money::format($price)) ?></dd></div>
            <div><dt>Début de période</dt><dd><?= Html::e($date(is_string($start) ? $start : null)) ?></dd></div>
            <div><dt><?= $state === 'trial' ? 'Fin de l\'essai' : 'Échéance' ?></dt><dd><?= Html::e($date(is_string($end) ? $end : null)) ?></dd></div>
            <div><dt>Dernier paiement</dt><dd><?= $lastPayment !== null ? Html::e(Money::format((int) $lastPayment['amount'])) . '<small>le ' . Html::e($date((string) $lastPayment['paid_on'])) . '</small>' : 'Aucun' ?></dd></div>
        </dl>
    </article>

    <?php if ($state === 'pending' && $setupFee > 0): ?>
        <p class="alert" role="status">Le premier paiement inclut <?= Html::e(Money::format($setupFee)) ?> de frais de mise en place, payés une seule fois.</p>
    <?php endif; ?>

    <?php if (empty($page['canPay'])): ?>
        <p class="sb-note">Seul le responsable du commerce peut régler l'abonnement.</p>
    <?php elseif ($plans !== []): ?>
        <form class="sb-pay" id="payer" method="post" action="<?= Html::url('/abonnement/payer') ?>" data-sub-form<?= $openPayment ? '' : ' data-sub-collapsed' ?>>
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

            <section class="sb-step" aria-labelledby="sb-step-plan">
                <h2 id="sb-step-plan"><span class="sb-step-num" aria-hidden="true">1</span>Choisissez votre formule</h2>
                <?php if (isset($errors['months'])): ?><p class="field-error" role="alert"><?= Html::e($errors['months']) ?></p><?php endif; ?>
                <div class="sb-plans">
                    <?php foreach ($plans as $count => $plan): ?>
                        <?php $total = (int) $plan['amount'] + $setupFee; ?>
                        <label class="sb-plan<?= (int) $count === $bestMonths ? ' is-best' : '' ?>">
                            <input type="radio" name="months" value="<?= (int) $count ?>"<?= (int) $count === $selected ? ' checked' : '' ?>
                                data-amount="<?= $total ?>" data-amount-label="<?= Html::e(Money::format($total)) ?>" data-plan-label="<?= Html::e((string) $plan['label']) ?>" data-end="<?= Html::e($longDate((string) ($plan['new_end'] ?? ''))) ?>">
                            <?php if ((int) $count === $bestMonths): ?><span class="sb-plan-ribbon">Le plus avantageux</span><?php endif; ?>
                            <span class="sb-plan-name"><?= Html::e((string) $plan['label']) ?><?= (int) $plan['discount'] > 0 ? ' · -' . (int) $plan['discount'] . ' %' : '' ?></span>
                            <span class="sb-plan-price"><?= Html::e(Money::format($total)) ?></span>
                            <?php if ((int) $plan['discount'] > 0): ?><s><?= Html::e(Money::format((int) $plan['base'] + $setupFee)) ?></s><?php endif; ?>
                            <span class="sb-plan-meta"><?= (int) $count > 1 ? Html::e(Money::format((int) $plan['per_month'])) . ' / mois' : 'Sans engagement' ?></span>
                            <?php if ((int) $plan['saving'] > 0): ?><span class="sb-plan-save">Vous économisez <?= Html::e(Money::format((int) $plan['saving'])) ?></span><?php endif; ?>
                            <span class="sb-plan-check" aria-hidden="true"></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sb-step" aria-labelledby="sb-step-method">
                <h2 id="sb-step-method"><span class="sb-step-num" aria-hidden="true">2</span>Choisissez votre moyen de paiement</h2>
                <?php if (isset($errors['method'])): ?><p class="field-error" role="alert"><?= Html::e($errors['method']) ?></p><?php endif; ?>
                <div class="sb-methods">
                    <?php foreach ($methods as $key => $label): ?>
                        <label class="sb-method">
                            <input type="radio" name="method" value="<?= Html::e($key) ?>"<?= $key === (string) ($old['method'] ?? 'wave') ? ' checked' : '' ?> data-method-label="<?= Html::e($label) ?>">
                            <img src="<?= Html::asset($logos[$key] ?? 'assets/images/paiement/wave.png') ?>" alt="" width="56" height="56">
                            <span><strong><?= Html::e($label) ?></strong><small><?= $key === 'wave' ? 'Paiement depuis l\'application Wave' : 'Paiement par Orange Money (code ou application)' ?></small></span>
                            <span class="sb-plan-check" aria-hidden="true"></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="sb-step sb-recap" aria-labelledby="sb-step-recap">
                <h2 id="sb-step-recap"><span class="sb-step-num" aria-hidden="true">3</span>Vérifiez et payez</h2>
                <dl>
                    <div><dt>Formule</dt><dd data-recap-plan><?= Html::e((string) ($plans[$selected]['label'] ?? '')) ?></dd></div>
                    <div><dt>Actif jusqu'au</dt><dd data-recap-end><?= Html::e($longDate((string) ($plans[$selected]['new_end'] ?? ''))) ?></dd></div>
                    <?php if ($setupFee > 0): ?><div><dt>Dont mise en place</dt><dd><?= Html::e(Money::format($setupFee)) ?></dd></div><?php endif; ?>
                    <div class="sb-recap-total"><dt>Total à payer</dt><dd data-recap-amount><?= Html::e(Money::format((int) ($plans[$selected]['amount'] ?? 0) + $setupFee)) ?></dd></div>
                </dl>
                <button class="btn btn-primary sb-submit" type="submit"><span data-recap-button>Continuer vers le paiement</span></button>
                <p class="sb-secure"><?php $name = 'check'; include dirname(__DIR__, 2) . '/partials/ui-icon.php'; ?><?= $live ? 'Vous allez être redirigé vers la page sécurisée NabooPay pour valider le paiement sur votre téléphone.' : 'Démonstration : le paiement est simulé, aucun argent ne circule.' ?></p>
            </section>
        </form>
    <?php endif; ?>

    <div class="sb-bottom">
        <section class="sb-card" aria-labelledby="sb-history">
            <h2 id="sb-history">Historique des paiements</h2>
            <?php if ($payments === []): ?>
                <p class="muted">Aucun paiement pour l'instant. Vos reçus apparaîtront ici.</p>
            <?php else: ?>
                <ul class="sb-history">
                    <?php foreach ($payments as $payment): ?>
                        <?php $method = (string) $payment['method']; ?>
                        <li>
                            <?php if (isset($logos[$method])): ?><img src="<?= Html::asset($logos[$method]) ?>" alt="<?= Html::e($methodLabels[$method]) ?>" width="40" height="40"><?php else: ?><span class="sb-history-icon" aria-hidden="true">F</span><?php endif; ?>
                            <span class="sb-history-main">
                                <strong><?= Html::e(Money::format((int) $payment['amount'])) ?></strong>
                                <small><?= Html::e($date((string) $payment['paid_on'])) ?> · <?= (int) $payment['months'] ?> mois · <?= Html::e($methodLabels[$method] ?? $method) ?><?= $payment['channel'] === 'en-ligne' ? ' · en ligne' : '' ?></small>
                            </span>
                            <span class="sb-history-end"><small>Valable jusqu'au</small><strong><?= Html::e($date((string) $payment['period_end'])) ?></strong></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <aside class="sb-card sb-help" aria-labelledby="sb-help">
            <h2 id="sb-help">Bon à savoir</h2>
            <ul>
                <li><strong>Rien n'est perdu.</strong> Vos rendez-vous, clientes et réglages restent intacts, même si l'abonnement s'arrête.</li>
                <li><strong>Renouvelez à l'avance.</strong> Les mois payés s'ajoutent après votre échéance actuelle.</li>
                <li><strong>Formules longues moins chères.</strong> Payez plusieurs mois d'un coup pour profiter des réductions.</li>
                <li><strong>Une question ?</strong> L'équipe SESAM vous répond sur WhatsApp.</li>
            </ul>
        </aside>
    </div>
</section>
<script src="<?= Html::asset('assets/js/abonnement.js') ?>" defer></script>
