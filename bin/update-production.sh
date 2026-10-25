#!/bin/bash
# Mise à jour SESAM sur le VPS : git pull + Composer + migrations + permissions.
# Usage (root ou jomas avec sudo si besoin) :
#   /home/jomas/sesam-tech.com/bin/update-production.sh
set -euo pipefail

APP_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN="${PHP_BIN:-/usr/local/apps/php84/bin/php}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
BRANCH="${SESAM_GIT_BRANCH:-main}"

cd "$APP_ROOT"

if [ ! -d .git ]; then
  echo "Ce dossier n'est pas un dépôt git : $APP_ROOT" >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo "Fichier .env manquant — abandon (ne pas écraser la prod)." >&2
  exit 1
fi

echo "== git fetch / pull ($BRANCH) =="
git fetch origin
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "== Composer =="
if [ -x "$COMPOSER_BIN" ]; then
  "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
else
  echo "Composer introuvable ($COMPOSER_BIN) — étape ignorée." >&2
fi

echo "== Migrations SQL (idempotentes si déjà appliquées : erreurs possibles, on continue) =="
DB_NAME=$(grep -E '^DB_NAME=' .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
if [ -n "${DB_NAME:-}" ] && command -v mysql >/dev/null 2>&1; then
  for f in $(ls database/migrations/*.sql 2>/dev/null | sort); do
    echo "  -> $(basename "$f")"
    mysql "$DB_NAME" < "$f" 2>/dev/null || true
  done
else
  echo "Migrations SQL ignorées (mysql ou DB_NAME indisponible)."
fi

echo "== Permissions =="
chown -R jomas:jomas "$APP_ROOT" 2>/dev/null || true
chmod -R ug+rwX "$APP_ROOT/storage" 2>/dev/null || true
find "$APP_ROOT/storage" -type d -exec chmod 775 {} \; 2>/dev/null || true

# Recharge PHP-FPM si possible (opcache)
if command -v pkill >/dev/null 2>&1; then
  pkill -USR2 -f 'php-fpm: master.*/php84/' 2>/dev/null || true
fi

echo "== OK — SESAM à jour ($BRANCH @ $(git rev-parse --short HEAD)) =="
