# Tests SESAM

PHPUnit 11, configuré par `phpunit.xml`.

```
composer test
```

`composer test` lance `php -d xdebug.mode=off vendor/bin/phpunit`. Depuis la phase 19, le processus de test partage une seule connexion PDO (`Connection::make`) ; avant, un noyau retenu par Xdebug gardait 18 connexions ouvertes et la suite dépassait `max_connections`. Pour lancer PHPUnit à la main, garder `-d xdebug.mode=off` (plus rapide).

## Scénarios présents

- Normalisation des chemins, y compris le sous-dossier `projet Sesam/public`.
- Route trouvée, paramètre, 404 et 405.
- Contrôleur d'accueil et en-têtes de sécurité.
- Erreur inattendue : message générique à l'écran, détail seulement dans le journal.
- Connexion PDO à la base `sesam`.
- Rejet d'un POST sans jeton CSRF, acceptation avec jeton.
- Options du cookie de session.
- Validation `required`, `max` et `min`.
- Authentification : redirection des pages protégées, refus CSRF, identifiants invalides, compte inactif, login / logout, rôle sans permission `users.view`, reset par jeton, limitation de débit.
- RBAC : un Admin ne peut pas attribuer Super Admin ; un Staff ne liste pas les utilisateurs ; un Super Admin peut en désactiver un autre.
- Isolation tenant : un responsable A ne lit ni ne modifie un utilisateur B ; un `tenant_id` POST n'est pas suivi ; la liste SESAM n'inclut pas les comptes commerce ; un commerce suspendu est refusé sans message distinct ; `/commerces` et `/parametres` selon le rôle ; création d'un commerce avec son responsable.

- Dashboard : libellés par secteur, accueil commerce vs personnel SESAM, coquille notifications / profil / recherche.
- CRM : création client, note par un Staff, refus création Staff, isolation A/B, refus `/clients` pour le personnel SESAM.
- Paiements : parcours bac à sable NabooPay, webhook 503 sans secret, Staff ne peut pas initier un paiement.
- Messagerie : rendu de modèles, envoi interne → inbox, rappel mock, Staff sans envoi.
- Réservation : refus hors secteur `reservation`, anti-chevauchement de créneaux, acompte RDV → bac à sable paiement.
- Commande : refus hors secteur `commande`, mesures → statut « Mesures », avancement workflow, acompte → bac à sable.
- Loyer : refus hors secteur, contrat → 12 échéances, encaissement → bac à sable.
- Inscription : refus hors secteur, inscription → tranches, encaissement → bac à sable.
- Garde : refus hors secteur, isolation A/B, doublon de tour, stock insuffisant, alerte Bientôt, Staff sans `garde.staff`, photo produit convertie en WebP et inaccessible à un autre commerce.
- Upload image : PNG → WebP compressé, refus taille / double extension exécutable / fichier non image.
- Portail public : slug inconnu / commerce suspendu → 404 ; isolation des catalogues A/B ; anti-chevauchement de créneau ; acompte → bac à sable du slug uniquement ; commande + mesures ; inscription 1re tranche ; loyer contact ; garde calendrier sans stock ; POST sans CSRF → 419.

- Console SESAM : responsable de commerce refusé (403) sur la fiche et sur l'action d'abonnement, Commercial refusé sur `/commerces` ; activation → renouvellement (échu puis anticipé) → suspension (portail 404) → réactivation, avec historique ; transitions et saisies invalides → 422 sans écriture ; filtres secteur / recherche ; édition sur `/commerces/{id}/modifier`.

- Agence (`tests/AgencyTest.php`) : invité redirigé ; responsable de commerce et compte Commercial rattaché à un commerce refusés (403) ; Commercial sans démarrage de projet ni facture, Finance sans prospect ni devis ; planchers Essentiel / Professionnel et saisies invalides → 422 sans écriture ; devis brouillon → envoyé → accepté (projet créé, étapes automatiques) ; transitions interdites sans effet ; démarrage puis 6ᵉ démarrage du mois refusé ; factures plafonnées au montant du projet ; paiement (moyen invalide, date future, double paiement) et message affiché ; livraison avec URL `javascript:` refusée ; demande du site convertie une seule fois ; étape « Perdu » bloque le devis ; note échappée en sortie.

Les tests agence écrivent dans la base locale et suppriment leurs fiches (`p17-…`) à la fin. Le test de capacité tient compte des projets déjà démarrés dans le mois.

