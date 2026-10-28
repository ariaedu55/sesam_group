# Tâches planifiées SESAM

Trois scripts envoient les rappels et alertes automatiques. Sans eux, ils ne partent qu'au fil des visites
(rappels du matin à la première connexion de la journée, rappels clients au plus toutes les 5 minutes,
relances de loyer à l'ouverture de l'accueil Immo) : en production, il faut les planifier.

| Script | Rôle | Fréquence |
|---|---|---|
| `bin/notifications-quotidiennes.php` | Rappels du matin : programme du salon, commandes à traiter, échéances et retards de loyer, fins de bail, essais et abonnements, synthèses de l'équipe SESAM, suivi agence | Chaque jour à 7 h |
| `bin/rappels-clients.php` | Rappels aux clients (rendez-vous la veille ou 3 h avant) : WhatsApp, e-mail en secours | Toutes les 10 minutes |
| `bin/immo-relances.php` | Relances automatiques des loyers selon les réglages de chaque espace Immo | Chaque jour à 8 h |

Chaque script peut être relancé sans risque : un rappel n'est jamais envoyé deux fois.
L'heure du serveur doit être celle de Dakar (UTC, sans heure d'été).

## Production : cPanel (hébergement goo-bridge.com)

1. cPanel → **Cron Jobs** (Tâches Cron).
2. « Cron Email » : mettez votre adresse pour recevoir les erreurs, ou laissez vide.
3. Ajoutez les trois tâches ci-dessous. Remplacez `CHEMIN` par le dossier du site sur le serveur
   (ex. `/home/votrecompte/sesam`) et vérifiez le chemin de PHP 8.3 (cPanel → « MultiPHP Manager » ;
   en général `/opt/cpanel/ea-php83/root/usr/bin/php`, sinon `/usr/local/bin/php`).

| Minute | Heure | Jour | Mois | Jour sem. | Commande |
|---|---|---|---|---|---|
| `0` | `7` | `*` | `*` | `*` | `cd CHEMIN && /opt/cpanel/ea-php83/root/usr/bin/php bin/notifications-quotidiennes.php >> storage/logs/taches.log 2>&1` |
| `*/10` | `*` | `*` | `*` | `*` | `cd CHEMIN && /opt/cpanel/ea-php83/root/usr/bin/php bin/rappels-clients.php >> storage/logs/taches.log 2>&1` |
| `0` | `8` | `*` | `*` | `*` | `cd CHEMIN && /opt/cpanel/ea-php83/root/usr/bin/php bin/immo-relances.php >> storage/logs/taches.log 2>&1` |

## Production : sesam-tech.com (Webuzo, VPS)

Application : `/home/jomas/sesam-tech.com` · PHP 8.4 : `/usr/local/apps/php84/bin/php` · crons sous l’utilisateur **jomas** (lecture du `.env`).

Installation ou réparation des crons (en root) :

```bash
ssh root@62.171.190.193 -p 22
bash /home/jomas/sesam-tech.com/bin/install-vps-cron.sh
```

Ou copier le script depuis le dépôt (`bin/install-vps-cron.sh`). Le serveur est à l'heure de Berlin et cron ignore `TZ=` pour le planning : les tâches du matin tournent donc **chaque heure** avec `--heure=7` / `--heure=8` ; le script n'agit qu'à partir de cette heure **à Dakar (UTC)** et une seule fois par jour (fichiers `storage/cache/daily-notifications.txt` et `immo-relances.txt`).

| Minute | Heure | Commande |
|---|---|---|
| `5` | `*` | `notifications-quotidiennes.php --heure=7` |
| `*/10` | `*` | `rappels-clients.php` |
| `15` | `*` | `immo-relances.php --heure=8` |

Journal : `storage/logs/taches.log`.

Le PHP 8.4 de Webuzo n'a pas d'extension cURL fonctionnelle (bibliothèque `libldap-2.4` manquante) : SESAM s'en passe (push, NabooPay et Google utilisent les flux HTTPS natifs de PHP). Réinstaller PHP 8.4 depuis Webuzo réglera l'avertissement « curl.so ».

## Production : serveur Linux classique (crontab)

`crontab -e`, puis :

```
# SESAM — rappels et alertes automatiques
TZ=Africa/Dakar
0 7 * * *     cd /var/www/sesam && php bin/notifications-quotidiennes.php >> storage/logs/taches.log 2>&1
*/10 * * * *  cd /var/www/sesam && php bin/rappels-clients.php >> storage/logs/taches.log 2>&1
0 8 * * *     cd /var/www/sesam && php bin/immo-relances.php >> storage/logs/taches.log 2>&1
```

## Poste de développement Windows (WAMP)

Les tâches sont installées dans le Planificateur de tâches, dossier **SESAM** :

```
powershell -ExecutionPolicy Bypass -File bin\windows\installer-taches.ps1
```

Elles tournent quand la session Windows est ouverte, sans fenêtre (`bin\windows\lancer-masque.vbs`),
et écrivent dans `storage\logs\taches.log`. Pour les retirer :

```
powershell -Command "Unregister-ScheduledTask -TaskPath '\SESAM\' -TaskName * -Confirm:$false"
```

## Vérifier que tout tourne

- `storage/logs/taches.log` : une ligne datée par exécution, suivie du résultat.
- Rappels clients : la ligne indique si WhatsApp est réel ou simulé, et si l'e-mail de secours est actif (`smtp`).
- `php bin/test-email.php` : vérifie la connexion SMTP.
