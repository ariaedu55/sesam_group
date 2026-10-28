# Journal des changements

## 2026-10-29 — Page « Commencer » refaite, essai gratuit pilotable depuis la console

- Page `/commencer` refaite : bannière avec cadeau animé (couvercle qui saute, ticket « 1 mois offert », confettis), « Comment ça marche » en 3 étapes, cartes d'outils avec prix et « 1 mois offert », récapitulatif de l'offre qui suit l'outil choisi (aujourd'hui, pendant 30 jours, ensuite), « Ce qui se passe ensuite », aide, questions fréquentes. Même formulaire et mêmes champs (`signup.js`), connexion Google conservée.
- Trois popups propres à la page (`partials/commencer-popups.php`, `commencer.js`) : inscription guidée dans le popup (accueil cadeau puis 4 étapes : outil, vous, connexion, récapitulatif modifiable, création du compte) ouverte 5 s après l'arrivée ou par « Inscription guidée » ; « Attendez ! » quand le visiteur s'apprête à partir ; aide quand il bloque sur le formulaire. Chacun une fois par visite.
- Console SESAM, page Tarifs : interrupteur « Essai gratuit d'un mois à l'inscription » (table `platform_settings`, migration `202610290900_platform_settings.sql`). Désactivé : le compte est créé en « activation en attente », l'inscrit arrive sur la page Abonnement et son espace s'ouvre au paiement du premier mois (mensualité + frais de mise en place définis dans les tarifs, une seule fois). La page Commencer, l'e-mail de bienvenue et l'alerte de l'équipe s'adaptent.
- Tests `TrialSettingTest`.

## 2026-10-28 — Audit de production (sesam-tech.com) et corrections

- Push FCM sans l'extension cURL (absente du PHP 8.4 du serveur) : repli sur les flux HTTPS natifs. Les notifications push et les rappels du matin, qui plantaient sur `curl_init()`, fonctionnent de nouveau.
- Statistiques : l'alias SQL `returning` est un mot réservé de MariaDB 10.11 (production) ; renommé `returning_clients`.
- Connexion Google d'un compte existant : le paramètre `:google_sub` était utilisé deux fois dans la même requête (erreur HY093).
- Rappels du matin (7 h) et relances de loyer (8 h) à l'heure de Dakar quel que soit le fuseau du serveur (Europe/Berlin) : option `--heure`, crons horaires, une fois par jour.
- `www.sesam-tech.com` redirigé (301) vers `sesam-tech.com`, seul domaine autorisé par Firebase Auth.
- Adresses de démonstration `exemple.*` jamais envoyées en SMTP.
- Serveur : `session.gc_divisor = 1000` et `gc_probability = 1` dans le `php.ini` de PHP 8.4 (sauvegarde `php.ini.bak-audit-20261028`).
- Vérifié en production : API NabooPay (transactions), webhook protégé (401 sans signature), domaines Firebase, push FCM, statistiques, redirection www, tâches planifiées.

## 2026-10-27 — Popups de bienvenue animés (espace connecté et site public)

- À la connexion, un popup animé accueille l'utilisateur, une seule fois par connexion (`WelcomeService`, `partials/welcome.php`, `welcome.css`, `welcome.js`) :
  - **Première connexion d'un commerce** : visite guidée en 3 écrans adaptée au métier (Réservation, Boutique, Immo) — fusée, planète, confettis — avec les 3 premiers gestes en liens directs, « Suivant », « Passer », glisser sur téléphone.
  - **Retour d'un commerce** : « Bonjour / Bon après-midi / Bonsoir » selon l'heure, soleil levant, soleil ou lune, et les chiffres du jour qui défilent (rendez-vous du jour et à confirmer ; commandes à traiter et stock bas ; loyers à encaisser et en retard), plus les notifications non lues.
  - **Équipe SESAM** : tableau de bord animé et chiffres de la console (nouveaux commerces, essais qui finissent, abonnements échus).
