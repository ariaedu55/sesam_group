<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;
use Sesam\Services\SaasService;

$prices = is_array($prices ?? null) ? $prices : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$trial = is_array($trial ?? null) ? $trial : ['enabled' => true, 'updated_at' => null, 'updated_by_name' => null];
$trialOn = !empty($trial['enabled']);
$ro = $canManage ? '' : ' disabled';

?>
<section class="btq">
    <header class="btq-head">
        <div>
            <h1>Tarifs et essai gratuit</h1>
            <p class="muted">Ces tarifs s'appliquent automatiquement à chaque souscription et à chaque renouvellement payé depuis l'espace des commerces. Pour chaque service, proposez aussi des formules 3 mois, 6 mois et 1 an avec une réduction en pourcentage.</p>
        </div>
    </header>
    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert"><?= Html::e(implode(' ', $errors)) ?></p><?php endif; ?>

    <section class="btq-card trial-card<?= $trialOn ? ' is-on' : ' is-off' ?>" id="essai" aria-labelledby="trial-title">
        <div class="trial-card-head">
            <span class="trial-card-icon" aria-hidden="true">🎁</span>
            <div>
                <h2 id="trial-title">Essai gratuit d'un mois à l'inscription</h2>
                <p class="trial-card-state"><span class="btq-status btq-status-<?= $trialOn ? 'success' : 'muted' ?>"><?= $trialOn ? 'Activé' : 'Désactivé' ?></span>
                    <?php if (!empty($trial['updated_at'])): ?><small class="muted">Modifié le <?= Html::e(gmdate('d/m/Y à H:i', (int) strtotime((string) $trial['updated_at'] . ' UTC'))) ?><?= !empty($trial['updated_by_name']) ? ' par ' . Html::e((string) $trial['updated_by_name']) : '' ?></small><?php endif; ?></p>
            </div>
        </div>
        <ul class="trial-card-rules">
            <li><strong>Activé</strong> : chaque nouveau commerce ouvre son espace tout de suite et l'utilise gratuitement pendant 1 mois, puis s'abonne.</li>
            <li><strong>Désactivé</strong> : le compte est créé, mais l'espace ne s'ouvre qu'après le paiement du premier mois (mensualité ci-dessous, plus les frais de mise en place s'ils sont définis), par Wave ou Orange Money.</li>
            <li>Le changement s'applique aux <strong>prochaines</strong> inscriptions : les essais en cours continuent jusqu'à leur terme. La page « Commencer » s'adapte toute seule.</li>
        </ul>
        <?php if ($canManage): ?>
            <form method="post" action="<?= Html::url('/tarifs/essai') ?>" class="trial-card-form">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <input type="hidden" name="trial_enabled" value="<?= $trialOn ? '0' : '1' ?>">
                <button class="btn <?= $trialOn ? 'btn-ghost' : 'btn-primary' ?>" type="submit"><?= $trialOn ? 'Désactiver l\'essai gratuit' : 'Activer l\'essai gratuit' ?></button>
            </form>
        <?php else: ?>
            <p class="muted">Seuls les responsables des abonnements peuvent modifier ce réglage.</p>
        <?php endif; ?>
    </section>
    <form class="btq-form" method="post" action="<?= Html::url('/tarifs') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <?php foreach ($prices as $sector => $price): ?>
            <fieldset>
                <legend>Sesam <?= Html::e((string) $price['label']) ?></legend>
                <div class="btq-grid-2">
                    <div class="field">
                        <label for="<?= Html::e($sector) ?>_monthly"><span>Mensualité (FCFA)</span></label>
                        <input class="input" id="<?= Html::e($sector) ?>_monthly" name="<?= Html::e($sector) ?>_monthly" inputmode="numeric" value="<?= Html::e((string) ($old[$sector . '_monthly'] ?? $price['monthly_amount'])) ?>"<?= $ro ?>>
                    </div>
                    <div class="field">
                        <label for="<?= Html::e($sector) ?>_setup"><span>Frais de mise en place (FCFA)</span></label>
                        <input class="input" id="<?= Html::e($sector) ?>_setup" name="<?= Html::e($sector) ?>_setup" inputmode="numeric" value="<?= Html::e((string) ($old[$sector . '_setup'] ?? $price['setup_fee'])) ?>"<?= $ro ?>>
                    </div>
                </div>
                <div class="plan-grid" role="group" aria-label="Formules longues Sesam <?= Html::e((string) $price['label']) ?>">
                    <?php foreach ([3 => '3 mois', 6 => '6 mois', 12 => '1 an'] as $months => $planLabel):
                        $plan = $price['plans'][$months] ?? ['enabled' => true, 'discount' => 0];
                        $key = $sector . '_plan_' . $months;
                        $enabled = $old !== [] ? !empty($old[$key . '_enabled']) : (bool) $plan['enabled'];
                        $discount = (string) ($old[$key . '_discount'] ?? $plan['discount']);
                        $amount = SaasService::planAmount((int) $price['monthly_amount'], $months, (int) $discount);
                    ?>
                        <div class="plan-cell<?= $enabled ? '' : ' is-off' ?><?= isset($errors[$key . '_discount']) ? ' is-invalid' : '' ?>">
                            <label class="plan-toggle">
                                <input type="checkbox" name="<?= Html::e($key) ?>_enabled" value="1"<?= $enabled ? ' checked' : '' ?><?= $ro ?>>
                                <span><strong><?= Html::e($planLabel) ?></strong><small>Proposer</small></span>
                            </label>
                            <div class="field plan-discount">
                                <label for="<?= Html::e($key) ?>_discount"><span>Réduction</span></label>
                                <span class="plan-percent"><input class="input" id="<?= Html::e($key) ?>_discount" name="<?= Html::e($key) ?>_discount" inputmode="numeric" maxlength="3" value="<?= Html::e($discount) ?>"<?= isset($errors[$key . '_discount']) ? ' aria-invalid="true"' : '' ?><?= $ro ?>><span aria-hidden="true">%</span></span>
                            </div>
                            <p class="plan-price"><?php if ((int) $price['monthly_amount'] > 0): ?><strong><?= Html::e(Money::format($amount)) ?></strong><?php if ((int) $discount > 0): ?> <s><?= Html::e(Money::format((int) $price['monthly_amount'] * $months)) ?></s><?php endif; ?><?php else: ?>Mensualité à définir<?php endif; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>
        <?php if ($canManage): ?>
            <div class="btq-sticky-save"><button class="btn btn-primary" type="submit">Enregistrer les tarifs</button></div>
        <?php endif; ?>
    </form>
</section>
