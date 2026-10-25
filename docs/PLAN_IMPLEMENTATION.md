# Plan complet d'implémentation — SESAM Digital & SESAM SaaS

Transcription intégrale de `Plan_Complet_Implementation_SESAM_Digital_SaaS.pdf` (18 pages).

Ce fichier est une feuille de route. Il ne décrit pas du code, une base ou une interface déjà construits.

Le cahier des charges original est dans le dépôt : `Sesam-Digital-et-SaaS-Cahier-des-charges.pdf`, transcrit dans `docs/CAHIER_DES_CHARGES.md`. Pour le métier vendu (activités, modules, tarifs agence, parcours public), le cahier prime. Pour l'ordre des phases et le détail technique, ce plan prime. Si le plan et le cahier divergent, le PDF concerné fait foi.

## Synchronisation avec le cahier

### Exigences du cahier (à vendre / à construire)

- Deux activités : agence au projet, SaaS en abonnement mensuel.
- Cinq modules sectoriels seulement : Réservation, Commande, Loyer, Inscription, Garde.
- Agence : salons de beauté / instituts (massage, coiffure, lait de beauté, extensions) et salons de couture ; site neuf ou branché sur un site déjà là ; Essentiel dès 200 000 FCFA, Professionnel dès 450 000 FCFA, sur mesure sur devis ; 5 projets web par mois.
- SaaS : marque blanche, multi-tenant, lien public sans compte, Wave et Orange Money, WhatsApp, 3G.
- Lien public, paiements et WhatsApp = fonctions communes, pas un sixième module.
- Console interne Sesam : comptes, abonnements. Pas un service public.
- Articulation : Site → Outil → Gestion quotidienne.
- Marché : Dakar, pilotage New York, français. Signature : « L'innovation à portée de clic ».

### Présent dans ce plan, absent du cahier (proposition / extension)

- QR codes, centre de tickets, segmentation CRM avancée, assistant « Que voulez-vous faire ? » (section 16 du plan).
- Détail métier plus fin que le cahier (personnel, reprogrammation, tissus, classes, lots, etc.) : utile aux phases modules, pas à afficher comme service supplémentaire.
- Identité or / violet du cahier vs charte bleu / cyan du logo transmis : l'interface suit le bleu jusqu'à arbitrage client.

Un caractère nul, non récupéré par l'extraction, suit « Bonjour Fatou » dans l'exemple de dashboard. C'était probablement un pictogramme. Il n'a pas été inventé ici.

Architecture PHP MVC • MySQL • Multi-tenant • Modules métiers • Paiements • WhatsApp • UX accessible • Design futuriste.

## Objectif

Construire progressivement une plateforme numérique professionnelle permettant à SESAM de gérer son activité d'agence et son offre SaaS multi-secteurs.

## Stack cible

PHP MVC, PDO, MySQL/phpMyAdmin, Composer, HTML5, CSS3, JavaScript moderne, AJAX/Fetch, PHPUnit, APIs de paiement et WhatsApp.

## Principe de développement

Une phase à la fois. Analyse → implémentation → test → vérification visuelle → correction → documentation → arrêt.

## Référence fonctionnelle

Le cahier des charges fourni décrit SESAM Digital comme une maison mère regroupant une activité d'agence et une activité SaaS multi-secteurs. Les secteurs SaaS sont Réservation, Commande, Loyer, Inscription et Garde. Le SaaS doit fonctionner en multi-tenant, avec espace public sans compte, paiements Wave/Orange Money, notifications WhatsApp et fonctionnement en connexion faible.

Document préparé pour servir de feuille de route et de base de prompts d'implémentation dans Cursor.

## 1. Vision globale du produit

SESAM doit être conçu comme un écosystème unique plutôt que comme cinq applications indépendantes. Le cœur commun porte l'authentification, les tenants, les utilisateurs, les permissions, le CRM, les paiements, les notifications, les abonnements, les statistiques et la journalisation. Les modules sectoriels ajoutent ensuite leurs règles métier.

```
SESAM DIGITAL
│
├── SESAM DIGITAL — AGENCE
│   ├── Prospects / CRM
│   ├── Devis / Projets
│   ├── Clients
│   ├── Factures / Paiements
│   └── Sites livrés
│
└── SESAM SAAS
    ├── Cœur multi-tenant
    ├── CRM / Clients
    ├── Paiements
    ├── Notifications / WhatsApp
    ├── Abonnements
    └── Modules
        ├── Réservation
        ├── Commande
        ├── Loyer
        ├── Inscription
        └── Garde
```

