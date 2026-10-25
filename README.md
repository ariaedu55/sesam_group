# sesam_group

SESAM est une plateforme PHP unique avec deux activités : **SESAM Digital** (agence) et **SESAM SaaS** (commerces abonnés, avec trois modules : Réservation, Boutique, Immo).

Dépôt : https://github.com/ariaedu55/sesam_group

### Mise à jour production (VPS)

Sur le serveur, à la racine de l’app (`/home/jomas/sesam-tech.com`) :

```
./bin/update-production.sh
```

Le script fait `git pull` sur `main`, `composer install --no-dev`, tente les migrations SQL, puis ajuste les permissions. Le fichier `.env` n’est jamais versionné.

---

# SESAM — comptes et connexion (environnement local)

Ce fichier explique aussi comment lancer le projet en local, quels comptes existent, comment se connecter à chaque espace et comment en créer de nouveaux. La section comptes décrit l'état réel de la base locale relevé le 2026-10-08. Elle ne s'applique pas à la production.

## 1. Lancer le projet

1. Démarrer MySQL/MariaDB (WAMP). La base s'appelle `sesam`. Les identifiants sont dans `.env` (`DB_USER`, `DB_PASSWORD`).
2. À la racine du projet :

   ```
   composer serve
   ```

   Équivalent : `php -S localhost:8080 -t public public/index.php`. Laisser la fenêtre ouverte. Arrêt : `Ctrl+C`.
3. Ouvrir **http://localhost:8080/**.

L'adresse `http://localhost/projet%20Sesam/` (Apache WAMP) ne doit pas être utilisée. `APP_URL` reste `http://localhost:8080` dans `.env`.

## 2. Principe de connexion

- Il y a **une seule page de connexion** pour tout le monde : **http://localhost:8080/connexion** (e-mail + mot de passe).
- Il n'y a pas d'inscription publique. Tous les comptes sont créés par le personnel SESAM ou par le responsable d'un commerce.
- Après connexion, tout le monde arrive sur **`/espace`**. Ce que l'on voit dépend du compte :
  - compte **sans commerce** (personnel SESAM) : la **console SESAM** (utilisateurs, commerces, agence) ;
  - compte **rattaché à un commerce** : le **tableau de bord du commerce**, avec le menu du module de son secteur.
- Les clients finaux des commerces n'ont **pas de compte**. Ils utilisent le lien public du commerce : **`http://localhost:8080/t/{slug}`**.
- Sécurité : 5 tentatives de connexion par 15 minutes et par adresse IP, puis blocage temporaire. Mot de passe de 12 caractères minimum. Un commerce suspendu ne peut plus se connecter.

## 3. Comptes de démonstration

Créés par `php database/seeds/demo.php`. Relancer ce script réapplique le même mot de passe. Connexion : http://localhost:8080/connexion

**Mot de passe commun : `SesamDemo-2026!`**

### Personnel SESAM

| Rôle | E-mail | Après connexion |
| --- | --- | --- |
| Super Admin | `demo.superadmin@sesam.local` | Console : tableau de bord, revenus, commerces, agence, tarifs, utilisateurs |
| Admin | `demo.admin@sesam.local` | Comme le Super Admin |
| Commercial | `demo.commercial@sesam.local` | Agence : synthèse, prospects, devis |
| Finance | `demo.finance@sesam.local` | Revenus, abonnements (encaissements), agence : synthèse, factures |
| Support | `demo.support@sesam.local` | Connexion, profil, recherche |

### Commerces (un par module, rôle responsable)

| Module | Commerce | E-mail | Lien public (sans compte) |
| --- | --- | --- | --- |
| Réservation | Horizon Démo | `demo.reservation@sesam.local` | http://localhost:8080/t/demo-reservation |
| Boutique | Awa Couture | `demo.boutique@sesam.local` | http://localhost:8080/t/demo-boutique |
| Immo (ex-Loyer) | Immeuble Démo | `demo.loyer@sesam.local` | http://localhost:8080/t/demo-loyer |
| Réservation, inscrit seul en essai | Salon Essai Démo | `demo.essai@sesam.local` | essai gratuit qui se termine dans 5 jours |

### Équipe de chaque commerce (même mot de passe)

| Commerce | Gérant (Manager) | Employé (Staff) |
| --- | --- | --- |
| Horizon Démo (Réservation) | `demo.reservation.gerant@sesam.local` | `demo.reservation.employe@sesam.local` |
| Awa Couture (Boutique) | `demo.boutique.gerant@sesam.local` | `demo.boutique.employe@sesam.local` |
| Immeuble Démo (Immo) | `demo.loyer.gerant@sesam.local` | `demo.loyer.employe@sesam.local` |

