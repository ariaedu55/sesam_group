<?php

declare(strict_types=1);

use Sesam\Core\Boutique\Money;
use Sesam\Core\Immo\ImmoCatalog;
use Sesam\Helpers\Html;

$data = is_array($data ?? null) ? $data : [];
$settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
$journal = is_array($data['journal'] ?? null) ? $data['journal'] : [];
$toRemind = is_array($data['toRemind'] ?? null) ? $data['toRemind'] : [];
$canSend = (bool) ($canSend ?? false);
$canSettings = (bool) ($canSettings ?? false);
$after = array_filter(explode(',', (string) ($settings['remind_after_days'] ?? '')));
$rules = [];
if ((int) ($settings['remind_before_days'] ?? 0) > 0) {
    $rules[] = (int) $settings['remind_before_days'] . ' jour(s) avant l\'échéance';
}
if ((int) ($settings['remind_on_due'] ?? 0) === 1) {
    $rules[] = 'le jour de l\'échéance';
}
foreach ($after as $days) {
    $rules[] = (int) $days . ' jour(s) après';
}

?>
<section class="btq immo">
    <header class="btq-head">
        <div>
            <h1>Relances</h1>
            <p class="muted">Les locataires reçoivent un rappel WhatsApp automatique ; vous pouvez aussi relancer à la main depuis chaque loyer.</p>
        </div>
        <?php if ($canSend): ?>
            <form method="post" action="<?= Html::url('/immo/relances/lancer') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <button class="btn btn-primary" type="submit">Envoyer les relances du jour</button>
            </form>
        <?php endif; ?>
    </header>

    <section class="btq-card immo-rules">
        <div>
            <h2>Règles en place</h2>
            <?php if ((int) ($settings['auto_reminders'] ?? 0) !== 1): ?>
                <p><span class="btq-status btq-status-muted">Relances automatiques désactivées</span></p>
            <?php elseif ($rules === []): ?>
                <p class="muted">Aucune règle : rien n'est envoyé automatiquement.</p>
            <?php else: ?>
                <p>Rappel envoyé <?= Html::e(implode(', ', $rules)) ?>. Un loyer est « en retard » <?= (int) ($settings['grace_days'] ?? 0) ?> jour(s) après son échéance.</p>
            <?php endif; ?>
        </div>
        <?php if ($canSettings): ?><a class="btn btn-secondary" href="<?= Html::url('/immo/parametres#relances') ?>">Modifier</a><?php endif; ?>
    </section>

    <div class="btq-two">
        <section class="btq-card">
            <h2>Journal des relances</h2>
            <?php if ($journal === []): ?>
                <p class="muted">Aucune relance envoyée pour l'instant.</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($journal as $item): ?>
                        <li><a href="<?= Html::url('/immo/loyers/' . $item['due_id']) ?>">
                            <span><strong><?= Html::e((string) ($item['full_name'] ?? 'Locataire')) ?></strong><small><?= Html::e(ImmoCatalog::REMINDER_KINDS[$item['kind']] ?? $item['kind']) ?> · loyer de <?= Html::e(ImmoCatalog::monthLabel((string) $item['due_date'])) ?> · <?= Html::e(ImmoCatalog::date((string) $item['created_at'])) ?></small></span>
                            <span class="btq-status btq-status-<?= $item['status'] === 'sent' ? 'success' : 'warning' ?>"><?= $item['status'] === 'sent' ? 'Envoyée' : 'Pas de numéro' ?></span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section class="btq-card">
            <h2>Loyers en retard</h2>
            <?php if ($toRemind === []): ?>
                <p class="muted">Aucun retard.</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($toRemind as $due): ?>
                        <li><a href="<?= Html::url('/immo/loyers/' . $due['id'] . '#relances') ?>">
                            <span><strong><?= Html::e($due['full_name']) ?></strong><small><?= Html::e(ImmoCatalog::monthLabel($due['due_date'])) ?> · <?= (int) $due['days_late'] ?> j · <?= (int) $due['reminder_count'] ?> relance(s)</small></span>
                            <strong><?= Html::e(Money::format($due['remaining'])) ?></strong>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</section>
