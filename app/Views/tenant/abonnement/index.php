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
$price = (int) ($page['price'] ?? 0);
$setupFee = (int) ($page['setupFee'] ?? 0);
$state = (string) ($access['state'] ?? 'none');
$daysLeft = $access['days_left'] ?? null;
$end = $access['period_end'] ?? null;
$date = static fn (?string $value): string => $value === null || $value === '' ? '—' : gmdate('d/m/Y', (int) strtotime(substr($value, 0, 10) . ' UTC'));
$tone = match ($state) {
    'active' => 'success',
    'trial' => 'info',
    'overdue', 'trial_over', 'suspended' => 'danger',
    default => 'muted',
};
$headline = match ($state) {
    'trial' => 'Essai gratuit : ' . max(0, (int) $daysLeft) . ' jour(s) restant(s)',
    'trial_over' => 'Votre essai gratuit est terminé',
    'pending' => 'Activez votre espace',
    'active' => 'Abonnement actif jusqu\'au ' . $date($end),
    'overdue' => 'Abonnement échu depuis le ' . $date($end),
    'suspended' => 'Abonnement suspendu',
    default => 'Aucun abonnement en cours',
};
$methodLabels = ['wave' => 'Wave', 'orange-money' => 'Orange Money', 'especes' => 'Espèces', 'virement' => 'Virement', 'cheque' => 'Chèque'];

?>
<section class="btq sub-page">
    <header class="btq-head">
        <div>
            <h1>Abonnement</h1>
            <p class="muted">Sesam <?= Html::e((string) ($page['sector'] ?? '')) ?> · <?= Html::e(Money::format($price)) ?> par mois</p>
        </div>
    </header>

    <?php if ($state === 'pending'): ?>
        <p class="alert" role="status">Votre compte est créé. Réglez votre premier mois par Wave ou Orange Money : votre espace s'ouvre aussitôt le paiement confirmé.</p>
    <?php elseif (!empty($access['blocked'])): ?>
        <p class="alert alert-strong" role="alert">Votre espace est en pause : réglez l'abonnement pour retrouver toutes vos données, intactes.</p>
    <?php endif; ?>

    <article class="btq-card sub-status is-<?= Html::e($tone) ?>">
        <span class="btq-status btq-status-<?= Html::e($tone) ?>"><?= Html::e((string) ($access['label'] ?? '')) ?></span>
        <h2><?= Html::e($headline) ?></h2>
        <?php if ($state === 'trial'): ?>
            <p class="muted">Toutes les fonctions sont ouvertes jusqu'au <?= Html::e($date($end)) ?>. Abonnez-vous dès maintenant : votre abonnement démarrera aujourd'hui, sans rien perdre.</p>
        <?php elseif ($state === 'pending'): ?>
            <p class="muted"><?= Html::e(Money::format($price)) ?> par mois<?= $setupFee > 0 ? ', plus ' . Html::e(Money::format($setupFee)) . ' de frais de mise en place payés une seule fois' : '' ?>. Toutes les fonctions sont incluses, sans engagement.</p>
        <?php elseif ($state === 'active'): ?>
            <p class="muted">Réabonnez-vous à l'avance : les mois payés s'ajoutent après le <?= Html::e($date($end)) ?>.</p>
        <?php endif; ?>
    </article>

    <div class="btq-two">
        <section class="btq-card">
            <h2><?= $state === 'active' ? 'Prolonger mon abonnement' : ($state === 'pending' ? 'Activer mon espace' : 'M\'abonner') ?></h2>
            <?php if (empty($page['canPay'])): ?>
                <p class="muted">Seul le responsable du commerce peut régler l'abonnement.</p>
            <?php else: ?>
                <form method="post" action="<?= Html::url('/abonnement/payer') ?>" class="btq-form">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <fieldset class="sub-choices">
                        <legend>Durée</legend>
                        <?php foreach ($plans as $count => $plan): ?>
                            <label class="sub-choice">
                                <input type="radio" name="months" value="<?= (int) $count ?>"<?= (int) $count === 1 ? ' checked' : '' ?>>
                                <span>
                                    <strong><?= Html::e((string) $plan['label']) ?><?= (int) $plan['discount'] > 0 ? ' · -' . (int) $plan['discount'] . ' %' : '' ?></strong>
                                    <small><?= Html::e(Money::format((int) $plan['amount'] + $setupFee)) ?><?php if ((int) $plan['discount'] > 0): ?> <s><?= Html::e(Money::format((int) $plan['base'] + $setupFee)) ?></s><?php endif; ?></small>
                                    <?php if ((int) $plan['saving'] > 0): ?><small class="sub-choice-save">Vous économisez <?= Html::e(Money::format((int) $plan['saving'])) ?></small><?php elseif ((int) $count > 1): ?><small><?= Html::e(Money::format((int) $plan['per_month'])) ?> / mois</small><?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php if (isset($errors['months'])): ?><p class="field-error"><?= Html::e($errors['months']) ?></p><?php endif; ?>
                    <fieldset class="sub-choices">
                        <legend>Moyen de paiement</legend>
                        <?php foreach ($methods as $key => $label): ?>
                            <label class="sub-choice">
                                <input type="radio" name="method" value="<?= Html::e($key) ?>"<?= $key === 'wave' ? ' checked' : '' ?>>
                                <span class="pay-method-logo is-<?= $key === 'wave' ? 'wave' : 'om' ?>" aria-hidden="true"><?= $key === 'wave' ? 'W' : 'OM' ?></span>
                                <span><strong><?= Html::e($label) ?></strong></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php if (isset($errors['method'])): ?><p class="field-error"><?= Html::e($errors['method']) ?></p><?php endif; ?>
                    <div><button class="btn btn-primary" type="submit">Continuer vers le paiement</button></div>
                    <p class="btq-help">Démonstration : le paiement est simulé, aucun argent ne circule.</p>
                </form>
            <?php endif; ?>
        </section>

        <section class="btq-card">
            <h2>Historique des paiements</h2>
            <?php if ($payments === []): ?>
                <p class="muted">Aucun paiement pour l'instant.</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($payments as $payment): ?>
                        <li><div>
                            <span><strong><?= Html::e(Money::format((int) $payment['amount'])) ?></strong><small><?= Html::e($date((string) $payment['paid_on'])) ?> · <?= (int) $payment['months'] ?> mois · <?= Html::e($methodLabels[$payment['method']] ?? (string) $payment['method']) ?><?= $payment['channel'] === 'en-ligne' ? ' · en ligne' : '' ?></small></span>
                            <span class="btq-list-end"><small>jusqu'au</small><strong><?= Html::e($date((string) $payment['period_end'])) ?></strong></span>
                        </div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>
