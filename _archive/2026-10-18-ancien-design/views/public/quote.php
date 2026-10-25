<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$value = static function (string $key) use ($old): string {
    $item = $old[$key] ?? '';

    return Html::e(is_scalar($item) ? (string) $item : '');
};
$activity = is_scalar($old['activity'] ?? null) ? (string) $old['activity'] : '';
/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';
$activities = $catalog['activities'];
$landing = $catalog['landing'];

?>
<main class="page-sheet">
    <header class="services-intro">
        <p class="landing-kicker"><?= Html::e($landing['signature']) ?></p>
        <h1 class="landing-title">Décrivez le besoin. <span>Site, outil, ou les deux.</span></h1>
        <p class="lead">Choisissez votre activité. Salon, boutique, tailleur ou propriétaire : chaque demande est enregistrée pour préparer le devis.</p>
    </header>
    <form class="quote-form glass-card" method="post" action="<?= Html::url('/devis') ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="full_name">Nom</label>
            <input class="input<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>" id="full_name" name="full_name" type="text" required maxlength="120" value="<?= $value('full_name') ?>"<?= isset($errors['full_name']) ? ' aria-invalid="true" aria-describedby="full_name-error"' : '' ?>>
            <?php if (isset($errors['full_name'])): ?><p class="field-error" id="full_name-error"><?= Html::e($errors['full_name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="email">E-mail</label>
            <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= $value('email') ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p class="field-error" id="email-error"><?= Html::e($errors['email']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="phone">Téléphone</label>
            <input class="input<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="phone" name="phone" type="tel" required maxlength="30" autocomplete="tel" value="<?= $value('phone') ?>"<?= isset($errors['phone']) ? ' aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
            <?php if (isset($errors['phone'])): ?><p class="field-error" id="phone-error"><?= Html::e($errors['phone']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="activity">Activité</label>
            <select class="select<?= isset($errors['activity']) ? ' is-invalid' : '' ?>" id="activity" name="activity" required<?= isset($errors['activity']) ? ' aria-invalid="true" aria-describedby="activity-error"' : '' ?>>
                <option value="">Choisir</option>
                <?php foreach ($activities as $code => $label): ?>
                    <option value="<?= Html::e($code) ?>"<?= $activity === $code ? ' selected' : '' ?>><?= Html::e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['activity'])): ?><p class="field-error" id="activity-error"><?= Html::e($errors['activity']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="message">Besoin</label>
            <textarea class="input<?= isset($errors['message']) ? ' is-invalid' : '' ?>" id="message" name="message" required maxlength="2000" rows="5"<?= isset($errors['message']) ? ' aria-invalid="true" aria-describedby="message-error"' : '' ?>><?= $value('message') ?></textarea>
            <?php if (isset($errors['message'])): ?><p class="field-error" id="message-error"><?= Html::e($errors['message']) ?></p><?php endif; ?>
        </div>
        <button class="btn btn-primary" type="submit">Envoyer la demande</button>
    </form>
</main>
