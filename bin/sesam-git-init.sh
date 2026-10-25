#!/bin/bash
# Lie le dossier de production au dépôt GitHub (une seule fois).
# Prérequis : le code a déjà été poussé sur origin/main.
set -euo pipefail

APP=/home/jomas/sesam-tech.com
# Deploy key SSH (Host github.com-sesam dans /root/.ssh/config)
REPO=git@github.com-sesam:ariaedu55/sesam_group.git
BRANCH=main

cd "$APP"

git config --global --add safe.directory "$APP" 2>/dev/null || true

if [ -d .git ] && git rev-parse --git-dir >/dev/null 2>&1; then
  echo "Git déjà initialisé."
  git remote set-url origin "$REPO" 2>/dev/null || true
  git remote -v
  git status -sb
  exit 0
fi

if [ ! -f .env ]; then
  echo ".env manquant" >&2
  exit 1
fi

cp -a .env /root/sesam-tech.com.env.bak
# Nettoyer un .git incomplet éventuel
rm -rf .git
git init
git remote add origin "$REPO"
git fetch origin
git reset --hard "origin/$BRANCH"
git branch -M "$BRANCH"
git branch --set-upstream-to="origin/$BRANCH" "$BRANCH" 2>/dev/null || true

[ -f .env ] || cp -a /root/sesam-tech.com.env.bak .env
chown -R jomas:jomas "$APP"
chmod 600 .env
chmod +x bin/update-production.sh 2>/dev/null || true

echo "Dépôt lié à $REPO ($BRANCH @ $(git rev-parse --short HEAD))."
echo "Mises à jour suivantes : sesam-update"
