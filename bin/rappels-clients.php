<?php

declare(strict_types=1);

/*
 * Rappels clients programmés (rendez-vous la veille ou 3 h avant, etc.) : envoie ceux qui sont arrivés à l'heure.
 *
 * À lancer toutes les 5 à 15 minutes par le planificateur de tâches :
 *   php bin/rappels-clients.php
 * Sans tâche planifiée, les visites de l'application et des pages publiques les envoient (au plus toutes les 5 min).
 */

use Sesam\Core\Env;
use Sesam\Core\Tenant\TenantContext;
use Sesam\Services\MessagingService;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
/** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
$config = require $root . '/config/app.php';
$notificationConfig = require $root . '/config/notifications.php';
$logger = new Sesam\Core\Logger\Logger($root . '/storage/logs/app.log');

// E-mail en secours quand WhatsApp ne remet pas un rappel.
Sesam\Core\Mail\Mailer::setCurrent(new Sesam\Core\Mail\Mailer(require $root . '/config/mail.php', $logger));
$messaging = new MessagingService($config['db'], new TenantContext(), $notificationConfig, $logger);
$sent = $messaging->processDueReminders();
echo $sent . ' rappel(s) traité(s) · WhatsApp ' . ($messaging->isMockMode() ? 'simulé (NOTIFICATIONS_MODE=mock)' : 'réel') . ' · e-mail de secours : ' . (Sesam\Core\Mail\Mailer::current()?->driver() ?? 'off') . PHP_EOL;
