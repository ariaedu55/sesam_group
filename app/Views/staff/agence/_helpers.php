<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$money = static fn (int $amount): string => Html::e(number_format($amount, 0, ',', ' ')) . '&nbsp;FCFA';
$date = static fn (?string $value): string => $value === null || $value === '' ? '—' : date('d/m/Y', (int) strtotime(substr($value, 0, 10) . ' UTC'));
$monthLabel = static function (string $month): string {
    $names = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $number = (int) substr($month, 5, 2);

    return ($names[$number] ?? '') . ' ' . substr($month, 0, 4);
};
$stageChip = static fn (string $stage): string => match ($stage) {
    'gagne', 'projet-en-cours', 'livre' => 'chip-ok',
    'devis-envoye', 'negociation' => 'chip-warn',
    'perdu' => 'chip-off',
    default => 'chip-muted',
};
$initials = static fn (string $name): string => Html::e(mb_strtoupper(mb_substr(trim($name) === '' ? '?' : trim($name), 0, 2)));
$errorsList = is_array($errors ?? null) ? $errors : [];
$invalid = static fn (string $key): string => isset($errorsList[$key]) ? ' is-invalid" aria-invalid="true" aria-describedby="' . $key . '-error' : '';
$fieldError = static fn (string $key): string => isset($errorsList[$key])
    ? '<p class="field-error" id="' . $key . '-error">' . Html::e((string) $errorsList[$key]) . '</p>'
    : '';
