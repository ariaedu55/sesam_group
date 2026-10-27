<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/**
 * Champ mot de passe avec bouton afficher / masquer.
 *
 * Variables : $id, $name, $label ; optionnelles : $error, $errorId, $autocomplete, $minlength, $value (toujours vide en pratique).
 */
$id = is_string($id ?? null) ? $id : 'password';
$name = is_string($name ?? null) ? $name : 'password';
$label = is_string($label ?? null) ? $label : 'Mot de passe';
$error = is_string($error ?? null) ? $error : null;
$errorId = is_string($errorId ?? null) ? $errorId : $id . '-error';
$autocomplete = is_string($autocomplete ?? null) ? $autocomplete : 'current-password';
$minlength = isset($minlength) && is_numeric($minlength) ? (int) $minlength : null;
$icon = __DIR__ . '/ui-icon.php';
$invalid = $error !== null;

?>
<div class="field">
    <label for="<?= Html::e($id) ?>"><span><?= Html::e($label) ?></span></label>
    <div class="password-field">
        <input
            class="input input--auth signup-input<?= $invalid ? ' is-invalid' : '' ?>"
            id="<?= Html::e($id) ?>"
            name="<?= Html::e($name) ?>"
            type="password"
            required
            maxlength="255"
            autocomplete="<?= Html::e($autocomplete) ?>"
            data-password-input
            <?= $minlength !== null ? ' minlength="' . $minlength . '"' : '' ?>
            <?= $invalid ? ' aria-invalid="true" aria-describedby="' . Html::e($errorId) . '"' : '' ?>
        >
        <button
            type="button"
            class="password-toggle"
            data-password-toggle
            aria-label="Afficher le mot de passe"
            aria-pressed="false"
        >
            <span class="ui-icon" aria-hidden="true"><?php $name = 'eye'; include $icon; ?></span>
            <span class="sr-only">Afficher</span>
        </button>
    </div>
    <?php if ($invalid): ?>
        <p class="field-error" id="<?= Html::e($errorId) ?>"><?= Html::e($error) ?></p>
    <?php endif; ?>
</div>
