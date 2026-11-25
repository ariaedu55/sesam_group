<?php

declare(strict_types=1);

use Sesam\Core\Money\Country;
use Sesam\Core\Money\Currency;
use Sesam\Helpers\Html;
use Sesam\Services\SaasService;

$prices = is_array($prices ?? null) ? $prices : [];
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$canManage = (bool) ($canManage ?? false);
$trial = is_array($trial ?? null) ? $trial : ['enabled' => true, 'updated_at' => null, 'updated_by_name' => null];
$trialOn = !empty($trial['enabled']);
$usdRate = (float) ($usdRate ?? Currency::DEFAULT_USD_TO_XOF);
$ro = $canManage ? '' : ' disabled';
$products = \Sesam\Controllers\ActivityController::PRODUCTS;
$planLabels = [3 => '3 mois', 6 => '6 mois', 12 => '1 an'];
$icon = dirname(__DIR__, 2) . '/partials/ui-icon.php';
/** Une erreur dans ce module (et cette devise) : la carte s'ouvre et l'onglet est sélectionné. */
$hasError = static function (string $prefix) use ($errors): bool {
    foreach (array_keys($errors) as $key) {
        if (str_starts_with((string) $key, $prefix)) {
            return true;
        }
    }

    return false;
};

?>
<section class="btq px">
    <header class="btq-head">
        <div>
            <h1>Tarifs et essai gratuit</h1>
            <p class="muted">Chaque service a un tarif par devise. Le pays du client décide de la devise : <strong>FCFA</strong> pour l'Afrique francophone de la zone franc CFA, <strong>dollar</strong> pour les États-Unis et le Canada, <strong>euro</strong> pour l'Europe. Ouvrez un service pour régler ses prix.</p>
        </div>
    </header>
    <?php if ($errors !== []): ?><p class="alert alert-danger" role="alert"><?= Html::e(implode(' ', $errors)) ?></p><?php endif; ?>

    <details class="btq-card px-fold trial-card<?= $trialOn ? ' is-on' : ' is-off' ?>" id="essai">
        <summary class="px-fold-head">
            <span class="px-fold-icon" aria-hidden="true">🎁</span>
            <span class="px-fold-title"><strong>Essai gratuit d'un mois à l'inscription</strong><small><?= $trialOn ? 'Les nouveaux clients profitent d\'un mois offert.' : 'Les nouveaux clients paient leur premier mois pour ouvrir leur espace.' ?></small></span>
            <span class="btq-status btq-status-<?= $trialOn ? 'success' : 'muted' ?>"><?= $trialOn ? 'Activé' : 'Désactivé' ?></span>
            <span class="px-chevron" aria-hidden="true"></span>
        </summary>
        <div class="px-fold-body">
            <ul class="trial-card-rules">
                <li><strong>Activé</strong> : chaque nouveau commerce ouvre son espace tout de suite et l'utilise gratuitement pendant 1 mois, puis s'abonne.</li>
                <li><strong>Désactivé</strong> : le compte est créé, mais l'espace ne s'ouvre qu'après le paiement du premier mois (plus les frais de mise en place s'ils sont définis).</li>
                <li>Le changement s'applique aux <strong>prochaines</strong> inscriptions : les essais en cours continuent jusqu'à leur terme.</li>
            </ul>
            <?php if (!empty($trial['updated_at'])): ?><p class="muted">Modifié le <?= Html::e(gmdate('d/m/Y à H:i', (int) strtotime((string) $trial['updated_at'] . ' UTC'))) ?><?= !empty($trial['updated_by_name']) ? ' par ' . Html::e((string) $trial['updated_by_name']) : '' ?></p><?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= Html::url('/tarifs/essai') ?>" class="trial-card-form">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <input type="hidden" name="trial_enabled" value="<?= $trialOn ? '0' : '1' ?>">
                    <button class="btn <?= $trialOn ? 'btn-ghost' : 'btn-primary' ?>" type="submit"><?= $trialOn ? 'Désactiver l\'essai gratuit' : 'Activer l\'essai gratuit' ?></button>
                </form>
            <?php else: ?>
                <p class="muted">Seuls les responsables des abonnements peuvent modifier ce réglage.</p>
            <?php endif; ?>
        </div>
    </details>

    <form class="px-form" method="post" action="<?= Html::url('/tarifs') ?>" data-px-form>
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">

        <?php foreach ($prices as $sector => $price): ?>
            <?php
            $product = $products[$sector] ?? ['product' => 'Sesam ' . $price['label'], 'icon' => 'store', 'tone' => 'blue', 'pitch' => ''];
            $sectorError = $hasError($sector . '_');
            $firstErrorCurrency = null;
            foreach (array_keys(Currency::ALL) as $currency) {
                if ($firstErrorCurrency === null && $hasError($sector . '_' . $currency . '_')) {
                    $firstErrorCurrency = $currency;
                }
            }
            $activeCurrency = $firstErrorCurrency ?? Currency::XOF;
            ?>
            <details class="btq-card px-fold px-sector" data-tone="<?= Html::e($product['tone']) ?>"<?= $sectorError ? ' open' : '' ?>>
                <summary class="px-fold-head">
                    <span class="px-sector-icon" aria-hidden="true"><?php $name = $product['icon']; include $icon; ?></span>
                    <span class="px-fold-title"><strong><?= Html::e($product['product']) ?></strong><small><?= Html::e($product['pitch']) ?></small></span>
                    <span class="px-summary-prices">
                        <?php foreach ($price['currencies'] as $currency => $row): ?>
                            <span class="px-summary-price<?= (int) $row['monthly_amount'] > 0 ? '' : ' is-empty' ?>"><?= (int) $row['monthly_amount'] > 0 ? Html::e(Currency::format((int) $row['monthly_amount'], $currency)) : Html::e(Currency::short($currency)) . ' à définir' ?></span>
                        <?php endforeach; ?>
                    </span>
                    <span class="px-chevron" aria-hidden="true"></span>
                </summary>
                <div class="px-fold-body">
                    <div class="px-tabs" role="tablist" aria-label="Devises de <?= Html::e($product['product']) ?>">
                        <?php foreach (array_keys(Currency::ALL) as $currency): ?>
                            <?php $tabId = 'px-' . $sector . '-' . $currency; ?>
                            <button class="px-tab" type="button" role="tab" id="<?= Html::e($tabId) ?>-tab" aria-controls="<?= Html::e($tabId) ?>" aria-selected="<?= $currency === $activeCurrency ? 'true' : 'false' ?>" data-px-tab="<?= Html::e($tabId) ?>">
                                <span class="px-tab-symbol"><?= Html::e(Currency::ALL[$currency]['symbol']) ?></span>
                                <span><strong><?= Html::e(Currency::name($currency)) ?></strong><small><?= Html::e(Currency::ALL[$currency]['region']) ?></small></span>
                                <?php if ($hasError($sector . '_' . $currency . '_')): ?><span class="px-tab-alert" aria-label="Erreur">!</span><?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($price['currencies'] as $currency => $row): ?>
                        <?php
                        $tabId = 'px-' . $sector . '-' . $currency;
                        $prefix = $sector . '_' . $currency;
                        $monthlyValue = (string) ($old[$prefix . '_monthly'] ?? Currency::inputValue((int) $row['monthly_amount'], $currency));
                        $setupValue = (string) ($old[$prefix . '_setup'] ?? Currency::inputValue((int) $row['setup_fee'], $currency));
                        $short = Currency::short($currency);
                        ?>
                        <div class="px-panel" role="tabpanel" id="<?= Html::e($tabId) ?>" aria-labelledby="<?= Html::e($tabId) ?>-tab" data-px-panel data-currency="<?= Html::e($currency) ?>"<?= $currency === $activeCurrency ? '' : ' hidden' ?>>
                            <p class="px-panel-countries"><strong>Pays :</strong> <?= Html::e(implode(', ', Country::grouped()[$currency] ?? [])) ?></p>
                            <div class="btq-grid-2">
                                <div class="field">
                                    <label for="<?= Html::e($prefix) ?>_monthly"><span>Mensualité (<?= Html::e($short) ?>)</span></label>
                                    <input class="input<?= isset($errors[$prefix . '_monthly']) ? ' is-invalid' : '' ?>" id="<?= Html::e($prefix) ?>_monthly" name="<?= Html::e($prefix) ?>_monthly" inputmode="decimal" placeholder="<?= Currency::decimals($currency) > 0 ? 'ex. 29,90' : 'ex. 15000' ?>" value="<?= Html::e($monthlyValue) ?>" data-px-monthly<?= $ro ?>>
                                </div>
                                <div class="field">
                                    <label for="<?= Html::e($prefix) ?>_setup"><span>Frais de mise en place (<?= Html::e($short) ?>)</span></label>
                                    <input class="input<?= isset($errors[$prefix . '_setup']) ? ' is-invalid' : '' ?>" id="<?= Html::e($prefix) ?>_setup" name="<?= Html::e($prefix) ?>_setup" inputmode="decimal" value="<?= Html::e($setupValue) ?>"<?= $ro ?>>
                                </div>
                            </div>
                            <div class="plan-grid" role="group" aria-label="Formules longues <?= Html::e($product['product'] . ' en ' . $short) ?>">
                                <?php foreach ($planLabels as $months => $planLabel):
                                    $plan = $row['plans'][$months] ?? ['enabled' => true, 'discount' => 0];
                                    $key = $prefix . '_plan_' . $months;
                                    $enabled = $old !== [] ? !empty($old[$key . '_enabled']) : (bool) $plan['enabled'];
                                    $discount = (string) ($old[$key . '_discount'] ?? $plan['discount']);
                                    $amount = SaasService::planAmount((int) $row['monthly_amount'], $months, (int) $discount);
                                ?>
                                    <div class="plan-cell<?= $enabled ? '' : ' is-off' ?><?= isset($errors[$key . '_discount']) ? ' is-invalid' : '' ?>" data-px-plan="<?= $months ?>">
                                        <label class="plan-toggle">
                                            <input type="checkbox" name="<?= Html::e($key) ?>_enabled" value="1"<?= $enabled ? ' checked' : '' ?> data-px-enabled<?= $ro ?>>
                                            <span><strong>Abonnement <?= Html::e($planLabel) ?></strong><small>Proposer</small></span>
                                        </label>
                                        <div class="field plan-discount">
                                            <label for="<?= Html::e($key) ?>_discount"><span>Réduction</span></label>
                                            <span class="plan-percent"><input class="input" id="<?= Html::e($key) ?>_discount" name="<?= Html::e($key) ?>_discount" inputmode="numeric" maxlength="3" value="<?= Html::e($discount) ?>" data-px-discount<?= isset($errors[$key . '_discount']) ? ' aria-invalid="true"' : '' ?><?= $ro ?>><span aria-hidden="true">%</span></span>
                                        </div>
                                        <p class="plan-price" data-px-price><?php if ((int) $row['monthly_amount'] > 0): ?><strong><?= Html::e(Currency::format($amount, $currency)) ?></strong><?php if ((int) $discount > 0): ?> <s><?= Html::e(Currency::format((int) $row['monthly_amount'] * $months, $currency)) ?></s><?php endif; ?><?php else: ?>Mensualité à définir<?php endif; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>

        <details class="btq-card px-fold" id="taux"<?= isset($errors['usd_rate']) ? ' open' : '' ?>>
            <summary class="px-fold-head">
                <span class="px-fold-icon" aria-hidden="true">💱</span>
                <span class="px-fold-title"><strong>Conversion vers le FCFA</strong><small>Les revenus de la console restent en FCFA : les paiements en dollars et en euros y sont convertis.</small></span>
                <span class="px-summary-prices"><span class="px-summary-price">1 € = 655,957 FCFA</span><span class="px-summary-price">1 $ = <?= Html::e(rtrim(rtrim(number_format($usdRate, 3, ',', ' '), '0'), ',')) ?> FCFA</span></span>
                <span class="px-chevron" aria-hidden="true"></span>
            </summary>
            <div class="px-fold-body btq-grid-2">
                <div class="field">
                    <label><span>Euro</span></label>
                    <input class="input" value="655,957 FCFA (parité fixe officielle)" disabled>
                </div>
                <div class="field">
                    <label for="usd_rate"><span>Dollar américain (FCFA pour 1 $)</span></label>
                    <input class="input<?= isset($errors['usd_rate']) ? ' is-invalid' : '' ?>" id="usd_rate" name="usd_rate" inputmode="decimal" value="<?= Html::e((string) ($old['usd_rate'] ?? rtrim(rtrim(number_format($usdRate, 3, ',', ''), '0'), ','))) ?>"<?= $ro ?>>
                </div>
            </div>
        </details>

        <?php if ($canManage): ?>
            <div class="btq-sticky-save"><button class="btn btn-primary" type="submit">Enregistrer les tarifs</button></div>
        <?php endif; ?>
    </form>
</section>
<script src="<?= Html::asset('assets/js/prices.js') ?>" defer></script>
