# Sécurité SESAM

Contrôles réellement présents. Ce n'est pas un audit complet du produit.

## En place

- PDO préparé, identifiants de base uniquement dans `.env`, `.env` ignoré par Git.
- CSRF obligatoire pour POST, PUT, PATCH et DELETE. Le jeton vient du champ `_csrf` ou de l'en-tête `X-CSRF-Token`.
- Échappement HTML par `Sesam\Helpers\Html::e` dans les vues qui affichent des données.
- Erreurs inattendues : texte fixe pour le visiteur, détail dans `storage/logs/app.log`.
- Session `SESAMSESSID`, cookie `HttpOnly`, `SameSite=Lax`, `Secure` seulement en HTTPS, `use_strict_mode` et `use_only_cookies`. `session_regenerate_id` et nouveau jeton CSRF après login. Invalidation à la déconnexion. Session vidée après 2 h sans requête (`Session::IDLE_SECONDS`).
- En-têtes sur toute réponse, pages d'erreur 403/404/419/429/500 comprises (`SecurityHeaders`, appliqué par le `Kernel`) : `Content-Security-Policy` (`default-src 'self'`, `script-src 'self'` sans script en ligne, `style-src 'self' 'unsafe-inline'` à cause des attributs `style` des barres, `img-src 'self' data:`, `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'none'`), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` (caméra, micro, géolocalisation coupés), `Cross-Origin-Opener-Policy: same-origin`, `Strict-Transport-Security` en HTTPS seulement, `Cache-Control: no-store` sauf valeur posée par le contrôleur (photo produit : `private`). `X-Powered-By` retiré. `form-action` n'est pas restreint : le paiement redirige vers le prestataire après un POST.
- `.htaccess` d'interdiction sur `app`, `config`, `database`, `storage`, `tests` et `docs`.
- Demande de devis : champs validés, activité en liste blanche, insertion préparée. 5 demandes enregistrées par IP et par heure (`auth_attempts` action `quote`), au-delà 429.
- Authentification staff : `password_hash` / `password_verify`, message générique en cas d'échec, comptes inactifs refusés.
- Pas d'inscription publique.
- RBAC côté serveur. Super Admin et Admin gèrent les utilisateurs SESAM et les commerces. Un responsable de commerce gère les utilisateurs de son commerce. Les autres rôles se connectent à `/espace` selon leurs permissions.
- Un Admin ne peut pas attribuer Super Admin ni modifier un Super Admin. Le dernier Super Admin actif ne peut pas être désactivé.
- Mot de passe : 12 caractères minimum. Jeton de reset haché, une heure, usage unique.
- Limitation : 5 tentatives de login ou de reset par IP sur 15 minutes.
- L'envoi d'e-mail du lien de reset n'existe pas encore (notifications, phase 9). Un administrateur crée un lien affiché une seule fois.
- Isolation tenant : hors portail, le commerce vient de `users.tenant_id`, pas d’un champ de formulaire. Sur `/t/{slug}`, le commerce vient du slug actif. Un compte d’un commerce ne lit ni ne modifie les utilisateurs, clients, paiements ni données de module d’un autre. Le portail d’un commerce ne sert pas le catalogue ni le paiement d’un autre. Un refus d’isolation est journalisé (`INFO`). Un commerce suspendu est refusé à la connexion (message générique) et absent du portail (404).
- Marque blanche : `display_name` uniquement. Pas d'upload de logo, pas de palette par commerce.
- Upload photo produit Garde : extensions et MIME réels en liste blanche (JPEG, PNG, WebP, GIF), 4 Mo max, nom régénéré, refus des doubles extensions exécutables, conversion GD vers WebP (payload d’origine non conservé), stockage sous `storage/uploads/` (`.htaccess` deny). Lecture authentifiée, filtrée par `tenant_id`. Pas d’upload de logo commerce.

