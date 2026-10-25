# Base de données SESAM

phpMyAdmin n'est pas utilisé par l'application : il sert seulement à administrer MySQL. L'application passe par PDO.

## Connexion

| Paramètre | Valeur observée |
| --- | --- |
| Hôte | `127.0.0.1` |
| Port | `3306` |
| Base | `sesam` |
| Jeu de caractères | `utf8mb4` |
| Collation | `utf8mb4_unicode_ci` |
| Compte | Lu dans `.env` (`DB_USER`, `DB_PASSWORD`). Non recopié ici. |

La base `sesam` existe. Tables applicatives : `contact_requests`, `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `password_reset_tokens`, `auth_attempts`, `tenants`, `tenant_settings`, `notifications`, `clients`, `client_notes`, `client_tags`, `client_tags_map`, `client_activities`, `payments`, `payment_transactions`, `payment_webhook_events`, `message_templates`, `message_deliveries`, `message_reminders`.

`contact_requests` reste une table d'agence : pas de `tenant_id`.

## Table `contact_requests`

Demandes de devis de l'agence.

| Colonne | Rôle |
| --- | --- |
| `id` | Clé primaire |
| `full_name` | Nom, 120 caractères |
| `email` | E-mail, 190 caractères |
| `phone` | Téléphone, 30 caractères |
| `activity` | `salon-beaute`, `salon-couture`, `proprietaire`, `ecole`, `pharmacie` ou `autre` |
| `message` | Texte du besoin |
| `created_at` | Date UTC à l'insertion |

## Tables d'authentification

`users` : `tenant_id` nullable (personnel SESAM = NULL), `full_name`, `email` unique, `password_hash` (`password_hash` PHP), `is_active`, `last_login_at`, dates UTC. Clé étrangère vers `tenants`.

`roles` : huit slugs du plan (`super-admin`, `admin`, `commercial`, `support`, `finance`, `tenant-owner`, `manager`, `staff`).

`permissions` : `users.*`, `tenants.view`, `tenants.create`, `tenants.update`, `settings.view`, `settings.update`. Super Admin et Admin reçoivent `users.*` et `tenants.*`. `tenant-owner` reçoit `users.*` et `settings.*`. `manager` : `settings.view` et `settings.update`. `staff` : `settings.view`.

`user_roles` et `role_permissions` : clés étrangères, suppression en cascade.

`password_reset_tokens` : `token_hash` SHA-256, `expires_at`, `used_at`. Le jeton en clair n'est pas stocké.

`auth_attempts` : `action` (`login` ou `reset`), `ip`, `created_at`. Cinq essais par IP sur quinze minutes.

## Tables `tenants` et `tenant_settings`

`tenants` : `slug` unique, `name`, `sector` (`reservation`, `commande`, `loyer`, `inscription`, `garde`), `status` (`active` ou `suspended`), dates UTC.

`tenant_settings` : clé `tenant_id`, `display_name` (marque blanche, sans couleur ni logo personnalisés).

## Table `notifications`

Notifications internes par commerce : `tenant_id` obligatoire, `user_id` nullable (toute l'équipe si NULL), `title`, `body`, `read_at`, `created_at`. Alimentée aussi par le canal **interne** de `MessagingService`.

## Messagerie (phase 9)

`message_templates` : modèles système (`tenant_id` NULL) par canal (`internal`, `email`, `whatsapp`) et slug.

`message_deliveries` : historique (statut `pending`, `sent`, `failed`, `mock`, `cancelled`, message d'erreur, tentatives).

`message_reminders` : rappels planifiés (`send_at`, `variables_json`, traitement via `MessagingService::processDueReminders()`).

Configuration : `config/notifications.php`, variables `.env` (`NOTIFICATIONS_MODE`, SMTP, WhatsApp).

## CRM (phase 7)

`clients` : fiche par commerce (`tenant_id`), `full_name`, `email`, `phone` optionnels, `status` (`active` / `archived`).

`client_notes` : texte, auteur (`author_user_id`), horodatage UTC.

`client_tags` + `client_tags_map` : tags nommés uniques par commerce, liaison many-to-many.

`client_activities` : fil d'événements (création, mise à jour, note, tag, archivage) pour l'historique et le tableau de bord.

Permissions : `clients.view`, `clients.create`, `clients.update`, `clients.archive`, `clients.export`, `clients.notes`, `clients.tags` (attribuées aux rôles `tenant-owner`, `manager`, `staff` selon la migration).

## Paiements (phase 8, NabooPay provisoire)

`payments` : montant en FCFA entiers (`amount_minor`), `reference` unique, statuts `pending` / `success` / `failed` / `cancelled`, `gateway` (`naboopay`), `mode` (`sandbox` ou `live`), lien client optionnel.

`payment_transactions` : journal (checkout, webhook, bac à sable).

`payment_webhook_events` : idempotence des webhooks (`provider` + `event_id`).

Variables d'environnement : voir `.env.example` (`NABOOPAY_*`). Sans clé API : redirection vers le bac à sable `/paiements/{id}/bac-a-sable`. Sans secret webhook : `POST /webhooks/naboopay` répond **503**.

## Réservation (phase 10, back-office)

Secteur commerce `reservation`. Portail public : création de client + rendez-vous (mêmes tables).

`reservation_services` : prestations (`duration_minutes`, `price_minor`, `deposit_minor`, `is_active`).

`reservation_staff` + `reservation_staff_availability` : personnel et plages par jour (1–7, heures UTC).

`reservation_appointments` : lien client, prestation, personnel optionnel, `starts_at` UTC, statuts `pending` / `confirmed` / `cancelled` / `completed`, acompte et `payment_id` optionnel.

`reservation_categories` : table présente pour extension ; non exposée en UI phase 10.

Permissions : `reservation.view`, `reservation.services`, `reservation.staff`, `reservation.appointments` (owner/manager complets ; staff : view + appointments).

## Commande (phase 11, back-office)

Secteur commerce `commande`. Portail public : commande, mesures (`author_user_id` null) et acompte.

`commande_products` : types de vêtements / produits (`price_minor`, `deposit_minor`).

`commande_orders` : client, numéro, statuts (`new` → `measurements` → `confection` → `fitting` → `completed` → `delivered`, plus `cancelled`), total, acompte, tissus / instructions / URL photo, livraison.

`commande_order_items` : lignes (produit, quantité, montants figés).

`commande_order_measurements` : historique de mesures (cm) par commande.

`commande_deliveries` : échéance et notes de livraison (miroir de `delivery_due_at` sur la commande).

Permissions : `commande.view`, `commande.products`, `commande.orders`.

## Loyer (phase 12, back-office)

Secteur commerce `loyer`. Portail public : lecture des biens/unités + client/activité de contact. Pas d’encaissement de loyer sur le portail.

`loyer_properties` / `loyer_units` : biens et unités locatives.

`loyer_leases` : contrat locataire (client CRM), loyer, jour d'échéance (1–28), statut `active` / `ended`.

`loyer_rent_dues` : échéances (`pending` / `paid`), lien `payment_id`, synchronisation payée si paiement réussi.

Permissions : `loyer.view`, `loyer.properties`, `loyer.leases`, `loyer.dues`.

## Inscription (phase 13, back-office)

Secteur commerce `inscription`. Portail public : dossier + tranches, 1re tranche payable en bac à sable.

`inscription_classes` : classes scolaires actives.

`inscription_fee_types` : types de frais (`total_minor`, `installment_count` 1–12).

`inscription_enrollments` : dossier élève (`student_name`), classe, responsable (`guardian_client_id` → `clients`), statut `active` / `completed` / `cancelled`.

`inscription_installments` : tranches générées à l'inscription, échéance, lien `payment_id`, statut `pending` / `paid`.

Permissions : `inscription.view`, `inscription.classes`, `inscription.fees`, `inscription.enrollments`.

## Garde (phase 14, back-office)

Secteur commerce `garde`. Portail public : calendrier (`garde_shifts` / personnel) uniquement. Le stock n’est pas exposé. Le commerce est la pharmacie.

`garde_staff` : personnel de garde (nom, fonction).

`garde_shifts` : un tour par personne et par jour (`UNIQUE tenant_id, staff_id, shift_date`).

`garde_products` : catalogue (unité, seuil minimum, `image_path` relatif sous `storage/uploads/`).

`garde_batches` : lots (`lot_code` unique par produit), quantité restante, date de péremption. L'état Expiré / Bientôt / OK est calculé (Bientôt = 30 jours), pas stocké.

`garde_movements` : entrée, sortie, ajustement ; `created_by` → `users`.

Permissions : `garde.view`, `garde.staff`, `garde.shifts`, `garde.stock`.

## Abonnements commerces (phase 16, console SESAM)

`tenant_subscriptions` : une ligne par commerce (`tenant_id` clé primaire), statut `active` / `suspended`, `monthly_amount_minor` (FCFA, facultatif, saisi par SESAM), `period_start`, `period_end`. L'état « Échu » (actif dont `period_end` est passé) et « Non configuré » (pas de ligne) sont calculés, pas stockés.

`tenant_subscription_events` : historique (`activate`, `renew`, `suspend`), statut avant / après, fin de période, note, auteur (`actor_user_id` → `users`, `SET NULL`).

Permissions : `subscriptions.view`, `subscriptions.manage` (Super Admin, Admin).

## Agence SESAM Digital (phase 17)

Données du personnel SESAM, sans `tenant_id` : l'agence n'est pas un commerce.

`agency_prospects` : contact, commerce (facultatif), e-mail, téléphone, activité (liste de `home-catalog.php`), `source` (`manuel` / `site`), `contact_request_id` (unique, → `contact_requests`, `SET NULL`), `stage`, `next_action_on` (relance), `created_by` → `users` (`SET NULL`). Étapes : `nouveau`, `contacte`, `rendez-vous`, `devis-envoye`, `negociation`, `gagne`, `projet-en-cours`, `livre`, `perdu`. Un client est un prospect en `gagne`, `projet-en-cours` ou `livre` ; il n'y a pas de table clients agence.

`agency_prospect_events` : historique (création, fiche, étape, note, devis, projet, facture), texte 500 caractères, auteur `SET NULL`, suppression en cascade avec le prospect.

`agency_quotes` : référence unique `DV-AAAA-NNNN`, formule (`essentiel`, `professionnel`, `sur-mesure`), point de départ (`site-neuf`, `site-existant`), montant FCFA entier, contenu, statut `draft` / `sent` / `accepted` / `refused`, `sent_at`, `decided_at`.

`agency_projects` : un projet par devis accepté (`quote_id` unique), titre, formule, montant, statut `planned` / `in_progress` / `delivered`, `start_month` (`AAAA-MM`, posé au démarrage, sert à la capacité de 5 par mois), `started_on`, `delivered_on`, `site_url`.

`agency_invoices` : référence unique `FA-AAAA-NNNN`, objet, montant, statut `issued` / `paid`, `due_on`, `paid_on`, `payment_method` (`wave`, `orange-money`, `especes`, `virement`). « En retard » (émise et échéance passée) est calculé.

Permissions : `agency.view`, `agency.prospects`, `agency.quotes`, `agency.projects`, `agency.invoices`. Super Admin et Admin : toutes. Commercial : view, prospects, quotes. Finance : view, invoices.

## Statistiques (phase 18)

Aucune table nouvelle. Les statistiques lisent les tables existantes, toujours filtrées par `tenant_id`. Le chiffre d'affaires est la somme des `payments` au statut `success`, datés par `paid_at`. Les paiements du bac à sable sont comptés, car la colonne `mode` n'est pas filtrée. Les impayés Loyer et Inscription sont les échéances `pending` dont `due_date` est passée. Pour les autres secteurs, on affiche les `payments` en `pending`.

Index ajouté : `payments (tenant_id, status, paid_at)`. Permission `stats.view` (Voir les statistiques du commerce) : Super Admin, Admin, Tenant Owner, Manager. Le rôle Staff ne l'a pas.

## Migrations

- `database/migrations/202610041000_create_contact_requests.sql`
- `database/migrations/202610061100_create_auth_rbac.sql`
- `database/migrations/202610061200_create_tenants.sql`
- `database/migrations/202610061300_create_notifications.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610061400_create_crm.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610061500_create_payments.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610061600_create_messaging.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610061700_create_reservation.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610061800_create_commande.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610061900_create_loyer.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610062000_create_inscription.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610070900_create_garde.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610071000_garde_product_image.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610071100_create_tenant_subscriptions.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610071200_create_agency.sql` (exécutée sur la base locale `sesam`)
- `database/migrations/202610071300_create_statistics.sql` (exécutée sur la base locale `sesam` ; l'ajout d'index n'est pas rejouable)

Phase 15 : aucune table nouvelle. Le portail réutilise clients, activités, modules et paiements. Limitation : `auth_attempts.action = portal`.

Compte staff local : `database/seeds/staff.php` lit `SESAM_STAFF_EMAIL` et `SESAM_STAFF_PASSWORD` dans `.env`. Le mot de passe n'est pas recopié ici.

## Requêtes

`Sesam\Core\Database\Connection` ouvre PDO avec `ERRMODE_EXCEPTION` et les requêtes préparées natives (`ATTR_EMULATE_PREPARES` désactivé).
