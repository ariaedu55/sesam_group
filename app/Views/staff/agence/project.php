<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\AgencyInvoice;
use Sesam\Models\AgencyProject;
use Sesam\Services\Agency\AgencyCatalog;

require __DIR__ . '/_helpers.php';

$project = ($project ?? null) instanceof AgencyProject ? $project : null;
if ($project === null) {
    return;
}
$invoices = array_values(array_filter(is_array($invoices ?? null) ? $invoices : [], static fn ($i): bool => $i instanceof AgencyInvoice));
$capacity = is_array($capacity ?? null) ? $capacity : [];
$today = is_string($today ?? null) ? $today : gmdate('Y-m-d');
$form = is_string($form ?? null) ? $form : '';
$old = is_array($old ?? null) ? $old : [];
$canProjects = (bool) ($canProjects ?? false);
$canInvoices = (bool) ($canInvoices ?? false);
$oldValue = static function (string $formName, string $key, string $default = '') use ($form, $old): string {
    $value = $form === $formName ? ($old[$key] ?? $default) : $default;

    return Html::e(is_scalar($value) ? (string) $value : $default);
};
$amount = $project->amount();
$invoiced = $project->invoicedAmount();
$paid = $project->paidAmount();
$remaining = max(0, $amount - $invoiced);
$paidShare = $amount > 0 ? min(100, (int) round($paid * 100 / $amount)) : 0;
$invoicedShare = $amount > 0 ? min(100, (int) round($invoiced * 100 / $amount)) : 0;
$capacityFull = (int) ($capacity['used'] ?? 0) >= (int) ($capacity['limit'] ?? AgencyCatalog::MONTHLY_CAPACITY);
$statusChip = ['planned' => 'chip-muted', 'in_progress' => 'chip-warn', 'delivered' => 'chip-ok'];
$defaultDue = date('Y-m-d', (int) strtotime($today . ' +7 days UTC'));