### Principe architectural

- 80 % du moteur doit rester commun aux cinq déclinaisons.
- Chaque commerce possède un espace isolé par tenant et ne voit que ses propres données.
- Le portail public permet au client final de réserver, commander ou effectuer un parcours métier sans créer de compte.
- Les intégrations externes doivent être encapsulées derrière des services/adaptateurs pour pouvoir être remplacées ou étendues.
- Le design futuriste doit rester lisible, accessible et performant sur mobile et connexion faible.

## 2. Périmètre issu du cahier des charges

### SESAM Digital — Agence

- Conception et refonte de sites web facturées au projet.
- Cibles : salons de beauté/instituts et salons de couture.
- Offres : Essentiel à partir de 200 000 FCFA ; Professionnel à partir de 450 000 FCFA ; Sur mesure sur devis.
- Capacité annoncée : 5 projets de conception web par mois.
- Les cliniques et établissements de santé sont volontairement exclus du démarchage.

### SESAM SaaS

- Plateforme en marque blanche et multi-secteurs, vendue par abonnement mensuel.
- Chaque commerce possède un espace cloisonné.
- Réservation : prestations, créneaux, acompte.
- Commande : commandes sur mesure, mesures, livraison.
- Loyer : biens, échéances, rappels.
- Inscription : inscriptions et tranches de frais.
- Garde : tour de garde, stock et alertes de péremption.
- Lien public unique par commerce, sans compte ni application à installer.
- Paiement d'acompte ou total via Wave et Orange Money.
- Confirmation automatique et rappels via WhatsApp.
- Console interne SESAM pour les comptes, comptes actifs et abonnements.

Important : le cahier fourni mentionne une identité « D » or/violet, tandis que le logo transmis avec la demande présente une identité SESAM TECH dominée par le bleu, le bleu électrique et le cyan. Le plan de design ci-dessous utilise le logo transmis comme référence visuelle actuelle ; la charte finale devra être validée par le client.

## 3. Architecture technique recommandée

- PHP 8.x avec architecture MVC personnalisée.
- MySQL/MariaDB administrable via phpMyAdmin.
- PDO et requêtes préparées.
- Composer pour la gestion des dépendances.
- HTML5, CSS3 et JavaScript moderne sans dépendance front lourde obligatoire.
- AJAX / Fetch API pour les interactions dynamiques.
- PHPUnit pour les tests.
- Cron Jobs pour rappels, tâches périodiques et traitements asynchrones simples.
- APIs Wave, Orange Money et WhatsApp Business derrière des services dédiés.

### Arborescence cible

```
sesam/
├── app/
│   ├── Core/
│   │   ├── Database/
│   │   ├── Auth/
│   │   ├── Tenant/
│   │   ├── Router/
│   │   ├── Request/
│   │   ├── Response/
│   │   ├── Session/
│   │   ├── Validation/
│   │   ├── Security/
│   │   ├── Cache/
│   │   └── Logger/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Repositories/
│   ├── Middleware/
│   ├── Helpers/
│   ├── Modules/
│   │   ├── Agency/
│   │   ├── Reservation/
│   │   ├── Commande/
│   │   ├── Loyer/
│   │   ├── Inscription/
│   │   └── Garde/
│   └── Views/
│       ├── layouts/
│       ├── components/
│       ├── public/
│       ├── admin/
│       └── tenant/
├── config/
├── database/
│   ├── migrations/
│   ├── seeds/
│   └── backups/
├── public/
│   ├── index.php
│   └── assets/
├── storage/
│   ├── logs/
│   ├── uploads/
│   ├── invoices/
│   └── exports/
├── tests/
├── docs/
├── composer.json
└── .env
```

## 4. Architecture multi-tenant — priorité critique

Le cloisonnement strict des données est une exigence centrale. La solution recommandée est une base partagée avec un `tenant_id` sur les principales tables métier, accompagnée d'un TenantContext et de contrôles systématiques.

Référentiels cités : `tenants`, `users`, `roles`, `permissions`, `subscriptions`, `tenant_settings`.

