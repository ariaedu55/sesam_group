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
$channels = [
    MessageChannel::EMAIL => 'E-mail',
    MessageChannel::WHATSAPP => 'WhatsApp',
    MessageChannel::INTERNAL => 'Note interne (visible seulement par votre équipe)',
];
$channelShort = [MessageChannel::EMAIL => 'e-mail', MessageChannel::WHATSAPP => 'WhatsApp', MessageChannel::INTERNAL => 'note interne'];
$selectedChannel = (string) ($old['channel'] ?? MessageChannel::INTERNAL);
// Les modèles d'essai portent un nom technique : on les présente simplement.
$templateName = static function (MessageTemplate $template): string {
    return $template->slug() === 'test_message' ? 'Message d\'essai' : (string) preg_replace('/\s*\((interne|e-mail|WhatsApp)\)$/u', '', $template->name());
};

?>
<section>
    <h1>Envoyer une notification</h1>
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php if ($mockMode): ?>
        <p class="alert" role="status">Les e-mails partent réellement. L'envoi automatique par WhatsApp arrive bientôt : en attendant, un message WhatsApp est seulement enregistré dans l'historique.</p>
    <?php endif; ?>
    <?php if ($lastResult !== ''): ?>
        <p class="alert" role="status"><?= Html::e($lastResult) ?></p>
    <?php endif; ?>
    <form class="stack" method="post" action="<?= Html::url('/notifications/envoyer') ?>" data-notification-send autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="channel"><span>Comment envoyer le message&nbsp;?</span></label>
            <select class="input" id="channel" name="channel" required>
                <?php foreach ($channels as $value => $label): ?>
                    <option value="<?= Html::e($value) ?>"<?= $selectedChannel === $value ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['channel'])): ?><p class="field-error"><?= Html::e($errors['channel']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="template_slug"><span>Message</span></label>
            <select class="input" id="template_slug" name="template_slug" required>
                <option value="">— Choisir un message —</option>
                <?php foreach ($templates as $template): ?>
                    <?php if (!$template instanceof MessageTemplate) { continue; } ?>
                    <option value="<?= Html::e($template->slug()) ?>" data-channel="<?= Html::e($template->channel()) ?>"<?= $template->channel() !== $selectedChannel ? ' hidden disabled' : '' ?><?= ($old['template_slug'] ?? '') === $template->slug() && $selectedChannel === $template->channel() ? ' selected' : '' ?>>
                        <?= Html::e($templateName($template)) ?> (<?= Html::e($channelShort[$template->channel()] ?? $template->channel()) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['template_slug'])): ?><p class="field-error"><?= Html::e($errors['template_slug']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="recipient"><span>À qui&nbsp;?</span></label>
            <input class="input" id="recipient" name="recipient" required maxlength="190" placeholder="E-mail ou numéro WhatsApp du client" value="<?= Html::e((string) ($old['recipient'] ?? '')) ?>">
            <?php if (isset($errors['recipient'])): ?><p class="field-error"><?= Html::e($errors['recipient']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="name"><span>Nom du client <small>(facultatif)</small></span></label>
            <input class="input" id="name" name="name" maxlength="120" autocomplete="off" placeholder="Il apparaît dans le message" value="<?= Html::e((string) ($old['name'] ?? '')) ?>">
        </div>
        <div class="field">
            <label for="amount"><span>Montant en FCFA <small>(facultatif)</small></span></label>
            <input class="input" id="amount" name="amount" maxlength="20" inputmode="numeric" autocomplete="off" placeholder="Pour un message qui parle d'un paiement" value="<?= Html::e((string) ($old['amount'] ?? '')) ?>">
        </div>
        <button class="btn btn-primary" type="submit">Envoyer le message</button>
    </form>
</section>
