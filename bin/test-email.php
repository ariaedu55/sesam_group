<?php

declare(strict_types=1);

/*
 * Test de la configuration e-mail (SMTP) de SESAM.
 *
 *   php bin/test-email.php                       → vérifie seulement la connexion et le mot de passe (rien n'est envoyé)
 *   php bin/test-email.php vous@exemple.com      → vérifie, puis envoie un e-mail de test à cette adresse
 *
 * Le mot de passe n'est jamais affiché.
 */

use Sesam\Core\Env;
use Sesam\Core\Mail\Mailer;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = require $root . '/config/mail.php';
$logger = new Sesam\Core\Logger\Logger($root . '/storage/logs/app.log');

echo 'Pilote       : ' . $config['driver'] . "\n";
echo 'Serveur      : ' . $config['host'] . ':' . $config['port'] . ' (' . $config['encryption'] . ")\n";
echo 'Utilisateur  : ' . $config['username'] . "\n";
echo 'Mot de passe : ' . ($config['password'] !== '' ? 'renseigné (' . strlen($config['password']) . ' caractères)' : 'VIDE — à remplir dans .env (SMTP_PASSWORD)') . "\n";
echo 'Expéditeur   : ' . $config['from_name'] . ' <' . $config['from'] . ">\n\n";

if ($config['driver'] !== 'smtp') {
    echo "MAIL_DRIVER n'est pas « smtp » : aucun e-mail réel ne partira.\n";
    exit(1);
}

$mailer = new Mailer($config, $logger);
$started = microtime(true);
try {
    $mailer->checkConnection();
    echo '✓ Connexion et authentification SMTP réussies (' . round((microtime(true) - $started) * 1000) . " ms).\n";
} catch (Throwable $exception) {
    echo '✗ ' . $exception->getMessage() . "\n";
    exit(1);
}

$to = $argv[1] ?? '';
if ($to === '') {
    echo "Pour envoyer un e-mail de test : php bin/test-email.php votre@adresse.com\n";
    exit(0);
}
try {
    $mailer->sendTemplate(
        $to,
        'Test d\'envoi SESAM',
        'L\'envoi d\'e-mails fonctionne ✓',
        [
            'Cet e-mail confirme que SESAM peut envoyer des messages depuis ' . $config['from'] . '.',
            'Les e-mails « mot de passe oublié », de bienvenue et les rappels par e-mail partiront de cette adresse.',
        ],
        'Ouvrir SESAM',
        rtrim(Env::get('APP_URL', ''), '/') . '/connexion',
        'Envoyé le ' . gmdate('d/m/Y à H:i') . ' (heure de Dakar).',
    );
    echo '✓ E-mail de test envoyé à ' . $to . ". Vérifiez la boîte de réception (et les courriers indésirables).\n";
} catch (Throwable $exception) {
    echo '✗ Envoi refusé : ' . $exception->getMessage() . "\n";
    exit(1);
}
