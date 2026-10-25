# Système visuel SESAM

Référence du code réel et des règles Cursor 06–08 et 14–18. Ce n'est pas une maquette de fonctionnalités absentes.

Fichiers : `public/assets/css/sesam.css` (tokens et composants), `public/assets/css/public.css` (site public), `GET /design-system`. Logo : `public/assets/images/sesam-logo.jpeg` (copie de `logo.jpeg`), en-tête public, tel quel. Pas de webfont. Pas de pack d'icônes. Pas de mode sombre. Pas de préfixe `--color-*`.

La charte or/violet n'est pas utilisée.

## Tokens présents (`:root`)

| Variable | Valeur | Usage |
| --- | --- | --- |
| `--sesam-navy` | `#071A3D` | Navigation, titres, contrastes |
| `--sesam-blue` | `#0349B8` | Marque, action principale |
| `--sesam-electric` | `#087CFF` | Liens, focus, état actif |
| `--sesam-cyan` | `#00D9F5` | Accent et glow. Pas la couleur du petit texte |
| `--sesam-background` | `#F6F9FF` | Fond d'interface claire |
| `--sesam-surface` | `#ffffff` | Cartes, champs |
| `--sesam-text` | `#0C1730` | Texte |
| `--sesam-muted` | `#63708A` | Texte secondaire |
| `--sesam-line` | `rgba(7, 26, 61, 0.12)` | Bordure |
| `--sesam-space-1` … `--sesam-space-7` | 4px à 48px | Échelle 4 / 8 / 12 / 16 / 24 / 32 / 48 |
| `--sesam-radius-sm` / `md` / `lg` | 0.5 / 0.85 / 1.25rem | Coins |
| `--sesam-shadow` | ombre douce | Cartes |
| `--sesam-glow` | halo bleu | Accents rares |
| `--sesam-font` | Segoe UI, system-ui | Unique |
| `--sesam-sidebar` | 16.5rem | Tiroir d'outil (personnel SESAM) |
| `--sesam-rail` | 4.75rem | Rail d’icônes commerce |
| `--sesam-topbar` | 4.25rem | Barre haute d'outil |
| `--sesam-radius-xl` | 1.75rem | Coque tableau de bord commerce |
| `--sesam-canvas` | `#E8EFF9` | Fond des espaces `.is-app` |
| `--sesam-teal` / `emerald` / `amber` / `coral` / `slate` | `#0A9396` / `#0F9F6E` / `#E08A00` / `#E5484D` / `#4B5B78` | Teintes de rubrique, jamais sous du texte blanc |
| `--sesam-success` / `warning` / `danger` | `#0B7F58` / `#9A5B00` / `#C62F34` | Texte d'état sur fond clair |
| `--sesam-glass` / `glass-strong` | blanc 64 % / 84 % | Surfaces en verre |
| `--sesam-glass-dark` | marine 86 % | Rail des espaces |
| `--sesam-glass-line` / `glass-blur` | blanc 78 % / `blur(18px) saturate(165%)` | Bord et flou du verre |
| `--sesam-shadow-soft` / `shadow-lift` | ombres marine | Repos / survol |
| `--sesam-ease` | `cubic-bezier` | Micro-interactions |
| `--sesam-z-modal` | 60 | Modale de formulaire |

Aucun dégradé dans `sesam.css` (décision du 2026-10-07) : `--sesam-gradient` et `--sesam-dash-hero` ont été retirés, les anciens dégradés sont devenus des couleurs pleines. `public.css` en contient encore.

Les noms courts `--background` et `--text` du PDF ne sont pas repris.

## Typographie présente

`h1` en `clamp(1.8rem, 3vw, 2.6rem)`, `h2` 1.35rem, corps 1rem, line-height 1.5. Titres navy. Display marketing (accueil) : `.landing-title` `clamp(1.5rem, 5.4vw, 2.55rem)` mobile, jusqu’à `2.7rem` dès 600px, `2.85rem` dès 768px et `3.15rem` dès 1080px. Titres de service `clamp(1.32rem, 3.6vw, 1.8rem)`. Lead d’accueil `0.82rem`. Toujours navy / electric. Orbite 20,5 rem sur téléphone, 95 % de `.landing-stage` dès 600px jusqu’à 1079px. Cibles tactiles conservées à 2,75 rem.

## Composants présents dans `sesam.css`

Boutons principal, secondaire, ghost, désactivé ; champs et erreur ; liste déroulante ; cartes ; badges ; alertes ; fenêtre de confirmation ; tableau ; fil d'Ariane ; barre latérale ; barre haute ; indicateurs ; calendrier statique ; timeline statique ; état vide ; chargement ; squelette.