- Site public : popup ouvert 2 secondes après l'arrivée, une fois par visite et par page (rien sur Contact et Devis). Accueil, Services, Offres, À propos et Réalisations tirent une variante au hasard parmi 7 (bienvenue / fusée, 1 mois offert / cadeau, démo en direct / téléphone, Réservation / agenda, Boutique / sac, Immo / immeuble, Sites web / navigateur) ; chaque page service affiche le popup dédié à son service. Puces d'avantages, bouton d'essai (service présélectionné), lien vers la démo, « Non merci ».
- Fermeture fiabilisée : croix, « Non merci », fond ou Échap ferment immédiatement le popup (un seul écouteur, plus aucun clic capté pendant l'animation de sortie) ; un visiteur qui ferme un popup n'en revoit plus aucun pendant sa visite, même en changeant de page.
- Format compact sur téléphone : illustration réduite, texte limité à 3 lignes, avantages en ligne, deux boutons côte à côte ; le popup occupe environ 40 % de l'écran.
- Accessibles : fenêtre de dialogue, focus gardé dans le popup, Échap pour fermer, animations coupées si l'appareil demande moins de mouvement ; sur téléphone, le popup monte du bas de l'écran. Aplats SESAM, sans dégradé.
- Tests `WelcomeTest`.

## 2026-10-27 — Réservation en ligne guidée pas à pas, confirmation refaite

- Page publique du salon : la réservation devient un parcours guidé, une étape à la fois (Prestation → Personne → Date et heure → Vos infos), avec barre de progression cliquable, bouton « Continuer » collé en bas de l'écran, passage automatique à l'étape suivante dès qu'un choix est fait, retour possible à tout moment.
- Créneaux groupés par moment de la journée (matin, après-midi, soir) ; si un jour est complet ou fermé, le prochain jour libre est cherché automatiquement et proposé en un clic.
- Dernière étape : récapitulatif modifiable (prestation, personne, date, prix, acompte), champs vérifiés avant l'envoi (nom, numéro), bouton « Confirmer mon rendez-vous » / « Confirmer et payer l'acompte », double envoi impossible. Sans JavaScript, le formulaire complet reste utilisable.
- Nouvelle page de confirmation aux couleurs du salon (`portal/salon/confirmation.php`) : coche ou horloge selon que le rendez-vous est confirmé ou en attente, récapitulatif, « Et maintenant ? » en 3 points, WhatsApp et retour au salon. Le message technique « mode mock » n'est plus montré aux clients (page de confirmation générique aussi).
- Atouts du salon sur une ligne compacte sur téléphone ; phrase d'accueil de la page Immo plus engageante.
- Dégradés retirés (bannières salon et boutique, en-tête Immo) : aplats et motif de points, selon la charte.
- Tests : parcours et confirmation (`ReservationModuleTest`, `PublicPortalTest`).

## 2026-10-26 — Portails publics et photo des biens Immo

- Portails publics Réservation, Boutique et Immo : mise en page plus compacte sur mobile et tablette, cartes Boutique/Réservation limitées à 320 px, deux cartes par ligne sur téléphone, boutons Commander/Réserver plus petits (rayon 7 px).
- Sesam Immo : photo facultative à l’ajout/modification d’un bien (`loyer_properties.image_path`), affichée sur le portail public.

## 2026-10-25 — Mise en ligne https://sesam-tech.com

- Déploiement VPS Webuzo (`/home/jomas/sesam-tech.com`), document root `public/`, PHP 8.4-FPM.
- Nginx reverse proxy + Apache, HTTPS Let's Encrypt, redirection HTTP→HTTPS, cache proxy désactivé.
- Base `jomas_sesam`, migrations appliquées, compte staff seedé.
- Derrière le proxy local : `X-Forwarded-Proto` reconnu ; requêtes `HEAD` traitées comme `GET`.

## 2026-10-25 — NabooPay live (abonnements)

- Passerelle NabooPay API v2 : création de transaction, lecture du statut, en-tête webhook `X-Signature`, payload `order_id` / `transaction_status`.
- Abonnement commerce : avec `NABOOPAY_API_KEY` + `NABOOPAY_LIVE=1`, redirection vers le checkout NabooPay (Wave / Orange Money) ; confirmation via webhook ou GET transaction au retour.
- Bac à sable SESAM conservé si `NABOOPAY_LIVE=0` (tests PHPUnit inclus).
- Localhost : URLs de retour non envoyées à NabooPay (refusées) ; webhook nécessite une URL HTTPS publique.

## 2026-10-25 — Densité visuelle adaptée aux écrans

- Titres, leads, boutons, barre, héros et cartes bento un peu plus compacts.
- Scène héros : hauteurs 460 px desktop, 360 px tablette, 300 px mobile ; maquettes et toasts réduits.

## 2026-10-25 — Visuels Digital et Réservation retravaillés

- Sesam Digital : personnage noir avec outils de création, site sur desktop, app sur tablette et mobile.
- Sesam Réservation : personne qui réserve un créneau, téléphone avec notification « RDV confirmé ».

## 2026-10-25 — Nouveaux visuels héros (Boutique, Réservation, Immo, Web)

- Quatre photos neuves : boutique (téléphone en main), réservation (agenda multi-activités), immo (gestion locative), web (création site et application).
- Anciennes images CDC / commerçant / hero-card / desk / boutique.svg et doublons retirés de `public/assets/images`.
- Réservation présentée pour toute activité qui prend des rendez-vous, plus seulement les spas. Mot rotatif : « vos réservations ».
- Sesam Digital : titre et texte orientés sites web et applications.

## 2026-10-25 — Héros d'accueil : scène synchronisée au mot rotatif

- Quand le titre passe de boutique → salon → loyers → site, la scène de droite change avec : photo réelle, maquette du service, pastille et toast adaptés.
- Photos déjà présentes dans le projet (commerçant, réservation, loyer, agence). Transition croisée. Sans JS, le mot continue de tourner ; la première scène reste affichée.
- `prefers-reduced-motion` : première scène figée.

## 2026-10-21 — Tâches planifiées et e-mail de secours pour les clients

- Secours e-mail : quand WhatsApp ne remet pas un message (pas encore branché, échec, pas de numéro), le client qui a une adresse e-mail le reçoit par e-mail — confirmations de réservation et de paiement, confirmation / déplacement / annulation par le salon, rappel de rendez-vous, relances de loyer (manuelles et automatiques). `MessagingService::notifyClient`.
- Les scripts `bin/rappels-clients.php` et `bin/immo-relances.php` initialisent l'e-mail (le secours fonctionne aussi en tâche planifiée).
- Tâches planifiées Windows (dossier SESAM du Planificateur : rappels du matin 7 h, rappels clients toutes les 10 min, relances loyers 8 h), sans fenêtre, journal `storage/logs/taches.log` : `bin/windows/installer-taches.ps1`.
- Guide production (cPanel, crontab) : `docs/taches-planifiees.md`.
- Un rappel est « réservé » avant envoi : même si la tâche planifiée et l'envoi à la visite tournent en même temps, il ne part qu'une fois.
- Jamais d'envoi réel vers les adresses d'exemple ou de test (`example.com`, `.test`, `.local`…), même en SMTP : les comptes de démonstration et les tests ne reçoivent rien.
- Tests : secours e-mail (Réservation, Immo) ; 143 tests au vert.

## 2026-10-21 — Envoi d'e-mails par SMTP

- Client SMTP sans bibliothèque (`app/Core/Mail/SmtpClient.php` : SSL 465 ou STARTTLS 587, AUTH LOGIN, certificats vérifiés) et `Mailer` (texte + HTML aux couleurs de SESAM ; pilotes `smtp`, `log`, `off`). Réglages dans `config/mail.php`, valeurs et mot de passe dans `.env` (`SMTP_*`, `MAIL_*`).
- Mot de passe oublié : le lien de réinitialisation part par e-mail (valable 1 h, aucun indice si l'adresse est inconnue), puis une alerte confirme le changement de mot de passe. Le lien créé par l'équipe SESAM est aussi envoyé par e-mail.
- E-mail de bienvenue à l'inscription ; les rappels et messages par e-mail des commerces partent par SMTP dès que `MAIL_DRIVER=smtp` (WhatsApp reste simulé).
- Script `php bin/test-email.php [adresse]` : vérifie la connexion SMTP puis envoie un e-mail de test. Les tests automatiques n'envoient jamais d'e-mail (`MAIL_DRIVER_OVERRIDE=log`).
- Tests `MailTest` ; 142 tests au vert.

## 2026-10-20 — Notifications de l'équipe SESAM

- Support : le rôle n'avait aucun droit ; il voit désormais les commerces et les abonnements en lecture (migration `202610201100_staff_roles_notifications.sql`) et reçoit inscriptions, fins d'essai et essais perdus. Le commercial reste volontairement sans accès aux commerces.
- Nouveaux rappels du matin : essais terminés la veille sans abonnement (`sesam.lost`), abonnements toujours impayés plus d'une semaine après l'échéance (chaque lundi), demandes de devis du site non traitées depuis plus de 2 jours, prospects à relancer et devis sans réponse depuis 7 jours (`sesam.agency`), factures agence en retard (`sesam.invoices`).
- Encaissement saisi dans la console : le reste de l'équipe finance est prévenu (pas la personne qui l'a saisi).
- Sécurité (`sesam.security`) : nouveau compte créé dans l'équipe, et connexion bloquée après 5 mots de passe faux sur un compte de l'équipe (une alerte par jour et par adresse).
- Tests `StaffNotificationsTest` ; 140 tests au vert.

## 2026-10-20 — Réservation : rappels clients et messages fiables

- Les rappels « la veille » étaient programmés mais jamais envoyés (aucun envoi des rappels dus) : nouvel envoi `ReminderPump`, script `bin/rappels-clients.php`, et à défaut les visites de l'application et des pages publiques (au plus toutes les 5 minutes).
- Les réservations en ligne confirmées automatiquement ou après paiement de l'acompte reçoivent maintenant leur rappel.
- Le rappel ne dépend plus des droits de la personne qui confirme (une employée sans droit d'envoi le déclenchait en silence pour rien).
- Rappels rattachés au rendez-vous (migration `202610201000_reservation_reminders.sql`, colonne `reference`) : annulés si le rendez-vous est annulé, terminé ou marqué absent, remplacés s'il est déplacé ; jamais de rappel pour un rendez-vous annulé ni de doublon.
- Rendez-vous pris moins de 24 h avant : rappel 3 h avant.
- La cliente reçoit un message WhatsApp quand le salon confirme sa demande, déplace ou annule son rendez-vous.
- Salon : nouveau rappel du matin « Réservations à confirmer » (`reservation.pending`), en « attention » si la première a lieu dans moins de 2 jours.
- Message de confirmation après paiement de l'acompte : date lisible (« mardi 21/10 à 12:30 ») au lieu de la date brute.
- Tests `ReservationNotificationsTest` ; 138 tests au vert.

## 2026-10-20 — Notifications de la boutique complétées

- Stock : une sortie ou un ajustement manuel qui fait passer un produit sous son seuil déclenche aussi l'alerte « Stock bas » / « Produit épuisé » (avant : seulement à la confirmation d'une commande).
- Commande en ligne : la notification signale les produits que la commande va épuiser ou faire passer sous le seuil (niveau « attention »), et rappelle que le client l'envoie aussi sur WhatsApp.
- Rappels quotidiens : nouvelle catégorie « Commandes à traiter » (`boutique.pending`, chaque matin, commandes encore « nouvelles » des jours précédents) et point du stock le lundi (produits publiés épuisés ou en stock bas).
- Abonnement : le commerce reçoit une confirmation quand il paie en ligne ou quand SESAM enregistre son paiement.
- Test `testShopNotificationsCoverStockOrdersAndSubscription` ; 136 tests au vert.

## 2026-10-19 — Photos des prestations, pages publiques salon et boutique refaites

- Réservation : photo facultative pour chaque prestation (migration `202610191100_reservation_images.sql`, WebP compressé, « Retirer la photo »), vignette dans la liste, servie sur `/t/{slug}/prestation/{id}/image`.
- « Mon salon » : le logo (photo de profil) et la photo de couverture sont en tête des réglages, avec des libellés clairs ; ils s'affichent en haut de la page publique.
- Page publique du salon entièrement refaite (`sl-*`, `salon.css` autonome) : bannière avec couverture, logo et statut ouvert / fermé, prestations en cartes avec photo par catégorie, étapes numérotées, choix du jour et des créneaux en boutons, récapitulatif collant (carte à droite sur ordinateur, barre en bas sur téléphone), horaires avec le jour en cours, contact et WhatsApp.
- Page publique de la boutique refaite : bannière avec logo, statut des commandes, trois atouts, recherche et catégories qui restent visibles au défilement, cartes produit avec remise en pourcentage, bouton panier dans l'en-tête, volet de commande sans clignotement au chargement, fiche produit agrandie. Le panier et la commande WhatsApp fonctionnent comme avant.
- Toujours sans dégradé ni bordure colorée ; les couleurs suivent le thème choisi par le commerce. 135 tests au vert.

## 2026-10-19 — Notifications push Firebase, « Contrats de bail »

- Immo : « Baux » devient « Contrats de bail » dans le menu et sur tous les écrans.
- Sécurité : la clé du compte de service Firebase était téléchargeable depuis la racine du site (WAMP) ; elle est déplacée dans `storage/firebase/` (fermé au web) et tout fichier `.json` à la racine est refusé par `.htaccess`.
- Notifications refaites (migration `202610191000_push_notifications.sql`) : cloche pour les commerces et pour la console SESAM, catégories, lien vers l'écran concerné, « tout marquer comme lu », préférences par catégorie (dans l'appli / sur l'appareil), appareils enregistrés, anti-doublon des rappels.
- Push Firebase Cloud Messaging (API HTTP v1) : jeton d'accès signé avec la clé du compte de service (OpenSSL, sans bibliothèque), certificats racine Mozilla fournis (`storage/certs/cacert.pem`, absents du PHP de WAMP), appareils désinscrits oubliés automatiquement. SDK web Firebase 10.12.2 copié dans `public/assets/vendor/` (la CSP n'autorise que les scripts du site), service worker servi à la racine (`/firebase-messaging-sw.js`), bandeau d'activation, alerte en direct quand l'application est ouverte.
- Événements notifiés : réservation en ligne, acompte payé, commande en ligne, stock bas, loyer payé en ligne, intervention urgente, paiement reçu, inscription d'un commerce, demande de devis, abonnement payé. Rappels quotidiens (`DailyNotifications`, `bin/notifications-quotidiennes.php`) : programme du salon, échéances à 3 jours, loyers en retard, fins de contrat, essais et abonnements, synthèses SESAM.
- Tests : `NotificationsTest` ; 135 tests au vert. Test réel : authentification Google et API FCM v1 vérifiées.

