<?php

declare(strict_types=1);

use Sesam\Core\Env;

/*
 * E-mails envoyés par SESAM (mot de passe oublié, bienvenue, rappels par e-mail).
 * MAIL_DRIVER : smtp (envoi réel), log (écrit dans storage/logs/mail.log, rien ne part), off.
 * Le mot de passe SMTP se met dans .env (SMTP_PASSWORD), jamais dans ce fichier.
 */
$override = Env::get('MAIL_DRIVER_OVERRIDE', '');
$driver = strtolower(trim($override !== '' ? $override : Env::get('MAIL_DRIVER', 'log')));
$root = dirname(__DIR__);

return [
    'driver' => in_array($driver, ['smtp', 'log', 'off'], true) ? $driver : 'log',
    'host' => Env::get('SMTP_HOST', ''),
    'port' => (int) Env::get('SMTP_PORT', '465'),
    // ssl : connexion chiffrée dès le départ (port 465) ; tls : STARTTLS (port 587).
    'encryption' => strtolower(Env::get('SMTP_ENCRYPTION', 'ssl')),
    'username' => Env::get('SMTP_USER', ''),
    'password' => Env::get('SMTP_PASSWORD', ''),
    'from' => Env::get('MAIL_FROM', ''),
    'from_name' => Env::get('MAIL_FROM_NAME', 'SESAM'),
    'reply_to' => Env::get('MAIL_REPLY_TO', ''),
    'timeout' => 15,
    'ca_bundle' => $root . '/storage/certs/cacert.pem',
    'log_file' => $root . '/storage/logs/mail.log',
];