Les chiffres et le calendrier de la page démo sont des exemples fixes. Ils ne viennent pas de la base.

## Site public (existant)

Fond `#F7F9FF`. Accueil : `.landing` (pas `.hero`) + orbite des 6 services. Page `/services` : `.page-services`, carte agence navy, grille de 5 outils, pastilles communes, parcours 3 étapes. Sections verre, scènes distinctes, logo en en-tête. Section Réservation : photo incrustée dans `.service-panel` avec dégradé blanc. Section Inscription : même incrustation, photo à gauche. `overflow` des blocs de service : `visible` (pas `overflow-x: hidden` sur `html` / `body` / sections). En-tête : barre `.site-header-bar` en verre dépoli, largeur 100 %. CTA devis dans le menu mobile (`.nav-cta`) ; CTA d’en-tête (`.header-cta`) dès 480px. Liens Services, Offres, Contact visibles dès 768px (bouton Menu masqué). Accueil, À propos et Réalisations absents du menu d’en-tête ; présents dans le pied de page. Pied de page `.site-footer` navy, 4 colonnes dès 960px (marque, pages, services, pratique). Pas d’e-mail ni de téléphone public. Lien « Espace SESAM » vers `/connexion` dans la colonne pratique. Les sections `#parcours` et `.band.facts` ne sont plus rendues.

Connexion staff : `.auth-sheet` et `.auth-card` dans `sesam.css`. Personnel SESAM : `layouts/staff.php`. Commerce connecté : `layouts/tenant.php` (coque `.tenant-board`, rail d’icônes `.tenant-rail`, recherche pastille, profil avec initiales). Dashboard commerce : `.dash-premium` (bandeau `.dash-hero`, KPI, calendrier, tableaux métier / paiements / personnes). Module Garde : bandeau `.garde-hero`, calendrier `.garde-cal` (une colonne sous 720px), pastilles `.garde-chip-expired` / `soon` / `ok` (navy / électrique / bleu, pas de cyan en petit texte). Photo produit : `.garde-product-photo` (carré, `object-fit: cover`), zone d’upload `.garde-file`. Portail public : `layouts/portal.php`, `.portal-*` (monogramme, cartes radio, confirmation). Marque blanche : nom affiché du commerce, logo SESAM inchangé sur le portail. L’espace personnel SESAM utilise la même coque (rail d’icônes, barre flottante) depuis le 2026-10-07. Console SESAM (`layouts/staff.php`) : `.console-*` (bandeau marine plein réutilisant `.dash-hero`, indicateurs `.dash-kpi` sur 3 colonnes dès 720px et 2 colonnes compactes sans pastille sous 480px, barres secteur `.console-bar`, lignes-cartes `.console-row`, fiche en 2 colonnes dès 1100px). Pastilles d’état génériques `.chip-ok` / `-warn` / `-off` / `-muted` (texte teal foncé, bleu, blanc sur navy, gris ; versions blanches sur le bandeau).

Agence SESAM (`layouts/staff.php`, vues `staff/agence/*`) : réutilise `.console-*`, `.dash-*` et `.chip-*`, plus `.agency-*`. Onglets pilule `.agency-tabs` (couleur pleine sur l’onglet courant, retour à la ligne sous 360px), filtres pastilles `.agency-segment` avec compteur, KPI argent `.agency-kpi-money` (2 colonnes jusqu’à 1280px, 4 au-delà ; montants autorisés à passer à la ligne), entonnoir numéroté `.agency-funnel` (barres `.console-bar`), jauge de capacité `.agency-slots` (5 cases : pleine si occupée, pointillés si libre), frise d’étapes `.agency-stepper` (8 étapes en ligne, grille 4 × 2 sous 720px), cartes devis `.agency-quotes`, barre d’encaissement `.agency-progress` (payé plein sur facturé bleu clair) avec légende, cartes facture `.agency-invoice` (bord navy si en retard) et formulaire d’encaissement `.agency-pay` (côte à côte dès 1100px sur `/agence/factures`). Bouton compact `.btn-sm`. Dans une carte `.dash-card`, `.btn-ghost` passe en texte navy sur bordure grise (il restait blanc, donc invisible, sur fond blanc).