## 2026-10-18 — Sesam Immo, inscription « Commencer » avec essai, console réorganisée

- **Loyer devient Sesam Immo** (même clé interne `loyer`, nouvelles adresses `/immo/...` ; les anciennes `/loyer/...` et `/services/loyer` redirigent). Migration `202610181400_immo.sql` : fiches biens et logements détaillées, baux complets (charges, caution, préavis, fin), paiements partiels (`immo_payments`, historique repris), réglages (`immo_settings`), journal des relances, interventions, fiches locataires, permission `loyer.settings`.
- Espace Immo refait : Accueil avec alertes, Loyers (onglets, encaissement total ou partiel, lien de paiement en ligne, relance WhatsApp, quittance / reçu imprimable), Locataires, Biens, Baux, Interventions, Relances, Réglages. Échéancier automatique jusqu'au mois suivant, sans inventer d'impayés passés. Un paiement en ligne confirmé devient un encaissement du loyer (`ImmoLedger`). Relances automatiques selon les réglages, une fois par jour et par loyer ; script `bin/immo-relances.php` pour une tâche planifiée (dossier `bin/` fermé au web).
- Site : « Commencer » remplace « Demander un devis » dans la barre et dans toutes les sections ; lien « Connexion ». Page d'inscription `/commencer` : le commerce, son responsable et un essai d'un mois sont créés, puis le compte est connecté dans son espace. Le devis est réservé aux sites web, avec un formulaire détaillé (projet, type, pages, fonctions, domaine, budget, délai). Migration `202610181410_saas_signup.sql`.
- Abonnement : état « Essai gratuit » / « Essai terminé », bandeau dans l'espace, page **Abonnement** dans chaque espace avec réabonnement simulé (Wave / Orange Money) réservé au responsable (`subscription.pay`) ; espace en pause à la fin de l'essai ou 5 jours après une échéance non payée.
- Console SESAM : menu en rubriques (Pilotage, Commerces, Agence, Finance, Administration), essais en cours et à convertir, origine des commerces, essai de 15 jours ou 1 mois à la création, prolongation d'essai, page **Demandes de devis** (projets de site détaillés), page **Statistiques** (MRR, revenu moyen, conversion des essais, revenus par module, inscriptions par mois, agence).
- Démo : réglages et données Immo complètes, salon inscrit seul en essai (`demo.essai@sesam.local`), 2 demandes de devis site web. Tests : `ImmoModuleTest`, `SaasSignupTest` ; tests existants adaptés (132 au vert).

