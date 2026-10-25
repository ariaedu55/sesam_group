# Cahier des charges — SESAM Digital & SESAM SaaS

Transcription de `Sesam-Digital-et-SaaS-Cahier-des-charges.pdf` (3 pages).

Ce fichier garde le contenu métier du cahier. Ce n'est pas l'état du code. En cas d'écart, le PDF du cahier à la racine fait foi.

La feuille de route technique reste `docs/PLAN_IMPLEMENTATION.md` (transcription de `Plan_Complet_Implementation_SESAM_Digital_SaaS.pdf`). Les écarts plan / cahier sont listés dans ce plan, section « Synchronisation avec le cahier ».

## 0. Présentation générale

Sesam Digital est la maison mère qui regroupe deux activités distinctes, vendues séparément mais construites pour se compléter :

- **Sesam Digital (Agence)** — conception et refonte de sites web, facturés au projet
- **Sesam SaaS** — suite d'outils logiciels en abonnement mensuel, déclinés par secteur (Réservation, Commande, Loyer, Inscription, Garde)

Marché cible : commerces et indépendants à Dakar (Sénégal), pilotés depuis New York. Communication et supports en français.

Identité de marque : logo « D » or/violet, signature « L'innovation à portée de clic », à utiliser sur tous les supports Sesam Digital et leurs déclinaisons Sesam SaaS.

**Note projet :** l'interface livrée utilise la charte bleu / cyan / blanc (logo transmis). L'or / violet n'est pas appliqué tant que le client ne l'a pas tranché.

## PARTIE 1 — Sesam Digital (Agence)

### 1.1 Positionnement

Agence de marketing digital proposant des sites et outils interactifs sur mesure, que le client intègre à son site existant ou fait construire entièrement par Sesam Digital.

### 1.2 Clientèle cible

- Salons de beauté et instituts (massage, coiffure, vente de lait de beauté, extensions / cheveux)
- Salons de couture
- Profil recherché : établissement ayant déjà un site basique et une audience active, mais sans outils interactifs pour engager ses visiteurs
- Exclusion volontaire (interne, pas un argument public) : les cliniques et établissements de santé ne sont pas démarchés, en raison de la sensibilité des données personnelles / médicales

### 1.3 Offres et tarifs

| Formule | Tarif | Description |
| --- | --- | --- |
| Essentiel | à partir de 200 000 FCFA | Site vitrine simple, présentation de l'activité |
| Professionnel | à partir de 450 000 FCFA | Site plus complet, fonctionnalités additionnelles |
| Sur mesure | Sur devis | Besoins spécifiques, projet personnalisé |

Capacité : Sesam Digital limite son activité à 5 projets de conception web par mois, afin de garantir la qualité de livraison.

## PARTIE 2 — Sesam SaaS

### 2.1 Concept

Plateforme logicielle en marque blanche, multi-secteurs, vendue en abonnement mensuel. Chaque commerce abonné dispose d'un espace cloisonné (architecture multi-tenant) : il ne voit que ses propres données, sous son propre nom, bien que tous les espaces reposent sur le même moteur logiciel.

### 2.2 Modules sectoriels (seuls services SaaS vendus)

| Module | Secteur cible | Fonction principale |
| --- | --- | --- |
| Sesam Réservation | Salons de beauté et spas | Prestations, créneaux, acompte |
| Sesam Commande | Tailleurs et couture | Commandes sur mesure, mesures, livraison |
| Sesam Loyer | Propriétaires / immeubles | Biens, échéances, rappels de loyer |
| Sesam Inscription | Écoles | Inscriptions, tranches de frais de scolarité |
| Sesam Garde | Pharmacies | Tour de garde, stock et alerte de péremption |

Il n'y a pas de sixième module sectoriel. Le lien public, les paiements et WhatsApp sont des fonctions communes, pas des services à part.

### 2.3 Fonctionnalités attendues

**Espace client final (grand public)** — fonction de chaque module, pas un service séparé :

- Lien de réservation / commande unique par commerce, sans compte ni app à installer
- Sélection service / produit / bien, puis créneau ou échéance disponible
- Paiement d'acompte ou de la totalité via Wave et Orange Money
- Confirmation automatique par WhatsApp, puis rappel avant l'échéance

**Tableau de bord commerce (abonné)** :

- Vue d'ensemble des rendez-vous / commandes / échéances
- Gestion des services / produits / biens proposés
- Fiche client avec historique et suivi des paiements
- Module Pharmacie : calendrier de garde + stock avec alerte (Expiré / Bientôt / OK)

**Console d'administration Sesam (interne, pas un service public)** :

- Création et configuration des comptes commerce (faite manuellement par Sesam au lancement)
- Vue globale de tous les comptes actifs, tous secteurs confondus
- Suivi du statut d'abonnement par client

### 2.4 Exigences techniques

- Architecture multi-tenant avec cloisonnement strict des données
- Interface responsive, fonctionnelle en connexion faible (3G)
- Intégration API Wave et Orange Money
- Intégration WhatsApp Business API
- Socle technique commun aux 5 déclinaisons sectorielles

## PARTIE 3 — Articulation entre les deux offres

| Étape | Description |
| --- | --- |
| 1. Site | Sesam Digital conçoit ou refond le site du client (projet ponctuel) |
| 2. Outil | Le module Sesam SaaS adapté au métier du client s'y connecte (abonnement mensuel) |
| 3. Gestion quotidienne | Le client gère rendez-vous, paiements et rappels depuis un seul endroit |

## Services à afficher sur le site public

1. Sesam Digital — Agence
2. Sesam Réservation
3. Sesam Commande
4. Sesam Loyer
5. Sesam Inscription
6. Sesam Garde

À ne pas présenter comme un service vendu à part : lien public, console interne, exclusion des cliniques, QR code, tickets, segmentation avancée.
