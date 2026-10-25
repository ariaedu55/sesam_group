<?php

declare(strict_types=1);

use Sesam\Core\Commande\OrderStatus;
use Sesam\Helpers\Html;
use Sesam\Models\CommandeOrder;
use Sesam\Models\CommandeOrderItem;
use Sesam\Models\CommandeOrderMeasurement;

$detail = is_array($detail ?? null) ? $detail : [];
$order = ($detail['order'] ?? null) instanceof CommandeOrder ? $detail['order'] : null;
$items = is_array($detail['items'] ?? null) ? $detail['items'] : [];
$measurements = is_array($detail['measurements'] ?? null) ? $detail['measurements'] : [];
$delivery = is_array($detail['delivery'] ?? null) ? $detail['delivery'] : null;
$canManage = (bool) ($canManage ?? false);
$canPay = (bool) ($canPay ?? false);
$nextStatus = is_string($nextStatus ?? null) ? $nextStatus : null;
$nextStatusLabel = is_string($nextStatusLabel ?? null) ? $nextStatusLabel : null;

if ($order === null) {
    return;
}

?>
<section class="crm-detail">
    <h1><?= Html::e($order->orderNumber()) ?></h1>
    <p class="muted"><?= Html::e($order->clientName()) ?> · <?= Html::e(OrderStatus::label($order->status())) ?></p>
    <article class="card">
        <dl class="crm-dl">
            <dt>Total</dt>
            <dd><?= Html::e(number_format($order->totalMinor(), 0, ',', ' ')) ?> FCFA</dd>
            <dt>Acompte</dt>
            <dd><?= Html::e(number_format($order->depositAmountMinor(), 0, ',', ' ')) ?> FCFA</dd>
            <?php if ($order->paymentId() !== null): ?>
                <dt>Paiement</dt>
                <dd><a href="<?= Html::url('/paiements/' . $order->paymentId()) ?>">#<?= Html::e((string) $order->paymentId()) ?></a></dd>
            <?php endif; ?>
            <?php if ($order->deliveryDueAt() !== null): ?>
                <dt>Livraison prévue</dt>
                <dd><?= Html::e($order->deliveryDueAt()) ?> UTC</dd>
            <?php endif; ?>
        </dl>
    </article>

    <h2>Lignes</h2>
    <ul class="crm-list">
        <?php foreach ($items as $line): ?>
            <?php if (!$line instanceof CommandeOrderItem) { continue; } ?>
            <li><?= Html::e($line->productName()) ?> × <?= Html::e((string) $line->quantity()) ?> — <?= Html::e(number_format($line->lineTotalMinor(), 0, ',', ' ')) ?> FCFA</li>
        <?php endforeach; ?>
    </ul>

    <?php if ($canManage && $order->status() !== OrderStatus::CANCELLED && $order->status() !== OrderStatus::DELIVERED): ?>
        <div class="row">
            <?php if ($nextStatus !== null): ?>
                <form method="post" action="<?= Html::url('/commande/commandes/' . $order->id() . '/avancer') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <button class="btn btn-primary" type="submit">Passer à : <?= Html::e($nextStatusLabel ?? '') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canPay && $order->depositAmountMinor() >= 100 && $order->paymentId() === null): ?>
                <form method="post" action="<?= Html::url('/commande/commandes/' . $order->id() . '/acompte') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <button class="btn btn-secondary" type="submit">Encaisser l'acompte</button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= Html::url('/commande/commandes/' . $order->id() . '/annuler') ?>" data-confirm="Annuler cette commande ?">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <button class="btn btn-ghost" type="submit">Annuler</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <section class="card stack">
            <h2>Tissus, instructions, photo</h2>
            <form class="stack" data-modal-form="Modifier les détails" data-modal-title="Tissus, instructions, photo" data-modal-kicker="Commande" method="post" action="<?= Html::url('/commande/commandes/' . $order->id() . '/details') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <div class="field">
                    <label for="fabric_notes"><span>Tissus</span></label>
                    <textarea class="input" id="fabric_notes" name="fabric_notes" rows="2" maxlength="500"><?= Html::e($order->fabricNotes()) ?></textarea>
                </div>
                <div class="field">
                    <label for="instructions"><span>Instructions</span></label>
                    <textarea class="input" id="instructions" name="instructions" rows="2" maxlength="500"><?= Html::e($order->instructions()) ?></textarea>
                </div>
                <div class="field">
                    <label for="photo_url"><span>URL photo (référence)</span></label>
                    <input class="input" id="photo_url" name="photo_url" type="url" maxlength="500" value="<?= Html::e($order->photoUrl()) ?>" placeholder="https://">
                </div>
                <button class="btn btn-secondary" type="submit">Enregistrer</button>
            </form>
        </section>

        <section class="card stack">
            <h2>Mesures</h2>
            <form class="stack" data-modal-form="Ajouter des mesures" data-modal-title="Nouvelle fiche de mesures" data-modal-kicker="Commande" method="post" action="<?= Html::url('/commande/commandes/' . $order->id() . '/mesures') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <div class="row">
                    <div class="field">
                        <label for="chest_cm"><span>Poitrine (cm)</span></label>
                        <input class="input" id="chest_cm" name="chest_cm" inputmode="decimal">
                    </div>
                    <div class="field">
                        <label for="waist_cm"><span>Taille (cm)</span></label>
                        <input class="input" id="waist_cm" name="waist_cm" inputmode="decimal">
                    </div>
                    <div class="field">
                        <label for="hips_cm"><span>Hanches (cm)</span></label>
                        <input class="input" id="hips_cm" name="hips_cm" inputmode="decimal">
                    </div>
                    <div class="field">
                        <label for="length_cm"><span>Longueur (cm)</span></label>
                        <input class="input" id="length_cm" name="length_cm" inputmode="decimal">
                    </div>
                </div>
                <div class="field">
                    <label for="notes"><span>Notes</span></label>
                    <textarea class="input" id="notes" name="notes" rows="2" maxlength="500"></textarea>
                </div>
                <button class="btn btn-secondary" type="submit">Ajouter une fiche de mesures</button>
            </form>
            <?php if ($measurements !== []): ?>
                <ul class="crm-timeline">
                    <?php foreach ($measurements as $m): ?>
                        <?php if (!$m instanceof CommandeOrderMeasurement) { continue; } ?>
                        <li>
                            <p>
                                <?php if ($m->chestCm() !== null): ?>Poitrine <?= Html::e($m->chestCm()) ?> cm · <?php endif; ?>
                                <?php if ($m->waistCm() !== null): ?>Taille <?= Html::e($m->waistCm()) ?> cm · <?php endif; ?>
                                <?php if ($m->hipsCm() !== null): ?>Hanches <?= Html::e($m->hipsCm()) ?> cm · <?php endif; ?>
                                <?php if ($m->lengthCm() !== null): ?>Long. <?= Html::e($m->lengthCm()) ?> cm<?php endif; ?>
                            </p>
                            <p class="muted"><?= Html::e($m->createdAt()) ?><?php if ($m->authorName() !== ''): ?> · <?= Html::e($m->authorName()) ?><?php endif; ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card stack">
            <h2>Livraison</h2>
            <form class="stack" data-modal-form="Planifier la livraison" data-modal-title="Livraison" data-modal-kicker="Commande" method="post" action="<?= Html::url('/commande/commandes/' . $order->id() . '/livraison') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <div class="field">
                    <label for="delivery_due_at"><span>Date prévue (UTC)</span></label>
                    <input class="input" id="delivery_due_at" name="delivery_due_at" type="datetime-local" required>
                </div>
                <div class="field">
                    <label for="delivery_notes"><span>Notes livraison</span></label>
                    <textarea class="input" id="delivery_notes" name="delivery_notes" rows="2" maxlength="500"><?= Html::e(is_array($delivery) ? (string) ($delivery['notes'] ?? '') : '') ?></textarea>
                </div>
                <button class="btn btn-secondary" type="submit">Planifier</button>
            </form>
        </section>
    <?php endif; ?>

    <p><a href="<?= Html::url('/commande/commandes') ?>">← Liste des commandes</a></p>
</section>