## 2026-10-18 — espaces de gestion compacts sur mobile, démo complète, paiements simulés lisibles

- Mobile (moins de 640 px), tous les espaces de gestion : nouvelle feuille `mobile-app.css` chargée en dernier par `layouts/tenant` et `layouts/staff`. Textes, titres, boutons, cartes, chiffres clés (2 par ligne), listes, badges, tableaux (une ligne par cellule, défilement dans le cadre) et formulaires réduits d'un cran ; les champs gardent 16 px pour éviter le zoom d'iOS. Message des créneaux du rendez-vous sur toute la largeur.
- `boutique-admin.css` (composants communs du back-office) est chargé pour tous les modules, Loyer compris.
- Paiements : fiche en forme de reçu (Paiement reçu / En attente / Annulé, montant, date, transaction, historique en clair) avec « Simuler le paiement du client » ; écran de simulation façon NabooPay (Wave ou Orange Money, Payer, abandon). Un paiement confirmé solde aussitôt l'échéance de loyer liée. La fiche d'un rendez-vous affiche l'état de son acompte.
- Démo (`database/seeds/demo.php` + `demo_full.php`) : gérant et employé pour chaque commerce, clientes avec étiquettes et notes, rendez-vous sur 4 semaines avec acomptes, commandes de boutique avec stock et paiements, biens / baux / 6 mois de loyers (payés, en retard, lien en attente), pipeline complet de l'agence, notifications. Chaque bloc n'est ajouté qu'une fois. Sauvegarde avant chargement : `database/backups/sesam-avant-demo-complete-2026-10-18.sql`.

## 2026-10-18 — rendez-vous sans fiche préalable, produit dans la commande WhatsApp

- Réservation, « Nouveau rendez-vous » : plus besoin d'avoir des clientes en base. Le nom et le numéro se saisissent à la main (tous deux obligatoires pour une nouvelle cliente) et la fiche est créée à l'enregistrement. Pendant la frappe, les clientes déjà enregistrées apparaissent (recherche par nom ou par numéro, `GET /reservation/clientes?q=`) ; un clic ou Entrée remplit le nom et le numéro. Un même numéro écrit autrement (« +221 77… », « 77… ») retrouve la même fiche. Le formulaire ne charge plus toute la liste des clientes.
- Champs des espaces connectés : le focus a la même apparence partout (halo bleu) au lieu de la couleur de la rubrique, qui ressemblait à une erreur sur les rendez-vous.
- Boutique : chaque produit a sa fiche publique `/t/{slug}/produit/{id}` (photo, prix, description, « Ajouter au panier », question sur WhatsApp) avec les balises Open Graph. Dans le message de commande WhatsApp, chaque produit est suivi de son lien : WhatsApp affiche la carte du premier produit (photo, nom, prix) dans la discussion. L'aperçu demande une adresse publique (`APP_URL`) joignable par WhatsApp, donc pas en local.

## 2026-10-18 — console d'administration SESAM, responsive de tous les espaces

- Audit : l'accueil du personnel n'affichait que des raccourcis ; aucun suivi des revenus, des paiements d'abonnement, des relances ni de l'usage des commerces ; pas de notes internes ; pas de grille tarifaire ; le rôle Finance ne voyait pas les abonnements.
- Migration `202610181300_admin_console.sql` : paiements d'abonnement (`tenant_subscription_payments`), notes internes (`tenant_notes`), tarifs par module (`saas_prices`), permission `revenue.view` (Super Admin, Admin, Finance) ; Finance voit aussi les commerces et gère les abonnements.
- Tableau de bord `/espace` du personnel : revenu mensuel récurrent, encaissé du mois et tendance, reste à encaisser, commerces actifs, histogramme 12 mois (abonnements / agence), abonnements à relancer, commerces par module, pouls de l'agence, dernières demandes de devis, derniers encaissements, nouveaux commerces. Chaque bloc suit les droits du rôle.
- Pages `/revenus` (par mois, détail, échéances à 30 jours, export CSV) et `/tarifs`.
- Fiche commerce : encaissement d'un paiement (prolonge l'accès d'autant de mois, réactive un commerce suspendu), historique et total payé, utilisation sur 30 jours (clients, activité du module, dernière connexion), notes internes.
- Démo : quelques mois d'abonnement encaissés pour chaque commerce, Loyer laissé échu pour illustrer les relances.
- Responsive : contrôle de chaque page à 375 px et 768 px (console, espaces Réservation, Boutique, Loyer, pages publiques des commerces, site). Corrigés : grilles qui s'élargissaient à la taille d'un tableau (`/revenus`, `/boutique/stock`), formulaire de réservation public élargi par la bande des jours, e-mails longs dans les listes, bandeau incliné de l'accueil, chiffres clés serrés sur tablette.
- Tests : `AdminConsoleTest` (paiement, prolongation, revenus, export, notes, tarifs, droits).

## 2026-10-18 — module Réservation complet