Le gérant fait tout sauf gérer les utilisateurs ; l'employé consulte et saisit au quotidien (voir § 4.2).

### Ce que contient la démo

Données fictives (numéros 77 00x xx xx, e-mails `@exemple.sn`), datées autour du jour où le script est lancé. Le script n'ajoute chaque bloc qu'une fois.

- **Horizon Démo (salon)** : 5 prestations, 2 personnes (Aminata, Khady), horaires, 16 clientes avec étiquettes (Fidèle, VIP, Nouvelle cliente) et notes, 30 rendez-vous sur 4 semaines (terminés, absents, annulés, confirmés, à confirmer), acomptes payés en ligne, 3 paiements en attente dont « Solde coupe et coiffage — Oumou Kane ».
- **Awa Couture (boutique)** : 6 produits avec stock et historique, 9 clientes, 12 commandes `BTQ-DEMO-…` (nouvelles, confirmées, livrées, 1 annulée) en plus d'une commande plus ancienne qui ont fait baisser le stock, 4 commandes payées par Wave (simulé), 1 lien en attente (« Commande BTQ-DEMO-009 »).
- **Immeuble Démo (Immo)** : réglages de l'agence et des relances, fiches locataires (pièce d'identité, garant, contact d'urgence), cautions (une non versée), un bail qui se termine dans 40 jours, un paiement partiel avec ses relances, 3 interventions dont une urgente ; 2 biens (Immeuble Liberté 6, Villa Almadies), 5 logements, 5 locataires avec bail, 6 mois d'échéances : loyers payés (Wave simulé ou à la main), 2 mois de retard pour Cheikh Fall, le loyer d'octobre d'Awa Thiaw avec un lien de paiement en attente.
- **Console SESAM** : abonnements encaissés sur plusieurs mois (Immo échu, à relancer), un salon inscrit seul en essai, 2 demandes de devis site web détaillées, revenus, tarifs.
- **Agence** : 8 prospects à toutes les étapes, 5 devis (envoyés, acceptés, refusé), 2 projets (en cours, livré), 4 factures (payées, à payer), 2 demandes du site.
- **Notifications** : 3 par commerce.

### Sesam Immo (ex-Loyer) en bref

