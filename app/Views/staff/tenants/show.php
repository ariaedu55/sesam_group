<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Tenant;
use Sesam\Models\TenantSubscription;

$tenant = $tenant instanceof Tenant ? $tenant : null;
if ($tenant === null) {
    return;
}
$subscription = ($subscription ?? null) instanceof TenantSubscription ? $subscription : null;
$state = is_string($state ?? null) ? $state : 'none';
$owners = is_array($owners ?? null) ? $owners : [];
$events = is_array($events ?? null) ? $events : [];
$userCount = (int) ($userCount ?? 0);
$today = is_string($today ?? null) ? $today : gmdate('Y-m-d');
$old = is_array($subscriptionOld ?? null) ? $subscriptionOld : [];
$errors = is_array($subscriptionErrors ?? null) ? $subscriptionErrors : [];
$canUpdate = (bool) ($canUpdate ?? false);
$canSee = (bool) ($canSeeSubscriptions ?? false);
$canManage = (bool) ($canManageSubscriptions ?? false);
$chip = ['active' => 'chip-ok', 'overdue' => 'chip-warn', 'suspended' => 'chip-off', 'none' => 'chip-muted', 'trial' => 'chip-ok', 'trial_over' => 'chip-warn'];
$actionLabels = ['activate' => 'Activation', 'renew' => 'Renouvellement', 'suspend' => 'Suspension'];
$date = static fn (?string $value): string => $value === null || $value === '' ? '—' : date('d/m/Y', (int) strtotime(substr($value, 0, 10) . ' UTC'));
$oldValue = static function (string $key, string $default = '') use ($old): string {
    $value = $old[$key] ?? $default;

    return Html::e(is_scalar($value) ? (string) $value : $default);
};
$publicUrl = Html::url('/t/' . $tenant->slug());
$end = $subscription?->periodEnd();
$daysLeft = $end !== null ? (int) floor((strtotime($end . ' UTC') - strtotime($today . ' UTC')) / 86400) : null;
$amount = $subscription?->monthlyAmount();
$payments = is_array($payments ?? null) ? $payments : [];
$paidTotal = (int) ($paidTotal ?? 0);
$notes = is_array($notes ?? null) ? $notes : [];
$usage = is_array($usage ?? null) ? $usage : [];
$defaultAmount = (int) ($defaultAmount ?? 0);
$methods = is_array($methods ?? null) ? $methods : [];
$payOld = is_array($paymentOld ?? null) ? $paymentOld : [];
$payErrors = is_array($paymentErrors ?? null) ? $paymentErrors : [];
$noteError = is_string($noteError ?? null) ? $noteError : '';
$payValue = static function (string $key, string $default = '') use ($payOld): string {
    $value = $payOld[$key] ?? $default;

    return Html::e(is_scalar($value) ? (string) $value : $default);
};
$money = static fn (int $value): string => number_format($value, 0, ',', ' ') . ' FCFA';