- Audit : prestations sans modification, équipe sans fiche ni absences, pas d'agenda, pas d'horaires du salon, statuts « Terminé » et « Absent » inutilisables, page publique avec une date/heure à saisir au hasard, rappel envoyé avec un modèle de test.
- Migrations `202610181200_reservation_complete.sql` (réglages, horaires, fermetures et absences, prestations par personne, prix, origine et paiement des rendez-vous) et `202610181210_reservation_templates.sql` (modèle de rappel `rdv_rappel`).
- Moteur de créneaux libres (`AvailabilityService`) : horaires du salon, fermetures, horaires et absences de chaque personne, prestations qu'elle réalise, rendez-vous existants, pause entre deux clientes, délai minimum, réservation au plus tard N jours avant. Tant qu'un salon n'a pas réglé ses horaires, seules les disponibilités de l'équipe comptent.
- Espace salon : accueil (journée, demandes à confirmer, chiffre de la semaine, taux d'absence, lien à partager), agenda jour/semaine par personne, rendez-vous filtrables (période, statut, personne, recherche), statuts Confirmé / Terminé / Absent / Annulé, déplacer, payé, acompte, WhatsApp à la cliente, création rapide d'une cliente, prestations par catégorie modifiables, fiche de chaque personne (prestations, horaires, absences), page « Mon salon » (présentation, WhatsApp, horaires en deux plages, fermetures, règles de réservation, confirmation automatique). Historique des rendez-vous sur la fiche cliente.
- Page publique `/t/{slug}` : prestations par catégorie, choix de la personne (seulement celles qui font la prestation), jours et créneaux libres réels, résumé, acompte Wave / Orange Money si demandé, attribution automatique d'une personne libre.

## 2026-10-18 — refonte complète du site public

- Nouvelle identité « éditorial & aplats », calée sur le logo : bleu `#0a6cf0` en couleur principale, marine `#071a3d` pour le texte et les fonds sombres, cyan `#3cc4f5` en accent, bleus clairs pour les grands aplats. Tailles de texte, boutons et blocs réduites d'un cran. Aucun dégradé ni bordure colorée sur toute la plateforme (site, espaces connectés, boutique).
- Une seule feuille pour le site public : `public/assets/css/site.css` (+ `site.js` : apparitions au défilement, halo qui suit le pointeur). Les anciennes `public.css` et `public-glass.css` sont rangées dans `_archive/2026-10-18-ancien-design/`.
- En-tête en pilule flottante ; accueil refait : titre géant avec mot qui change, scène de maquettes animées, bandeau défilant, mosaïque des services, focus Boutique, étapes, chiffres, offres, appel final ; pied de page sombre avec grand logotype.
- Maquettes d'interface dessinées en CSS (`app/Views/public/mocks/`) à la place des photos : site, boutique sur téléphone, réservation, loyer.
- Toutes les pages publiques reprises dans ce style : Services, chaque service, Sesam Boutique, À propos, Offres, Contact, Réalisations, Devis.
- Espaces connectés : à partir de 768 px, le menu reste ouvert avec le nom de chaque lien.

## 2026-10-18 — Sesam Boutique, retrait de Garde et Inscription

- Les modules Garde et Inscription sont retirés : pages publiques, liens de navigation, espaces commerçant, routes, permissions, tables (`202610181100_remove_garde_inscription_commande.sql`), styles et tests. Les commerces de ces secteurs sont suspendus, pas effacés. Les fichiers retirés sont gardés dans `_archive/2026-10-18-garde-inscription-commande/` (non servi) ; la base d'avant est sauvegardée dans `database/backups/sesam-avant-boutique-2026-10-18.sql`.
- Le module Commande devient **Sesam Boutique** (`202610181000_create_boutique.sql`) : produits avec photo, prix, ancien prix et catégorie ; stock suivi avec historique et alerte ; commandes Nouvelle → Confirmée → Livrée (ou Annulée), le stock baisse à la confirmation et revient à l'annulation ; fiches clients reliées ; page « Ma boutique » (nom, logo, couverture, couleur, numéro WhatsApp, horaires, livraison, réseaux sociaux, ouverture).
- Page publique `/t/{slug}` d'une boutique : catalogue, recherche, catégories, panier, puis « Commander sur WhatsApp » qui enregistre la commande et ouvre WhatsApp sur le numéro du commerçant avec le message rédigé.
- Site : en-tête sur une ligne (logo, liens, « Demander un devis ») ; sous 1 180 px, le bouton menu est tout à droite après le devis. Nouvelle page `/services/boutique` ; `/services/commande` redirige vers elle.
- `Html::url()` garde désormais la requête (`?…`) et l'ancre (`#…`) au lieu de les encoder.

## 2026-10-11 — accueil plus compact, logo plus grand

- Sections principales de l'accueil (hero, repères, étapes, chapitres, fonctions communes, bandeau final) et le contenu du pied de page : 90 % de la largeur, plafond 1 400 px. La barre de navigation n'est pas modifiée.
- Textes, marges, photos des chapitres et orbite légèrement réduits. Boutons inchangés.
- Logo de l'en-tête : 6,5 rem de haut, pour que le visuel se lise.

## 2026-10-11 — bleu clair aligné sur le bandeau final

- Bouton devis (en-tête et hero), bouton menu, pastille « Sites web » de l'orbite et liens « Voir … » : bleu uni `#087cff`, survol `#0349b8`. Pas de dégradé sur ces boutons.
- Centre de l'orbite, fonctions communes et pied de page : même dégradé que `.home-finale`. Halo et trait de la planète éclaircis dans ces bleus.

## 2026-10-11 — fond public plus blanc, boutons unis

- Fond des pages publiques blanc plein. Les halos de couleur derrière les pages sont retirés.
- Boutons principaux, bouton menu et liens « Voir le service » : bleu uni `#0349b8`, survol marine uni. Plus de dégradé sur ces boutons.

## 2026-10-08 — comptes de démonstration

- `database/seeds/demo.php` : un compte par rôle du personnel SESAM et un commerce responsable par module (Réservation, Commande, Loyer, Inscription, Garde). Mot de passe commun documenté dans `README.md`. Relancer le script réapplique ce mot de passe.

## 2026-10-08 — espaces connectés : socle « Aurora Glass »

- Nouvelle feuille `app-glass.css`, chargée après `sesam.css` par les layouts `tenant`, `staff`, `auth` et `main`. `sesam.css` n'est pas modifié.
- Fond presque blanc, rail en verre clair avec icônes teintées par rubrique, barre du haut en verre, bandeaux en dégradé bleu avec sphères, cartes et indicateurs arrondis, boutons primaires en dégradé, champs, tableaux, pastilles, alertes et modale harmonisés avec le site public.
- Formulaires de page entière (`form.stack`) : vitre centrée de 46 rem.
- Page de connexion : halos, carte en verre, bouton en dégradé.
- Périmètre : socle commun uniquement. Les pages métier (Réservation, Commande, Loyer, Inscription, Garde) et la console ne changent que par héritage ; leur mise en page propre reste à traiter.

## 2026-10-08 — fond plus clair, barre pleine largeur

- Fond du site public presque blanc (léger dégradé bleuté), halos très atténués.
- Bandeau « Commerces à Dakar · Pilotage depuis New York » supprimé. Le bouton « Demander un devis » reste dans la barre.
- Barre de navigation sur toute la largeur de l'écran, sans arrondi ni cadre (simple filet clair en bas). Contenu centré sur 72 rem.

## 2026-10-08 — refonte « Aurora Glass » du site public

- `public-glass.css` réécrite : fond dégradé fixe avec trois halos flous, cartes en verre (flou, reflet intérieur, grand rayon), accents par métier (`data-skin`), boutons dégradés.
- Accueil : ordre hero → repères chiffrés → « Comment ça marche » en 3 cartes à icônes → six métiers → un chapitre par métier (image collante, pastille, étapes numérotées) → fonctions communes marine → bandeau final bleu. Textes, structure de l'en-tête, orbite et tailles de boutons inchangés.
- Pages service : héros à deux colonnes dès 960 px (icône, texte, photo cadrée), étapes numérotées, scène en verre (marine pour l'agence), encadré « De votre côté » collant, bandeau d'appel au devis avant les autres services.
- Contact : héros avec halo, trois cartes à icônes, double appel à l'action. Icône `bell` corrigée.
- Corrections : colonne du héros service écrasée à 768 px, pastille Commande rognée à 320 px, espacement des liens de la barre à 768 px.

## 2026-10-08 — thème public glassmorphism

- Feuille `public-glass.css` activée sur tout le site public : fond doux, barre d’annonce, en-tête et cartes en verre, pied de page vitré.
- Accueil : bandeau de repères chiffrés sous le hero. Pages service et contact : héros et blocs en verre, barre de couleur par métier.

## 2026-10-08 — hero blanc, menu et orbite

- Le bloc d'accueil est sur fond blanc. Le texte sous le titre est plus court, et sur mobile les deux boutons restent sur une seule ligne, plus petits.
- Mobile : trois liens, puis un bouton menu pour le reste. Tablette : cinq liens, le reste dans le même bouton. Grand écran : tous les liens.
- Les chiffres de la planète sont retirés. Les bulles Commande et Garde se posent sur le bord de l'orbite, autour du centre.

## 2026-10-08 — accueil en cartes

- Sous le hero, les sections deviennent des cartes blanches arrondies sur fond clair : titres plus grands, photos arrondies, étapes en pastilles, formules en trois cartes (Professionnel en bleu), bandeau marine pour les fonctions communes. Le bloc du hero n'est pas modifié.

## 2026-10-08 — planète numérique de l'orbite

- Derrière les bulles, un disque rond porte méridien, équateur, graduations et douze repères (015 à 345). Les bulles et le centre restent au-dessus et cliquables.

## 2026-10-08 — hero tablette et grand écran

- Tablette : l'orbite prend toute la largeur du bloc d'accueil, et les bulles, le centre et les icônes grandissent avec elle.
- La bulle mise en avant par l'animation passe en bleu, texte et icône blancs.
- Grand écran : le bloc, le titre, le texte et l'orbite sont plus grands. Les boutons « Demander un devis » et « Tous les services » gardent 40 px de haut.

## 2026-10-08 — accueil détaillé

- Sous le hero, l'accueil reprend le cahier : agence et SaaS, formules Essentiel / Professionnel / Sur mesure, cinq projets web par mois, une section par métier, fonctions communes (lien, Wave, Orange Money, WhatsApp), puis les trois étapes site → outil → gestion.
- La barre ne contient plus Services ni Offres. Les six métiers s'affichent avec leur nom, en marine. Contact et le devis restent. Le hero n'est pas modifié.
- Ces liens restent dans le pied de page.

## 2026-10-08

- Pages `/services/agence`, `/reservation`, `/commande`, `/loyer`, `/inscription`, `/garde` : une seule offre, scène du cahier, parcours et « De votre côté ». `/services` reste la vue d'ensemble.
- Inconnu `/services/portail` : 404.

## 2026-10-07

- Refonte des espaces (hors phase, demande utilisateur) : verre, couleurs pleines, modales de formulaire.
  - Aucun dégradé dans `sesam.css` : `--sesam-gradient` et `--sesam-dash-hero` retirés, bandeaux et barres en couleurs pleines. Nouveaux tokens : teintes de rubrique (sarcelle, émeraude, ambre, corail, ardoise), états, verre, ombres, `--sesam-z-modal`.
  - L'espace personnel SESAM (`layouts/staff.php`) reprend la coque du commerce : rail d'icônes, barre flottante, `body.is-app`. Chaque entrée de menu a une teinte (`data-tone`), reprise sur le titre de la page.
  - Densité outil : texte 15 px, boutons et champs 38 px, `.btn-sm`, `.btn-danger`, en-têtes de tableau compacts.
  - Ajouts et modifications en modale plein écran : 38 liens `data-modal` et 12 formulaires `data-modal-form`, plus les actions rapides du tableau de bord. `Kernel` transforme une redirection en `200` + `X-Sesam-Location` quand la requête porte `X-Sesam-Modal: 1`, pour que le message flash atteigne la page suivante.
  - Contraste : bouton primaire toujours bleu ; l'élément actif du rail est un anneau coloré, pas un fond ambre ou vert sous du texte blanc.
  - Tests : `ModalRequestTest` (3 tests) ; `AgencyTest` suit le nouveau balisage du menu. 124 → 127 tests.
- Phase 20 : tests.
  - Base commune `tests/Support/IntegrationTestCase.php` ; nouveaux fichiers `WebhookTest`, `CrossTenantAccessTest`, `SideEffectsTest` ; garde-fous responsive dans `DesignSystemTest` ; capture d'en-têtes dans `RequestPathTest`. 99 → 124 tests.
  - Correctifs trouvés par les tests : en-tête de signature NabooPay perdu par `Request::capture` (tout webhook réel refusé) ; webhook tardif capable de défaire un paiement réussi ; fiche client fantôme laissée par une demande refusée sur le portail.
  - Correctif responsive trouvé au contrôle navigateur : 9 listes (Réservation, Commande, Loyer, Inscription) débordaient de l'écran sous 430 px. Leurs tableaux sont dans `.table-wrap`, qui passe en `position: relative` (le libellé masqué « Action » de la fiche produit Garde élargissait la page).
- Phase 19 : sécurité et performance.
  - Audit : XSS (toutes les sorties de vues), injection SQL, redirections, isolation tenant, uploads, CSRF. Aucune faille trouvée ; constats dans `docs/SECURITY.md`.
  - En-têtes : `SecurityHeaders` remplace `SecurityHeadersMiddleware` et s'applique aussi aux pages d'erreur (avant, une 403/404/419/429/500 partait sans en-tête). Ajout de la CSP, de `Permissions-Policy`, COOP, HSTS en HTTPS, `Cache-Control: no-store` par défaut ; `X-Powered-By` retiré.
  - Les deux `onsubmit="return confirm(…)"` deviennent `data-confirm` (géré par `sesam.js`) pour respecter la CSP.
  - Session : mode strict, cookies seuls, vidage après 2 h d'inactivité ; nouveau jeton CSRF à la connexion.
  - Formulaire `/devis` : 5 demandes par IP et par heure, au-delà 429.
  - Base : une connexion PDO partagée par requête au lieu d'une vingtaine.
  - 3G / mobile : HTML compressé en gzip ; photos des services en WebP via `<picture>` (6 photos : 1,93 Mo → 0,47 Mo, JPEG en repli) ; logo des layouts 88 Ko → 21 Ko ; URL d'assets versionnées `?v=` ; `public/.htaccess` ajoute cache long et compression sous Apache.
- Phase 18 : page `/statistiques` pour chaque commerce (`StatisticsController`, `StatisticsService`, `StatisticsRepository`), entrée « Statistiques » dans le menu.
  - Période : 30 jours, 90 jours ou 12 mois, comparée à la période précédente.
  - Indicateurs communs : chiffre d'affaires encaissé avec variation, taux de paiement, panier moyen, impayés en retard (Loyer, Inscription) ou paiements en attente (autres secteurs), clients nouveaux / actifs / payeurs / fidèles, encaissements par mois (6 ou 12 mois), paiements demandés par statut, activité des fiches clients.
  - Bloc métier selon le secteur :
    - Réservation : rendez-vous, taux de confirmation, annulations, prestations les plus demandées.
    - Commande : commandes, valeur, livraisons à l'heure, produits les plus commandés.
    - Loyer et Inscription : attendu, encaissé, taux de recouvrement.
    - Garde : gardes assurées, unités entrées et sorties, lots à surveiller, produits les plus sortis.
  - Accès : permission `stats.view` (responsable, gérant), index `payments (tenant_id, status, paid_at)`. Pas d'export ni de statistiques globales SESAM sur cette page.
- Phase 17 : gestion commerciale de l'agence sur `/agence` (`AgencyController`, `AgencyService`), réservée au personnel SESAM. Synthèse : pipeline par étape, relances dues, jauge de capacité (5 projets démarrés par mois), devis en attente, reste à encaisser, encaissé du mois, demandes du site à suivre. Prospects : création, fiche, étapes manuelles (Nouveau → Négociation, Perdu), notes et date de relance, historique, vue Clients (prospects gagnés). Une demande du formulaire `/devis` devient prospect une seule fois. Devis par formule avec planchers du cahier (Essentiel ≥ 200 000, Professionnel ≥ 450 000 FCFA, Sur mesure libre) ; envoyé → étape « Devis envoyé », accepté → projet créé et étape « Gagné ». Projets : démarrage (6ᵉ du mois refusé, étape « Projet en cours »), livraison avec adresse du site (étape « Livré »). Factures par projet plafonnées au montant, paiement enregistré à la main (Wave, Orange Money, espèces, virement). Tables `agency_*`, permissions `agency.*`. Pas de PDF, d'envoi automatique ni de paiement en ligne des factures agence.
- Correctif d'affichage : `.btn-ghost` dans une `.dash-card` (bouton « Réinitialiser » de la console des commerces, invisible sur fond blanc).

- Phase 16 : console interne SESAM sur `/commerces` (`TenantConsoleService`). Vue globale : indicateurs (commerces, accès actifs / suspendus, abonnements échus, à renouveler sous 7 jours, sans abonnement), répartition par secteur, filtres recherche / secteur / accès / abonnement. Fiche `/commerces/{id}` : responsable, comptes, lien public, abonnement et historique. Abonnement mensuel : activation, renouvellement (+1 mois), suspension ; l'activation rouvre l'accès du commerce, la suspension le coupe (connexion et portail). Montant mensuel en saisie libre facultative (aucun tarif SaaS n'est écrit dans le cahier). Édition déplacée sur `/commerces/{id}/modifier`. Tables `tenant_subscriptions`, `tenant_subscription_events`, permissions `subscriptions.view` / `subscriptions.manage` (Super Admin, Admin). Pas de tickets ni de facturation automatique.
- Tests : `composer test` lance PHPUnit sans Xdebug. En mode `develop`, Xdebug garde la dernière exception avec sa pile, ce qui retenait un noyau entier (18 connexions PDO) et provoquait « Trop de connexions » MySQL.
- Phase 15 : portail public sans compte `/t/{slug}` (`PublicPortalService`, `layouts/portal.php`). Parcours par secteur : réservation, commande, inscription (acompte / 1re tranche bac à sable), loyer (catalogue + contact, sans encaissement), garde (calendrier, sans stock). Tenant lu depuis le slug, CSRF, limitation 12 POST / 15 min, confirmation PRG + jeton de session, WhatsApp mock. Lien affiché dans `/parametres`. Pas de QR, pas de logo commerce.
- Phase 14 : module Garde back-office (`DutyService`, personnel, calendrier, produits, lots, mouvements, alertes Expiré / Bientôt / OK). Routes `/garde/*`, KPI et navigation secteur garde. Le commerce est la pharmacie (pas de table pharmacies). Pas de portail public.
- Photo produit Garde : upload JPEG/PNG/WebP/GIF, conversion WebP, redimensionnement 960 px, compression (`config/uploads.php`). Fichiers sous `storage/uploads/` (hors public), lecture via `/garde/produits/{id}/image` isolée au commerce.

## 2026-10-06

- Tableau de bord commerce calqué sur la maquette premium (rail d’icônes, bandeau, KPI, calendrier, tableaux). Données métier réelles, charte bleu SESAM. Pas de vocabulaire médical. Espace personnel SESAM inchangé.
- Phase 12 : module Loyer back-office (`RentService`, biens/unités, contrats, 12 échéances auto, encaissement NabooPay bac à sable, relance mock). Routes `/loyer/*`, KPI et navigation secteur loyer.
- Phase 13 : module Inscription back-office (`EnrollmentService`, classes, types de frais, dossiers, tranches auto, encaissement bac à sable). Routes `/inscription/*`, KPI et navigation secteur inscription. Responsables via clients CRM ; pas de portail public.
- Phase 11 : module Commande back-office (`OrderWorkflowService`, produits, commandes/lignes, mesures historisées, workflow statuts, livraison, acompte paiement). Routes `/commande/*`, KPI et navigation secteur commande. Photo = URL saisie (pas d’upload). Pas de portail public.
- Phase 10 : module Réservation back-office (`BookingService`, prestations, personnel, disponibilités, rendez-vous, confirm/annuler/reprogrammer, acompte via `PaymentService`, rappel J-1 via `MessagingService`). Routes `/reservation/*`, KPI tableau de bord branchés, navigation commerce secteur réservation. Pas de portail public.
- Phase 9 : messagerie unifiée (`MessagingService`) — interne, e-mail et WhatsApp en mock par défaut, modèles, historique des envois, rappels planifiés (`processDueReminders`). SMTP/API live non branchés.
- Phase 8 : paiements NabooPay en mode provisoire (`PaymentService`, tables transactions/webhooks, bac à sable local sans clé API, endpoint webhook refusé tant que `NABOOPAY_WEBHOOK_SECRET` est vide). Live API non branché.
- Phase 7 : CRM commerce (clients, fiches, notes, tags, historique, filtres, export CSV). Réservé aux comptes avec `tenant_id` ; le personnel SESAM n'y accède pas. Raccourci « Ajouter un client » activé sur le tableau de bord si droit `clients.create`.
- Phase 6 : dashboard SaaS commerce (`layouts/tenant.php`), KPI à zéro et actions rapides par secteur (désactivées tant que les modules absents), notifications internes (liste vide possible), profil, recherche, barre latérale et topbar dédiées. Le personnel SESAM conserve `layouts/staff.php`.
- Phase 5 : core multi-tenant. Tables `tenants` et `tenant_settings`, `users.tenant_id`, `TenantContext`, isolation A/B, `/commerces` (personnel SESAM) et `/parametres` (nom affiché du commerce). Pas de dashboard SaaS, pas de console d'abonnements, pas de portail `/t/{slug}`.
- Phase 4 : connexion du personnel SESAM, déconnexion, sessions, RBAC, gestion des utilisateurs, mot de passe oublié. Pas d'inscription publique. Pas de tableau de bord SaaS.
- Serveur local prévu : `php -S localhost:8080` avec racine `public/` (`composer serve`). `APP_URL=http://localhost:8080`. WAMP n’est plus l’URL de travail. Règle Cursor `19-local-php-server.mdc`.
- Accueil tablette (< 1080px) : `.landing-orbit` et `.landing-copy` à 95 % de `.landing-stage` dès 600px. Orbite agrandie, nœuds lisibles.
- Panneaux de service : numéros `01 / 06` retirés. Agence : secteur, motif et paragraphe d’intro retirés. Titres secondaires Commande / Loyer / Inscription / Garde retirés. Pied : « Communication en français » et note e-mail/téléphone retirés.
- Photos Agence, Commande et Loyer réduites dès 600px (`max-width: 20rem`, rapport 4/3). Colonnes visuelles plus étroites dès 900px.
- Accueil : textes des 6 services réécrits d’après le cahier, en phrases simples (qui, quoi, comment). Motifs et jargon « espace cloisonné » retirés. Garde centrée sur calendrier et péremption, sans lien public inventé.
- Page `/services` : même catalogue que l’accueil, cartes, photos, formules agence, fonctions communes et parcours en 3 étapes.
- Pages Offres, À propos, Contact, Réalisations et Devis alignées sur le même habillage et le cahier. Activités du devis : salon, tailleur, propriétaire, école, pharmacie, autre. Pas de tarif SaaS inventé.

## 2026-10-04

- Fondations MVC : routeur, requête, réponse, contrôleur, modèles, services, repositories, middlewares, session, validation, CSRF et connexion PDO à la base `sesam`.
- Aucune table et aucun module métier.
- Système visuel : variables CSS, composants et page `/design-system`. Pas de charte or/violet.
- Site public : accueil, à propos, services, offres, réalisations, contact et demande de devis enregistrée dans `contact_requests`.
- Accueil clair : composition inspirée d'une maquette blanche, charte bleu SESAM, verre dépoli, reveal dans les deux sens de défilement.
- L'accueil présente l'agence, les cinq outils, le lien public, Wave, Orange Money, WhatsApp et les trois offres.
- Accueil repris : scène marine futuriste, une section par service du plan, reveal au scroll, parcours et compteur. Pas d'onglets qui masquent les métiers.
- Accueil aligné sur le cahier : catalogue PHP, textes Dakar / New York / formules décrites, 7 photos métier, layouts distincts, scroll spy. Charte bleu / cyan.
- Scène d'accueil claire : titre + boutons à gauche, réseau des services à droite (composition type landing communautaire, sans marque tierce).
- Accueil et page Services limités au cahier : agence + Réservation, Commande, Loyer, Inscription, Garde. Le lien public n'est plus un septième service. Cahier transcrit dans `docs/CAHIER_DES_CHARGES.md`, synchronisé avec le plan.
- Images Réservation (salons de beauté et spas) et Loyer (immeubles et appartements) : collages photo sans texte ni écran.
- Images Inscription (école : classe, cour, secrétariat, cartables) et Garde (pharmacie : façade, rayons, calendrier, boîte) : même traitement photo sans texte ni écran.
- Six sections d'accueil avec un design distinct (studio sombre, agenda spa, ticket atelier, listing immeuble, dossier école, tableau pharmacie), verre dépoli, scènes illustratives et animations one-shot. Image Commande reprise en collage atelier, sans écran.
- Logo `sesam-logo.jpeg` dans l'en-tête public. Barres de défilement internes des sections retirées (`overflow` visible).
- Règles Cursor design / UX / responsive : `14` à `18`, mise à jour de `06`–`08` et `10`. Pas de changement métier.
- Accueil responsive mobile-first : gouttière sous 768px, 80 % ensuite, menu + devis mobile, colonnes de service à 900px, sans `overflow-x: hidden` sur la page.
- Accueil mobile / tablette : orbite circulaire (même dessin) au-dessus du texte, réduite pour tenir dans la colonne. Deux colonnes landing à 1100px.
- Sections principales d’accueil (`.landing`, `.band`, `.service-block`) : 98 % sur mobile, 90 % dès 768px.
- Section Réservation : photo `sesam-cdc-reservation.jpg` incrustée dans la carte, fondu blanc transparent (mobile vers le bas, dès 900px depuis la gauche).
- Section Inscription : photo `sesam-cdc-inscription.jpg` incrustée à gauche, fondu blanc vers le dossier et le texte.
- Accueil plus dense : titres, orbite, paddings et cartes de service réduits sur mobile, tablette et desktop. Cibles tactiles 2,75 rem conservées.
- Accueil : sections `#parcours` et `.band.facts` retirées. Pied de page public complet (marque, pages, services, pratique), sans e-mail ni téléphone inventés.
- Accueil : deux colonnes landing dès 1080px (`.landing` 90 %). Orbite et texte agrandis sur tablette. Titre plus grand tablette / PC. Lead raccourci, synthétisé d’après le cahier (agence + cinq outils).
