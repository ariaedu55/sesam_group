<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\User;

$actor = $actor ?? null;
$cards = is_array($cards ?? null) ? $cards : [];
$status = is_string($status ?? null) ? $status : '';
$csrf = is_string($csrf ?? null) ? $csrf : '';
$openCount = count(array_filter($cards, static fn (array $c): bool => empty($c['locked'])));
$firstName = $actor instanceof User ? (explode(' ', trim($actor->fullName()))[0] ?? '') : '';

/**
 * Pastille d'abonnement d'une activité : essai, actif, échu, bloqué.
 *
 * @param array<string, mixed> $access
 * @return array{0: string, 1: string}
 */
$badge = static function (array $access): array {
    $state = (string) ($access['state'] ?? 'none');
    $days = $access['days_left'] ?? null;
    $end = is_string($access['period_end'] ?? null) ? gmdate('d/m/Y', (int) strtotime($access['period_end'] . ' UTC')) : '';
    $blocked = (bool) ($access['blocked'] ?? false);

    return match (true) {
        $state === 'trial' => ['Essai gratuit · ' . max(0, (int) $days) . ' j restants', is_int($days) && $days <= 7 ? 'warning' : 'info'],
        $state === 'active' => ['Abonnement actif' . ($end !== '' ? ' · jusqu\'au ' . $end : ''), 'success'],
        $blocked => [(string) ($access['label'] ?? 'À régler'), 'danger'],
        $state === 'overdue' => ['Échu depuis le ' . $end, 'warning'],
        default => [(string) ($access['label'] ?? 'Sans abonnement'), 'muted'],
    };
};

?>
<section class="hub-hero">
    <p class="hub-kicker">Mes abonnements SESAM</p>
    <h1>Bonjour<?= $firstName !== '' ? ' ' . Html::e($firstName) : '' ?>, que souhaitez-vous ouvrir ?</h1>
    <p class="hub-lead">Votre compte donne accès à <strong><?= $openCount ?> activités</strong>. Choisissez celle sur laquelle vous voulez travailler. Vous pourrez revenir ici à tout moment avec le bouton « Mes activités ».</p>
</section>

<?php if ($status !== ''): ?>
    <p class="hub-alert" role="status"><?= Html::e($status) ?></p>
<?php endif; ?>

<ul class="hub-grid" role="list">
    <?php foreach ($cards as $card): ?>
        <?php
        [$badgeText, $badgeTone] = $badge(is_array($card['access'] ?? null) ? $card['access'] : []);
        $open = (bool) $card['open'];
        $blocked = (bool) (($card['access']['blocked'] ?? false));
        ?>
        <?php if (!empty($card['locked'])): ?>
        <li class="hub-card is-locked" data-tone="<?= Html::e($card['tone']) ?>" aria-disabled="true">
            <div class="hub-card-media">
                <img src="<?= Html::asset($card['image']) ?>" alt="" loading="lazy">
                <span class="hub-card-chip">
                    <?php $name = $card['icon']; include dirname(__DIR__, 2) . '/partials/ui-icon.php'; ?>
                    <?= Html::e($card['product']) ?>
                </span>
                <span class="hub-card-lock" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                </span>
            </div>
            <div class="hub-card-body">
                <h2><?= Html::e($card['name']) ?></h2>
                <p class="hub-card-pitch"><?= Html::e($card['pitch']) ?></p>
                <p class="hub-card-closed hub-card-locked-note">Accès non autorisé. Demandez à votre responsable de vous l'ouvrir.</p>
            </div>
        </li>
        <?php continue; endif; ?>
        <li class="hub-card<?= $open ? '' : ' is-closed' ?><?= $card['current'] ? ' is-current' : '' ?>" data-tone="<?= Html::e($card['tone']) ?>">
            <div class="hub-card-media">
                <img src="<?= Html::asset($card['image']) ?>" alt="" loading="lazy">
                <span class="hub-card-chip">
                    <?php $name = $card['icon']; include dirname(__DIR__, 2) . '/partials/ui-icon.php'; ?>
                    <?= Html::e($card['product']) ?>
                </span>
                <?php if ($card['current']): ?>
                    <span class="hub-card-current">Ouverte</span>
                <?php endif; ?>
            </div>
            <div class="hub-card-body">
                <h2><?= Html::e($card['name']) ?></h2>
                <p class="hub-card-pitch"><?= Html::e($card['pitch']) ?></p>
                <p class="hub-card-meta">
                    <span class="hub-pill" data-tone="<?= Html::e($open ? $badgeTone : 'danger') ?>"><?= Html::e($open ? $badgeText : 'Accès suspendu') ?></span>
                    <span class="hub-card-link">sesam-tech.com/t/<?= Html::e($card['slug']) ?></span>
                </p>
                <?php if ($open): ?>
                    <form method="post" action="<?= Html::url('/mes-activites/' . $card['id']) ?>">
                        <input type="hidden" name="_csrf" value="<?= Html::e($csrf) ?>">
                        <button class="hub-card-cta" type="submit">
                            <span><?= $blocked ? 'Régler l\'abonnement' : 'Ouvrir ' . Html::e($card['product']) ?></span>
                            <?php $name = 'arrow'; include dirname(__DIR__, 2) . '/partials/ui-icon.php'; ?>
                        </button>
                    </form>
                <?php else: ?>
                    <p class="hub-card-closed">Contactez l'équipe SESAM pour réactiver cette activité.</p>
                <?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<p class="hub-foot">Besoin d'une autre activité SESAM ? <a href="<?= Html::url('/contact') ?>">Parlez-en à l'équipe</a>, elle sera ajoutée à ce même compte.</p>
