#!/bin/bash
# Installe les crons SESAM pour l'utilisateur jomas (Webuzo / sesam-tech.com).
set -euo pipefail
PHP=/usr/local/apps/php84/bin/php
APP=/home/jomas/sesam-tech.com
TMP=$(mktemp)
crontab -u jomas -l 2>/dev/null | grep -v 'sesam-tech.com' | grep -v 'SESAM —' | grep -v '^TZ=Africa/Dakar$' > "$TMP" || true
{
  cat "$TMP"
  echo '# SESAM — rappels et alertes (docs/taches-planifiees.md)'
  # Lancées chaque heure : les scripts n'agissent qu'à 7 h / 8 h heure de Dakar, une fois par jour
  # (le serveur est en Europe/Berlin et cron ignore TZ= pour le planning).
  echo "5 * * * * cd $APP && $PHP bin/notifications-quotidiennes.php --heure=7 >> storage/logs/taches.log 2>&1"
  echo "*/10 * * * * cd $APP && $PHP bin/rappels-clients.php >> storage/logs/taches.log 2>&1"
  echo "15 * * * * cd $APP && $PHP bin/immo-relances.php --heure=8 >> storage/logs/taches.log 2>&1"
} | crontab -u jomas -
rm -f "$TMP"
echo 'Crontab jomas :'
crontab -u jomas -l
