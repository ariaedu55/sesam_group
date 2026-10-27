<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/**
 * Un filtre principal (période, étape, vue…) sous forme de liste déroulante.
 *
 * @var string $recordFilterFieldId
 * @var string $recordFilterFieldLabel
 * @var string $recordFilterFieldName name du select (soumis avec le formulaire live-filter)
 * @var list<array{value: string, label: string, selected: bool}> $recordFilterOptions
 */

$recordFilterFieldId = is_string($recordFilterFieldId ?? null) ? $recordFilterFieldId : 'record-filter';
$recordFilterFieldLabel = is_string($recordFilterFieldLabel ?? null) ? $recordFilterFieldLabel : 'Filtre';
$recordFilterFieldName = is_string($recordFilterFieldName ?? null) ? $recordFilterFieldName : 'filtre';
$recordFilterOptions = is_array($recordFilterOptions ?? null) ? $recordFilterOptions : [];

if ($recordFilterOptions === []) {
    return;
}

?>
<div class="field record-filter-field">
    <label for="<?= Html::e($recordFilterFieldId) ?>"><span><?= Html::e($recordFilterFieldLabel) ?></span></label>
    <select class="input" id="<?= Html::e($recordFilterFieldId) ?>" name="<?= Html::e($recordFilterFieldName) ?>">
        <?php foreach ($recordFilterOptions as $option): ?>
            <?php
            $value = is_string($option['value'] ?? null) ? $option['value'] : '';
            $label = is_string($option['label'] ?? null) ? $option['label'] : '';
            $selected = (bool) ($option['selected'] ?? false);
            ?>
            <option value="<?= Html::e($value) ?>"<?= $selected ? ' selected' : '' ?>><?= Html::e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
