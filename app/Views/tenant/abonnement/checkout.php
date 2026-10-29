<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Helpers\Html;

$checkout = is_array($checkout ?? null) ? $checkout : [];
$amount = (int) ($checkout['amount'] ?? 0);
$method = (string) ($checkout['method'] ?? 'wave');

?>
<section class="btq pay-sandbox">
    <p class="alert" role="status"><strong>Simulation de démonstration</strong> : cet écran imite le paiement NabooPay. Aucun appel à Wave ni à Orange Money, aucun débit.</p>
    <article class="btq-card pay-checkout">
        <p class="pay-checkout-brand">NabooPay · paiement sécurisé</p>
        <p class="pay-checkout-label">Abonnement SESAM · <?= Html::e((string) ($checkout['planLabel'] ?? (($checkout['months'] ?? 1) . ' mois'))) ?><?= (int) ($checkout['discount'] ?? 0) > 0 ? ' · -' . (int) $checkout['discount'] . ' %' : '' ?></p>
        <p class="pay-checkout-amount"><?= Html::e(Money::format($amount)) ?></p>
        <?php if ((int) ($checkout['saving'] ?? 0) > 0): ?><p class="muted">Réduction de <?= Html::e(Money::format((int) $checkout['saving'])) ?> incluse.</p><?php endif; ?>
        <?php if ((int) ($checkout['setupFee'] ?? 0) > 0): ?><p class="muted">Dont <?= Html::e(Money::format((int) $checkout['setupFee'])) ?> de frais de mise en place (une seule fois).</p><?php endif; ?>
        <p class="muted pay-checkout-with">Payé avec <img src="<?= Html::asset($method === 'wave' ? 'assets/images/paiement/wave.png' : 'assets/images/paiement/orange-money.png') ?>" alt="" width="28" height="28"> <strong><?= Html::e((string) ($checkout['methodLabel'] ?? '')) ?></strong></p>
        <p>Votre abonnement sera actif jusqu'au <strong><?= Html::e(gmdate('d/m/Y', (int) strtotime((string) ($checkout['newEnd'] ?? 'now') . ' UTC'))) ?></strong>.</p>
        <form method="post" action="<?= Html::url('/abonnement/payer/confirmer') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <input type="hidden" name="months" value="<?= (int) ($checkout['months'] ?? 1) ?>">
            <input type="hidden" name="method" value="<?= Html::e($method) ?>">
            <button class="btn btn-primary pay-checkout-btn" type="submit" name="decision" value="pay">Payer <?= Html::e(Money::format($amount)) ?></button>
            <button class="btn btn-ghost pay-checkout-btn" type="submit" name="decision" value="cancel">Annuler</button>
        </form>
    </article>
</section>
