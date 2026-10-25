<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$val = static function (string $key) use ($old): string {
    $value = $old[$key] ?? '';

    return is_scalar($value) ? (string) $value : '';
};

?>
<fieldset>
    <legend>Vos informations</legend>
    <div class="field">
        <label for="full_name"><span>Nom</span></label>
        <input class="input<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>" id="full_name" name="full_name" required maxlength="120" autocomplete="name" value="<?= Html::e($val('full_name')) ?>">
        <?php if (isset($errors['full_name'])): ?><p class="field-error"><?= Html::e($errors['full_name']) ?></p><?php endif; ?>
    </div>
    <div class="field">
        <label for="phone"><span>Téléphone</span></label>
        <input class="input<?= isset($errors['phone']) ? ' is-invalid' : '' ?>" id="phone" name="phone" required maxlength="40" autocomplete="tel" value="<?= Html::e($val('phone')) ?>">
        <?php if (isset($errors['phone'])): ?><p class="field-error"><?= Html::e($errors['phone']) ?></p><?php endif; ?>
    </div>
    <div class="field">
        <label for="email"><span>E-mail (facultatif)</span></label>
        <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= Html::e($val('email')) ?>">
        <?php if (isset($errors['email'])): ?><p class="field-error"><?= Html::e($errors['email']) ?></p><?php endif; ?>
    </div>
</fieldset>
