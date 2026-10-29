<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$current = is_string($current ?? null) ? $current : '';
$links = [
    'contact' => ['/contact', 'Contact', 'chat'],
    'connexion' => ['/connexion', 'Connexion', 'person'],
];
$catalog = require dirname(__DIR__) . '/public/home-catalog.php';
$serviceLinks = $catalog['nav'];
$serviceIcons = [];
foreach ($catalog['services'] as $serviceKey => $serviceItem) {
    $serviceIcons[$serviceKey] = (string) ($serviceItem['icon'] ?? '');
}
$icon = dirname(__DIR__) . '/partials/ui-icon.php';
// Liens gardés dans la barre sur mobile (2) et tablette (3) ; les autres passent dans le menu.
$quickLinks = ['agence' => 'sh-q', 'boutique' => 'sh-q', 'reservation' => 'sh-q sh-q-tab'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? 'SESAM') ?></title>
    <meta name="description" content="SESAM crée votre site et vous donne les outils pour vendre : boutique en ligne avec commandes WhatsApp, réservation, gestion immobilière. Dakar.">
    <meta name="theme-color" content="#f4f2ec">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/site.css') ?>">
<?php include dirname(__DIR__) . '/partials/phone-input-assets.php'; ?>
</head>
<body class="site">
<a class="sr-only" href="#contenu">Aller au contenu</a>
<header class="site-header">
    <div class="sh-bar">
        <a class="sh-brand" href="<?= Html::url('/') ?>" aria-label="SESAM, accueil">
            <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="" width="180" height="180">
            <span>SESAM</span>
        </a>
        <nav class="sh-quick" aria-label="Accès rapide">
            <?php foreach ($quickLinks as $quickKey => $quickClass): ?>
                <a class="<?= Html::e($quickClass) ?>" href="<?= Html::url('/services/' . $quickKey) ?>"<?= $current === 'service-' . $quickKey ? ' aria-current="page"' : '' ?>><?= Html::e($serviceLinks[$quickKey]) ?></a>
            <?php endforeach; ?>
        </nav>
        <nav class="sh-nav" id="site-nav" data-nav aria-label="Pages du site">
            <ul class="sh-links">
                <?php foreach ($serviceLinks as $serviceKey => $serviceLabel): ?>
                    <li<?= isset($quickLinks[$serviceKey]) ? ' class="' . ($serviceKey === 'reservation' ? 'is-quick-tab' : 'is-quick') . '"' : '' ?>>
                        <a href="<?= Html::url('/services/' . $serviceKey) ?>"<?= $current === 'service-' . $serviceKey ? ' aria-current="page"' : '' ?>>
                            <span class="sh-ico" aria-hidden="true"><?php $name = $serviceIcons[$serviceKey]; include $icon; ?></span>
                            <span><?= Html::e($serviceLabel) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($links as $key => [$path, $label, $linkIcon]): ?>
                    <li<?= $key === 'connexion' ? ' class="is-login"' : '' ?>>
                        <a href="<?= Html::url($path) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>>
                            <span class="sh-ico" aria-hidden="true"><?php $name = $linkIcon; include $icon; ?></span>
                            <span><?= Html::e($label) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="sh-end">
            <a class="sh-login" href="<?= Html::url('/connexion') ?>"<?= $current === 'connexion' ? ' aria-current="page"' : '' ?>>Connexion</a>
            <a class="s-btn s-btn--plain sh-cta" href="<?= Html::url('/commencer') ?>"<?= $current === 'commencer' ? ' aria-current="page"' : '' ?>>Commencer</a>
            <button class="sh-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
                <?php $name = 'menu'; include $icon; ?>
            </button>
        </div>
    </div>
</header>
<div id="contenu">
<?php // $content est le HTML déjà produit par une vue du projet. Chaque vue échappe ses propres données. ?>
<?= $content ?>
</div>
<footer class="site-footer">
    <div class="s-wrap">
        <div class="sf-top">
            <div class="sf-pitch">
                <p class="s-kicker" style="color: var(--accent)">L'innovation à portée de clic</p>
                <p class="s-h3" style="color: #fff">Un site clair. Des outils simples. Un suivi proche.</p>
                <p>Sesam Digital crée ou refond votre site. Sesam SaaS vous donne l'outil de votre métier, en abonnement mensuel. Dakar, suivi depuis New York.</p>
                <a class="s-btn s-btn--lime" href="<?= Html::url('/commencer') ?>">Commencer gratuitement <span class="s-btn-arrow"><?php $name = 'arrow'; include $icon; ?></span></a>
            </div>
            <div class="sf-cols">
                <nav aria-labelledby="footer-services">
                    <h2 id="footer-services">Services</h2>
                    <ul>
                        <?php foreach ($serviceLinks as $serviceKey => $serviceLabel): ?>
                            <li><a href="<?= Html::url('/services/' . $serviceKey) ?>"><?= Html::e($serviceLabel) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                <nav aria-labelledby="footer-pages">
                    <h2 id="footer-pages">Pages</h2>
                    <ul>
                        <?php foreach ([
                            ['/', 'Accueil', 'accueil'],
                            ['/services', 'Services', 'services'],
                            ['/offres', 'Offres', 'offres'],
                            ['/a-propos', 'À propos', 'a-propos'],
                            ['/portfolio', 'Réalisations', 'portfolio'],
                            ['/contact', 'Contact', 'contact'],
                        ] as [$path, $label, $key]): ?>
                            <li><a href="<?= Html::url($path) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>><?= Html::e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                <div>
                    <h2 id="footer-pratique">Pratique</h2>
                    <ul>
                        <li>Commerces à Dakar</li>
                        <li>Wave et Orange Money</li>
                        <li>Commandes et rappels WhatsApp</li>
                        <li>Lien public, sans application</li>
                        <li><a href="<?= Html::url('/connexion') ?>">Connexion</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <p class="sf-word" aria-hidden="true">SESAM</p>
        <div class="sf-base">
            <p>© <?= Html::e(gmdate('Y')) ?> SESAM — Digital &amp; SaaS</p>
            <p>Dakar · New York</p>
        </div>
    </div>
</footer>
<script src="<?= Html::asset('assets/js/sesam.js') ?>" defer></script>
<script src="<?= Html::asset('assets/js/site.js') ?>" defer></script>
<?php require dirname(__DIR__) . '/partials/site-popup.php'; ?>
<?php require dirname(__DIR__) . '/partials/jotform-agent.php'; ?>
</body>
</html>
