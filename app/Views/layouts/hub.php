<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\User;

$actor = $actor ?? null;
$actorName = $actor instanceof User ? $actor->fullName() : '';
$csrf = is_string($csrf ?? null) ? $csrf : '';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? 'SESAM') ?></title>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/sesam.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/activities.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/language.css') ?>">
</head>
<body class="hub-body">
<header class="hub-top">
    <a class="hub-brand" href="<?= Html::url('/mes-activites') ?>">
        <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="" width="40" height="40">
        <span>SESAM</span>
    </a>
    <div class="hub-who">
        <?php $langSwitchClass = 'lang-switch--dark'; include dirname(__DIR__) . '/partials/language-switch.php'; ?>
        <a class="hub-profile" href="<?= Html::url('/profil') ?>" title="Mon profil">
            <span class="hub-avatar" aria-hidden="true"><?= Html::e(Html::initials($actorName !== '' ? $actorName : 'S')) ?></span>
            <span class="hub-name"><?= Html::e($actorName) ?></span>
        </a>
        <form method="post" action="<?= Html::url('/deconnexion') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf) ?>">
            <button class="hub-logout" type="submit">
                <?php $name = 'logout'; include dirname(__DIR__) . '/partials/ui-icon.php'; ?>
                <span>Se déconnecter</span>
            </button>
        </form>
    </div>
</header>
<main class="hub-main">
    <?= $content ?? '' ?>
</main>
<?php include dirname(__DIR__) . '/partials/gtranslate.php'; ?>
</body>
</html>
