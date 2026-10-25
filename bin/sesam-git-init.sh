#!/bin/bash
# Lie le dossier de production au dépôt GitHub (une seule fois).
# Prérequis : le code a déjà été poussé sur origin/main.
set -euo pipefail

APP=/home/jomas/sesam-tech.com
REPO=https://github.com/ariaedu55/sesam_group.git
BRANCH=main

cd "$APP"

if [ -d .git ]; then
  echo "Git déjà initialisé."
  git remote -v
  git status -sb
  exit 0
fi

if [ ! -f .env ]; then
  echo ".env manquant" >&2
  exit 1
fi

cp -a .env /root/sesam-tech.com.env.bak
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