- Statistiques (`tests/StatisticsTest.php`) :
  - Accès : invité redirigé ; Staff du commerce et Super Admin sans commerce refusés (403), sans lien de menu ; Manager et responsable autorisés.
  - Période inconnue ramenée à 30 jours.
  - Paiements : chiffre d'affaires, variation par rapport à la période précédente, taux de paiement, panier moyen et paiements en attente calculés sur des paiements datés. Les encaissements d'un autre commerce ne sont jamais comptés.
  - Clients : nouveaux, actifs, payeurs et fidèles.
  - Rendez-vous : taux de confirmation, annulations et classement des prestations ; la période 90 jours élargit les totaux.
  - Loyer : attendu, encaissé, taux de recouvrement, impayés en retard (y compris hors période) et contrats actifs.
  - Commande : valeur hors annulations, produits classés sans les commandes annulées. Inscription et Garde affichent leur bloc.

Les tests statistiques créent leurs commerces (`p18-…`) et les suppriment avec leurs données.

- Durcissement (`tests/SecurityHardeningTest.php`) :
  - CSP, `nosniff`, `X-Frame-Options`, `Permissions-Policy` et `Cache-Control: no-store` sur une page, une 404, un refus CSRF 419 et une redirection ; pas de HSTS en HTTP, HSTS en HTTPS ; un `Cache-Control` posé par le contrôleur est conservé.
  - Aucune vue ne contient de script en ligne ni d'attribut `on…=` (compatibilité CSP).
  - Deux appels à `Connection::make` renvoient la même connexion.
  - Session vidée après plus de 2 h d'inactivité, conservée en deçà.
  - Le jeton CSRF change à la connexion ; l'ancien est refusé (419).
  - Formulaire de devis : 5 demandes acceptées, la 6ᵉ refusée (429) et non enregistrée.
- Site public : URL d'assets versionnées (`?v=`), source WebP dans `<picture>` pour les six photos, logo réduit.

## Phase 20

Les tests d'intégration récents héritent de `tests/Support/IntegrationTestCase.php` : commerces, comptes et événements préfixés `p20-`, connexion par le vrai formulaire, jeton CSRF lu dans la page, insertion directe. Après chaque test, toutes les lignes portant le `tenant_id` des commerces créés sont supprimées (tables lues dans `information_schema`), ainsi que les comptes, les tentatives par IP et les événements webhook.

- Webhooks NabooPay (`tests/WebhookTest.php`), via `WebhookController` et l'en-tête `X-Naboo-Signature` :
  - signature valide → paiement réussi, `paid_at` et identifiant de transaction ; rejeu du même `event_id` sans seconde transaction ;
  - signature fausse, absente ou corps modifié → 401, rien d'enregistré ;
  - JSON invalide, champs manquants, statut inconnu → 400 ; référence inconnue → 200 et événement gardé ;
  - un « failed », « cancelled » ou « pending » tardif ne défait pas un paiement réussi ; un « pending » ne rouvre pas un échec ; un succès ultérieur reste accepté ;
  - webhook de succès → l'échéance de loyer liée passe à « payée » à la lecture, sans second paiement à l'encaissement ;
  - la route `/webhooks/naboopay` répond en JSON sans exiger de jeton CSRF.
- Isolation entre commerces (`tests/CrossTenantAccessTest.php`) : le responsable du commerce A reçoit 403/404 en lecture et en écriture sur les clients (fiche, modification, archivage, note), paiements (fiche, bac à sable, confirmation, annulation), rendez-vous (confirmer, annuler, acompte, reprogrammer), commandes (avancer, annuler, acompte, livraison, détails, mesures), biens, contrats et échéances (encaisser, relancer), dossiers et tranches d'inscription (y compris une tranche B sous un dossier A), tours de garde (retrait), produits et photos du commerce B. La ligne visée reste identique octet pour octet. Une notification d'un autre commerce ou d'un collègue ne peut pas être marquée lue. Le portail de A ne sert ni ne confirme un paiement de B. Contrôle positif : A ouvre ses propres fiches (200).
- Effets de bord (`tests/SideEffectsTest.php`) :
  - bac à sable confirmé deux fois puis annulé → une seule transaction, statut réussi ;
  - échéance de loyer, tranche d'inscription, acompte de commande et de rendez-vous : la seconde demande renvoie sur la fiche avec un message, sans second paiement ; une échéance réglée n'est plus encaissable ;
  - envoi de notification : destinataire vide, canal inconnu, modèle vide ou introuvable → 422 sans envoi ; Staff → 403 ;
  - portail : demande incomplète, prestation inconnue ou créneau déjà pris → 422 sans fiche client créée ; 12 demandes par IP puis 429, même valide ; une autre IP passe.
- Responsive (`tests/DesignSystemTest.php`) : chaque layout porte le viewport mobile et `lang="fr"`, sans `user-scalable=no` ; chaque feuille CSS a des media queries ; chaque `<table>` des vues est dans un `.table-wrap`.
- Requête (`tests/RequestPathTest.php`) : `Request::capture` garde les en-têtes `X-Naboo-Signature` et `X-CSRF-Token`.

Le dernier passage complet : Phase 20 tests (124 tests, 1403 assertions), via `composer test`. L’URL locale attendue est `http://localhost:8080/`.

