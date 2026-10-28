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

// --heure=7 : tâche lancée chaque heure ; elle n'agit qu'à partir de 7 h à Dakar (UTC, sans heure d'été)
// et une seule fois par jour. Le planning ne dépend plus du fuseau horaire du serveur.
$stamp = $firebase['cache_dir'] . DIRECTORY_SEPARATOR . 'daily-notifications.txt';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--heure=(\d{1,2})$/', $arg, $m) === 1) {
        if ((int) gmdate('G') < (int) $m[1] || (is_file($stamp) && trim((string) file_get_contents($stamp)) === gmdate('Y-m-d'))) {
            exit(0);
        }
    }
}
echo '[' . gmdate('Y-m-d H:i') . ' Dakar] rappels du matin' . "
";

$notifier = new Notifier($config['db'], $firebase, $logger);
Notifier::setCurrent($notifier);
$jobs = new DailyNotifications($config['db'], $notifier, $firebase['cache_dir'] . DIRECTORY_SEPARATOR . 'daily-notifications.txt');
$today = gmdate('Y-m-d');
$counts = $jobs->run($today);
@file_put_contents($stamp, $today);

foreach ($counts as $name => $count) {
    echo str_pad($name, 20) . ' : ' . $count . " notification(s)\n";
}
echo 'Envoi push : ' . ($notifier->isLive() ? 'Firebase' : 'journal (PUSH_DRIVER=log)') . "\n";