Exemple de données métier : `clients`, `services`, `appointments`, `orders`, `properties`, `rent_payments`, `students`, `guard_shifts`, `inventory`, `payments`, `notifications`.

Les données métier portent `tenant_id` lorsque leur portée est celle d'un commerce.

- Créer TenantContext pour déterminer le tenant courant.
- Créer TenantMiddleware pour bloquer tout accès hors contexte.
- Créer des repositories/services tenant-aware.
- Ne jamais laisser un controller décider librement du tenant à utiliser.
- Tester explicitement qu'un utilisateur du tenant A ne peut jamais lire ou modifier les données du tenant B.
- Journaliser les actions sensibles.

Règle : une requête métier ne doit pas dépendre d'un simple SELECT global. Le contexte tenant doit être injecté/contrôlé avant l'accès aux données.

## 5. Design system futuriste SESAM

Le design doit refléter l'univers numérique du logo transmis : bleu nuit, bleu profond, bleu électrique et cyan lumineux. L'objectif est un rendu premium et futuriste sans sacrifier la simplicité pour les utilisateurs peu technophiles.

### Palette proposée

| Variable | Valeur | Usage |
| --- | --- | --- |
| `--sesam-navy` | `#071A3D` | fonds sombres, navigation, titres forts |
| `--sesam-blue` | `#0349B8` | marque, actions principales |
| `--sesam-electric` | `#087CFF` | CTA, états actifs, liens |
| `--sesam-cyan` | `#00D9F5` | accents, glows, détails |
| `--background` | `#F6F9FF` | interface claire |
| `--text` | `#0C1730` | texte principal |
| `--muted` | `#63708A` | texte secondaire |

### Composants

- Boutons, champs, cartes, tableaux, badges, alertes, modales, dropdowns.
- Sidebar, topbar, breadcrumbs, loaders, skeletons et empty states.
- Composants de statistiques, calendrier, timeline client et cartes d'activité.
- Système de spacing, rayons, ombres, gradients et états hover/focus/disabled.

### Animations

- Apparition progressive du hero et des éléments clés.
- Scroll reveal : fade, slide-up, scale léger et parallax mesuré.
- Hover avec glow subtil et déplacement léger.
- Transitions fluides entre états et panneaux.
- Compteurs et progressions animés dans les dashboards.
- Respect de `prefers-reduced-motion`.
- Pas de vidéo lourde en arrière-plan : priorité à la performance 3G.

## 6. UX et accessibilité

- Mobile-first et responsive.
- Actions principales visibles immédiatement.
- Icône + texte plutôt que des icônes seules.
- Libellés simples et messages d'erreur compréhensibles.
- Assistants étape par étape pour les parcours complexes.
- Empty states pédagogiques avec action recommandée.
- Recherche, filtres et pagination sur les listes importantes.
- Confirmation des actions destructives.
- Contraste et tailles de texte adaptés.
- Animations désactivables pour les utilisateurs sensibles au mouvement.

### Exemple de dashboard

```
Bonjour Fatou
Votre activité aujourd'hui
[ 12 rendez-vous ] [ 03 commandes ]
[ 02 paiements en attente ] [ 04 échéances ]
Actions rapides
[ + Nouveau rendez-vous ]
[ + Ajouter un client ]
[ + Enregistrer un paiement ]
[ + Ajouter une prestation ]
Activité récente
Client X — réservation confirmée
Client Y — paiement reçu
Client Z — échéance demain
```

## 7. Fonctionnalités du cœur commun

### Authentification et comptes

- login
- logout
- mot de passe oublié/réinitialisation
- sessions sécurisées
- 2FA optionnelle
- gestion des utilisateurs

### RBAC

- Super Admin
- Admin
- Commercial
- Support
- Finance
- Tenant Owner
- Manager
- Staff
- permissions granulaires

### CRM

- clients
- notes
- tags
- historique
- activités
- recherche
- segmentation

### Paiements

- PaymentService
- transactions
- webhooks
- statuts
- reçus
- remboursements
- journal

### Notifications

- WhatsApp
- email
- notifications internes
- templates
- rappels
- historique

### Abonnements

- plans
- statuts
- activation
- suspension
- renouvellement
- historique

### Audit

- audit logs
- utilisateur
- tenant
- action
- date/heure
- IP si nécessaire et conforme au cadre retenu