Statistiques du commerce (`tenant/statistiques/index.php`) : préfixe `.stats-*`, avec réutilisation de `.dash-hero`, `.console-hero`, `.console-eyebrow`, `.dash-card` et `.console-bar`.
- Bandeau : sélecteur de période `.stats-periods`, pilule translucide dont l'option active est blanche, cibles de 44 px, partagée en trois sous 480 px.
- Indicateurs `.stats-kpi` : une colonne, puis 2 dès 560 px et 4 dès 1280 px. Le chiffre d'affaires `.stats-kpi-lead` a un fond marine plein et une pastille de variation `.stats-delta` (`is-up` blanche, `is-down` navy). La carte des impayés porte un liseré électrique.
- Graphique `.stats-chart` : barres verticales en CSS, sans bibliothèque, hauteur en pourcentage. Le mois en cours est en électrique plein. Sur 12 mois et sous 720 px, les montants disparaissent et les mois passent en initiales ; un texte `sr-only` donne le mois complet, le montant et le nombre de paiements.
- Chiffres `.stats-figures` en 2 colonnes, 4 dans le bloc métier dès 720 px.
- Barres de répartition `.stats-bars` : `is-warn` bleu clair (en attente, bientôt), `is-alert` navy (échoué, annulé, expiré, non réglé).
- Classement numéroté `.stats-top`.
- Grilles `.stats-grid` (2 colonnes dès 1100 px), bloc métier sur 2 colonnes dès 900 px.
- Icône de menu `chart`.

## Espaces commerce et SESAM (`body.is-app`)

Les deux layouts (`tenant.php`, `staff.php`) partagent la même coque : `body.is-app`, `.tenant-shell`, rail `.tenant-rail`, barre `.tenant-chrome`, contenu `main.tenant-content`. Le personnel SESAM a aussi `is-staff`, le commerce `is-tenant`.

- Fond `--sesam-canvas` avec deux cercles pleins semi-transparents fixes (`body::before` / `::after`), qui donnent de la matière au verre.
- Rail marine translucide (`--sesam-glass-dark`), collant et arrondi dès 960 px. Chaque lien porte `data-tone` : icône dans la teinte claire de la rubrique, élément actif avec anneau de la teinte. Le bouton menu est masqué dès 960 px (le rail y est toujours visible).
- Barre du haut flottante en verre, collante.
- Surfaces en verre : `.card`, `.panel`, `.empty`, `.dash-card`, `.dash-kpi`, `.stats-kpi`, `.table-wrap`, `.console-rows`, `.garde-product-card`, alertes. Flou retiré sous 720 px (fond plus opaque), repli blanc si `backdrop-filter` n'existe pas.
- `main` porte la teinte de la rubrique courante : barre colorée devant le `h1`, anneau de focus des champs.
- Indicateurs : pastille pleine par position (électrique, émeraude, ambre, sarcelle) ; `console-kpi-off` corail, `console-kpi-muted` ardoise. Barres secteur de la console colorées par rang.
- Densité : texte 15 px, boutons et champs 38 px, `.btn-sm` 32 px, `th` en petites capitales sur fond teinté.
- Boutons : primaire bleu plein (texte blanc), secondaire en verre, ghost marine ; dans un bandeau, primaire blanc et secondaire translucide. `.btn-danger` disponible.
- Pastilles : `chip-ok` émeraude, `chip-warn` ambre, `chip-off` corail, `chip-muted` ardoise ; même logique pour `garde-chip-*`.

### Modale de formulaire

`<dialog class="form-modal">` créé par `sesam.js` uniquement si `body.is-app` existe. Plein écran sous 720 px ; panneau en verre de 48 rem au-delà, formulaire sur deux colonnes (textarea, fieldset et boutons en pleine largeur). En-tête : surtitre (`data-modal` ou `data-modal-kicker`), titre, bouton de fermeture rond, filet animé.

- `a[data-modal="Rubrique"]` : la page GET est chargée en `fetch` avec l'en-tête `X-Sesam-Modal: 1`, son `main.content` est affiché (son `h1` devient le titre, son bandeau devient une phrase d'introduction). Les POST de la modale partent en `fetch` ; une redirection devient `200` + `X-Sesam-Location` côté `Kernel`, puis le navigateur suit l'adresse, ce qui garde le message flash. Une réponse 422 réaffiche le formulaire avec ses erreurs dans la modale.
- `form[data-modal-form="Libellé"]` : le formulaire de la page est remplacé par un bouton `.form-modal-trigger` et déplacé dans la modale ; il est soumis normalement. Si la page revient avec une erreur de champ, la modale se rouvre seule.
- Fermeture : bouton, clic sur le fond, `Échap`, ou lien « Annuler » vers la page courante. Le focus revient au déclencheur.
- Sans JavaScript, liens et formulaires fonctionnent comme des pages classiques.

