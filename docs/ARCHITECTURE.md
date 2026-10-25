# Architecture SESAM

État après la phase 19 : modules back-office, portail public sans compte `/t/{slug}`, console SESAM des commerces (`/commerces`), gestion commerciale de l'agence (`/agence`), statistiques de chaque commerce (`/statistiques`) et durcissement sécurité / performance.

## Namespace

Racine unique : `Sesam\`. Autoload PSR-4 de `app/` via Composer.

## Flux

`public/index.php` charge Composer, construit le noyau, capture la requête et envoie la réponse.

```
Request
  → SessionMiddleware
  → CsrfMiddleware
  → AuthMiddleware
  → TenantMiddleware
  → Router
  → Controller → Service → Repository
  → View
  → SecurityHeaders (appliqué par le Kernel à toute réponse, pages d'erreur comprises)
```

`Connection::make` renvoie une connexion PDO partagée par processus : une requête HTTP ouvre une seule connexion MySQL, au montage du noyau, y compris pour les pages publiques. `public/index.php` active `zlib.output_compression` (HTML gzip).

| Classe | Rôle actuel |
| --- | --- |
| `Sesam\Core\Kernel` | Enchaîne les middlewares et le routeur. Transforme les exceptions en réponses HTTP, puis applique `SecurityHeaders`. Si la requête porte `X-Sesam-Modal: 1` (formulaire ouvert en modale), une redirection 3xx devient `200` + `X-Sesam-Location`. |
| `Sesam\Core\Security\SecurityHeaders` | CSP, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP, HSTS en HTTPS, `Cache-Control: no-store` par défaut. |
| `Sesam\Core\Router\Router` | Routes GET et POST, segments `{param}`. |
| `Sesam\Core\Request\Request` | Méthode, chemin, query, corps, IP, en-tête CSRF. |
| `Sesam\Core\Response\Response` | Statut, en-têtes, HTML. |
| `Sesam\Core\Response\ViewRenderer` | Vues sous `app/Views`. Marketing : `layouts/public.php`. Portail commerce : `layouts/portal.php`. Auth : `layouts/auth.php`. Personnel SESAM : `layouts/staff.php`. Commerce connecté : `layouts/tenant.php`. Démo : `layouts/main.php`. |
| `Sesam\Core\Dashboard\SectorDashboard` | Libellés KPI et actions rapides par secteur (sans données métier inventées). |
| `Sesam\Core\Database\Connection` | PDO MySQL préparé, paramètres dans `.env`. Une instance partagée par base et par processus. |
| `Sesam\Core\Session\Session` | Session unique `SESAMSESSID`, mode strict, cookies seuls. Rotation après login, invalidation à la déconnexion, vidée après 2 h d'inactivité. |
| `Sesam\Core\Security\Csrf` | Jeton de session. |
| `Sesam\Core\Security\ErrorHandler` | Pages d'erreur. Les exceptions inattendues vont dans `storage/logs/app.log`. |
| `Sesam\Core\Validation\Validator` | Règles `required`, `string`, `integer`, `email`, `min`, `max`. |
| `Sesam\Core\Auth\Role` | Huit rôles. Listes `SESAM` et `TENANT`. |
| `Sesam\Core\Auth\Permission` | `users.*`, `tenants.*`, `subscriptions.*`, `agency.*`, `stats.view`, `settings.*`, `clients.*`, `payments.*`, `notifications.*`, `reservation.*`, `commande.*`, `loyer.*`, `inscription.*`, `garde.*`. |
| `Sesam\Core\Tenant\TenantContext` | Commerce lié par le middleware : `users.tenant_id` hors portail ; slug `/t/{slug}` (commerce actif) sur le portail public. |
| `Sesam\Core\Tenant\Sector` | Cinq secteurs : réservation, commande, loyer, inscription, garde. |
| `Sesam\Middleware\AuthMiddleware` | Redirige l'espace connecté (dont `/commerces`, `/agence`, `/clients`, `/paiements`, `/statistiques`, `/reservation`, `/commande`, `/loyer`, `/inscription`, `/garde`, `/notifications`, `/profil`, `/recherche`) vers `/connexion`. Un compte déjà connecté quitte les pages d'auth. |
| `Sesam\Middleware\TenantMiddleware` | Sur `/t/{slug}` : lie le commerce du slug s’il est actif, sinon aucun. Ailleurs : commerce du compte ; session coupée si suspendu ou introuvable. |
| `Sesam\Controllers\AuthController` | Login, logout, mot de passe oublié, réinitialisation. |
| `Sesam\Controllers\AccountController` | `/espace` : tableau de bord commerce ou accueil personnel SESAM. |
| `Sesam\Controllers\NotificationController` | Liste des notifications internes du commerce (`/notifications`). |
| `Sesam\Controllers\ProfileController` | Profil connecté (`/profil`). |
| `Sesam\Controllers\SearchController` | Recherche limitée au périmètre (`/recherche`). |
| `Sesam\Controllers\ClientController` | CRM commerce : liste, fiche, notes, tags, export (`/clients`). |
| `Sesam\Controllers\UserController` | Liste, création et modification des comptes du périmètre (SESAM ou un commerce). |
| `Sesam\Controllers\TenantController` | `/commerces` pour le personnel SESAM. `/parametres` pour le commerce connecté. |
| `Sesam\Services\AuthService` | Vérification du mot de passe, session, jetons de reset, limitation de débit, refus d'un commerce suspendu. |
| `Sesam\Services\UserService` | Règles RBAC et cloisonnement des utilisateurs. |
| `Sesam\Services\TenantService` | Création / modification des commerces et du nom affiché. |
| `Sesam\Services\TenantConsoleService` | Console SESAM : vue globale, filtres, fiche commerce, abonnement (activation, renouvellement, suspension) et historique. Connexion PDO ouverte à la première utilisation. |
| `Sesam\Controllers\AgencyController` | Agence SESAM Digital sous `/agence` (personnel SESAM uniquement, layout staff). |
| `Sesam\Services\Agency\AgencyService` | Prospects, demandes du site, devis, projets, capacité mensuelle, factures et paiements manuels. Refuse tout compte rattaché à un commerce (403). Connexion PDO ouverte à la première utilisation. |
| `Sesam\Services\Agency\AgencyCatalog` | Constantes agence : étapes du pipeline, formules et planchers, capacité (5), statuts, moyens de paiement ; activités lues dans `Views/public/home-catalog.php`. |
| `Sesam\Repositories\AgencyProspectRepository`, `AgencyQuoteRepository`, `AgencyProjectRepository` | Tables `agency_*` et lecture des `contact_requests` non suivies. Références `DV-AAAA-NNNN` et `FA-AAAA-NNNN`. |
| `Sesam\Controllers\StatisticsController` | Statistiques du commerce connecté sous `/statistiques` (layout tenant). |
| `Sesam\Services\StatisticsService` | Période 30 jours / 90 jours / 12 mois (liste blanche, 30 jours par défaut) comparée à la période précédente ; chiffre d'affaires, taux de paiement, panier moyen, impayés ou paiements en attente, clients, encaissements par mois, activité CRM, bloc métier selon le secteur. Refuse un compte sans commerce ou sans `stats.view` (403). Lecture seule. |
| `Sesam\Repositories\StatisticsRepository` | Agrégats SQL en lecture, tous filtrés par `tenant_id` : `payments`, `clients`, `client_activities`, `reservation_*`, `commande_*`, `loyer_rent_dues`, `inscription_*`, `garde_*`. |
| `Sesam\Services\DashboardService` | Tableau de bord commerce : KPI paiements ; KPI rendez-vous si secteur `reservation`. |
| `Sesam\Services\NotificationService` | Boîte de réception interne (liste, non lues). |
| `Sesam\Services\MessagingService` | Envoi multi-canal, modèles, historique, rappels (mock/live). |
| `Sesam\Services\ProfileService` | Nom et mot de passe du compte connecté. |
| `Sesam\Services\SearchService` | Recherche utilisateurs, clients (commerce) et commerces (staff). |
| `Sesam\Services\ClientService` | Clients, notes, tags, activités ; isolation `tenant_id`. |
| `Sesam\Services\PaymentService` | Paiements NabooPay (sandbox sans clé, webhooks signés si secret). |
| `Sesam\Core\Payment\PaymentGatewayInterface` | Abstraction passerelle ; implémentation `NabooPayGateway`. |
| `Sesam\Controllers\PaymentController` | Liste, initiation, bac à sable, détail (`/paiements`). |
| `Sesam\Controllers\WebhookController` | `POST /webhooks/naboopay` (hors session, sans CSRF). |
| `Sesam\Services\Reservation\BookingService` | Prestations, équipe, disponibilités, RDV, acomptes, rappels. |
| `Sesam\Controllers\ReservationPrestationController` | `/reservation/prestations`. |
| `Sesam\Controllers\ReservationTeamController` | `/reservation/equipe` et disponibilités. |
| `Sesam\Controllers\ReservationAppointmentController` | `/reservation/rendez-vous`. |
| `Sesam\Services\Commande\OrderWorkflowService` | Produits, commandes, mesures, statuts, livraison, acomptes. |
| `Sesam\Controllers\CommandeProductController` | `/commande/produits`. |
| `Sesam\Controllers\CommandeOrderController` | `/commande/commandes`. |
| `Sesam\Services\Loyer\RentService` | Biens, contrats, échéances, encaissement, relances. |
| `Sesam\Controllers\LoyerPropertyController` | `/loyer/biens`. |
| `Sesam\Controllers\LoyerLeaseController` | `/loyer/contrats`. |
| `Sesam\Controllers\LoyerDueController` | `/loyer/echeances`. |
| `Sesam\Services\Inscription\EnrollmentService` | Classes, frais, dossiers, tranches, encaissement. |
| `Sesam\Controllers\InscriptionClassController` | `/inscription/classes`. |
| `Sesam\Controllers\InscriptionFeeTypeController` | `/inscription/frais`. |
| `Sesam\Controllers\InscriptionEnrollmentController` | `/inscription/dossiers`. |
| `Sesam\Services\Storage\ImageUploadService` | Validation, conversion WebP, compression et stockage hors `public/`. |
| `Sesam\Services\Garde\DutyService` | Personnel, calendrier, produits, lots, photos, mouvements, alertes de péremption. |
| `Sesam\Controllers\GardeShiftController` | `/garde/calendrier`. |
| `Sesam\Controllers\GardeStaffController` | `/garde/personnel`. |
| `Sesam\Controllers\GardeStockController` | `/garde/produits`, photo `/garde/produits/{id}/image`, `/garde/lots`, `/garde/alertes`, `/garde/mouvements`. |
| `Sesam\Services\Portal\PublicPortalService` | Portail public : catalogue, création client, réservation/commande/inscription/loyer, paiement bac à sable, confirmation, WhatsApp mock, rate limit. |
| `Sesam\Controllers\PortalController` | `/t/{slug}`, confirmation, bac à sable public. |
| `Sesam\Repositories\UserRepository` | Lecture et écriture de `users`, filtre `tenant_id`. |
| `Sesam\Repositories\TenantRepository` | Lecture et écriture de `tenants` et `tenant_settings`. |
| `Sesam\Models\User` | Identité, `tenant_id`, rôles et permissions. |
| `Sesam\Models\Tenant` | Commerce, secteur, état, nom affiché. |

## Routes présentes

| Méthode | Chemin | Réponse |
| --- | --- | --- |
| GET | `/` | Accueil public. |
| GET | `/a-propos` | À propos. |
| GET | `/services` | Sites web et cinq outils annoncés. |
| GET | `/offres` | Essentiel, Professionnel, sur mesure. |
| GET | `/portfolio` | Réalisations. Aucun projet publié. |
| GET | `/contact` | Contact, sans adresse ni téléphone inventés. |
| GET | `/devis` | Formulaire de demande. |
| POST | `/devis` | Enregistrement, ou 422 si les champs sont refusés. |
| GET | `/design-system` | Démonstration du système visuel. |
| GET | `/connexion` | Formulaire de connexion. Pas d'inscription publique. |
| POST | `/connexion` | Session, ou 422 si les identifiants sont refusés. |
| POST | `/deconnexion` | Fin de session. |
| GET / POST | `/mot-de-passe` | Demande de réinitialisation. |
| GET / POST | `/mot-de-passe/reinitialiser/{token}` | Nouveau mot de passe. |
| GET | `/espace` | Tableau de bord commerce ou accueil personnel SESAM. |
| GET | `/utilisateurs` | Liste des comptes du périmètre (`users.view`). |
| GET / POST | `/utilisateurs/nouveau` | Création. |
| GET / POST | `/utilisateurs/{id}` | Modification. |
| POST | `/utilisateurs/{id}/lien-reinitialisation` | Lien unique, affiché une fois. |
| GET | `/commerces` | Console : indicateurs et liste filtrable (`tenants.view`). |
| GET / POST | `/commerces/nouveau` | Création, avec un responsable. |
| GET | `/commerces/{id}` | Fiche commerce, abonnement et historique. |
| GET | `/commerces/{id}/modifier` | Formulaire de modification. |
| POST | `/commerces/{id}` | Enregistrement de la modification (`tenants.update`). |
| POST | `/commerces/{id}/abonnement` | Activation, renouvellement ou suspension (`subscriptions.manage`). |
| GET | `/agence` | Synthèse agence : pipeline, capacité du mois, devis en attente, encaissements, demandes du site (`agency.view`). |
| GET | `/agence/prospects` | Prospects et clients (`?vue=clients`), filtres étape / recherche, demandes du site à suivre. |
| GET / POST | `/agence/prospects/nouveau`, `/agence/prospects` | Création d'un prospect (`agency.prospects`). |
| GET | `/agence/prospects/{id}` | Fiche : étapes, devis, projets, suivi, historique. |
| GET / POST | `/agence/prospects/{id}/modifier`, `/agence/prospects/{id}` | Modification (`agency.prospects`). |
| POST | `/agence/prospects/{id}/etape`, `/agence/prospects/{id}/note` | Étape manuelle, note et relance (`agency.prospects`). |
| POST | `/agence/demandes/{id}/convertir` | Demande `/devis` → prospect, une seule fois (`agency.prospects`). |
| POST | `/agence/prospects/{id}/devis` | Devis brouillon (`agency.quotes`). |
| POST | `/agence/devis/{id}/statut` | Envoyé, accepté (crée le projet) ou refusé (`agency.quotes`). |
| GET | `/agence/projets`, `/agence/projets/{id}` | Projets, capacité, encaissement. |
| POST | `/agence/projets/{id}/statut` | Démarrer (capacité 5 / mois) ou livrer (`agency.projects`). |
| POST | `/agence/projets/{id}/factures` | Facture, dans la limite du montant du projet (`agency.invoices`). |
| GET | `/agence/factures` | Factures : à payer, en retard, payées. |
| POST | `/agence/factures/{id}/payer` | Paiement reçu : moyen et date (`agency.invoices`). |
| GET / POST | `/parametres` | Nom affiché du commerce connecté (`settings.view` / `settings.update`) et lien `/t/{slug}`. |
| GET / POST | `/t/{slug}` | Portail public du commerce (sans compte). |
| GET | `/t/{slug}/confirmation` | Confirmation PRG (jeton de session `k`). |
| GET | `/t/{slug}/paiement/{id}/bac-a-sable` | Bac à sable NabooPay du portail. |
| POST | `/t/{slug}/paiement/{id}/confirmer` | Simulation succès (sandbox). |
| POST | `/t/{slug}/paiement/{id}/annuler` | Simulation annulation (sandbox). |
| GET | `/notifications` | Boîte de réception interne. |
| GET | `/notifications/historique` | Historique des envois (`notifications.history`). |
| GET / POST | `/notifications/envoyer` | Envoi test (`notifications.send`). |
| POST | `/notifications/{id}/lu` | Marquer une notification comme lue. |
| GET / POST | `/profil` | Profil du compte connecté. |
| GET | `/recherche` | Recherche dans le périmètre autorisé. |
| GET | `/clients` | Liste clients du commerce (`clients.view`). |
| GET | `/clients/export` | Export CSV filtré (`clients.export`). |
| GET / POST | `/clients/nouveau`, `/clients` | Création (`clients.create`). |
| GET / POST | `/clients/{id}`, `/clients/{id}/modifier` | Fiche et modification. |
| POST | `/clients/{id}/notes` | Note (`clients.notes`). |
| POST | `/clients/{id}/archiver`, `/clients/{id}/restaurer` | Archivage (`clients.archive`). |
| GET | `/paiements` | Liste des paiements du commerce (`payments.view`). |
| GET / POST | `/paiements/nouveau`, `/paiements` | Initiation (`payments.create`) → bac à sable si pas de clé API. |
| GET | `/paiements/{id}/bac-a-sable` | Simulation NabooPay locale. |
| GET | `/statistiques` | Statistiques du commerce, `?periode=30j` (défaut), `90j` ou `12m` ; valeur inconnue → 30 jours (`stats.view`). |
| POST | `/webhooks/naboopay` | Webhook NabooPay (503 si secret absent). |
| autre | chemin inconnu | 404 |
| méthode incorrecte sur une route connue | | 405 |
| POST, PUT, PATCH, DELETE sans jeton | | 419 |

Portail public (sans compte) : `GET/POST /t/{slug}`, `GET /t/{slug}/confirmation`, `GET /t/{slug}/paiement/{id}/bac-a-sable` et POST confirmer/annuler. Un slug inconnu ou un commerce suspendu répondent 404. Pas d’inscription de compte public. Modules back-office : `/reservation/*`, `/commande/*`, `/loyer/*`, `/inscription/*`, `/garde/*`. Personnel SESAM : console `/commerces` et agence `/agence`.

Hors portail, le commerce vient du compte authentifié. Sur `/t/{slug}`, il vient du slug. Un `tenant_id` de formulaire n’est pas une source de vérité.

## Dossiers encore vides de code métier

`app/Core/Cache`, `app/Modules/*`, `app/Views/components`, `app/Views/admin`.

Le site public est dans `app/Views/public` et `public/assets/css/public.css`. L'espace staff réutilise `sesam.css` (topbar, sidebar, formulaires). Pas de tableau de bord mélangeant rendez-vous, commandes et paiements.

Assets : `Html::asset` ajoute `?v=<date de modification>` ; `public/.htaccess` (Apache) met ces fichiers en cache un an et compresse texte et SVG. `Html::picture` sert la version WebP d'une photo quand `assets/images/<nom>.webp` existe, le JPEG restant le repli. Les layouts utilisent `sesam-logo-360.jpg` (21 Ko) au lieu du logo source de 1254 px. Les confirmations de formulaire passent par `data-confirm` (géré par `sesam.js`) : aucun script en ligne.

## Point d'entrée web

Le document root prévu est `public/`. L’URL locale de travail est `http://localhost:8080/`. Commande : `composer serve` (équivalent : `php -S localhost:8080 -t public public/index.php`). Le routeur `public/index.php` sert aussi les fichiers statiques en mode `cli-server`.

Le `.htaccess` à la racine du projet redirige vers `public/index.php` et interdit l'accès direct à `app/`, `config/`, `database/`, `storage/`, `tests/`, `docs/`, `.env` et `vendor/` si Apache est utilisé. Apache n’est pas le serveur de travail.

## Base

Voir `docs/DATABASE.md`.
