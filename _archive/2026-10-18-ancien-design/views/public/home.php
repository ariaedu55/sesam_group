<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array<string, mixed> $catalog */
$catalog = require __DIR__ . '/home-catalog.php';

$asset = static function (string $file, string $alt, int $width, int $height, bool $lazy = true): void {
    echo Html::picture($file, $alt, $width, $height, $lazy);
};

$landing = $catalog['landing'];
$nav = $catalog['nav'];
$services = $catalog['services'];
$icon = dirname(__DIR__) . '/partials/ui-icon.php';

?>
<main>
    <section class="landing">
        <div class="landing-stage">
            <div class="landing-copy">
                <p class="landing-kicker"><?= Html::e($landing['signature']) ?></p>
                <h1 class="landing-title"><?= Html::e($landing['title']) ?>
                    <span><?= Html::e($landing['title_line']) ?></span></h1>
                <p class="lead"><?= Html::e($landing['pitch']) ?></p>
                <p class="landing-actions">
                    <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
                    <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services') ?>">Tous les services</a>
                </p>
            </div>
            <div class="landing-orbit" data-service-nav>
                <svg class="orbit-planet" viewBox="0 0 200 200" aria-hidden="true">
                    <circle class="orbit-planet-disc" cx="100" cy="100" r="78" />
                    <ellipse class="orbit-planet-line" cx="100" cy="100" rx="78" ry="26" />
                    <ellipse class="orbit-planet-line" cx="100" cy="100" rx="26" ry="78" />
                    <circle class="orbit-planet-line" cx="100" cy="100" r="52" />
                    <circle class="orbit-planet-line is-rim" cx="100" cy="100" r="78" />
                    <g class="orbit-planet-ticks">
                        <?php for ($tick = 0; $tick < 24; $tick++): ?>
                            <?php
                            $tickAngle = deg2rad($tick * 15 - 90);
                            $tickInner = $tick % 2 === 0 ? 68 : 72;
                            $tickX1 = 100 + cos($tickAngle) * $tickInner;
                            $tickY1 = 100 + sin($tickAngle) * $tickInner;
                            $tickX2 = 100 + cos($tickAngle) * 78;
                            $tickY2 = 100 + sin($tickAngle) * 78;
                            ?>
                            <line x1="<?= round($tickX1, 2) ?>" y1="<?= round($tickY1, 2) ?>" x2="<?= round($tickX2, 2) ?>"
                                y2="<?= round($tickY2, 2) ?>" />
                        <?php endfor; ?>
                    </g>
                </svg>
                <svg class="orbit-lines" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <line x1="50" y1="50" x2="18" y2="20" />
                    <line x1="50" y1="50" x2="80" y2="16" />
                    <line x1="50" y1="50" x2="74" y2="84" />
                    <line x1="50" y1="50" x2="26" y2="84" />
                </svg>
                <p class="orbit-hub">
                    <strong>SESAM</strong>
                    <span>Agence et SaaS</span>
                </p>
                <?php
                // Quatre services : les pastilles occupent les quatre coins de l'orbite.
                $slots = [1, 2, 4, 5];
                $node = 0;
                foreach ($nav as $key => $label): ?>
                    <?php $service = $services[$key]; ?>
                    <a class="orbit-node orbit-node--<?= $slots[$node] ?? ($node + 1) ?> is-<?= Html::e($service['skin']) ?>"
                        href="<?= Html::url('/services/' . $key) ?>" data-service="<?= Html::e($key) ?>">
                        <span class="orbit-icon" aria-hidden="true">
                            <?php $name = (string) $service['icon'];
                            include $icon; ?>
                        </span>
                        <span class="orbit-node-text">
                            <strong><?= Html::e($label) ?></strong>
                        </span>
                    </a>
                    <?php $node++; endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-trust" aria-label="Repères SESAM">
        <ul>
            <li>
                <strong>5</strong>
                <span>projets web livrés par mois</span>
            </li>
            <li>
                <strong>4</strong>
                <span>offres : site et trois outils métier</span>
            </li>
            <li>
                <strong>2</strong>
                <span>paiements : Wave et Orange Money</span>
            </li>
            <li>
                <strong>1</strong>
                <span>lien à partager, aucune application à installer</span>
            </li>
        </ul>
    </section>

    <section class="home-steps" aria-labelledby="home-steps-title">
        <header>
            <p class="eyebrow">Comment les deux offres se suivent</p>
            <h2 id="home-steps-title">Du site à la gestion de tous les jours</h2>
        </header>
        <ol>
            <?php $stepIcons = ['briefcase', 'gear', 'chart']; ?>
            <?php foreach ($catalog['journey'] as $stepIndex => $step): ?>
                <li>
                    <span class="home-step-icon" aria-hidden="true">
                        <?php $name = $stepIcons[$stepIndex] ?? 'spark';
                        include $icon; ?>
                    </span>
                    <span class="home-step-num"><?= Html::e($step['step']) ?></span>
                    <h3><?= Html::e($step['label']) ?></h3>
                    <p><?= Html::e($step['text']) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="home-bridge">
        <div class="home-bridge-copy">
            <p class="eyebrow">Deux activités, un même suivi</p>
            <h2>Le site se facture au projet. L'outil, au mois.</h2>
            <p>Sesam Digital crée ou refond votre site. Sesam SaaS y ajoute l'outil du métier, en abonnement mensuel.
                Commerces à Dakar, pilotage depuis New York.</p>
        </div>
        <div class="home-bridge-pair">
            <article>
                <p class="home-kicker">Sesam Digital</p>
                <h3>Agence</h3>
                <p>Conception et refonte de sites, pour les salons de beauté, les instituts et les salons de couture. Le
                    site peut être construit entièrement, ou recevoir un outil sur celui qui existe déjà.</p>
            </article>
            <article>
                <p class="home-kicker">Sesam SaaS</p>
                <h3>Trois outils</h3>
                <p>Réservation, Boutique en ligne ou Loyer. Chaque commerce ne voit que ses données, sous son
                    nom. Le client final ouvre un lien : pas de compte, pas d'application.</p>
            </article>
        </div>
    </section>

    <section class="home-picker" aria-labelledby="home-picker-title">
        <header>
            <p class="eyebrow">Votre métier</p>
            <h2 id="home-picker-title">Le site, puis l'outil qui correspond à votre activité</h2>
        </header>
        <ul>
            <?php foreach ($services as $key => $service): ?>
                <li class="home-pick" data-skin="<?= Html::e($service['skin']) ?>">
                    <a href="<?= Html::url('/services/' . $key) ?>">
                        <span class="home-pick-icon" aria-hidden="true">
                            <?php $name = (string) $service['icon'];
                            include $icon; ?>
                        </span>
                        <strong><?= Html::e($nav[$key]) ?></strong>
                        <span class="home-pick-sector"><?= Html::e($service['sector']) ?></span>
                        <span class="home-pick-go" aria-hidden="true">→</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php $index = 0;
    foreach ($services as $key => $service): ?>
        <section class="home-chapter<?= $index % 2 === 1 ? ' is-flip' : '' ?>" data-skin="<?= Html::e($service['skin']) ?>"
            data-service="<?= Html::e($key) ?>" aria-labelledby="home-<?= Html::e($key) ?>">
            <div class="home-chapter-copy">
                <p class="eyebrow"><?= Html::e($service['label']) ?></p>
                <h2 id="home-<?= Html::e($key) ?>"><?= Html::e($service['title']) ?></h2>
                <p class="home-sector"><?= Html::e($service['sector']) ?></p>
                <?php if ($key === 'agence'): ?>
                    <p>Sesam Digital conçoit ou refond votre site, facturé au projet. Le commerce peut faire construire son
                        site, ou brancher un outil interactif sur celui qu'il a déjà.</p>
                <?php else: ?>
                    <p><?= Html::e($service['lead']) ?></p>
                <?php endif; ?>
                <ul class="home-points">
                    <?php foreach ($service['points'] as $point): ?>
                        <li><?= Html::e($point) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($service['public'] !== []): ?>
                    <ol class="home-flow">
                        <?php foreach ($service['public'] as $step): ?>
                            <li><?= Html::e($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
                <?php if ($service['alerts'] !== []): ?>
                    <ul class="home-alerts">
                        <?php foreach ($service['alerts'] as $alert): ?>
                            <li><?= Html::e($alert[0]) ?><span><?= Html::e($alert[1]) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($service['commerce'] !== []): ?>
                    <div class="home-side">
                        <h3><?= Html::e($catalog['common']['commerce_label']) ?></h3>
                        <ul>
                            <?php foreach ($service['commerce'] as $line): ?>
                                <li><?= Html::e($line) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <p class="home-chapter-link">
                    <a href="<?= Html::url('/services/' . $key) ?>">Voir <?= Html::e($nav[$key]) ?></a>
                </p>
            </div>
            <figure class="home-chapter-visual">
                <?php $asset($service['image'][0], $service['image'][1], 960, 720, $index > 0); ?>
                <span class="home-visual-chip" aria-hidden="true">
                    <span class="home-visual-chip-icon">
                        <?php $name = (string) $service['icon'];
                        include $icon; ?>
                    </span>
                    <?= Html::e($nav[$key]) ?>
                </span>
            </figure>
            <?php if ($key === 'agence'): ?>
                <div class="home-prices">
                    <?php foreach ($catalog['offers'] as $offer): ?>
                        <article>
                            <h3><?= Html::e($offer['name']) ?></h3>
                            <p class="home-price"><?= Html::e($offer['price']) ?></p>
                            <p><?= Html::e($offer['description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="home-capacity">5 projets de conception web par mois, pour garantir la qualité de livraison.</p>
            <?php endif; ?>
        </section>
        <?php $index++; endforeach; ?>

    <section class="home-common" aria-labelledby="home-common-title">
        <div class="home-common-inner">
            <p class="eyebrow">Fonctions communes</p>
            <h2 id="home-common-title">Inclus dans chaque outil, pas un service de plus</h2>
            <p><?= Html::e($catalog['common']['whatsapp']) ?> Le paiement se fait par
                <?= Html::e($catalog['common']['wallets'][0]) ?> ou <?= Html::e($catalog['common']['wallets'][1]) ?>, en
                acompte ou en totalité.</p>
            <ul>
                <?php foreach ([
                    ['spark', 'Lien unique', 'Sans compte, sans application à installer'],
                    ['card', 'Wave et Orange Money', 'Acompte ou totalité'],
                    ['bell', 'WhatsApp', "Confirmation, puis rappel avant l'échéance"],
                ] as [$commonIcon, $commonTitle, $commonText]): ?>
                    <li>
                        <span class="home-common-icon" aria-hidden="true">
                            <?php $name = $commonIcon;
                            include $icon; ?>
                        </span>
                        <strong><?= Html::e($commonTitle) ?></strong>
                        <span><?= Html::e($commonText) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>

    <section class="home-finale" aria-labelledby="home-finale-title">
        <div>
            <p class="eyebrow"><?= Html::e($landing['signature']) ?></p>
            <h2 id="home-finale-title">Un site clair. Des outils simples. <span>Un suivi proche.</span></h2>
        </div>
        <p class="landing-actions">
            <a class="btn btn-primary" href="<?= Html::url('/devis') ?>">Demander un devis</a>
            <a class="btn btn-ghost landing-ghost" href="<?= Html::url('/services') ?>">Tous les services</a>
        </p>
    </section>
</main>