# Règles Cursor du projet SESAM

Index des règles persistantes. Il décrit leur rôle et leur mode d'application. Il ne décrit pas une application déjà construite.

## État du dépôt

Les faits applicatifs (code, logo, tokens) sont dans `00-core-project.mdc` et `docs/DESIGN_SYSTEM.md`. Cet index liste uniquement les fichiers `.mdc`.

## Modes

| Mode | Frontmatter | Effet |
| --- | --- | --- |
| Always Apply | `alwaysApply: true` | Chaque tâche |
| Fichiers | `globs` + `alwaysApply: false` | Lorsque les fichiers correspondants sont dans le contexte |
| Intelligente | `alwaysApply: false` sans `globs` | L'agent la charge selon la description |

## Fichiers

| Fichier | Mode | Rôle |
| --- | --- | --- |
| `00-core-project.mdc` | Always Apply | Identité SESAM, faits du dépôt, statuts exigence / existant / proposition, anti-hallucination |
| `01-architecture-mvc.mdc` | Fichiers PHP applicatifs | Controller, Service, Repository, Model, Middleware, View |
| `02-php-backend.mdc` | `**/*.php` | PHP 8, PDO, Composer, secrets, style aligné sur l'existant |
| `03-database-mysql.mdc` | SQL, repositories, database | Schéma réel, migrations, requêtes préparées |
| `04-security.mdc` | PHP, JS, vues, config, `.env` | CSRF, XSS, sessions, RBAC, uploads, journaux |
| `05-multitenant.mdc` | `app/`, `database/`, `tests/` | TenantContext, `tenant_id`, isolation |
| `06-frontend-ux.mdc` | Vues, CSS, JS | Parcours, formulaires, empty states, accessibilité |
| `07-design-system.mdc` | CSS, vues | Palette `--sesam-*`, logo, lisibilité ; renvoie vers 14–18 |
| `08-animations-performance.mdc` | CSS, JS, assets | Mouvement léger, `prefers-reduced-motion`, 3G |
| `09-testing-quality.mdc` | Intelligente | Scénarios critiques et PHPUnit |
| `10-development-workflow.mdc` | Always Apply | Huit étapes, phases, rapport, arrêt |
| `11-documentation.mdc` | Intelligente | Docs alignées sur le code réel |
| `12-saas-modules.mdc` | Intelligente | Cinq modules, agence, portail sans compte |
| `13-integrations-payments.mdc` | Intelligente | Paiements, webhooks, WhatsApp et notifications |
| `14-visual-identity.mdc` | CSS, vues, public | Logo, futurisme premium, densités Digital / SaaS, marketing |
| `15-design-tokens.mdc` | `**/*.css` | Tokens `--sesam-*`, type, spacing, z-index |
| `16-responsive.mdc` | CSS, JS, vues | Mobile-first, overflow, nav, tableaux, images |
| `17-ui-patterns.mdc` | CSS, vues | Boutons, cartes, formulaires, états, dashboard |
| `18-visual-qa.mdc` | Intelligente | Recette visuelle à l'étape Vérification (ne remplace pas le workflow) |
| `19-local-php-server.mdc` | Always Apply | URL `http://localhost:8080/`, `composer serve`, pas WAMP |

## Arbitrages écrits pour éviter les contradictions

- Une seule méthode de travail : les 8 étapes de `10-development-workflow.mdc`. La recette UI de `18-visual-qa.mdc` s'y ajoute à l'étape Vérification. Pas de second workflow à 10 phases.
- Palette d'interface : bleu nuit, bleu profond, bleu électrique, cyan, blanc. L'identité or/violet du PDF n'est pas appliquée tant que le client ne l'a pas validée.
- Variables CSS : uniquement `--sesam-*`. Pas d'alias `--color-primary` / `--background`.
- `overflow-x: hidden` n'est pas un correctif responsive (`16-responsive.mdc`).
- Liste de tables du PDF : proposition d'architecture initiale, pas une migration à exécuter.
- Section 16 du PDF : propositions, pas des exigences, sauf si une tâche les demande.
- Données agence et données d'un commerce SaaS : périmètres distincts. `tenant_id` n'est pas ajouté par réflexe.
- Accès transverse réservé à une console SESAM explicite, autorisée et journalisée. Il ne retire pas l'isolation des repositories courants.
- Namespace PHP : `Sesam\`.