## 8. Modules sectoriels

### 8.1 SESAM Réservation

- Services et catégories.
- Durée, prix et personnel.
- Disponibilités et calendrier.
- Rendez-vous, confirmation, annulation et reprogrammation.
- Acompte et paiement.
- Rappels automatiques.

Parcours public : Service → Personnel (si applicable) → Jour → Heure → Paiement → Confirmation → WhatsApp.

### 8.2 SESAM Commande

- Produits/types de vêtements.
- Commandes et lignes de commande.
- Fiche de mesures avec historique.
- Tissus, photos et instructions.
- Prix et acompte.
- Workflow : nouvelle → mesures → confection → essayage → terminée → livrée.

### 8.3 SESAM Loyer

- Immeubles, biens et unités.
- Locataires et contrats.
- Échéanciers.
- Paiements et retards.
- Rappels.
- Documents et historique.

### 8.4 SESAM Inscription

- Établissements, classes, niveaux/filières selon le besoin.
- Élèves et responsables.
- Dossiers et inscriptions.
- Types de frais et tranches.
- Paiements, soldes et reçus.
- Suivi de statut.

### 8.5 SESAM Garde

- Pharmacies et personnel.
- Calendrier de garde.
- Produits, lots et stock.
- Entrées, sorties et ajustements.
- Péremption.
- Alertes : Expiré / Bientôt / OK.

## 9. Portail public sans compte

Le cahier prévoit un lien unique par commerce, sans compte ni application à installer. Ce portail doit être extrêmement simple et optimisé pour mobile.

```
/commerce/nom-du-commerce
Logo
Nom du commerce
Présentation
Services / Produits / Biens
Choisir
↓
Disponibilité / échéance
↓
Informations client
↓
Paiement
↓
Confirmation
↓
WhatsApp
```

- URLs courtes et partageables.
- QR code optionnel pour les supports physiques.
- Chargement rapide.
- Aucun parcours d'inscription obligatoire.
- Validation serveur de toutes les données.
- Protection contre double réservation/double soumission.

## 10. Espace SESAM Digital — gestion de l'agence

- Prospects et pipeline commercial.
- Clients.
- Devis.
- Projets.
- Tâches et échéances.
- Paiements et factures.
- Sites livrés.
- Abonnements SaaS associés.

```
Nouveau prospect
↓
Contacté
↓
Rendez-vous
↓
Devis envoyé
↓
Négociation
↓
Gagné
↓
Projet en cours
↓
Livré
```

Prévoir une jauge de capacité mensuelle correspondant à la limite de 5 projets web par mois indiquée dans le cahier des charges.

## 11. Première architecture de base de données

Cette liste est une architecture initiale : elle doit être confirmée par l'audit et la modélisation détaillée avant création des tables.