## Accueil et pages service (2026-10-08)

L'accueil (`.landing` + `.home-range`) ne détaille plus les six offres. L'orbite porte une icône par bulle et lie vers `/services/{clé}`. Un disque `.orbit-planet` (méridien, graduations, repères 015–345) reste derrière les bulles. Le centre est marine, texte blanc. Sites web et Réservation sont bleus (texte blanc). Commande, Loyer, Inscription et Garde ont un fond teinté et un texte foncé de la même famille. Six cartes `.home-card` (photo, secteur, une ligne) mènent aux mêmes pages.

Chaque service a sa page `public/service.php` (`.offer-page.is-*`) : bandeau teinté (marine pour l'agence et la garde), étapes, scène déjà écrite, photo, encadré « De votre côté », liens vers les autres services. `/services` reste la page qui les présente tous.

La barre affiche le logo et le devis, puis une rangée avec les six métiers (icône et nom, une seule couleur marine) et Contact. Services et Offres ne sont plus dans la barre ; ils restent dans le pied de page. Sous 720 px, le menu reprend les six noms. Les sections sous le hero (`.home-bridge`, `.home-chapter`, `.home-common`, `.home-steps`) sont des cartes arrondies sur le fond clair, sans bordure teintée. Professionnel est la carte bleue des trois formules. Le bandeau des fonctions communes et le pied de page reprennent le dégradé bleu clair du bandeau final, texte blanc.

### Aurora Glass (`public-glass.css`, chargée en dernier)

Variables `--g-fill`, `--g-fill-strong`, `--g-line`, `--g-blur`, `--g-shadow(-soft)`, `--g-radius(-md/-sm)`, `--g-grad`, `--g-band` (dégradé du bandeau final), `--g-blue` (`#087cff`, boutons unis), `--g-width`. Chaque page service porte `data-skin` (agency, reservation, commande, loyer, inscription, garde) qui fixe `--sk`, `--sk-rgb`, `--sk-ink` ; le verre, les pastilles et le bandeau d'appel en héritent. Surfaces : verre clair (fond blanc translucide, flou 20–26 px), bandeau `--g-band` (fonctions communes, pied de page, bandeau final, centre de l'orbite) ou cristal marine (scène agence). Repli sans `backdrop-filter` : fond blanc plein. Mouvement : montée douce des cartes et dérive des halos, coupés par `prefers-reduced-motion`.

### Espaces connectés : `app-glass.css`

Couche posée sur `sesam.css` pour `body.is-app` et `body.auth-body`. Variables `--ag-fill`, `--ag-fill-strong`, `--ag-line`, `--ag-edge`, `--ag-blur`, `--ag-shadow(-soft)`, `--ag-grad` (action), `--ag-hero` (bandeaux), `--ag-radius(-sm)`. Le rail est en verre clair : chaque icône porte la teinte `--tone` de sa rubrique, l'élément actif devient une pastille pleine. Les bandeaux `.dash-hero` / `.garde-hero` sont en dégradé bleu avec une sphère cyan et un disque de verre. Mobile (< 720 px) : flou retiré, fonds plus opaques.

## Breakpoints observés

`sesam.css` : 720px, 960px. `public.css` : 480px, 600px, 700px, 720px, 768px, 900px, 960px, 1080px, 1100px. Accueil : sections principales à 90 % de la largeur, plafonnées à 1 400 px. Barre d’en-tête 100 % ; orbite circulaire au-dessus du texte jusqu’à 1080px (95 % de `.landing-stage` dès 600px), deux colonnes ensuite (`.landing` 90 %) ; colonnes de service à 900px. Photos Agence / Commande / Loyer : `max-width: 20rem` dès 600px. Contrôle visuel exigé par les règles : 320, 375, 390, 430, 768, 1024, 1280, 1440.

## Mouvement

Reveal, survol, menus. `prefers-reduced-motion` coupe animation et transition. Sans JS, textes restent visibles. Animation permanente : seulement si demandée et bornée (tour des nœuds d'accueil). Compteur `data-count` encore dans `sesam.js`, plus de bloc métriques sur l’accueil.

## Absents (ne pas les inventer)

Mode sombre, bibliothèque d'icônes, webfont, alias `--color-primary`, Bootstrap / Tailwind.

## Vérification

Page `/design-system` et accueil public ouverts dans le navigateur lors des livraisons UI. Menu mobile actionné. Pas de défilement horizontal aux largeurs testées.
