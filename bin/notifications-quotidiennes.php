<?php

declare(strict_types=1);

/*
 * Rappels quotidiens SESAM (notifications dans l'application et sur les appareils) :
 * programme du salon, échéances et retards de loyer, fins de contrat de bail, essais et abonnements,
 * synthèses pour le personnel SESAM. Chaque rappel n'est envoyé qu'une fois.
 *
 * À lancer chaque matin vers 7 h par le planificateur de tâches :
 *   php bin/notifications-quotidiennes.php
 * Sans tâche planifiée, la première visite connectée de la journée les déclenche.
 */

use Sesam\Core\Env;
use Sesam\Services\DailyNotifications;
use Sesam\Services\Notifier;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
/** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
$config = require $root . '/config/app.php';
$firebase = require $root . '/config/firebase.php';
$logger = new Sesam\Core\Logger\Logger($root . '/storage/logs/app.log');

$notifier = new Notifier($config['db'], $firebase, $logger);
Notifier::setCurrent($notifier);
$jobs = new DailyNotifications($config['db'], $notifier, $firebase['cache_dir'] . DIRECTORY_SEPARATOR . 'daily-notifications.txt');
$today = gmdate('Y-m-d');
$counts = $jobs->run($today);
@file_put_contents($firebase['cache_dir'] . DIRECTORY_SEPARATOR . 'daily-notifications.txt', $today);

foreach ($counts as $name => $count) {
    echo str_pad($name, 20) . ' : ' . $count . " notification(s)\n";
}
echo 'Envoi push : ' . ($notifier->isLive() ? 'Firebase' : 'journal (PUSH_DRIVER=log)') . "\n";
