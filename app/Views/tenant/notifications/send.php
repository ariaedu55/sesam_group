<?php

declare(strict_types=1);

use Sesam\Core\Messaging\MessageChannel;
use Sesam\Helpers\Html;
use Sesam\Models\MessageTemplate;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$templates = is_array($templates ?? null) ? $templates : [];
$mockMode = (bool) ($mockMode ?? true);
$lastResult = is_string($lastResult ?? null) ? $lastResult : '';

?>
<section>
    <h1>Envoyer une notification</h1>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($mockMode): ?>
        <p class="alert" role="status">E-mail et WhatsApp sont en <strong>mock</strong> : le contenu est journalisé, pas expédié. Passez en <code>NOTIFICATIONS_MODE=live</code> avec SMTP/WhatsApp configurés pour tenter un envoi réel (API non branchée tant que non livrée).</p>
    <?php else: ?>
        <p class="alert" role="status">Mode <strong>live</strong> : sans SMTP/WhatsApp valides, les envois externes échouent explicitement.</p>
    <?php endif; ?>
    <?php if ($lastResult !== ''): ?>
        <p class="alert" role="status"><?= Html::e($lastResult) ?></p>
    <?php endif; ?>
    <form class="stack" method="post" action="<?= Html::url('/notifications/envoyer') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="channel"><span>Canal</span></label>
            <select class="input" id="channel" name="channel" required>
                <option value="<?= MessageChannel::INTERNAL ?>"<?= ($old['channel'] ?? '') === MessageChannel::INTERNAL ? ' selected' : '' ?>>Interne (boîte équipe)</option>
                <option value="<?= MessageChannel::EMAIL ?>"<?= ($old['channel'] ?? '') === MessageChannel::EMAIL ? ' selected' : '' ?>>E-mail</option>
                <option value="<?= MessageChannel::WHATSAPP ?>"<?= ($old['channel'] ?? '') === MessageChannel::WHATSAPP ? ' selected' : '' ?>>WhatsApp</option>
            </select>
            <?php if (isset($errors['channel'])): ?><p class="field-error"><?= Html::e($errors['channel']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="template_slug"><span>Modèle</span></label>
            <select class="input" id="template_slug" name="template_slug" required>
                <option value="">— Choisir —</option>
                <?php foreach ($templates as $template): ?>
                    <?php if (!$template instanceof MessageTemplate) { continue; } ?>
                    <option value="<?= Html::e($template->slug()) ?>" data-channel="<?= Html::e($template->channel()) ?>"<?= ($old['template_slug'] ?? '') === $template->slug() && ($old['channel'] ?? '') === $template->channel() ? ' selected' : '' ?>>
                        <?= Html::e($template->name()) ?> (<?= Html::e($template->channel()) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['template_slug'])): ?><p class="field-error"><?= Html::e($errors['template_slug']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="recipient"><span>Destinataire</span></label>
            <input class="input" id="recipient" name="recipient" required maxlength="190" placeholder="E-mail, téléphone ou libellé interne" value="<?= Html::e((string) ($old['recipient'] ?? '')) ?>">
            <?php if (isset($errors['recipient'])): ?><p class="field-error"><?= Html::e($errors['recipient']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="name"><span>Prénom / nom (variable {{name}})</span></label>
            <input class="input" id="name" name="name" maxlength="120" value="<?= Html::e((string) ($old['name'] ?? '')) ?>">
        </div>
        <div class="field">
            <label for="amount"><span>Montant FCFA (variable {{amount}}, optionnel)</span></label>
            <input class="input" id="amount" name="amount" maxlength="20" value="<?= Html::e((string) ($old['amount'] ?? '')) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Envoyer</button>
    </form>
</section>