?>
<section class="console">
    <p class="console-back"><a href="<?= Html::url('/commerces') ?>">← Tous les commerces</a></p>
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">Sesam <?= Html::e($tenant->sectorLabel()) ?></p>
            <h1><?= Html::e($tenant->displayName()) ?></h1>
            <p><?= $tenant->name() !== $tenant->displayName() ? Html::e($tenant->name()) . ' · ' : '' ?>/t/<?= Html::e($tenant->slug()) ?></p>
            <p class="console-hero-chips">
                <span class="chip <?= $tenant->isActive() ? 'chip-ok' : 'chip-off' ?>">Accès <?= $tenant->isActive() ? 'actif' : 'suspendu' ?></span>
                <?php if ($tenant->signupSource() === 'site'): ?><span class="chip chip-muted">Inscrit depuis le site</span><?php endif; ?>
                <?php if ($canSee): ?>
                    <span class="chip <?= $chip[$state] ?? 'chip-muted' ?>">Abonnement <?= Html::e(mb_strtolower(TenantSubscription::label($state))) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="dash-hero-actions">
            <?php if ($canUpdate): ?>
                <a class="dash-cta dash-cta-solid" href="<?= Html::url('/commerces/' . $tenant->id() . '/modifier') ?>" data-modal="Commerces">Modifier la fiche</a>
            <?php endif; ?>
            <?php if ($tenant->isActive()): ?>
                <a class="dash-cta dash-cta-ghost" href="<?= $publicUrl ?>" target="_blank" rel="noopener">Page publique</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($errors !== []): ?>
        <div class="alert alert-strong" role="alert">
            <?php foreach ($errors as $message): ?>
                <p class="field-error"><?= Html::e(is_string($message) ? $message : '') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="console-detail">
        <div class="console-side">
        <?php if ($canSee): ?>
            <article class="dash-card console-sub" aria-labelledby="sub-title">
                <div class="dash-card-head">
                    <h2 id="sub-title">Abonnement</h2>
                    <span class="chip <?= $chip[$state] ?? 'chip-muted' ?>"><?= Html::e(TenantSubscription::label($state)) ?></span>
                </div>
                <?php if ($subscription === null): ?>
                    <p class="muted">Aucun abonnement n'est encore enregistré pour ce commerce.</p>
                <?php else: ?>
                    <dl class="console-facts">
                        <div><dt>Module</dt><dd>Sesam <?= Html::e($tenant->sectorLabel()) ?></dd></div>
                        <div><dt>Période couverte</dt><dd><?= $date($subscription->periodStart()) ?> → <?= $date($end) ?></dd></div>
                        <div>
                            <dt>Échéance</dt>
                            <dd>
                                <?php if ($daysLeft === null): ?>—
                                <?php elseif ($state === 'suspended'): ?>Suspendu
                                <?php elseif ($daysLeft < 0): ?>Échu depuis <?= -$daysLeft ?> jour<?= -$daysLeft > 1 ? 's' : '' ?>
                                <?php elseif ($daysLeft === 0): ?>Aujourd'hui
                                <?php else: ?>Dans <?= $daysLeft ?> jour<?= $daysLeft > 1 ? 's' : '' ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <div><dt>Montant mensuel convenu</dt><dd><?= $amount === null ? 'Non renseigné' : Html::e(number_format($amount, 0, ',', ' ')) . ' FCFA' ?></dd></div>
                    </dl>
                <?php endif; ?>

                <?php if ($canManage): ?>
                    <?php if (in_array($state, ['trial', 'trial_over'], true)): ?>
                        <div class="console-trial">
                            <p><strong><?= $state === 'trial' ? 'Essai gratuit jusqu\'au ' . $date($end) : 'Essai terminé le ' . $date($end) ?></strong><?= $state === 'trial' && $daysLeft !== null ? ' · ' . max(0, $daysLeft) . ' jour(s) restant(s)' : ' · le commerce ne voit plus que sa page Abonnement' ?></p>
                            <form method="post" action="<?= Html::url('/commerces/' . $tenant->id() . '/essai') ?>" class="console-trial-form">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <button class="btn btn-secondary btn-sm" type="submit" name="trial_days" value="15">+ 15 jours d'essai</button>
                                <button class="btn btn-secondary btn-sm" type="submit" name="trial_days" value="30">+ 1 mois d'essai</button>
                            </form>
                            <p class="muted">Pour le passer en payant : enregistrez son premier paiement ci-dessous, ou activez l'abonnement.</p>
                        </div>
                    <?php endif; ?>
                    <?php if (in_array($state, ['none', 'suspended', 'trial', 'trial_over'], true)): ?>
                        <form class="console-action" method="post" action="<?= Html::url('/commerces/' . $tenant->id() . '/abonnement') ?>">
                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                            <input type="hidden" name="action" value="activate">
                            <h3><?= $state === 'none' ? 'Activer l\'abonnement' : 'Réactiver l\'abonnement' ?></h3>
                            <div class="console-action-grid">
                                <div class="field">
                                    <label for="period_start"><span>Début de période</span></label>
                                    <input class="input<?= isset($errors['period_start']) ? ' is-invalid' : '' ?>" id="period_start" name="period_start" type="date" required value="<?= $oldValue('period_start', $today) ?>"<?= isset($errors['period_start']) ? ' aria-invalid="true"' : '' ?>>
                                </div>
                                <div class="field">
                                    <label for="monthly_amount"><span>Montant mensuel (FCFA, facultatif)</span></label>
                                    <input class="input<?= isset($errors['monthly_amount']) ? ' is-invalid' : '' ?>" id="monthly_amount" name="monthly_amount" type="text" inputmode="numeric" maxlength="12" value="<?= $oldValue('monthly_amount', $amount === null ? ($defaultAmount > 0 ? (string) $defaultAmount : '') : (string) $amount) ?>"<?= isset($errors['monthly_amount']) ? ' aria-invalid="true"' : '' ?>>
                                </div>
                            </div>
                            <div class="field">
                                <label for="note-activate"><span>Note interne (facultatif)</span></label>
                                <input class="input" id="note-activate" name="note" type="text" maxlength="180" value="<?= ($old['action'] ?? '') === 'activate' ? $oldValue('note') : '' ?>">
                            </div>
                            <p class="muted">La période dure un mois. L'accès du commerce et sa page publique sont rouverts.</p>
                            <button class="btn btn-primary" type="submit"><?= $state === 'none' ? 'Activer' : 'Réactiver' ?></button>
                        </form>
                    <?php else: ?>
                        <div class="console-action-pair">
                            <form class="console-action" method="post" action="<?= Html::url('/commerces/' . $tenant->id() . '/abonnement') ?>">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <input type="hidden" name="action" value="renew">
                                <h3>Renouveler</h3>
                                <div class="field">
                                    <label for="note-renew"><span>Note interne (facultatif)</span></label>
                                    <input class="input" id="note-renew" name="note" type="text" maxlength="180" placeholder="Ex. paiement reçu par Wave">
                                </div>
                                <p class="muted">Ajoute un mois<?= $state === 'overdue' ? ' à partir d\'aujourd\'hui' : ' après le ' . $date($end) ?>.</p>
                                <button class="btn btn-primary" type="submit">Renouveler d'un mois</button>
                            </form>
                            <form class="console-action console-action-danger" method="post" action="<?= Html::url('/commerces/' . $tenant->id() . '/abonnement') ?>">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <input type="hidden" name="action" value="suspend">
                                <h3>Suspendre</h3>
                                <div class="field">
                                    <label for="note-suspend"><span>Motif (facultatif)</span></label>
                                    <input class="input" id="note-suspend" name="note" type="text" maxlength="180">
                                </div>
                                <p class="muted">Le commerce ne pourra plus se connecter et sa page publique sera fermée.</p>
                                <button class="btn btn-secondary" type="submit">Suspendre l'abonnement</button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        <?php endif; ?>

            <?php if ($canSee): ?>
                <article class="dash-card" id="encaissements" aria-labelledby="pay-title">
                    <div class="dash-card-head">
                        <h2 id="pay-title">Encaissements</h2>
                        <span class="muted">Total reçu : <strong><?= Html::e($money($paidTotal)) ?></strong></span>
                    </div>
                    <?php if ($canManage): ?>
                        <form class="console-pay" method="post" action="<?= Html::url('/commerces/' . $tenant->id() . '/encaissement') ?>">
                            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                            <p class="muted">Enregistrez un paiement reçu : l'accès est prolongé du nombre de mois payés et le commerce est réactivé s'il était suspendu.</p>
                            <?php if ($payErrors !== []): ?>
                                <div class="alert alert-strong" role="alert">
                                    <?php foreach ($payErrors as $message): ?><p class="field-error"><?= Html::e(is_string($message) ? $message : '') ?></p><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="console-pay-grid">
                                <div class="field">
                                    <label for="pay_amount"><span>Montant reçu (FCFA)</span></label>
                                    <input class="input<?= isset($payErrors['amount']) ? ' is-invalid' : '' ?>" id="pay_amount" name="amount" inputmode="numeric" maxlength="12" required value="<?= $payValue('amount', $defaultAmount > 0 ? (string) $defaultAmount : '') ?>">
                                </div>
                                <div class="field">
                                    <label for="pay_months"><span>Mois payés</span></label>
                                    <select class="input<?= isset($payErrors['months']) ? ' is-invalid' : '' ?>" id="pay_months" name="months">
                                        <?php foreach ([1, 2, 3, 6, 12] as $m): ?>
                                            <option value="<?= $m ?>"<?= $payValue('months', '1') === (string) $m ? ' selected' : '' ?>><?= $m ?> mois<?= $defaultAmount > 0 ? ' (' . Html::e($money($defaultAmount * $m)) . ')' : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="pay_date"><span>Date du paiement</span></label>
                                    <input class="input<?= isset($payErrors['paid_on']) ? ' is-invalid' : '' ?>" id="pay_date" name="paid_on" type="date" max="<?= Html::e($today) ?>" required value="<?= $payValue('paid_on', $today) ?>">
                                </div>
                                <div class="field">
                                    <label for="pay_method"><span>Moyen de paiement</span></label>
                                    <select class="input<?= isset($payErrors['method']) ? ' is-invalid' : '' ?>" id="pay_method" name="method">
                                        <?php foreach ($methods as $key => $label): ?>
                                            <option value="<?= Html::e((string) $key) ?>"<?= $payValue('method', 'wave') === $key ? ' selected' : '' ?>><?= Html::e((string) $label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="pay_reference"><span>Référence (facultatif)</span></label>
                                    <input class="input" id="pay_reference" name="reference" maxlength="80" placeholder="N° de transaction" value="<?= $payValue('reference') ?>">
                                </div>
                                <div class="field">
                                    <label for="pay_note"><span>Note (facultatif)</span></label>
                                    <input class="input<?= isset($payErrors['note']) ? ' is-invalid' : '' ?>" id="pay_note" name="note" maxlength="180" value="<?= $payValue('note') ?>">
                                </div>
                            </div>
                            <div><button class="btn btn-primary" type="submit">Enregistrer le paiement</button></div>
                        </form>
                    <?php endif; ?>
                    <?php if ($payments === []): ?>
                        <p class="muted dash-empty">Aucun paiement enregistré pour ce commerce.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table console-payments">
                                <thead><tr><th scope="col">Date</th><th scope="col">Montant</th><th scope="col">Durée</th><th scope="col">Moyen</th><th scope="col">Accès jusqu'au</th></tr></thead>
                                <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= $date((string) $payment['paid_on']) ?></td>
                                        <td><strong><?= Html::e($money((int) $payment['amount'])) ?></strong><?php if ($payment['reference'] !== ''): ?><br><small class="muted"><?= Html::e((string) $payment['reference']) ?></small><?php endif; ?></td>
                                        <td><?= (int) $payment['months'] ?> mois</td>
                                        <td><?= Html::e((string) ($methods[$payment['method']] ?? $payment['method'])) ?></td>
                                        <td><?= $date((string) $payment['period_end']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </div>

        <div class="console-side">
            <article class="dash-card" aria-labelledby="owner-title">
                <div class="dash-card-head"><h2 id="owner-title">Responsable</h2></div>
                <?php if ($owners === []): ?>
                    <p class="muted">Aucun responsable enregistré.</p>
                <?php else: ?>
                    <ul class="console-people">
                        <?php foreach ($owners as $owner): ?>
                            <li>
                                <span class="console-avatar" aria-hidden="true"><?= Html::e(mb_strtoupper(mb_substr((string) $owner['full_name'], 0, 2))) ?></span>
                                <span>
                                    <strong><?= Html::e((string) $owner['full_name']) ?></strong>
                                    <a href="mailto:<?= Html::e((string) $owner['email']) ?>"><?= Html::e((string) $owner['email']) ?></a>
                                    <?php if (!$owner['is_active']): ?><span class="chip chip-muted">Compte désactivé</span><?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <dl class="console-facts">
                    <div><dt>Comptes du commerce</dt><dd><?= $userCount ?></dd></div>
                    <?php if ($tenant->phone() !== ''): ?><div><dt>Téléphone</dt><dd><a href="<?= Html::e(\Sesam\Core\Boutique\WhatsApp::link(\Sesam\Core\Boutique\WhatsApp::normalize($tenant->phone()))) ?>" target="_blank" rel="noopener"><?= Html::e($tenant->phone()) ?> · WhatsApp</a></dd></div><?php endif; ?>
                    <div><dt>Créé le</dt><dd><?= $date($tenant->createdAt()) ?></dd></div>
                    <div><dt>Lien public</dt><dd><a href="<?= $publicUrl ?>" target="_blank" rel="noopener" class="console-link"><?= $publicUrl ?></a></dd></div>
                </dl>
            </article>


            <article class="dash-card" aria-labelledby="usage-title">
                <div class="dash-card-head"><h2 id="usage-title">Utilisation</h2><span class="muted">30 derniers jours</span></div>
                <dl class="console-usage">
                    <div><dt>Clients</dt><dd><?= (int) ($usage['clients'] ?? 0) ?></dd></div>
                    <div><dt><?= Html::e(ucfirst((string) ($usage['activity_label'] ?? 'activité'))) ?></dt><dd><?= (int) ($usage['activity_30d'] ?? 0) ?></dd></div>
                    <div><dt>Comptes</dt><dd><?= $userCount ?></dd></div>
                    <div><dt>Dernière connexion</dt><dd><?= ($usage['last_login'] ?? '') === '' ? 'Jamais' : $date((string) $usage['last_login']) ?></dd></div>
                </dl>
                <?php if (($usage['last_login'] ?? '') === '' || strtotime((string) $usage['last_login']) < strtotime($today . ' -14 days')): ?>
                    <p class="muted console-hint">Peu d'activité : pensez à appeler le commerçant pour l'accompagner.</p>
                <?php endif; ?>
            </article>

            <article class="dash-card" id="notes" aria-labelledby="notes-title">
                <div class="dash-card-head"><h2 id="notes-title">Notes internes</h2></div>
                <?php if ($notes !== []): ?>
                    <ul class="console-notes">
                        <?php foreach ($notes as $note): ?>
                            <li><p><?= Html::e((string) $note['body']) ?></p><small><?= $date((string) $note['created_at']) ?> · <?= Html::e($note['actor'] !== '' ? (string) $note['actor'] : 'Compte supprimé') ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">Aucune note. Consignez ici les appels, demandes et accords avec le commerçant.</p>
                <?php endif; ?>
                <form method="post" action="<?= Html::url('/commerces/' . $tenant->id() . '/notes') ?>">
                    <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                    <div class="field">
                        <label for="note_body"><span>Nouvelle note</span></label>
                        <textarea class="input<?= $noteError !== '' ? ' is-invalid' : '' ?>" id="note_body" name="note_body" rows="3" maxlength="1000" required></textarea>
                        <?php if ($noteError !== ''): ?><p class="field-error"><?= Html::e($noteError) ?></p><?php endif; ?>
                    </div>
                    <button class="btn btn-secondary btn-sm" type="submit">Ajouter la note</button>
                </form>
            </article>

            <?php if ($canSee): ?>
                <article class="dash-card" aria-labelledby="history-title">
                    <div class="dash-card-head"><h2 id="history-title">Historique</h2></div>
                    <?php if ($events === []): ?>
                        <p class="muted dash-empty">Aucun mouvement d'abonnement.</p>
                    <?php else: ?>
                        <ol class="timeline console-timeline">
                            <?php foreach ($events as $event): ?>
                                <?php $action = (string) ($event['action'] ?? ''); ?>
                                <li>
                                    <strong><?= Html::e($actionLabels[$action] ?? $action) ?></strong>
                                    <?php if ($action !== 'suspend' && !empty($event['period_end'])): ?>
                                        <span class="muted">jusqu'au <?= $date((string) $event['period_end']) ?></span>
                                    <?php endif; ?>
                                    <br><span class="muted"><?= $date((string) $event['created_at']) ?> · <?= Html::e((string) ($event['actor_name'] ?? 'Compte supprimé')) ?></span>
                                    <?php if (($event['note'] ?? '') !== ''): ?>
                                        <br><span><?= Html::e((string) $event['note']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </div>
    </div>
</section>
