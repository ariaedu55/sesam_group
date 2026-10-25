<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\AgencyProject;
use Sesam\Models\AgencyProspect;
use Sesam\Models\AgencyQuote;
use Sesam\Services\Agency\AgencyCatalog;

require __DIR__ . '/_helpers.php';

$prospect = ($prospect ?? null) instanceof AgencyProspect ? $prospect : null;
if ($prospect === null) {
    return;
}
$events = is_array($events ?? null) ? $events : [];
$quotes = array_values(array_filter(is_array($quotes ?? null) ? $quotes : [], static fn ($q): bool => $q instanceof AgencyQuote));
$projects = array_values(array_filter(is_array($projects ?? null) ? $projects : [], static fn ($p): bool => $p instanceof AgencyProject));
$today = is_string($today ?? null) ? $today : gmdate('Y-m-d');
$form = is_string($form ?? null) ? $form : '';
$old = is_array($old ?? null) ? $old : [];
$canProspects = (bool) ($canProspects ?? false);
$canQuotes = (bool) ($canQuotes ?? false);
$oldValue = static function (string $formName, string $key, string $default = '') use ($form, $old): string {
    $value = $form === $formName ? ($old[$key] ?? $default) : $default;

    return Html::e(is_scalar($value) ? (string) $value : $default);
};
$projectByQuote = [];
foreach ($projects as $project) {
    $projectByQuote[$project->quoteId()] = $project;
}
$funnel = array_keys(array_diff_key(AgencyCatalog::STAGES, ['perdu' => true]));
$position = array_search($prospect->stage(), $funnel, true);
$quoteChip = ['draft' => 'chip-muted', 'sent' => 'chip-warn', 'accepted' => 'chip-ok', 'refused' => 'chip-off'];
$eventLabels = ['created' => 'Création', 'updated' => 'Fiche', 'stage' => 'Étape', 'note' => 'Suivi', 'quote' => 'Devis', 'project' => 'Projet', 'invoice' => 'Facture'];
$next = $prospect->nextActionOn();
$selectedOffer = $form === 'quote' && is_string($old['offer'] ?? null) ? $old['offer'] : '';
$selectedPath = $form === 'quote' && is_string($old['site_path'] ?? null) ? $old['site_path'] : 'site-neuf';
$phoneHref = preg_replace('/[^0-9+]/', '', $prospect->phone()) ?? '';

