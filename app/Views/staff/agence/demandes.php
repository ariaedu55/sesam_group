<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Services\Agency\AgencyCatalog;
use Sesam\Services\QuoteService;

$requests = is_array($requests ?? null) ? $requests : [];
$view = (string) ($view ?? 'nouvelles');
$pending = (int) ($pending ?? 0);
$canProspects = (bool) ($canProspects ?? false);
$activities = is_array($activities ?? null) ? $activities : AgencyCatalog::activities();
$agencyTab = 'demandes';
$date = static fn (string $value): string => gmdate('d/m/Y à H:i', (int) strtotime($value . ' UTC'));
$tabs = ['nouvelles' => 'À traiter', 'suivies' => 'Devenues prospects', 'toutes' => 'Toutes'];

?>
<section class="console agency">
    <header class="dash-hero console-hero">
        <div>
            <p class="console-eyebrow">SESAM Digital · sites web</p>
            <h1>Demandes de devis</h1>
            <p>Les projets de site envoyés depuis le formulaire « Devis site web ». Transformez une demande en prospect pour préparer son devis.</p>
        </div>
    </header>

    <?php require __DIR__ . '/_nav.php'; ?>

    <nav class="btq-tabs" aria-label="Filtrer les demandes">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= Html::url('/agence/demandes' . ($key === 'nouvelles' ? '' : '?vue=' . $key)) ?>"<?= $view === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?><?= $key === 'nouvelles' && $pending > 0 ? ' <span>' . $pending . '</span>' : '' ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($requests === []): ?>
        <div class="dash-card"><p class="muted"><?= $view === 'nouvelles' ? 'Aucune demande à traiter.' : 'Aucune demande ici.' ?></p></div>
    <?php else: ?>
        <div class="quote-requests">
            <?php foreach ($requests as $request): ?>
                <article class="dash-card quote-request">
                    <header class="quote-request-head">
                        <div>
                            <h2><?= Html::e((string) $request['full_name']) ?><?= ($request['business_name'] ?? '') !== '' && $request['business_name'] !== null ? ' · ' . Html::e((string) $request['business_name']) : '' ?></h2>
                            <p class="muted"><?= Html::e($activities[$request['activity']] ?? (string) $request['activity']) ?> · reçue le <?= Html::e($date((string) $request['created_at'])) ?></p>
                        </div>
                        <?php if ($request['prospect_id'] !== null): ?>
                            <a class="chip chip-ok" href="<?= Html::url('/agence/prospects/' . (int) $request['prospect_id']) ?>">Prospect · <?= Html::e(AgencyCatalog::STAGES[$request['prospect_stage']] ?? (string) $request['prospect_stage']) ?></a>
                        <?php else: ?>
                            <span class="chip chip-warn">À traiter</span>
                        <?php endif; ?>
                    </header>
                    <dl class="quote-request-facts">
                        <?php if ($request['site_type']): ?><div><dt>Projet</dt><dd><?= Html::e(QuoteService::SITE_TYPES[$request['site_type']] ?? (string) $request['site_type']) ?> · <?= Html::e(mb_strtolower(QuoteService::SITE_STATES[$request['site_state']] ?? (string) $request['site_state'])) ?></dd></div><?php endif; ?>
                        <?php if ($request['budget']): ?><div><dt>Budget</dt><dd><?= Html::e(QuoteService::BUDGETS[$request['budget']] ?? (string) $request['budget']) ?></dd></div><?php endif; ?>
                        <?php if ($request['deadline']): ?><div><dt>Délai</dt><dd><?= Html::e(QuoteService::DEADLINES[$request['deadline']] ?? (string) $request['deadline']) ?></dd></div><?php endif; ?>
                        <?php if ($request['has_domain']): ?><div><dt>Domaine</dt><dd><?= Html::e(QuoteService::DOMAINS[$request['has_domain']] ?? (string) $request['has_domain']) ?></dd></div><?php endif; ?>
                        <?php if ($request['pages']): ?><div class="is-wide"><dt>Pages</dt><dd><?= Html::e((string) $request['pages']) ?></dd></div><?php endif; ?>
                        <?php if ($request['features']): ?><div class="is-wide"><dt>Fonctions</dt><dd><?= Html::e((string) $request['features']) ?></dd></div><?php endif; ?>
                    </dl>
                    <p class="quote-request-message"><?= nl2br(Html::e(trim(explode("\n\nSite :", (string) $request['message'])[0]))) ?></p>
                    <?php if ($request['inspiration']): ?><p class="muted">Exemples aimés : <?= Html::e((string) $request['inspiration']) ?></p><?php endif; ?>
                    <footer class="quote-request-foot">
                        <a class="btn btn-secondary btn-sm" href="tel:<?= Html::e(preg_replace('/[^\d+]/', '', (string) $request['phone']) ?? '') ?>"><?= Html::e((string) $request['phone']) ?></a>
                        <a class="btn btn-secondary btn-sm" href="mailto:<?= Html::e((string) $request['email']) ?>"><?= Html::e((string) $request['email']) ?></a>
                        <?php if ($canProspects && $request['prospect_id'] === null): ?>
                            <form method="post" action="<?= Html::url('/agence/demandes/' . (int) $request['id'] . '/convertir') ?>">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <button class="btn btn-primary btn-sm" type="submit">Suivre comme prospect</button>
                            </form>
                        <?php endif; ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
