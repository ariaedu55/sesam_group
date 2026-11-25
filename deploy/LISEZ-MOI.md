# Mise à jour de sesam-tech.com (sans GitHub)

Deux fichiers à installer sur le serveur :

| Fichier | Contenu |
|---|---|
| `sesam-mise-a-jour.zip` | Les 79 fichiers modifiés ou nouveaux, avec leurs dossiers (pas de `.env`) |
| `sesam-mise-a-jour.sql` | Les nouvelles tables et colonnes de la base de données |

Ordre : **base de données d'abord, fichiers ensuite.** Sinon le site cherchera les nouvelles colonnes avant qu'elles existent.

## 1. Sauvegarder

Dans le panneau de l'hébergeur (Webuzo) :
- **phpMyAdmin** → base de données SESAM → onglet **Exporter** → Exécuter. Gardez le fichier.
- **Gestionnaire de fichiers** → dossier `/home/jomas/sesam-tech.com` → compressez-le (ou téléchargez-le).

## 2. Base de données

phpMyAdmin → base de données SESAM → onglet **Importer** → choisir `sesam-mise-a-jour.sql` → Exécuter.

À faire **une seule fois**. Si phpMyAdmin indique « Duplicate column », la mise à jour est déjà faite : passez à l'étape 3.

## 3. Fichiers

Gestionnaire de fichiers → dossier `/home/jomas/sesam-tech.com` → **Envoyer** `sesam-mise-a-jour.zip` →
clic droit sur le zip → **Extraire** dans ce même dossier, en **remplaçant** les fichiers existants → supprimez ensuite le zip.

Le zip contient les chemins depuis la racine du site (`app/…`, `public/…`, `database/…`) : il doit être extrait
dans `/home/jomas/sesam-tech.com`, pas dans `public/`.

## 4. Vérifier

- https://sesam-tech.com : les drapeaux 🇫🇷 / 🇬🇧 apparaissent en haut à droite.
- https://sesam-tech.com/commencer : la première étape demande « Votre pays ».
- Console → **Tarifs** : chaque service est replié, avec les onglets FCFA / Dollar / Euro. Remplissez les prix en dollars et en euros.

En cas de problème : réimportez la sauvegarde de l'étape 1 et remettez l'ancien dossier.

## Important pour la suite

Le serveur se met normalement à jour depuis GitHub (`bin/update-production.sh`, qui fait `git pull`).
Après cette installation à la main, les fichiers du serveur diffèrent de GitHub : la prochaine mise à jour par
`update-production.sh` risque d'échouer tant que ces changements ne sont pas aussi envoyés sur GitHub
(le fichier `0001-…patch` de ce dossier les contient, prêt à appliquer sur le dépôt).