- Console SESAM (`/commerces`) : `tenants.view` pour la lecture, `subscriptions.view` / `subscriptions.manage` pour l'abonnement (Super Admin, Admin). Actions en POST avec CSRF, liste blanche d'actions, transitions vérifiées côté serveur, transaction, journal `INFO` et historique avec auteur. La suspension coupe la connexion et le portail du commerce.
- Agence (`/agence`) : réservée au personnel SESAM. Le service refuse (403) tout compte rattaché à un commerce, même s'il portait une permission `agency.*`. Permissions séparées : prospects, devis, projets, factures (Commercial sans projets ni factures, Finance sans prospects ni devis). Actions en POST avec CSRF, listes blanches (étapes manuelles, formules, statuts, moyens de paiement), transitions de devis et de projet vérifiées côté serveur, planchers des formules, facturation plafonnée au montant du projet, capacité de 5 démarrages par mois vérifiée dans une transaction (`FOR UPDATE`), paiement refusé s'il est déjà enregistré ou daté dans le futur, adresse de site limitée à `http(s)://`. Une demande du site ne devient prospect qu'une fois (index unique). Journal `INFO` et historique avec auteur.
- Statistiques (`/statistiques`) : lecture seule, en GET. Le commerce vient de la session, jamais d'un paramètre. Un compte sans commerce reçoit 403, de même qu'un compte sans `stats.view` (le rôle Staff ne l'a pas). Chaque agrégat est filtré par `tenant_id`. La période passe par une liste blanche. Les noms de tables des échéances viennent d'une constante, pas de l'entrée utilisateur.

## Audit phase 19 (2026-10-07)

- XSS : analyse des `<?=` de toutes les vues. Les valeurs saisies passent par `Html::e`, directement ou dans une fermeture (`$value`, `$oldValue`, `$money`…). Le reste : entiers, constantes, ternaires littéraux, URL produites par `Html::url`. `sesam.js` n'utilise ni `innerHTML` ni `eval`.
- SQL : requêtes préparées. Les seules concaténations portent sur `LIMIT`/`OFFSET` typés `int` (strict_types), des clauses construites avec des paramètres liés, et des noms de tables en constante.
- Redirections : cibles internes construites par le serveur ; `retour` de l'agence en liste blanche ; l'URL de paiement vient du prestataire, pas de l'entrée.
- Isolation : les requêtes par identifiant des données de commerce portent `tenant_id`. Les exceptions concernent des données globales (agence, commerces, utilisateurs filtrés par `UserService`, relances planifiées).
- IP client : `REMOTE_ADDR` uniquement. Derrière un proxy, toutes les requêtes partageraient la limite de débit : à configurer au déploiement.

## Phase 20 (2026-10-07)

- Webhook NabooPay : `Request::capture` ne gardait que l'en-tête `X-CSRF-Token`. La signature `X-Naboo-Signature` était perdue en production et tout webhook réel recevait 401. Tous les en-têtes `HTTP_*` sont désormais lus.
- Webhooks dans le désordre : un paiement réussi ne repasse plus en échec, annulé ou en attente, et un paiement terminé ne redevient pas « en attente ». L'événement est enregistré (pas de retraitement) et journalisé.
- Portail public : une demande refusée après la saisie du nom et du téléphone (créneau pris, prestation inconnue…) laissait une fiche client invitée. La soumission est maintenant transactionnelle ; le compteur anti-abus reste hors transaction et compte aussi les refus.

## Pas encore en place

2FA, encaissement automatique des abonnements, purge de la table `auth_attempts`, en-têtes de cache des fichiers statiques sous `php -S` (le serveur intégré ne lit pas `.htaccess`). Les demandes de devis restent des données d’agence, pas des données d’un commerce. Le portail public existe (`/t/{slug}`) : CSRF, 12 POST / 15 min par IP (`auth_attempts` action `portal`), confirmation par jeton de session (pas d’ID énumérable), WhatsApp en mock (non expédié).