- **Accueil** : encaissé du mois sur l'attendu, impayés, taux d'occupation, baux en cours, liste « À traiter » (retards, échéances sous 7 jours, paiements partiels, fins de bail, cautions non versées, interventions urgentes, logements libres), graphique attendu / encaissé sur 6 mois.
- **Loyers** : par mois ou par onglet (à encaisser, en retard, partiels, payés). Sur chaque loyer : encaisser tout ou une partie (espèces, Wave, Orange Money, virement, chèque), lien de paiement en ligne (simulation NabooPay), relance WhatsApp au message modifiable, quittance ou reçu imprimable.
- **Locataires**, **Biens** (logements avec type, pièces, surface, loyer, charges, caution), **Baux** (nouveau locataire saisi sur place, durée, préavis, caution, fin de bail), **Interventions** (priorité, prestataire, coût), **Relances** (règles et journal), **Réglages** (identité sur les quittances, jour d'échéance, délai de grâce, relances avant / le jour / après, message, paiements partiels et minimum).
- Relances automatiques : envoyées à la première ouverture de l'accueil Immo du jour, ou chaque matin par une tâche planifiée : `php bin/immo-relances.php`.

### Inscription en libre-service et abonnement

- Sur le site, **Commencer** (`/commencer`) : service, nom, nom du commerce, WhatsApp, e-mail, mot de passe. Le compte est connecté tout de suite dans son espace, avec **1 mois d'essai gratuit**. Le devis n'existe plus que pour les **sites web** (`/devis`, formulaire détaillé).
- Dans chaque espace commerce, **Abonnement** (`/abonnement`) : état, jours d'essai restants, historique, réabonnement 1 / 3 / 6 / 12 mois payé par Wave ou Orange Money (simulation). À la fin de l'essai (ou 5 jours après une échéance non payée), seule cette page reste ouverte.
- Console SESAM : menu en rubriques (Commerces, Agence, Finance), **Essais en cours**, origine « Inscrits depuis le site », essai de 15 jours ou 1 mois à la création d'un commerce, « + 15 jours / + 1 mois d'essai » sur la fiche, **Demandes de devis** site web, **Statistiques** (MRR, conversion des essais, revenus par module, inscriptions, agence).

### Notifications (cloche et push Firebase)

- **Où** : la cloche en haut à droite de chaque espace (commerces et console SESAM) et la page **Notifications**. Les notifications arrivent aussi sur le téléphone ou l'ordinateur une fois activées : bandeau « Recevoir les alertes sur cet appareil ? » ou **Notifications → Réglages et appareils → Activer sur cet appareil**, puis « Envoyer une notification de test ».
- **Ce qui est notifié** :
  - Réservation : nouvelle réservation en ligne, acompte payé, programme du jour (chaque matin).
  - Boutique : nouvelle commande en ligne, stock bas ou épuisé.
  - Immo : loyer payé en ligne, échéances dans 3 jours, loyers qui passent en retard, fin de contrat de bail sous 30 jours, intervention urgente.
  - Tous les commerces : paiement en ligne reçu, essai et abonnement (J-7, J-3, J-1, fin).
  - Console SESAM : nouvelle inscription, demande de devis site web, abonnement payé en ligne, essais qui se terminent, abonnements échus.
- Chaque compte choisit, catégorie par catégorie, « dans l'appli » et « sur l'appareil ». Un rappel planifié ne part qu'une fois.
- **Rappels du jour** : `php bin/notifications-quotidiennes.php` chaque matin (planificateur de tâches). Sans tâche planifiée, la première visite connectée après 7 h les envoie.
- **Rappels clients** (rendez-vous la veille, ou 3 h avant) : `php bin/rappels-clients.php` toutes les 5 à 15 minutes. Sans tâche planifiée, les visites de l'application et des pages publiques les envoient (au plus toutes les 5 minutes). Les messages WhatsApp restent simulés tant que `NOTIFICATIONS_MODE` n'est pas `live`.
- **Tâches planifiées** : voir [docs/taches-planifiees.md](docs/taches-planifiees.md) (cPanel, crontab, Planificateur Windows via `bin\windows\installer-taches.ps1`). Les messages aux clients partent par WhatsApp, et par e-mail en secours quand WhatsApp ne les remet pas.
- **Configuration** (`.env`) : `PUSH_DRIVER=fcm` (ou `log` pour seulement journaliser), `FIREBASE_*` (valeurs web publiques et clé VAPID), `FIREBASE_CREDENTIALS=storage/firebase/service-account.json` : la clé du compte de service, **secrète**, fermée au web, à ne jamais publier. Les tests n'envoient jamais de vrai push (`PUSH_DRIVER_OVERRIDE=log` dans `phpunit.xml`).
- Les notifications push demandent `https` (ou `localhost`). Sur iPhone, il faut ajouter SESAM à l'écran d'accueil (iOS 16.4 et plus).

### Tester un paiement simulé

Les paiements passent par le bac à sable NabooPay : aucun appel à Wave ou Orange Money, aucun argent ne circule.

1. Se connecter avec un compte de commerce, ouvrir **Paiements** et choisir une ligne **En attente** (ou en créer une avec « Initier un paiement »).
2. Sur la fiche, **Simuler le paiement du client** ouvre l'écran de paiement : choisir Wave ou Orange Money puis **Payer**.
3. La fiche devient un reçu **Paiement reçu** (montant, date, numéro de transaction, historique). Pour un loyer, l'échéance passe aussitôt à « Payé » et sort des retards.

Le client peut aussi payer depuis la page publique : acompte demandé à la réservation sur `/t/demo-reservation`, puis écran de simulation identique.

Ces comptes sont locaux. Ne pas les réutiliser en production.

### 3.1 Autres comptes déjà présents

Personnel SESAM d'origine (console, agence) :

| Compte | Rôle | E-mail | Mot de passe | Accès après connexion |
| --- | --- | --- | --- | --- |
| Équipe SESAM | Super Admin | `equipe@sesam.local` | valeur de `SESAM_STAFF_PASSWORD` dans `.env` | `/espace`, `/utilisateurs`, `/commerces`, `/agence` |

Pour lire le mot de passe : ouvrir `.env` et regarder la ligne `SESAM_STAFF_PASSWORD=`. Il n'est volontairement pas recopié ici.

Pour recréer ou réinitialiser ce compte à partir de `.env` :

```
php database/seeds/staff.php
```

### 3.2 Comptes de commerce

Ces comptes ont tous le rôle **Tenant Owner** (responsable du commerce), sauf mention contraire. Ils se connectent sur `/connexion`, puis arrivent sur le tableau de bord de leur commerce.

| Secteur (module) | Commerce | Slug (lien public) | E-mail | Mot de passe |
| --- | --- | --- | --- | --- |
| Réservation | Horizon Spa (Salon Horizon) | `salon-horizon` | `awa.horizon@example.com` | Information non déterminée dans le projet. |
| Boutique | « Res test » | `p10-23d156` | `p10-cmd-53286a@example.com` | `ValidPass-1234` |
| Loyer | « Inscription test » | `p13-fae9a2` | `p13-loy-abdfab@example.com` | `ValidPass-1234` |
| Réservation | « Res test » | `p10-a2e2f0` | `p10-rdv-6f6eff@example.com` | `ValidPass-1234` |

Remarques :

- Le mot de passe d'`awa.horizon@example.com` n'est pas retrouvable (seul son empreinte est stockée). Pour y accéder : voir « Réinitialiser un mot de passe » (section 6).
- Les commerces dont le slug commence par `p9-`, `p10-` et `p13-` sont des **restes des tests automatisés**. Leurs comptes ont aussi le mot de passe `ValidPass-1234` (par exemple `p9-msg-04d649@example.com`, Réservation ; `p9-staff-msg-036c4d@example.com`, rôle Staff, Réservation). Ils peuvent disparaître ou être supprimés à tout moment. Pour un usage durable, créez vos propres commerces (section 5).
- `ValidPass-1234` est un mot de passe de test, pas un mot de passe de production.
- À ce jour, aucun abonnement n'est configuré sur les commerces (affiché « Non configuré » dans la console).

## 4. Rôles et droits

Un compte reçoit un ou plusieurs rôles. Les droits sont vérifiés côté serveur.

### 4.1 Rôles du personnel SESAM (sans commerce)

| Rôle | Ce qu'il peut faire |
| --- | --- |
| Super Admin | Tout le périmètre SESAM : utilisateurs (créer, modifier, rôles, mot de passe, désactiver), commerces (voir, créer, modifier), abonnements, statistiques, agence (prospects, devis, projets, factures). |
| Admin | Mêmes droits que le Super Admin. |
| Commercial | Agence : voir, prospects, devis. Pas de projets, ni de factures. |
| Finance | Revenus (`/revenus`, export CSV), commerces et abonnements (voir, encaisser), agence : voir, factures. Pas de prospects ni de devis. |
| Support | Aucune permission enregistrée dans la base. |

### 4.2 Rôles d'un commerce

| Rôle | Ce qu'il peut faire |
| --- | --- |
| Tenant Owner (responsable) | Tout dans son commerce : clients, paiements, statistiques, module métier, notifications, paramètres, et gestion des utilisateurs de son commerce. |
| Manager | Comme le responsable, mais sans gestion des utilisateurs ni archivage des clients. |
| Staff | Lecture et saisie courante : voir les clients et ajouter des notes, voir les paiements, agir sur les rendez-vous, commandes de la boutique ou échéances selon le module (sans gérer prestations, produits, stock, paramètres de la boutique ou contrats), voir les paramètres. |

Un compte de commerce ne voit que les données de son commerce. Les comptes de commerce n'ont pas accès à `/commerces` ni à `/agence`. Les comptes du personnel SESAM n'ont pas accès à `/notifications` ni à `/parametres` (réservés aux commerces).

## 5. Comment se connecter à chaque espace

### 5.1 Console SESAM et agence (personnel)

1. Aller sur http://localhost:8080/connexion.
2. Saisir `equipe@sesam.local` et le mot de passe de `.env`.
3. Sur `/espace`, le menu propose : Tableau de bord, Revenus, Commerces, Agence, Tarifs, Utilisateurs, Recherche, Mon profil.
   - **Tableau de bord** (`/espace`) : revenu mensuel récurrent, encaissé du mois, reste à encaisser, commerces actifs, encaissements sur 12 mois, abonnements à relancer (échus ou sous 7 jours), commerces par module, pouls de l'agence et dernières demandes de devis.
   - **Revenus** (`/revenus`) : abonnements et factures agence encaissés par mois, détail d'un mois, échéances des 30 prochains jours, export CSV pour Excel.
   - **Tarifs** (`/tarifs`) : mensualité et frais de mise en place par module, proposés par défaut à l'activation.
   - **Fiche commerce** (`/commerces/{id}`) : enregistrer un paiement (montant, 1 à 12 mois, date, Wave / Orange Money / espèces / virement / chèque, référence) qui prolonge l'accès et réactive le commerce, historique des paiements, utilisation sur 30 jours, notes internes.
   - **Utilisateurs** (`/utilisateurs`) : créer un compte du personnel (nom, e-mail, mot de passe de 12 caractères minimum, rôles).
   - **Commerces** (`/commerces`) : liste, filtres (secteur, accès, abonnement), fiche, création, modification, suspension.
   - **Agence** (`/agence`) : synthèse, prospects, projets, factures. Capacité annoncée : 5 projets web par mois.

### 5.2 Un commerce, selon son module

La connexion est la même pour tous les commerces. Le menu s'adapte au **secteur** du commerce :

| Secteur | Entrées du menu (en plus de Tableau de bord, Clients, Paiements, Statistiques, Notifications, Équipe, Paramètres, Mon profil) | Lien public des clients |
| --- | --- | --- |
| Réservation | Accueil, Agenda, Rendez-vous, Prestations, Équipe, Clients, Paiements, Statistiques, Mon salon (l'entrée « Équipe » des comptes devient « Comptes ») | `/t/{slug}` : prestations, créneaux libres, réservation et acompte |
| Boutique | Accueil (lien à partager), Commandes, Produits, Stock, Clients, Ma boutique (pas d'entrée Paiements ni Paramètres) | `/t/{slug}` : catalogue, panier, commande sur WhatsApp |
| Immo | Loyers, Locataires, Biens, Baux, Interventions, Relances, Réglages | `/t/{slug}` |

Exemples :

- **Boutique** : se connecter avec `demo.boutique@sesam.local`, puis Commandes, Produits, Stock et Ma boutique. Le numéro WhatsApp de la démo est fictif.
- **Immo** : `p13-loy-abdfab@example.com`, puis Loyers, Locataires, Biens, Baux.
- **Réservation** : `demo.reservation@sesam.local` (salon de démonstration avec prestations, équipe et rendez-vous), puis Agenda, Rendez-vous, Prestations, Équipe, Mon salon.

Le lien public d'un commerce s'ouvre sans compte, par exemple http://localhost:8080/t/demo-boutique. Le paiement y passe par le bac à sable local (aucun vrai paiement), car `NABOOPAY_API_KEY` est vide dans `.env`. Les notifications sont en mode `mock` (aucun e-mail ni WhatsApp réel).

### 5.3 Utilisateurs d'un commerce (Manager, Staff)

Le responsable (Tenant Owner) crée les comptes de son équipe depuis **Équipe** (`/utilisateurs`) : nom, e-mail, mot de passe, et rôle Manager ou Staff. L'équipe se connecte ensuite sur la même page `/connexion`.

## 6. Créer des comptes et des commerces

### Créer un commerce (avec son responsable)

1. Se connecter en Super Admin ou Admin.
2. Aller sur **Commerces → Nouveau commerce** (`/commerces/nouveau`).
3. Remplir : nom interne, nom affiché, **slug** (devient le lien public `/t/{slug}`), **secteur** (Réservation, Boutique ou Immo), état (Actif / Suspendu).
4. Remplir le bloc **Responsable du commerce** : nom, e-mail, mot de passe (12 caractères minimum). Ce compte est créé avec le rôle Tenant Owner.
5. Le responsable peut se connecter immédiatement sur `/connexion`.

### Créer un utilisateur du personnel SESAM

1. Se connecter en Super Admin ou Admin.
2. **Utilisateurs → Nouvel utilisateur** (`/utilisateurs/nouveau`), choisir les rôles (Super Admin, Admin, Commercial, Support, Finance).

### Réinitialiser un mot de passe

- Le formulaire « Mot de passe oublié » (`/mot-de-passe`) n'envoie pas d'e-mail en local : il enregistre la demande, et un administrateur SESAM crée le lien.
- Un Super Admin ou un Admin ouvre la fiche de l'utilisateur dans **Utilisateurs** (`/utilisateurs/{id}`), puis :
  - saisit un nouveau mot de passe, ou
  - clique sur **Créer un lien de réinitialisation** (valable une heure, affiché une seule fois).
- Le responsable d'un commerce peut faire de même pour les comptes de son commerce.

### Suspendre ou réactiver un commerce

Fiche du commerce (`/commerces/{id}/modifier`) : état **Suspendu**. Les comptes du commerce ne peuvent plus se connecter tant qu'il est suspendu.

## 7. À savoir

- `.env` n'est pas versionné. Ne jamais y mettre de vrais mots de passe dans un fichier partagé, ni les recopier dans ce README.
- Les mots de passe de test (`ValidPass-1234`) servent uniquement en local. En production, tout compte doit avoir un mot de passe propre, et les commerces de test doivent être supprimés.
- Les tests automatisés créent leurs propres comptes avec le préfixe `p9-`, `p10-`, `p13-`, etc. Commande : `composer test`.
- Pour la structure du projet, la base et la sécurité : voir `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/SECURITY.md` et `docs/TESTS.md`.