?>
<section class="console agency">
    <p class="console-back"><a href="<?= Html::url('/agence/projets') ?>">← Tous les projets</a></p>
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">Projet web · <?= Html::e($project->offerLabel()) ?></p>
            <h1><?= Html::e($project->title()) ?></h1>
            <p><a class="agency-hero-link" href="<?= Html::url('/agence/prospects/' . $project->prospectId()) ?>">Fiche client →</a></p>
            <p class="console-hero-chips">
                <span class="chip <?= $statusChip[$project->status()] ?? 'chip-muted' ?>"><?= Html::e($project->statusLabel()) ?></span>
                <?php if ($project->startMonth() !== null): ?><span class="chip chip-muted">Compté en <?= Html::e($monthLabel($project->startMonth())) ?></span><?php endif; ?>
            </p>
        </div>
        <?php if ($project->siteUrl() !== ''): ?>
            <div class="dash-hero-actions">
                <a class="dash-cta dash-cta-solid" href="<?= Html::e($project->siteUrl()) ?>" target="_blank" rel="noopener noreferrer">Voir le site</a>
            </div>
        <?php endif; ?>
    </header>

    <div class="console-detail">
        <div class="console-side">
            <article class="dash-card" aria-labelledby="money-title">
                <div class="dash-card-head">
                    <h2 id="money-title">Encaissement</h2>
                    <span class="chip <?= $paid >= $amount ? 'chip-ok' : 'chip-muted' ?>"><?= $paidShare ?>&nbsp;% payé</span>
                </div>
                <p class="agency-capacity-figure"><strong><?= $money($paid) ?></strong> <span>sur <?= $money($amount) ?></span></p>
                <div class="agency-progress" role="img" aria-label="<?= $paidShare ?> % payé, <?= $invoicedShare ?> % facturé">
                    <span class="agency-progress-invoiced" style="width: <?= $invoicedShare ?>%"></span>
                    <span class="agency-progress-paid" style="width: <?= $paidShare ?>%"></span>
                </div>
                <ul class="agency-legend">
                    <li><span class="agency-legend-paid" aria-hidden="true"></span>Payé <?= $money($paid) ?></li>
                    <li><span class="agency-legend-invoiced" aria-hidden="true"></span>Facturé <?= $money($invoiced) ?></li>
                    <li><span aria-hidden="true"></span>Reste à facturer <?= $money($remaining) ?></li>
                </ul>
            </article>

            <article class="dash-card" id="factures" aria-labelledby="invoices-title">
                <div class="dash-card-head">
                    <h2 id="invoices-title">Factures</h2>
                    <span class="chip chip-muted"><?= count($invoices) ?></span>
                </div>
                <?php if ($invoices === []): ?>
                    <p class="muted dash-empty">Aucune facture émise.</p>
                <?php else: ?>
                    <ul class="agency-invoices">
                        <?php foreach ($invoices as $invoice): ?>
                            <?php require __DIR__ . '/_invoice.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($canInvoices && $remaining > 0): ?>
                    <form class="console-action" data-modal-form="Émettre une facture" data-modal-title="Émettre une facture" data-modal-kicker="Agence" method="post" action="<?= Html::url('/agence/projets/' . $project->id() . '/factures') ?>" novalidate>
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <h3>Émettre une facture</h3>
                        <div class="field">
                            <label for="label"><span>Objet</span></label>
                            <input class="input<?= $form === 'invoice' ? $invalid('label') : '' ?>" id="label" name="label" type="text" maxlength="120" required placeholder="Acompte 50 %" value="<?= $oldValue('invoice', 'label') ?>">
                            <?= $form === 'invoice' ? $fieldError('label') : '' ?>
                        </div>
                        <div class="console-action-grid">
                            <div class="field">
                                <label for="amount"><span>Montant (FCFA)</span></label>
                                <input class="input<?= $form === 'invoice' ? $invalid('amount') : '' ?>" id="amount" name="amount" type="text" inputmode="numeric" maxlength="12" required value="<?= $oldValue('invoice', 'amount', (string) $remaining) ?>">
                                <?= $form === 'invoice' ? $fieldError('amount') : '' ?>
                            </div>
                            <div class="field">
                                <label for="due_on"><span>Échéance</span></label>
                                <input class="input<?= $form === 'invoice' ? $invalid('due_on') : '' ?>" id="due_on" name="due_on" type="date" required value="<?= $oldValue('invoice', 'due_on', $defaultDue) ?>">
                                <?= $form === 'invoice' ? $fieldError('due_on') : '' ?>
                            </div>
                        </div>
                        <p class="muted">Il reste <?= $money($remaining) ?> à facturer sur ce projet.</p>
                        <button class="btn btn-primary" type="submit">Émettre</button>
                    </form>
                <?php endif; ?>
            </article>
        </div>

        <div class="console-side">
            <article class="dash-card" aria-labelledby="project-title">
                <div class="dash-card-head"><h2 id="project-title">Projet</h2></div>
                <dl class="console-facts">
                    <div><dt>Formule</dt><dd><?= Html::e($project->offerLabel()) ?></dd></div>
                    <div><dt>Montant du devis</dt><dd><?= $money($amount) ?></dd></div>
                    <div><dt>Démarré le</dt><dd><?= $date($project->startedOn()) ?></dd></div>
                    <div><dt>Livré le</dt><dd><?= $date($project->deliveredOn()) ?></dd></div>
                    <?php if ($project->siteUrl() !== ''): ?>
                        <div><dt>Adresse du site</dt><dd><a class="console-link" href="<?= Html::e($project->siteUrl()) ?>" target="_blank" rel="noopener noreferrer"><?= Html::e($project->siteUrl()) ?></a></dd></div>
                    <?php endif; ?>
                </dl>

                <?php if ($form === 'status' && isset($errorsList['action'])): ?>
                    <p class="alert alert-strong agency-alert" role="alert"><?= Html::e((string) $errorsList['action']) ?></p>
                <?php endif; ?>

                <?php if ($canProjects && $project->status() === 'planned'): ?>
                    <form class="console-action" method="post" action="<?= Html::url('/agence/projets/' . $project->id() . '/statut') ?>">
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <input type="hidden" name="action" value="start">
                        <h3>Démarrer le projet</h3>
                        <p class="muted"><?= $capacityFull
                            ? 'Les ' . AgencyCatalog::MONTHLY_CAPACITY . ' places du mois sont prises. Le démarrage sera possible le mois prochain.'
                            : 'Il occupera une place sur ' . AgencyCatalog::MONTHLY_CAPACITY . ' en ' . Html::e($monthLabel(substr($today, 0, 7))) . '.' ?></p>
                        <button class="btn btn-primary" type="submit"<?= $capacityFull ? ' disabled' : '' ?>>Démarrer maintenant</button>
                    </form>
                <?php elseif ($canProjects && $project->status() === 'in_progress'): ?>
                    <form class="console-action" data-modal-form="Livrer le projet" data-modal-title="Livrer le projet" data-modal-kicker="Agence" method="post" action="<?= Html::url('/agence/projets/' . $project->id() . '/statut') ?>" novalidate>
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <input type="hidden" name="action" value="deliver">
                        <h3>Livrer le projet</h3>
                        <div class="field">
                            <label for="site_url"><span>Adresse du site (facultatif)</span></label>
                            <input class="input<?= $form === 'status' ? $invalid('site_url') : '' ?>" id="site_url" name="site_url" type="url" maxlength="255" placeholder="https://" value="<?= $oldValue('status', 'site_url') ?>">
                            <?= $form === 'status' ? $fieldError('site_url') : '' ?>
                        </div>
                        <button class="btn btn-primary" type="submit">Marquer livré</button>
                    </form>
                <?php endif; ?>
            </article>

            <?php require __DIR__ . '/_capacity.php'; ?>
        </div>
    </div>
</section>