?>
<section class="console agency">
    <p class="console-back"><a href="<?= Html::url('/agence/prospects') ?>">← Tous les prospects</a></p>
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow"><?= Html::e($prospect->activityLabel()) ?></p>
            <h1><?= Html::e($prospect->displayName()) ?></h1>
            <?php if ($prospect->businessName() !== ''): ?><p><?= Html::e($prospect->fullName()) ?></p><?php endif; ?>
            <p class="console-hero-chips">
                <span class="chip <?= $stageChip($prospect->stage()) ?>"><?= Html::e($prospect->stageLabel()) ?></span>
                <span class="chip chip-muted"><?= $prospect->source() === 'site' ? 'Demande du site' : 'Ajout manuel' ?></span>
                <?php if ($prospect->isClient()): ?><span class="chip chip-ok">Client</span><?php endif; ?>
            </p>
        </div>
        <div class="dash-hero-actions">
            <?php if ($phoneHref !== ''): ?>
                <a class="dash-cta dash-cta-solid" href="tel:<?= Html::e($phoneHref) ?>">Appeler</a>
            <?php endif; ?>
            <?php if ($prospect->email() !== ''): ?>
                <a class="dash-cta dash-cta-ghost" href="mailto:<?= Html::e($prospect->email()) ?>">Écrire</a>
            <?php endif; ?>
            <?php if ($canProspects): ?>
                <a class="dash-cta dash-cta-ghost" href="<?= Html::url('/agence/prospects/' . $prospect->id() . '/modifier') ?>" data-modal="Agence">Modifier</a>
            <?php endif; ?>
        </div>
    </header>

    <ol class="agency-stepper<?= $prospect->stage() === 'perdu' ? ' is-lost' : '' ?>" aria-label="Avancement dans le pipeline">
        <?php foreach ($funnel as $index => $stage): ?>
            <?php $state = $position === false ? '' : ($index < $position ? 'is-done' : ($index === $position ? 'is-current' : '')); ?>
            <li class="<?= $state ?>"<?= $state === 'is-current' ? ' aria-current="step"' : '' ?>>
                <span class="agency-step-dot" aria-hidden="true"><?= $index + 1 ?></span>
                <span class="agency-step-label"><?= Html::e(AgencyCatalog::STAGES[$stage]) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>

    <?php if ($errorsList !== [] && !in_array($form, ['quote', 'note', 'stage'], true)): ?>
        <div class="alert alert-strong" role="alert">
            <?php foreach ($errorsList as $message): ?><p class="field-error"><?= Html::e((string) $message) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="console-detail">
        <div class="console-side">
            <article class="dash-card" id="devis" aria-labelledby="quotes-title">
                <div class="dash-card-head">
                    <h2 id="quotes-title">Devis</h2>
                    <span class="chip chip-muted"><?= count($quotes) ?></span>
                </div>
                <?php if ($quotes === []): ?>
                    <p class="muted dash-empty">Aucun devis pour ce prospect.</p>
                <?php else: ?>
                    <ul class="agency-quotes">
                        <?php foreach ($quotes as $quote): ?>
                            <?php $linked = $projectByQuote[$quote->id()] ?? null; ?>
                            <li>
                                <div class="agency-quote-head">
                                    <strong><?= Html::e($quote->reference()) ?></strong>
                                    <span class="chip <?= $quoteChip[$quote->status()] ?? 'chip-muted' ?>"><?= Html::e($quote->statusLabel()) ?></span>
                                </div>
                                <p class="agency-quote-amount"><?= $money($quote->amount()) ?></p>
                                <p class="muted"><?= Html::e($quote->offerLabel()) ?> · <?= Html::e($quote->sitePathLabel()) ?> · <?= $date($quote->createdAt()) ?></p>
                                <?php if ($quote->description() !== ''): ?>
                                    <p class="agency-quote-desc"><?= nl2br(Html::e($quote->description())) ?></p>
                                <?php endif; ?>
                                <?php if ($canQuotes && in_array($quote->status(), ['draft', 'sent'], true)): ?>
                                    <div class="agency-quote-actions">
                                        <?php foreach ($quote->status() === 'draft' ? ['sent' => 'Marquer envoyé'] : ['accepted' => 'Accepté par le client'] as $nextStatus => $label): ?>
                                            <form method="post" action="<?= Html::url('/agence/devis/' . $quote->id() . '/statut') ?>">
                                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                                <input type="hidden" name="prospect_id" value="<?= $prospect->id() ?>">
                                                <input type="hidden" name="status" value="<?= $nextStatus ?>">
                                                <button class="btn btn-primary btn-sm" type="submit"><?= $label ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                        <form method="post" action="<?= Html::url('/agence/devis/' . $quote->id() . '/statut') ?>">
                                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                            <input type="hidden" name="prospect_id" value="<?= $prospect->id() ?>">
                                            <input type="hidden" name="status" value="refused">
                                            <button class="btn btn-ghost btn-sm" type="submit">Refusé</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                <?php if ($linked !== null): ?>
                                    <a class="agency-more" href="<?= Html::url('/agence/projets/' . $linked->id()) ?>">Projet : <?= Html::e($linked->statusLabel()) ?> →</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($canQuotes && $prospect->stage() !== 'perdu'): ?>
                    <form class="console-action" data-modal-form="Préparer un devis" data-modal-title="Préparer un devis" data-modal-kicker="Agence" method="post" action="<?= Html::url('/agence/prospects/' . $prospect->id() . '/devis') ?>" novalidate>
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <h3>Préparer un devis</h3>
                        <div class="console-action-grid">
                            <div class="field">
                                <label for="offer"><span>Formule</span></label>
                                <select class="select<?= $form === 'quote' ? $invalid('offer') : '' ?>" id="offer" name="offer" required>
                                    <option value="">Choisir…</option>
                                    <?php foreach (AgencyCatalog::OFFERS as $slug => $offer): ?>
                                        <option value="<?= Html::e($slug) ?>"<?= $selectedOffer === $slug ? ' selected' : '' ?>><?= Html::e($offer['label']) ?> — <?= $slug === 'sur-mesure' ? 'sur devis' : 'dès ' . Html::e(number_format($offer['min'], 0, ',', ' ')) . ' FCFA' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $form === 'quote' ? $fieldError('offer') : '' ?>
                            </div>
                            <div class="field">
                                <label for="site_path"><span>Point de départ</span></label>
                                <select class="select<?= $form === 'quote' ? $invalid('site_path') : '' ?>" id="site_path" name="site_path" required>
                                    <?php foreach (AgencyCatalog::SITE_PATHS as $slug => $label): ?>
                                        <option value="<?= Html::e($slug) ?>"<?= $selectedPath === $slug ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $form === 'quote' ? $fieldError('site_path') : '' ?>
                            </div>
                        </div>
                        <div class="field">
                            <label for="amount"><span>Montant (FCFA)</span></label>
                            <input class="input<?= $form === 'quote' ? $invalid('amount') : '' ?>" id="amount" name="amount" type="text" inputmode="numeric" maxlength="12" required placeholder="200000" value="<?= $oldValue('quote', 'amount') ?>">
                            <?= $form === 'quote' ? $fieldError('amount') : '' ?>
                        </div>
                        <div class="field">
                            <label for="description"><span>Contenu (facultatif)</span></label>
                            <textarea class="input<?= $form === 'quote' ? $invalid('description') : '' ?>" id="description" name="description" rows="3" maxlength="2000" placeholder="Pages, fonctions, délais…"><?= $oldValue('quote', 'description') ?></textarea>
                            <?= $form === 'quote' ? $fieldError('description') : '' ?>
                        </div>
                        <button class="btn btn-primary" type="submit">Créer le devis</button>
                    </form>
                <?php endif; ?>
            </article>

            <?php if ($projects !== []): ?>
                <article class="dash-card" aria-labelledby="projects-title">
                    <div class="dash-card-head"><h2 id="projects-title">Projets</h2></div>
                    <ul class="console-rows">
                        <?php foreach ($projects as $project): ?>
                            <li>
                                <a class="console-row" href="<?= Html::url('/agence/projets/' . $project->id()) ?>">
                                    <span class="console-avatar" aria-hidden="true">W</span>
                                    <span class="console-row-main">
                                        <strong><?= Html::e($project->title()) ?></strong>
                                        <span class="muted"><?= $money($project->amount()) ?> · payé <?= $money($project->paidAmount()) ?></span>
                                    </span>
                                    <span class="console-row-chips"><span class="chip <?= $project->status() === 'delivered' ? 'chip-ok' : ($project->status() === 'in_progress' ? 'chip-warn' : 'chip-muted') ?>"><?= Html::e($project->statusLabel()) ?></span></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endif; ?>
        </div>

        <div class="console-side">
            <article class="dash-card" aria-labelledby="contact-title">
                <div class="dash-card-head"><h2 id="contact-title">Coordonnées</h2></div>
                <dl class="console-facts">
                    <div><dt>Contact</dt><dd><?= Html::e($prospect->fullName()) ?></dd></div>
                    <div><dt>Téléphone</dt><dd><?= $prospect->phone() !== '' ? Html::e($prospect->phone()) : '—' ?></dd></div>
                    <div><dt>E-mail</dt><dd class="console-link"><?= $prospect->email() !== '' ? Html::e($prospect->email()) : '—' ?></dd></div>
                    <div><dt>Prochaine relance</dt><dd><?= $next === null ? 'Aucune' : $date($next) . ($next <= $today && !in_array($prospect->stage(), ['livre', 'perdu'], true) ? ' <span class="chip chip-warn">à faire</span>' : '') ?></dd></div>
                </dl>
            </article>

            <?php if ($canProspects): ?>
                <article class="dash-card" aria-labelledby="follow-title">
                    <div class="dash-card-head"><h2 id="follow-title">Suivi</h2></div>
                    <?php if (!$prospect->isClient()): ?>
                        <form class="console-action agency-action-tight" data-modal-form="Changer d'étape" data-modal-title="Changer d'étape" data-modal-kicker="Agence" method="post" action="<?= Html::url('/agence/prospects/' . $prospect->id() . '/etape') ?>" novalidate>
                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                            <h3>Changer d'étape</h3>
                            <div class="field">
                                <label for="stage"><span>Nouvelle étape</span></label>
                                <select class="select<?= $form === 'stage' ? $invalid('stage') : '' ?>" id="stage" name="stage">
                                    <?php foreach (AgencyCatalog::MANUAL_STAGES as $stage): ?>
                                        <option value="<?= Html::e($stage) ?>"<?= $prospect->stage() === $stage ? ' selected' : '' ?>><?= Html::e(AgencyCatalog::STAGES[$stage]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $form === 'stage' ? $fieldError('stage') : '' ?>
                            </div>
                            <div class="field">
                                <label for="stage-note"><span>Commentaire (facultatif)</span></label>
                                <input class="input<?= $form === 'stage' ? $invalid('note') : '' ?>" id="stage-note" name="note" type="text" maxlength="300" value="<?= $oldValue('stage', 'note') ?>">
                                <?= $form === 'stage' ? $fieldError('note') : '' ?>
                            </div>
                            <p class="muted">Gagné, Projet en cours et Livré suivent automatiquement les devis et les projets.</p>
                            <button class="btn btn-secondary" type="submit">Mettre à jour</button>
                        </form>
                    <?php endif; ?>
                    <form class="console-action agency-action-tight" data-modal-form="Noter un échange" data-modal-title="Noter un échange" data-modal-kicker="Agence" method="post" action="<?= Html::url('/agence/prospects/' . $prospect->id() . '/note') ?>" novalidate>
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                        <h3>Noter un échange</h3>
                        <div class="field">
                            <label for="note"><span>Note</span></label>
                            <textarea class="input<?= $form === 'note' ? $invalid('note') : '' ?>" id="note" name="note" rows="2" maxlength="500" placeholder="Appel, rendez-vous, objection…"><?= $oldValue('note', 'note') ?></textarea>
                            <?= $form === 'note' ? $fieldError('note') : '' ?>
                        </div>
                        <div class="field">
                            <label for="next_action_on"><span>Prochaine relance</span></label>
                            <input class="input<?= $form === 'note' ? $invalid('next_action_on') : '' ?>" id="next_action_on" name="next_action_on" type="date" value="<?= $oldValue('note', 'next_action_on', $next ?? '') ?>">
                            <?= $form === 'note' ? $fieldError('next_action_on') : '' ?>
                        </div>
                        <button class="btn btn-primary" type="submit">Enregistrer</button>
                    </form>
                </article>
            <?php endif; ?>

            <article class="dash-card" aria-labelledby="history-title">
                <div class="dash-card-head"><h2 id="history-title">Historique</h2></div>
                <?php if ($events === []): ?>
                    <p class="muted dash-empty">Aucun échange enregistré.</p>
                <?php else: ?>
                    <ol class="timeline console-timeline">
                        <?php foreach ($events as $event): ?>
                            <?php $kind = (string) ($event['kind'] ?? ''); ?>
                            <li>
                                <strong><?= Html::e($eventLabels[$kind] ?? $kind) ?></strong>
                                <span class="muted">· <?= $date((string) $event['created_at']) ?> · <?= Html::e((string) ($event['actor_name'] ?? 'Compte supprimé')) ?></span>
                                <br><span class="agency-event-body"><?= Html::e((string) $event['body']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </article>
        </div>
    </div>
</section>
