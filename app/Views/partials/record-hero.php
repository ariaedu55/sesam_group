<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var string $heroBackHref */
/** @var string $heroBackLabel */
/** @var string $heroTitle */
/** @var string $heroSubtitle */
/** @var string $heroTone */
/** @var string|null $heroInitials */
/** @var string|null $heroImageSrc */
/** @var string|null $heroImageAlt */
/** @var string|null $heroStatusText */
/** @var string $heroStatusTone */
/** @var list<array{label: string, value: string, hint?: string, alert?: bool}> $heroStats */
/** @var string $heroActions HTML des boutons (optionnel) */

$heroBackHref = is_string($heroBackHref ?? null) ? $heroBackHref : '';
$heroBackLabel = is_string($heroBackLabel ?? null) ? $heroBackLabel : 'Retour';
$heroTitle = is_string($heroTitle ?? null) ? $heroTitle : '';
$heroSubtitle = is_string($heroSubtitle ?? null) ? $heroSubtitle : '';
$heroTone = is_string($heroTone ?? null) && $heroTone !== '' ? $heroTone : 'blue';
$heroInitials = is_string($heroInitials ?? null) && $heroInitials !== '' ? $heroInitials : null;
$heroImageSrc = is_string($heroImageSrc ?? null) && $heroImageSrc !== '' ? $heroImageSrc : null;
$heroImageAlt = is_string($heroImageAlt ?? null) ? $heroImageAlt : '';
$heroStatusText = is_string($heroStatusText ?? null) && $heroStatusText !== '' ? $heroStatusText : null;
$heroStatusTone = is_string($heroStatusTone ?? null) && $heroStatusTone !== '' ? $heroStatusTone : 'muted';
$heroStats = is_array($heroStats ?? null) ? $heroStats : [];
$heroActions = is_string($heroActions ?? null) ? $heroActions : '';
$heroBadgesHtml = is_string($heroBadgesHtml ?? null) ? $heroBadgesHtml : '';
$icon = __DIR__ . '/ui-icon.php';

?>
<header class="record-hero" data-tone="<?= Html::e($heroTone) ?>">
    <?php if ($heroBackHref !== ''): ?>
        <a class="record-back" href="<?= Html::url($heroBackHref) ?>">
            <?php $name = 'arrow-left'; include $icon; ?>
            <?= Html::e($heroBackLabel) ?>
        </a>
    <?php endif; ?>
    <div class="record-hero-main">
        <div class="record-hero-id">
            <?php if ($heroImageSrc !== null): ?>
                <span class="record-avatar is-photo">
                    <img src="<?= Html::e($heroImageSrc) ?>" alt="<?= Html::e($heroImageAlt) ?>" width="72" height="72" loading="lazy" decoding="async">
                </span>
            <?php elseif ($heroInitials !== null): ?>
                <span class="record-avatar" aria-hidden="true"><?= Html::e($heroInitials) ?></span>
            <?php endif; ?>
            <div class="record-hero-copy">
                <h1><?= Html::e($heroTitle) ?></h1>
                <?php if ($heroSubtitle !== ''): ?>
                    <p class="record-subtitle"><?= $heroSubtitle ?></p>
                <?php endif; ?>
                <?php if ($heroStatusText !== null || $heroBadgesHtml !== ''): ?>
                    <div class="record-hero-badges">
                        <?php if ($heroStatusText !== null): ?>
                            <span class="btq-status btq-status-<?= Html::e($heroStatusTone) ?>"><?= Html::e($heroStatusText) ?></span>
                        <?php endif; ?>
                        <?= $heroBadgesHtml ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($heroActions !== ''): ?>
            <div class="record-hero-actions"><?= $heroActions ?></div>
        <?php endif; ?>
    </div>
    <?php if ($heroStats !== []): ?>
        <div class="record-hero-stats" role="list">
            <?php foreach ($heroStats as $stat): ?>
                <?php
                if (!is_array($stat)) {
                    continue;
                }
                $label = is_string($stat['label'] ?? null) ? $stat['label'] : '';
                $value = is_string($stat['value'] ?? null) ? $stat['value'] : '';
                $hint = is_string($stat['hint'] ?? null) ? $stat['hint'] : '';
                $alert = !empty($stat['alert']);
                ?>
                <article class="record-stat<?= $alert ? ' is-alert' : '' ?>" role="listitem">
                    <p><?= Html::e($label) ?></p>
                    <strong><?= Html::e($value) ?></strong>
                    <?php if ($hint !== ''): ?><small><?= Html::e($hint) ?></small><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</header>
