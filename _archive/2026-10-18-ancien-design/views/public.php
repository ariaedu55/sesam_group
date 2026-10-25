<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$current = is_string($current ?? null) ? $current : '';
$links = [
    'contact' => ['/contact', 'Contact'],
];
$catalog = require dirname(__DIR__) . '/public/home-catalog.php';
$serviceLinks = $catalog['nav'];
$serviceIcons = [];
foreach ($catalog['services'] as $serviceKey => $serviceItem) {
    $serviceIcons[$serviceKey] = (string) ($serviceItem['icon'] ?? '');
}
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($title ?? 'SESAM') ?></title>
    <link rel="stylesheet" href="<?= Html::asset('assets/css/sesam.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/public.css') ?>">
    <link rel="stylesheet" href="<?= Html::asset('assets/css/public-glass.css') ?>">
</head>
<body class="site">
<div class="site-ambient" aria-hidden="true">
    <span></span>
    <span></span>
    <span></span>
</div>
<header class="site-header">
    <div class="sh-bar">
        <a class="sh-brand" href="<?= Html::url('/') ?>" aria-label="SESAM, accueil">
            <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="SESAM" width="180" height="180">
        </a>
        <nav class="sh-nav" id="site-nav" data-nav aria-label="Pages du site">
            <ul class="sh-links">
                <?php foreach ($serviceLinks as $serviceKey => $serviceLabel): ?>
                    <li>
                        <a href="<?= Html::url('/services/' . $serviceKey) ?>"<?= $current === 'service-' . $serviceKey ? ' aria-current="page"' : '' ?>>
                            <span class="sh-ico" aria-hidden="true"><?php $name = $serviceIcons[$serviceKey]; include $icon; ?></span>
                            <span><?= Html::e($serviceLabel) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php foreach ($links as $key => [$path, $label]): ?>
                    <li>
                        <a href="<?= Html::url($path) ?>"<?= $current === $key ? ' aria-current="page"' : '' ?>>
                            <span class="sh-ico" aria-hidden="true"><?php $name = 'chat'; include $icon; ?></span>
                            <span><?= Html::e($label) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="sh-end">
            <a class="btn btn-primary sh-cta" href="<?= Html::url('/devis') ?>">Demander un devis</a>
            <button class="sh-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav" aria-label="Menu">
                <?php $name = 'menu'; include $icon; ?>
            </button>
        </div>
    </div>
</header>
<?php // $content est le HTML déjà produit par une vue du projet. Chaque vue échappe ses propres données. ?>
<?= $content ?>
<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-grid">
            <div class="site-footer-brand">
                <a class="site-footer-logo" href="<?= Html::url('/') ?>" aria-label="SESAM">
                    <img src="<?= Html::asset('assets/images/sesam-logo-360.jpg') ?>" alt="SESAM" width="180" height="180">
                </a>
                <p class="site-footer-kicker">L'innovation à portée de clic</p>
                <p class="site-footer-lead">Sesam Digital crée ou refond votre site. Sesam SaaS y ajoute l'outil de votre métier, en abonnement mensuel. Dakar, suivi depuis New York.</p>
                <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
            </div>
            <nav class="site-footer-col" aria-labelledby="footer-pages">
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
            <nav class="site-footer-col" aria-labelledby="footer-services">
                <h2 id="footer-services">Services</h2>
                <ul>
                    <?php foreach ($serviceLinks as $serviceKey => $serviceLabel): ?>
                        <li><a href="<?= Html::url('/services/' . $serviceKey) ?>"><?= Html::e($serviceLabel) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="site-footer-col">
                <h2 id="footer-pratique">Pratique</h2>
                <ul class="site-footer-facts">
                    <li>Marché : commerces à Dakar</li>
                    <li>Pilotage depuis New York</li>
                    <li>Wave et Orange Money</li>
                    <li>Commandes et rappels WhatsApp</li>
                    <li>Lien public, sans application</li>
                    <li><a href="<?= Html::url('/connexion') ?>">Espace SESAM</a></li>
                </ul>
            </div>
        </div>
        <div class="site-footer-base">
            <p>SESAM</p>
            <p>Un site clair. Des outils simples. <span>Un suivi proche.</span></p>
        </div>
    </div>
</footer>
<script src="<?= Html::asset('assets/js/sesam.js') ?>" defer></script>
</body>
</html>