| Domaine | Tables principales |
| --- | --- |
| Core | `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `tenants`, `tenant_settings`, `subscriptions`, `subscription_plans`, `audit_logs`, `notifications` |
| CRM | `clients`, `client_notes`, `client_tags`, `client_tags_map`, `client_activities` |
| Paiements | `payments`, `payment_transactions`, `payment_webhooks`, `payment_methods`, `refunds` |
| Réservation | `services`, `service_categories`, `staff`, `staff_availability`, `appointments` |
| Commande | `products`, `orders`, `order_items`, `measurements`, `order_measurements`, `deliveries` |
| Loyer | `properties`, `units`, `renters`, `leases`, `rent_schedules`, `rent_payments` |
| Inscription | `schools`, `students`, `guardians`, `classes`, `enrollments`, `fee_types`, `fee_installments`, `student_payments` |
| Garde | `pharmacies`, `guard_shifts`, `products`/`stock`, `stock_movements`, `product_batches`, `expiry_alerts` |

## 12. Plan d'implémentation A → Z

### Phase 0 — Audit

Analyser le projet existant, le code, la base, les routes, l'authentification, les dépendances, les assets, les risques de sécurité et les écarts avec le cahier. Ne rien développer. Produire `docs/AUDIT.md`.

### Phase 1 — Fondations MVC

Mettre en place Router, Request, Response, Controller, Model, Repository, Service, Middleware, Database, Session, Validation et Security. Ajouter des tests minimaux.

### Phase 2 — Design System

Créer la charte visuelle, variables CSS, composants, animations, responsive, états et page de démonstration.

### Phase 3 — Site public SESAM

Accueil, services, offres, à propos, portfolio, contact, demande de devis, hero futuriste, CTA et animations.

### Phase 4 — Authentification

Login, logout, reset password, sessions sécurisées, gestion des utilisateurs et RBAC.

### Phase 5 — Multi-tenant Core

TenantContext, TenantMiddleware, tenant settings, branding, permissions et tests d'isolation.

### Phase 6 — Dashboard SaaS

Sidebar, topbar, dashboard, notifications, profil, settings, recherche, quick actions et adaptation au secteur.

### Phase 7 — CRM

Clients, fiches, historique, notes, tags, activités, recherche, filtres et exports.

### Phase 8 — Paiements

PaymentService, interface de gateway, transactions, webhooks, statuts et mode sandbox/mock avant les vraies clés.

### Phase 9 — Notifications

WhatsApp, email, notifications internes, templates, rappels et historique.

### Phase 10 — Réservation

Services, personnel, planning, disponibilités, rendez-vous, acomptes, paiements, rappels, annulation et reprogrammation.

### Phase 11 — Commande

Produits, commandes, mesures, confection, livraison, paiements et statuts.

### Phase 12 — Loyer

Biens, locataires, contrats, échéances, paiements, retards et rappels.

### Phase 13 — Inscription

Élèves, responsables, classes, inscriptions, frais, tranches, paiements et reçus.

### Phase 14 — Garde

Calendrier, gardes, stock, lots, péremptions, alertes et mouvements.

### Phase 15 — Portail client public

Parcours sans compte pour réservation, commande, inscription ou autre workflow métier.

### Phase 16 — Console SESAM

Commerces, création, activation, suspension, abonnements, statistiques, support et configuration.

### Phase 17 — Gestion commerciale agence

Prospects, CRM, devis, projets, clients, factures, paiements, pipeline et capacité mensuelle.

### Phase 18 — Statistiques

CA, paiements, rendez-vous, commandes, clients, conversion, impayés et activité.

### Phase 19 — Sécurité et performance

Audit sécurité, isolation tenant, XSS, CSRF, SQL injection, sessions, uploads, rate limiting, cache, 3G et mobile.

### Phase 20 — Tests

Unitaires, intégration, authentification, permissions, multi-tenant, paiements, webhooks, notifications, parcours publics et responsive.

### Phase 21 — Déploiement

Serveur, PHP, MySQL, Apache/Nginx selon environnement, SSL, cron, logs, sauvegardes, monitoring, staging et production.

### Phase 22 — Recette client

Scénarios métier, validation client, corrections, non-régression, documentation et mise en production.

## 13. Méthode obligatoire pour Cursor — éviter les hallucinations

Le projet doit être développé progressivement. Cursor ne doit jamais essayer d'implémenter toutes les phases en une seule opération.

1. Analyser l'existant avant toute modification.
2. Relire le cahier des charges et les documents techniques.
3. Définir précisément le périmètre de la phase.
4. Identifier les fichiers concernés.
5. Implémenter uniquement la phase.
6. Tester immédiatement.
7. Corriger les erreurs.
8. Vérifier le responsive.
9. Vérifier le design visuellement.
10. Vérifier sécurité et permissions.
11. Documenter les changements.
12. S'arrêter et attendre la validation avant la phase suivante.

### Rapport obligatoire après chaque phase

- Fichiers créés.
- Fichiers modifiés.
- Fonctionnalités réalisées.
- Tests exécutés et résultats.
- Bugs rencontrés et corrigés.
- Problèmes connus.
- Décisions/propositions ajoutées.
- Éléments restant à faire.
- Prochaine phase recommandée, sans l'implémenter.

## 14. Prompt maître Cursor

TU ES L'ARCHITECTE ET DÉVELOPPEUR PRINCIPAL DU PROJET SESAM DIGITAL / SESAM SAAS.

### Objectif

Développer progressivement une plateforme PHP MVC professionnelle, modulaire, sécurisée, multi-tenant et responsive destinée à Sesam Digital et Sesam SaaS.

### Source de vérité

Le cahier des charges fourni par le client est la source de vérité fonctionnelle. Ne jamais inventer une fonctionnalité métier importante sans l'indiquer explicitement comme une proposition.

### Stack

- PHP 8.x
- MVC
- PDO
- MySQL
- Composer
- HTML5
- CSS3
- JavaScript moderne
- AJAX / Fetch API
- PHPUnit

### Contraintes

- MVC propre
- Controllers / Models / Services / Repositories / Views séparés
- validation côté serveur obligatoire
- CSRF / XSS / SQL injection / sessions sécurisées
- RBAC
- multi-tenant strict
- aucune fuite de données entre tenants
- responsive mobile-first
- connexion faible 3G
- code maintenable

### Design

Design futuriste, premium, numérique et élégant. Palette inspirée du logo : bleu nuit, bleu profond, bleu électrique, cyan et blanc. Utiliser gradients, glows subtils, glassmorphism léger, micro-interactions, scroll reveal et transitions fluides. Respecter `prefers-reduced-motion`. Ne pas sacrifier lisibilité, accessibilité ou performance.

### Règle absolue

NE JAMAIS implémenter tout le projet en une seule fois.

Pour chaque phase :

1. analyser l'existant
2. rappeler l'objectif
3. identifier les fichiers concernés
4. vérifier les dépendances
5. proposer l'architecture de la phase
6. implémenter uniquement cette phase
7. tester immédiatement
8. corriger les erreurs
9. vérifier le responsive
10. vérifier le design visuellement
11. vérifier sécurité et permissions
12. produire un rapport
13. arrêter l'implémentation

NE PAS PASSER À LA PHASE SUIVANTE automatiquement.

Créer et maintenir :

- `docs/AUDIT.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/SECURITY.md`
- `docs/TESTS.md`

En cas d'incertitude : NE PAS INVENTER. Identifier l'incertitude et demander/indiquer une décision.

## 15. Prompts prêts à copier dans Cursor

### Prompt — Phase 0

Commence uniquement par la PHASE 0 : AUDIT. Ne développe aucune fonctionnalité.

Analyse complètement le projet actuel :

- structure des dossiers
- code PHP
- architecture
- base de données
- routes
- authentification
- dépendances
- assets
- JavaScript
- CSS
- configuration
- failles de sécurité

Compare l'existant avec le cahier des charges SESAM.

Identifie :

- ce qui existe
- ce qui est incomplet
- ce qui est incorrect
- ce qui doit être refactorisé
- ce qui manque
- les risques techniques
- les risques multi-tenant
- les problèmes performance/UX/sécurité

Crée `docs/AUDIT.md`. NE CODE RIEN. À la fin, arrête-toi et donne le rapport complet.

### Prompt — Phase 1

Implémente uniquement la PHASE 1 : FONDATIONS MVC.

Avant toute modification :

- relis `AUDIT.md`
- analyse le code existant
- réutilise ce qui est viable
- ne casse pas les fonctionnalités existantes

Mets en place : Router, Request, Response, Controller, Model, Repository, Service, Middleware, Database, Session, Validation, Security.

Prépare la structure modulaire SESAM sans développer les modules métiers.

Ajoute des tests minimaux :

- routing
- connexion DB
- controller
- gestion d'erreur

Teste réellement, corrige les problèmes, mets à jour `docs/ARCHITECTURE.md`. Arrête-toi à la fin.

### Prompt — Phase 2

Implémente uniquement la PHASE 2 : DESIGN SYSTEM SESAM. Utilise le logo fourni comme référence.

Construis :

- variables CSS
- couleurs
- gradients
- typographie
- boutons
- inputs
- cards
- badges
- alerts
- modals
- tables
- sidebar
- navbar
- loaders
- skeletons
- empty states

Créer une identité futuriste bleu nuit / bleu électrique / cyan / blanc. Ajouter animations, hover, scroll reveal, transitions et `prefers-reduced-motion`. Ne développe aucune fonctionnalité métier. Créer une page de démonstration. Vérifier desktop/tablette/mobile, lisibilité, contraste et performance. Tester puis arrêter.

### Prompt — modèle à utiliser pour les phases suivantes

Implémente uniquement la PHASE [NUMÉRO] : [NOM].

Avant de coder :

1. lire le cahier des charges
2. lire `docs/AUDIT.md`
3. lire `docs/ARCHITECTURE.md`
4. lire `docs/DATABASE.md`
5. lire `docs/DESIGN_SYSTEM.md`
6. analyser le code existant

Implémente uniquement le périmètre de cette phase. Respecte MVC, multi-tenant, sécurité et design system. Ne pas inventer de comportement métier non validé.

Après :

- tester
- corriger
- vérifier sécurité
- vérifier responsive
- vérifier visuellement
- vérifier absence de régression
- mettre à jour la documentation

NE PAS passer à la phase suivante.

Rapport final :

- réalisé
- fichiers modifiés
- tests
- bugs corrigés
- problèmes connus
- propositions
- prochaine phase recommandée

## 16. Extensions stratégiques recommandées

Les éléments suivants sont des recommandations de conception produit, et non des exigences explicitement détaillées dans le cahier. Ils doivent être validés avant d'être considérés comme obligatoires.

- CRM plus complet : segmentation, tags, clients fréquents, timeline.
- Facturation : devis, factures, reçus, dépenses, exports CSV/PDF.
- QR codes pour les liens publics des commerces.
- Statistiques et KPI : CA, impayés, clients actifs, activité.
- Centre de notifications unifié.
- Centre de support/tickets.
- Assistant « Que voulez-vous faire ? » avec actions rapides.
- Templates WhatsApp/email personnalisables.
- Mode sandbox pour toutes les intégrations externes.
- Exports et sauvegardes contrôlés.

## 17. Checklist sécurité et qualité

- PDO + requêtes préparées.
- CSRF sur les formulaires sensibles.
- Échappement systématique des sorties HTML.
- Validation serveur.
- Hash sécurisé des mots de passe.
- Sessions sécurisées et rotation après authentification.
- Contrôle des permissions côté serveur.
- Isolation tenant sur chaque accès métier.
- Validation MIME/taille/noms des uploads.
- Rate limiting sur authentification et endpoints sensibles.
- Webhooks vérifiés et idempotents.
- Secrets dans `.env` et jamais dans Git.
- Logs sans exposer de secrets ou données sensibles inutiles.
- Sauvegardes régulières de la base.
- Tests d'accès croisé entre tenants.

## 18. Déploiement et exploitation

- Environnement local de développement.
- Environnement staging pour recette.
- Production séparée.
- HTTPS obligatoire en production.
- Configuration PHP adaptée.
- MySQL avec sauvegardes automatiques.
- Cron pour rappels et tâches périodiques.
- Rotation des logs.
- Monitoring de disponibilité et erreurs.
- Procédure de rollback.
- Documentation d'installation et de maintenance.

## 19. Checklist finale avant mise en production

- Toutes les phases validées.
- Base de données documentée.
- Migrations/structure de production vérifiées.
- Authentification testée.
- RBAC testé.
- Isolation multi-tenant testée.
- Paiements sandbox puis production validés.
- Webhooks testés.
- WhatsApp testé.
- Rappels testés.
- Parcours public sans compte testé sur mobile.
- Responsive desktop/tablette/mobile.
- Performance 3G contrôlée.
- Accessibilité et `prefers-reduced-motion` vérifiés.
- Sauvegarde/restauration testée.
- Logs et monitoring opérationnels.
- Documentation livrée.
- Recette client signée.

## 20. Résultat cible

À la fin de l'implémentation, SESAM doit être une plateforme unique capable de relier l'activité d'agence et les services SaaS. Un client peut acheter un site auprès de SESAM Digital, puis connecter un module métier SaaS adapté à son activité. Le commerce dispose ensuite d'un tableau de bord simple pour gérer clients, activité, paiements et rappels, tandis que le client final peut utiliser un lien public sans installer d'application ni créer de compte.

```
SESAM DIGITAL
│
├── Agence
│   ├── Prospects
│   ├── Devis
│   ├── Projets
│   ├── Factures
│   └── Clients
│
└── SaaS
    ├── Multi-tenant
    ├── CRM
    ├── Paiements
    ├── WhatsApp
    ├── Abonnements
    └── 5 modules métier
```

### Objectif UX

- Simple pour le commerçant.
- Rapide pour le client final.
- Puissant pour SESAM.
- Sécurisé pour tous.
- Futuriste sans être compliqué.

Fin du document — Feuille de route A → Z pour l'implémentation progressive dans Cursor.
