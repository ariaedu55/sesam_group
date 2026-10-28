<?php

declare(strict_types=1);

/*
 * Relances automatiques des loyers Sesam Immo, pour tous les espaces Immo actifs.
 * À lancer une fois par jour (cron / planificateur de tâches), par exemple à 8 h :
 *   php bin/immo-relances.php
 * Sans tâche planifiée, les relances partent quand même à la première ouverture de l'accueil Immo du jour.
 */

use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Core\Tenant\Sector;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Repositories\TenantRepository;
use Sesam\Services\Immo\ImmoService;
use Sesam\Services\MessagingService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
/** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
$config = require $root . '/config/app.php';
// --heure=8 : tâche lancée chaque heure ; elle n'agit qu'à partir de 8 h à Dakar (UTC) et une fois par jour.
$stamp = $root . '/storage/cache/immo-relances.txt';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--heure=(\d{1,2})$/', $arg, $m) === 1) {
        if ((int) gmdate('G') < (int) $m[1] || (is_file($stamp) && trim((string) file_get_contents($stamp)) === gmdate('Y-m-d'))) {
            exit(0);
        }
    }
}
echo '[' . gmdate('Y-m-d H:i') . ' Dakar] relances des loyers' . "\n";
$notificationConfig = require $root . '/config/notifications.php';

$context = new TenantContext();
$logger = new Sesam\Core\Logger\Logger($root . '/storage/logs/app.log');
// E-mail en secours quand WhatsApp ne remet pas une relance.
Sesam\Core\Mail\Mailer::setCurrent(new Sesam\Core\Mail\Mailer(require $root . '/config/mail.php', $logger));
$messaging = new MessagingService($config['db'], $context, $notificationConfig, $logger);
$immo = new ImmoService($config['db'], $context, null, $messaging);
$tenants = new TenantRepository(Connection::make($config['db']));

$total = 0;
foreach ($tenants->all() as $tenant) {
    if ($tenant->sector() !== Sector::LOYER || !$tenant->isActive()) {
        continue;
    }
    $context->bind($tenant);
    $immo->ensureSchedule($tenant->id());
    $settings = $immo->settingsFor($tenant->id());
    if ((int) $settings['auto_reminders'] !== 1) {
        continue;
    }
    $sent = $immo->runReminders($tenant->id());
    $total += $sent;
    echo $tenant->displayName() . ' : ' . $sent . " relance(s)\n";
}
echo 'Total : ' . $total . " relance(s) envoyée(s)\n";
@file_put_contents($stamp, gmdate('Y-m-d'));
