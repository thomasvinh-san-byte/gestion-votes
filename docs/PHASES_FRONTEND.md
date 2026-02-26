# AG-Vote — Plan de remaniement frontend

> **Méthode Linus** : petits incréments, chaque phase compilable et testable, zéro régression, fondations d'abord.
>
> **Pile technique** : PHP 8.4 MVC + HTMX + Web Components vanilla + CSS pur. Aucun outil de build (pas de npm, Vite, React, TypeScript).

---

## 0. État des lieux

### Ce qui existe (v3.19.2)

| Couche | Technologie | Fichiers |
|--------|------------|----------|
| Backend | PHP 8.4 custom MVC | `app/Controller/` (37 controllers), `app/Core/`, `app/Repository/` |
| Frontend | HTMX + Web Components vanilla | `public/*.htmx.html` (14 pages), `public/assets/js/` |
| CSS | Fichier unique + pages | `design-system.css` (2 702 l.), `app.css` (814 l.), 16 fichiers page |
| Composants JS | Web Components (`ag-*`) | `public/assets/js/components/` (8 composants, Shadow DOM) |
| Pages JS | Scripts par page | `public/assets/js/pages/` (24 scripts) |
| Shell JS | Sidebar + Drawer + Mobile | `shell.js` (502 l.), `utils.js` (799 l.) |
| API | REST PHP | `app/api.php`, `app/routes.php` |

### Composants Web existants

| Composant | Fichier | Rôle |
|-----------|---------|------|
| `ag-kpi` | `components/ag-kpi.js` | Carte indicateur chiffré |
| `ag-badge` | `components/ag-badge.js` | Badge texte coloré |
| `ag-spinner` | `components/ag-spinner.js` | Indicateur de chargement |
| `ag-toast` | `components/ag-toast.js` | Notification toast |
| `ag-quorum-bar` | `components/ag-quorum-bar.js` | Barre de quorum |
| `ag-vote-button` | `components/ag-vote-button.js` | Bouton de vote |
| `ag-popover` | `components/ag-popover.js` | Menu contextuel |
| `ag-searchable-select` | `components/ag-searchable-select.js` | Select avec recherche |

### Ce que le wireframe définit (cible visuelle)

| Aspect | Cible |
|--------|-------|
| Design system | « Acte Officiel » — 52+ CSS tokens, 2 thèmes (clair/sombre) |
| Polices | Bricolage Grotesque (corps), Fraunces (display), JetBrains Mono (code) |
| Couleur accent | Bleu encre `#1650E0` (remplace Indigo `#4f46e5`) |
| Fond | Parchemin `#EDECE6` (remplace Slate `#f8fafc`) |
| Sidebar | Rail 58px → 252px au hover/pin (remplace fixe 210px) |
| Header | Glassmorphisme `backdrop-filter: blur()` |
| Mobile | Bottom nav 5 boutons (remplace hamburger seul) |
| Pages | 16 pages, 22 composants réutilisables, 203 interactions |
| Accessibilité | RGAA 97%, skip-link, focus-trap, aria-live, prefers-reduced-motion |

### Décision architecturale

**Le remaniement est un restyling progressif du frontend existant.**
Le backend PHP, l'API REST, les templates HTMX et la structure Web Components sont conservés. Le design system « Acte Officiel » du wireframe est appliqué sur la pile technique existante. Aucun outil de build n'est ajouté.

---

## Principes Linus

1. **Chaque phase produit un livrable fonctionnel.** Pas de phase « préparatoire » sans résultat visible.
2. **Chaque phase est autonome.** On peut s'arrêter après n'importe quelle phase et avoir un produit utilisable.
3. **Les fondations sont posées en premier.** Design tokens → Shell → Composants → Pages simples → Pages complexes.
4. **Une seule chose à la fois.** Chaque phase a un périmètre clair et borné.
5. **Tests à chaque phase.** Checklist de conformité wireframe avant de passer à la suivante.
6. **On travaille avec l'existant.** Modifier `design-system.css`, `shell.js`, les `ag-*` — pas de réécriture depuis zéro.
7. **Pas de dépendance externe.** Zéro npm, zéro build tool. CSS pur, vanilla JS, Shadow DOM.

---

## Phase 1 — Design Tokens « Acte Officiel »

**Objectif** : remplacer la palette Inter/Indigo/Slate par Bricolage Grotesque/Encre/Parchemin dans le fichier `design-system.css` existant.

**Fichiers modifiés** : `public/assets/css/design-system.css`, balises `<link>` Google Fonts dans chaque `*.htmx.html`.

### 1.1 Polices

Remplacer dans `:root` de `design-system.css` :

| Token actuel | Valeur actuelle | Nouvelle valeur |
|-------------|----------------|----------------|
| `--font-sans` | `'Inter', system-ui, …` | `'Bricolage Grotesque', system-ui, sans-serif` |
| `--font-mono` | `'JetBrains Mono', …` | `'JetBrains Mono', ui-monospace, monospace` (inchangé) |
| _(nouveau)_ `--font-display` | — | `'Fraunces', Georgia, serif` |

- [ ] Remplacer le `<link>` Google Fonts dans chaque template HTMX pour charger Bricolage Grotesque + Fraunces + JetBrains Mono
- [ ] Mettre à jour `--font-sans` et ajouter `--font-display` dans `:root`
- [ ] Appliquer `font-family: var(--font-display)` aux titres `h1`, `h2` dans les styles de base

### 1.2 Couleurs — Thème clair

Remplacer dans `:root` de `design-system.css` :

| Token | Ancien | Nouveau | Notes |
|-------|--------|---------|-------|
| `--color-bg` | `#f8fafc` | `#EDECE6` | Parchemin |
| `--color-bg-subtle` | `#f1f5f9` | `#E5E3D8` | Surface alt |
| `--color-surface` | `#ffffff` | `#FAFAF7` | Surface principale |
| `--color-surface-raised` | `#ffffff` | `#FFFFFF` | Inchangé |
| _(nouveau)_ `--color-glass` | — | `rgba(250,250,247,.95)` | Glassmorphisme |
| `--color-border` | `#e2e8f0` | `#CDC9BB` | Bordure principale |
| `--color-border-subtle` | `#f1f5f9` | `#DEDAD0` | Bordure douce |
| `--color-border-strong` | `#94a3b8` | `#BCB7A5` | Bordure tirets |
| `--color-primary` | `#4f46e5` | `#1650E0` | Bleu encre |
| `--color-primary-hover` | `#4338ca` | `#1140C0` | |
| `--color-primary-active` | `#3730a3` | `#0C30A0` | |
| `--color-primary-subtle` | `#eef2ff` | `#EBF0FF` | |
| _(nouveau)_ `--color-primary-glow` | — | `rgba(22,80,224,.12)` | Halo focus |
| `--color-text` | `#0f172a` | `#52504A` | Texte courant |
| `--color-text-secondary` | `#334155` | `#151510` | Texte titre (renommé `--color-text-dark`) |
| `--color-text-muted` | `#64748b` | `#857F72` | Texte atténué |
| _(nouveau)_ `--color-text-light` | — | `#B5B0A0` | Texte très léger |
| `--color-success` | `#16a34a` | `#0B7A40` | |
| `--color-danger` | `#dc2626` | `#C42828` | |
| `--color-warning` | `#d97706` | `#B56700` | |
| _(nouveau)_ `--color-purple` | — | `#5038C0` | Violet sémantique |

- [ ] Mettre à jour toutes les couleurs `:root` selon la table ci-dessus
- [ ] Ajouter les tokens manquants (`--color-glass`, `--color-primary-glow`, `--color-text-light`, `--color-purple` avec déclinaisons `-bg`, `-border`)
- [ ] Ajouter les tokens sidebar : `--sidebar-bg: #0C1018`, `--sidebar-hover`, `--sidebar-active`, `--sidebar-border`, `--sidebar-text`, `--sidebar-text-hover`
- [ ] Ajouter les tokens tag : `--tag-bg: #E5E3D8`, `--tag-text: #6B6860`

### 1.3 Couleurs — Thème sombre

Remplacer dans `[data-theme="dark"]` de `design-system.css` :

- [ ] `--color-bg: #0B0D10`, `--color-surface: #141820`, `--color-surface-raised: #1E2438`
- [ ] `--color-primary: #3D7EF8`, `--color-primary-hover: #5C96FA`
- [ ] `--color-text: #7A8499`, `--color-text-dark: #ECF0FA`
- [ ] `--color-border: #252C3C`, `--color-border-subtle: #1E2434`
- [ ] Ombres sombres adaptées (opacité accrue)
- [ ] Tous les tokens sémantiques (danger, success, warn, purple) en version sombre

### 1.4 Ombres et géométrie

- [ ] Remplacer les ombres `--shadow-*` (base `rgba(21,21,16,…)` au lieu de `rgb(15 23 42 / …)`)
- [ ] Ajouter `--shadow-focus: 0 0 0 2px #fff, 0 0 0 4px rgba(22,80,224,.4)`
- [ ] Simplifier les rayons : `--radius: 8px`, `--radius-sm: 6px`, `--radius-lg: 10px`

### 1.5 Layout tokens

Remplacer dans `:root` :

| Token | Ancien | Nouveau |
|-------|--------|---------|
| `--sidebar-width` | `210px` | _(supprimé, remplacé par 2 tokens)_ |
| _(nouveau)_ `--sidebar-rail` | — | `58px` |
| _(nouveau)_ `--sidebar-expanded` | — | `252px` |
| `--header-height` | `64px` | `64px` (inchangé) |

- [ ] Ajouter `--sidebar-rail` et `--sidebar-expanded`
- [ ] Supprimer `--sidebar-width`
- [ ] Remplacer `--tr` transition globale : `.15s ease`

### 1.6 Styles de base

- [ ] Mettre à jour la typographie body : `font-size: 14px`, `line-height: 1.6`
- [ ] Mettre à jour `::selection` avec couleur accent
- [ ] Ajouter `@media (prefers-reduced-motion: reduce)` : `*, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }`
- [ ] Mettre à jour les styles `a:hover` avec la nouvelle couleur primary

### 1.7 Persona accent colors

Les 7 couleurs persona existantes restent, mais il faut les harmoniser avec la palette Acte Officiel :

- [ ] Vérifier le contraste de chaque `--persona-*` sur fond `#EDECE6` et `#0B0D10`
- [ ] Ajuster si nécessaire pour WCAG AA (4.5:1 texte, 3:1 composants)

### Livrable

L'ensemble du site change de palette en un instant. Toutes les pages existantes affichent les nouvelles polices, couleurs et ombres sans modification de HTML ni de JS.

### Validation

- [ ] Les 52+ tokens CSS du wireframe sont présents dans `design-system.css`
- [ ] Le mode sombre fonctionne (`[data-theme="dark"]`)
- [ ] Les polices Bricolage Grotesque / Fraunces / JetBrains Mono se chargent correctement
- [ ] Contraste WCAG AA vérifié sur texte courant (clair + sombre)
- [ ] `prefers-reduced-motion` désactive les animations
- [ ] Les 14 pages existantes s'affichent sans régression de layout

---

## Phase 2 — Shell (sidebar rail, header glassmorphisme, mobile)

**Objectif** : refondre la coquille applicative — sidebar en rail, header en glassmorphisme, bottom nav mobile.

**Fichiers modifiés** : `public/assets/js/core/shell.js`, `public/assets/css/app.css` (sections shell), chaque `*.htmx.html` (markup sidebar/header).

### 2.1 Sidebar rail/expanded

Refactorer la sidebar fixe 210px en sidebar rail 58px → 252px.

**CSS** (`app.css` — section `.app-sidebar`) :

- [ ] Largeur par défaut `var(--sidebar-rail)` (58px), transition `width .15s ease`
- [ ] Au hover (`.app-sidebar:hover`) ou pin (`.app-sidebar.pinned`) : largeur `var(--sidebar-expanded)` (252px)
- [ ] Position : `position: fixed`, `top: 0`, `left: 0`, `height: 100vh`, `z-index: var(--z-fixed)`
- [ ] Background : `var(--sidebar-bg)` (#0C1018, quasi-noir)
- [ ] Texte : `var(--sidebar-text)` (blanc 85%)
- [ ] Bordure droite : `var(--sidebar-border)` (blanc 8%)
- [ ] Items nav : icône toujours visible, label en `opacity: 0` → `opacity: 1` au hover/pin
- [ ] Item actif : barre accent gauche `::before` (3px, `var(--color-primary)`)
- [ ] Badges nav (`.nav-badge`) : masqués en rail, visibles en expanded
- [ ] Groupes collapsibles (`.nav-group`) avec chevron animé
- [ ] Indicateurs de scroll (gradient fade haut/bas) quand le contenu dépasse

**JS** (`shell.js`) :

- [ ] Bouton pin (`.sidebar-pin`) : toggle classe `.pinned`, persistance `localStorage('sidebar-pinned')`
- [ ] Restaurer état pin au chargement
- [ ] Section aperçu tablette en bas (`.sidebar-device-section`)
- [ ] Version en footer (`.sidebar-version`)
- [ ] Lien aide en footer sidebar

**HTML** (chaque `*.htmx.html`) :

- [ ] Réduire le markup sidebar : icônes SVG inline (24×24) + `<span class="nav-label">Texte</span>`
- [ ] Ajouter `data-tour="sidebar"` pour la visite guidée future
- [ ] 5 groupes de navigation correspondant aux personas

### 2.2 Header glassmorphisme

Refactorer le header 64px existant.

**CSS** (`app.css` — section `.app-header`) :

- [ ] Background : `var(--color-glass)` avec `backdrop-filter: blur(12px) saturate(1.2)`
- [ ] Border bottom : `1px solid var(--color-border-soft)`
- [ ] Position : `sticky`, `top: 0`, `z-index: var(--z-sticky)`
- [ ] Margin-left : `var(--sidebar-rail)` (décalé par la sidebar)
- [ ] Layout flex : logo (masqué sur desktop, visible mobile) | contexte page | actions droite

**Éléments header** :

- [ ] Contexte page (`.header-ctx`) : titre de la page courante, mis à jour par HTMX `hx-push-url`
- [ ] Bouton recherche globale (`.search-trigger`) : icône loupe + `Ctrl+K`
- [ ] Bouton notifications (`.notif-bell`) + badge compteur
- [ ] Toggle thème clair/sombre (réutiliser `ThemeToggle` existant)
- [ ] Nom utilisateur + bouton déconnexion
- [ ] Hamburger mobile (`.hamburger`) : visible `< 768px`

### 2.3 Layout principal

**CSS** :

- [ ] `.app-main` : `margin-left: var(--sidebar-rail)`, `padding: var(--space-6)`, transition fluide
- [ ] Quand sidebar pinnée : `margin-left: var(--sidebar-expanded)`
- [ ] `max-width: var(--content-max)` pour le contenu
- [ ] Skip link accessibilité (`.skip-link`) en haut de page
- [ ] Footer applicatif (`.app-footer`)

### 2.4 Mobile (< 768px)

**CSS** :

- [ ] Sidebar : `position: fixed`, `left: -260px` → `left: 0` via `.sidebar-open`
- [ ] Overlay (`.sidebar-overlay`) : fond semi-transparent
- [ ] Header compact : height `46px`, hamburger visible
- [ ] Main : `margin-left: 0` (pas de décalage sidebar)
- [ ] Bottom navigation (`.mobile-bnav`) : barre fixe en bas, 5 boutons (Dashboard, Séances, Vote, Stats, Plus)

**JS** (`shell.js`) :

- [ ] `MobileNav.open()` / `.close()` : toggle sidebar drawer + overlay
- [ ] Bottom nav : highlight item actif selon la route
- [ ] Fermer sidebar au tap overlay ou navigation
- [ ] Swipe gauche pour fermer (optionnel)

### 2.5 DeviceBar

- [ ] Indicateur « PC » ou « Tablette » sous le header pour contexte d'interface
- [ ] Implémenté en CSS pur (classe sur `<body>` selon la route)

### Livrable

Shell complet navigable. Sidebar rail au repos, 252px au hover/pin. Header glassmorphisme. Bottom nav mobile. Toutes les pages existantes affichent le nouveau shell.

### Validation

- [ ] Sidebar au rail (58px) sans hover, 252px au hover
- [ ] Pin fonctionne et persiste (localStorage)
- [ ] Labels nav apparaissent/disparaissent en transition fluide
- [ ] Header glassmorphisme visible (contenu scrolle derrière)
- [ ] Mobile : drawer sidebar + bottom nav 5 boutons
- [ ] `margin-left` du main s'adapte sidebar rail/pinned
- [ ] Accessibilité : skip-link, `aria-labels`, focus visible sidebar
- [ ] Les 14 pages existantes s'affichent correctement dans le nouveau shell

---

## Phase 3 — Composants partagés (Web Components + CSS)

**Objectif** : créer les composants réutilisables manquants et restyler les existants selon le wireframe. Chaque nouveau composant est un Web Component (`ag-*`) avec Shadow DOM, suivant le pattern des 8 composants existants.

**Fichiers modifiés** : `public/assets/js/components/*.js`, `public/assets/js/components/index.js`, `public/assets/css/design-system.css` (section Components).

### 3.1 Restyler les composants existants

Mettre à jour le CSS interne (Shadow DOM) de chaque composant pour correspondre au wireframe :

| Composant | Changements |
|-----------|------------|
| `ag-kpi` | Nouveau fond `var(--surface)`, bordure `var(--border)`, ombre `var(--shadow-sm)`, hover scale |
| `ag-badge` | Nouvelles couleurs sémantiques (`--danger`, `--success`, `--warn`, `--purple`) |
| `ag-spinner` | Couleur accent `var(--accent)` |
| `ag-toast` | 4 variantes (success, info, warn, error), auto-dismiss 4.2s, animation entrée/sortie, empilage vertical |
| `ag-quorum-bar` | Segments colorés, seuil visuel, étiquette pourcentage |
| `ag-vote-button` | Grille 2×2, confirmation en 2 temps, animation check |
| `ag-popover` | Fond `var(--surface-raised)`, ombre `var(--shadow-lg)`, flèche CSS |
| `ag-searchable-select` | Style input/dropdown conforme wireframe |

- [ ] Mettre à jour `ag-kpi` avec styles Acte Officiel
- [ ] Mettre à jour `ag-badge` avec couleurs sémantiques wireframe
- [ ] Mettre à jour `ag-toast` avec animations et variantes wireframe
- [ ] Mettre à jour `ag-quorum-bar` avec design wireframe
- [ ] Mettre à jour `ag-vote-button` avec grille et confirmation 2 temps
- [ ] Mettre à jour `ag-popover` et `ag-searchable-select`

### 3.2 Nouveaux composants — Modales

**`ag-modal`** — `public/assets/js/components/ag-modal.js`

- [ ] Backdrop blur (`backdrop-filter: blur(4px)`), animation `modalIn` (scale + fade)
- [ ] Focus-trap (Tab/Shift+Tab cyclent dans la modale)
- [ ] Fermeture : Escape, clic backdrop, bouton ×
- [ ] Attributs : `open`, `title`, `size` (sm/md/lg)
- [ ] Slots : default (contenu), `footer` (boutons)
- [ ] Variante `ag-confirm-dialog` : icône + titre + message + boutons Annuler/Confirmer
- [ ] API JS : `AgModal.open(title, content)`, `AgModal.confirm(title, message, type)`

### 3.3 Nouveaux composants — Navigation et feedback

**`ag-pagination`** — `public/assets/js/components/ag-pagination.js`

- [ ] Boutons numérotés, état actif, chevrons précédent/suivant
- [ ] Attributs : `total`, `page`, `per-page`
- [ ] Événement `page-change` (CustomEvent)

**`ag-breadcrumb`** — `public/assets/js/components/ag-breadcrumb.js`

- [ ] Fil d'Ariane avec séparateurs `/`
- [ ] Attribut : `items` (JSON array `[{label, href}]`)

**`ag-scroll-top`** — `public/assets/js/components/ag-scroll-top.js`

- [ ] Bouton flottant, visible après 300px scroll
- [ ] Animation fade-in/out

**`ag-page-header`** — `public/assets/js/components/ag-page-header.js`

- [ ] Titre avec barre accent à gauche, sous-titre, slot actions droite
- [ ] Attributs : `title`, `subtitle`

### 3.4 Nouveaux composants — Données et visualisation

**`ag-donut`** — `public/assets/js/components/ag-donut.js`

- [ ] Graphique SVG circulaire avec segments proportionnels
- [ ] Attributs : `data` (JSON `[{label, value, color}]`), `size`
- [ ] Label central (total ou pourcentage)

**`ag-mini-bar`** — `public/assets/js/components/ag-mini-bar.js`

- [ ] Barres horizontales mini avec tooltips
- [ ] Attributs : `data` (JSON), `max`

**`ag-tooltip`** — `public/assets/js/components/ag-tooltip.js`

- [ ] Bulle d'aide au hover, positionnement auto (top/bottom/left/right)
- [ ] Attribut : `text`, `position`

### 3.5 Nouveaux composants — Formulaires

**`ag-time-input`** — `public/assets/js/components/ag-time-input.js`

- [ ] Saisie HH:MM avec validation, navigation clavier (flèches haut/bas)
- [ ] Copier-coller intelligent (`18:30`, `14h00`, `1830`)
- [ ] Attributs : `value`, `min`, `max`

**`ag-tz-picker`** — `public/assets/js/components/ag-tz-picker.js`

- [ ] Sélecteur de fuseau horaire (60 fuseaux) avec recherche
- [ ] Basé sur `Intl.supportedValuesOf('timeZone')`
- [ ] Attributs : `value`

### 3.6 Nouveaux composants — Stepper et accordéon

**`ag-stepper`** — `public/assets/js/components/ag-stepper.js`

- [ ] Barre de progression multi-étapes (horizontal ou vertical)
- [ ] Attributs : `steps` (JSON `[{label, status}]`), `current`, `orientation`
- [ ] États : `pending`, `active`, `done`
- [ ] Ligne connectrice entre les étapes, couleur selon état

**`ag-accordion`** — `public/assets/js/components/ag-accordion.js`

- [ ] Panneaux pliables/dépliables avec animation hauteur
- [ ] Attributs : `items` (JSON `[{title, content}]`), `multiple` (ouvrir plusieurs)
- [ ] Chevron animé (rotation 180°)

### 3.7 Styles CSS partagés (non-composants)

Éléments stylés en CSS pur dans `design-system.css`, sans besoin de Web Component :

| Classe CSS | Wireframe | Description |
|-----------|-----------|-------------|
| `.btn`, `.btn-p`, `.btn-ghost`, `.btn-sm`, `.btn-lg` | Boutons | Styles bouton avec accent, danger, success, warn, ghost |
| `.field`, `.field-label`, `.field-input`, `.field-hint` | Champs | Styles de formulaire uniformes |
| `.tag`, `.tag-accent`, `.tag-danger`, `.tag-success` | Tags | Étiquettes colorées |
| `.chip`, `.chip.active` | Chips | Filtres sélectionnables |
| `.card`, `.card:hover` | Cartes | Conteneur élevé avec ombre |
| `.alert`, `.alert-success`, `.alert-warn` | Alertes | Bannière d'information |
| `.avatar`, `.avatar-sm`, `.avatar-lg` | Avatars | Cercle avec initiales et couleurs |
| `.progress-bar`, `.progress-fill` | Barres | Barre de progression simple |
| `.skeleton` | Squelettes | Placeholder animé (shimmer) |
| `.live-dot` | Indicateur live | Point pulsant (animation) |
| `.session-banner` | Bannière session | Timer inactivité fixe en bas |

- [ ] Ajouter/mettre à jour les styles `.btn-*` selon wireframe (couleurs, padding, border-radius Acte Officiel)
- [ ] Ajouter/mettre à jour `.field-*`, `.tag-*`, `.chip-*`, `.card`, `.alert-*`
- [ ] Ajouter `.avatar` avec 8 couleurs et initiales
- [ ] Ajouter `.skeleton` avec animation shimmer
- [ ] Ajouter `.live-dot` avec animation pulse
- [ ] Ajouter `.session-banner` pour le timer d'inactivité

### 3.8 Recherche globale

Implémentée en vanilla JS dans `shell.js` (pas un Web Component — c'est un overlay shell) :

- [ ] Overlay Ctrl+K : fond blur, input centré, résultats en liste
- [ ] Index de recherche statique (pages, actions)
- [ ] Navigation clavier (flèches, Enter, Escape)
- [ ] Fermeture au clic extérieur ou Escape

### 3.9 Panel notifications

Implémenté en vanilla JS dans `shell.js` :

- [ ] Dropdown depuis la cloche header
- [ ] Items avec dot coloré, message, timestamp
- [ ] Bouton « Tout lire »
- [ ] Chargement via HTMX (`hx-get="/api/notifications"`)

### Livrable

Tous les composants fonctionnels. Les 8 existants restylés, 10+ nouveaux créés, styles CSS partagés à jour. L'application utilise la palette Acte Officiel de bout en bout.

### Validation

- [ ] Chaque `ag-*` fonctionne en isolation (créer élément en console → rendu correct)
- [ ] Focus-trap dans `ag-modal`
- [ ] `ag-toast` s'empile et disparaît après 4.2s
- [ ] `ag-time-input` accepte copier-coller (`18:30`, `14h00`, `1830`)
- [ ] `ag-tz-picker` filtre les fuseaux
- [ ] `ag-donut` affiche des segments proportionnels
- [ ] `ag-stepper` affiche correctement les états (pending/active/done)
- [ ] Recherche globale navigable au clavier
- [ ] Styles `.btn-*`, `.card`, `.tag-*` conformes au wireframe
- [ ] Tous les composants respectent le mode sombre

---

## Phase 4 — Pages statiques (Landing, Dashboard, Aide)

**Objectif** : les 3 pages les plus simples, sans interaction CRUD complexe. Mise à jour du HTML HTMX et des scripts de page.

**Fichiers modifiés** : `public/login.htmx.html`, `public/admin.htmx.html` (dashboard), `public/help.htmx.html`, CSS page associés, scripts `public/assets/js/pages/`.

### 4.1 Landing / Login (`/login`)

- [ ] Refondre `login.htmx.html` : header landing (logo + liens Doc/Support)
- [ ] Section hero : titre en `var(--font-display)`, description, 3 fonctionnalités
- [ ] Carte connexion : champs email/mot de passe (vrais formulaires, pas la démo wireframe)
- [ ] Footer landing
- [ ] Fond parchemin `var(--color-bg)`, carte blanche `var(--color-surface-raised)`

### 4.2 Dashboard (`/admin` ou route principale)

- [ ] Bannière onboarding (`.ob-banner`) : dismissable, stockage localStorage
- [ ] Carte action urgente : bordure `var(--color-danger)`, cliquable
- [ ] 4 `<ag-kpi>` cliquables avec navigation HTMX (`hx-get`)
- [ ] Grille 2 colonnes : prochaines séances (liste HTMX) + tâches en attente
- [ ] 3 raccourcis en `.card` cliquables
- [ ] Lien vers visite guidée
- [ ] Mettre à jour `admin.css` pour la grille et les cartes Acte Officiel

### 4.3 Aide (`/aide`)

- [ ] Section visites guidées : 7 cartes (`.card`), grille CSS `auto-fill`
- [ ] FAQ : `<ag-accordion>` avec 23 questions, 5 catégories
- [ ] Recherche + filtres par catégorie (`.chip` actifs)
- [ ] Section exports dans `<details>` natif HTML
- [ ] Bouton « Contacter le support »

### Livrable

3 pages complètes avec design Acte Officiel, navigables depuis la sidebar.

### Validation

- [ ] Landing : formulaire connexion fonctionnel (pas une démo)
- [ ] Dashboard : KPI cliquables, navigation HTMX correcte
- [ ] Aide : FAQ filtrable, accordion fonctionne, recherche filtre en temps réel
- [ ] Responsive sur les 3 pages
- [ ] Mode sombre correct

---

## Phase 5 — Pages CRUD (Séances, Membres, Utilisateurs, Archives)

**Objectif** : les pages de listes avec filtres, pagination, recherche, modales CRUD. Toutes les interactions passent par HTMX.

**Fichiers modifiés** : `public/meetings.htmx.html`, `public/members.htmx.html`, `public/admin.htmx.html` (section utilisateurs), `public/archives.htmx.html`, CSS et JS de page associés.

### 5.1 Séances (`/seances`)

- [ ] Toggle vue Liste / Calendrier (`.chip` + HTMX `hx-get` pour changer de vue)
- [ ] Barre de recherche + tri (date, nom, statut) via `hx-trigger="input changed delay:300ms"`
- [ ] Filtres chips (Toutes, À venir, En cours, Terminées) avec `hx-get`
- [ ] Liste paginée (`<ag-pagination>`, 5 par page, hauteur stable)
- [ ] `<ag-popover>` actions par séance (Ouvrir, Modifier, Dupliquer, Supprimer)
- [ ] Vue calendrier : grille CSS 7 colonnes, événements colorés par statut
- [ ] État vide (illustration + message + bouton CTA)

### 5.2 Membres (`/membres`)

- [ ] 4 `<ag-kpi>` (Membres, Poids total, Groupes, Inactifs)
- [ ] Filtres groupes (`.chip` : Tous, Lot A/B/C, Inactifs)
- [ ] Recherche fuzzy (réutiliser `fuzzySearch()` de `utils.js`) + export CSV
- [ ] Tableau HTML avec colonnes : Nom, Lot, Clé générale, Clé ascenseur, Statut
- [ ] `<ag-popover>` actions (Modifier, Historique, Désactiver)
- [ ] `<ag-modal>` ajout membre (formulaire `.field-*`)
- [ ] `<ag-modal>` détail membre (fiche + historique participation via HTMX)
- [ ] État vide simulable
- [ ] Panel contextuel « Clés de répartition » (via `ShellDrawer.open()`)

### 5.3 Utilisateurs (`/utilisateurs`)

- [ ] Tableau : Nom, Courriel, Rôle, Statut, Dernière connexion
- [ ] `<ag-modal>` ajout utilisateur (nom, email, rôle select, statut)
- [ ] Panel contextuel « Rôles » via drawer
- [ ] Tags colorés par rôle : Admin = `--danger`, Gestionnaire = `--warn`, Opérateur = `--accent`

### 5.4 Archives (`/archives`)

- [ ] Recherche + filtre par type (`.chip`)
- [ ] Tableau paginé (`<ag-pagination>`) : Séance, Date, Résolutions, Présents, Actions
- [ ] `<ag-modal>` détail archive (KPI + résolutions + téléchargements)
- [ ] Bouton téléchargement complet

### Livrable

4 pages avec données réelles (PHP backend), CRUD fonctionnel via HTMX + modales, pagination, filtres.

### Validation

- [ ] Séances : bascule liste/calendrier sans perte de filtre
- [ ] Membres : état vide → ajout via modale → liste mise à jour (HTMX swap)
- [ ] Recherche filtre en temps réel (fuzzy)
- [ ] Pagination stable (pas de saut de layout)
- [ ] Modales : focus-trap, Escape ferme, formulaires soumis via HTMX

---

## Phase 6 — Wizard et Hub (séance)

**Objectif** : les 2 pages les plus complexes en termes de formulaires et navigation interne. Le wizard crée une séance, le hub la pilote.

**Fichiers modifiés** : `public/meetings.htmx.html` (ou nouveau template wizard), JS page dédié, CSS.

### 6.1 Wizard création de séance

- [ ] `<ag-stepper>` horizontal 5 étapes en haut de page
- [ ] **Étape 1** — Infos générales : titre, type (select), date (input date), `<ag-time-input>`, lieu, quorum (input number), alerte délai 21j
- [ ] **Étape 2** — Participants : import CSV/XLSX (drag-drop zone CSS), ajout manuel, liste avec voix, total + quorum calculé en JS
- [ ] **Étape 3** — Résolutions : formulaire ajout (titre, description, majorité select, clé select, vote secret toggle), liste numérotée, panel guide majorités (drawer)
- [ ] **Étape 4** — Récapitulatif : résumé 9 lignes, alerte « Prêt à créer », bouton « Créer la séance » (`hx-post`)
- [ ] **Étape 5** — Confirmation : succès (check animé), prochaine étape (convocations), liens navigation
- [ ] Navigation Précédent/Suivant : validation JS côté client avant d'avancer, panel contextuel par étape (drawer)
- [ ] Persistance brouillon en `sessionStorage` pour ne pas perdre les données

### 6.2 Hub séance (`/seances/:id`)

- [ ] Identité séance : carte avec icône, titre, date, lieu, participants (HTMX load)
- [ ] `<ag-stepper>` vertical 6 étapes : Préparer / Convoquer / Émarger / Voter / Clôturer / Archiver
- [ ] Carte action principale (`.hub-action`) : contenu change selon l'étape courante (HTMX swap)
- [ ] Checklist avancement avec barre de progression (`<ag-kpi>` + `.progress-bar`)
- [ ] Bouton action principal + bouton aperçu
- [ ] `<ag-accordion>` détails :
  - 4 KPI : Participants, Résolutions, Quorum requis, Convocations
  - Documents (liste avec téléchargement)
  - Carte 2e convocation
- [ ] Navigation entre étapes via HTMX (`hx-get="/api/meetings/{id}/step/{n}"`)

### Livrable

Wizard 5 étapes et Hub 6 étapes, navigation interne fluide, formulaires validés côté client.

### Validation

- [ ] Wizard : navigation avant/arrière, validation empêche d'avancer si champs manquants
- [ ] Wizard : brouillon persiste en sessionStorage
- [ ] Hub : changement d'étape met à jour l'action et la checklist
- [ ] `<ag-accordion>` détails s'ouvre/ferme avec animation
- [ ] `<ag-stepper>` reflète l'état correct (done/active/pending)
- [ ] Responsive : formulaires empilés sur mobile

---

## Phase 7 — Pages en direct (Opérateur, Votant, Écran)

**Objectif** : les 3 écrans temps réel — le cœur métier de l'application. Le backend envoie les mises à jour via polling HTMX (`hx-trigger="every 2s"`) ou WebSocket si déjà en place.

**Fichiers modifiés** : `public/operator.htmx.html`, `public/vote.htmx.html`, `public/assets/js/pages/operator.js`, `public/assets/js/pages/vote.js`, CSS associés.

### 7.1 Opérateur (`/seances/:id/live`)

- [ ] Header séance : `.live-dot`, titre, chronomètre JS (compteur temps réel), boutons Salle/Clôturer/Guide
- [ ] 4 `<ag-kpi>` en direct (Présents, Quorum, Ont voté, Résolution N/M)
- [ ] Tags : quorum atteint/non, correspondance, procurations (`.tag-success`, `.tag-danger`)
- [ ] `<ag-quorum-bar>` : segments colorés cliquables (1 par résolution, progression globale)
- [ ] Panel résolution principal :
  - Titre + méta (majorité, clé, secret) en `.tag-*`
  - 3 sous-onglets CSS (`.tab`) : Résultat / Avancé / Présences
  - **Résultat** : boutons Ouvrir/Fermer vote (`hx-post`), Proclamer, barres Pour/Contre/Abstention (`.progress-bar`), compteur voix
  - **Avancé** : comptage manuel (inputs number), N'ont pas voté (liste), Passerelle art. 25-1, notes secrétaire (textarea)
  - **Présences** : tableau participants avec statut badge, actions popover
- [ ] Barre d'action épinglée en bas (`position: sticky`) : Proclamer + Fermer avec raccourcis clavier (P, F)
- [ ] Sidebar ordre du jour : liste résolutions avec statut (`.tag-*`), cliquable pour navigation
- [ ] Demandes de parole : compteur + noms (HTMX polling)
- [ ] Barre de guidance contextuelle (message d'aide selon l'étape du vote)
- [ ] `<ag-modal>` quorum non atteint : 3 options (Reporter, Suspendre, Continuer)
- [ ] `<ag-modal>` procuration : formulaire mandant/mandataire
- [ ] `<ag-modal>` unanimité : confirmation spéciale
- [ ] Auto-avancement après proclamation : transition CSS animée vers résolution suivante

**JS** (`pages/operator.js`) :

- [ ] Raccourcis clavier : `P` = Proclamer, `F` = Fermer le vote
- [ ] Chronomètre : `setInterval` avec formatage MM:SS
- [ ] Polling HTMX pour mise à jour KPI et compteurs votes

### 7.2 Votant (`/vote/:token`)

- [ ] Frame tablette (`.tablet-frame`) avec DeviceBar indicateur
- [ ] Header votant : résolution N/M, timer dégressif (countdown), bouton grossir/réduire (`font-size` toggle)
- [ ] Barre progression résolutions (dots cliquables)
- [ ] Titre résolution + badge majorité
- [ ] 4 `<ag-vote-button>` en grille 2×2 (Pour, Contre, Abstention, Ne se prononce pas)
- [ ] Confirmation en 2 temps : sélection → confirmer (`hx-post`) → écran « Merci » (check animé)
- [ ] Poids votant + lot + procurations (affiché en bas)
- [ ] Demande de parole : 3 états (idle, waiting, speaking) via bouton toggle

**JS** (`pages/vote.js`) :

- [ ] Timer dégressif (`setInterval`, affichage MM:SS, alerte rouge < 30s)
- [ ] Polling pour savoir quand le vote est ouvert/fermé
- [ ] Désactivation boutons après vote confirmé

### 7.3 Écran public (`/seances/:id/ecran`)

- [ ] Plein écran : pas de sidebar ni header (layout dédié)
- [ ] Toggle clair/sombre (bouton flottant)
- [ ] Toggle vue vote/résultat
- [ ] Header minimal : bouton retour, `.live-dot`, chronomètre
- [ ] Titre séance + 3 `<ag-kpi>` (Présents, Quorum, Résolution)
- [ ] `<ag-quorum-bar>` avec seuil visuel
- [ ] Vue vote : titre résolution, barres Pour/Contre/Abstention animées, compteur voix
- [ ] Vue résultat : `<ag-badge>` ADOPTÉE/REJETÉE, barres détail
- [ ] Sidebar ordre du jour fixe (240px)
- [ ] Lien de vote en bas (QR code optionnel)
- [ ] Tailles responsive `clamp()` pour projection

### Livrable

3 écrans temps réel avec données live (polling HTMX), interactions complètes, raccourcis clavier.

### Validation

- [ ] Opérateur : ouvrir vote → voir progression → proclamer → auto-avance
- [ ] Votant : sélection → confirmation → validation → « Merci »
- [ ] Écran : bascule vote/résultat, clair/sombre
- [ ] Raccourcis clavier Opérateur (P, F) fonctionnels
- [ ] Quorum non atteint : les 3 options dans la modale fonctionnent
- [ ] Timer dégressif votant fonctionne
- [ ] Responsive : votant sur tablette, écran sur projecteur

---

## Phase 8 — PostSession et Statistiques

**Objectif** : les pages de clôture et d'analyse de données.

**Fichiers modifiés** : `public/postsession.htmx.html`, `public/analytics.htmx.html`, CSS et JS de page associés.

### 8.1 PostSession (`/seances/:id/cloture`)

- [ ] `<ag-stepper>` horizontal 4 étapes (Vérification, Validation, PV, Envoi)
- [ ] **Étape 1 — Vérification** : tableau résultats avec `<ag-badge>` Adoptée/Rejetée
- [ ] **Étape 2 — Validation** : récapitulatif + boutons Valider/Refuser (confirmation `<ag-modal>` irréversible)
- [ ] **Étape 3 — PV** : champs signataires (`.field-*`), observations, réserves, signature eIDAS (3 modes : aucune, simple, avancée)
- [ ] **Étape 4 — Envoi** : carte PV avec bouton envoi (`hx-post`), exports (7 formats), archivage
- [ ] Navigation Précédent/Suivant épinglée en bas (`position: sticky`)

### 8.2 Statistiques (`/stats`)

- [ ] 4 `<ag-kpi>` avec tendance (flèches ↑↓ + pourcentage vs année précédente)
- [ ] `<ag-donut>` répartition votes (Pour/Contre/Abstention)
- [ ] Graphique participation par séance : barres horizontales CSS (pas de librairie graphique)
- [ ] Résolutions par majorité : barres CSS
- [ ] Durée moyenne des séances : barres CSS
- [ ] Séances par mois : barres verticales CSS avec `<ag-tooltip>` au hover
- [ ] Filtre année (select) + export PDF (bouton `hx-get`)

### Livrable

2 pages complètes avec visualisations CSS pures (barres, donut SVG).

### Validation

- [ ] PostSession : parcours complet étape 1→4
- [ ] Statistiques : toutes les visualisations affichées avec données réelles
- [ ] `<ag-donut>` : segments proportionnels aux données
- [ ] Responsive : graphiques lisibles sur mobile (empilage vertical)
- [ ] Mode sombre : couleurs des graphiques adaptées

---

## Phase 9 — Audit et Paramètres

**Objectif** : les pages de contrôle et configuration système.

**Fichiers modifiés** : `public/trust.htmx.html` (audit), `public/admin.htmx.html` (paramètres), CSS et JS de page associés.

### 9.1 Audit (`/audit`)

- [ ] 4 `<ag-kpi>` (Intégrité, Événements, Anomalies, Dernière séance)
- [ ] Filtres par catégorie (`.chip` : Tous, Votes, Présences, Sécurité, Système)
- [ ] Toggle vue Tableau / Chronologie (`.chip` toggle)
- [ ] **Vue Tableau** : tableau avec sélection multiple (checkboxes) + export sélection
- [ ] **Vue Chronologie** : timeline verticale CSS (ligne + dots colorés + cartes événement)
- [ ] `<ag-modal>` détail événement (SHA-256 complet, métadonnées)
- [ ] `<ag-pagination>` en bas

### 9.2 Paramètres (`/parametres`)

Navigation verticale 6 onglets (CSS `.tab` vertical + HTMX `hx-get` par section) :

- [ ] **Règles de vote** : majorités disponibles (tableau), politiques de quorum, `<ag-modal>` création quorum
- [ ] **Clés de répartition** : liste avec alertes incomplet (`.alert-warn`), bouton nouvelle clé
- [ ] **Sécurité** : niveau CNIL (select), plafond procurations (input), séparation identité/bulletin (toggle), double vote (toggle), double auth (toggle)
- [ ] **Courrier** : 5 templates éditables, `<ag-modal>` édition avec variables insérables (boutons cliquables)
- [ ] **Général** : `<ag-tz-picker>`, email support (input), SMTP (inputs), logo (upload), RGPD (toggle)
- [ ] **Accessibilité** : déclaration RGAA complète (texte), corrections, mesures, non-conformités, contact

### Livrable

2 pages complexes avec toutes les interactions de configuration, données persistées via HTMX POST.

### Validation

- [ ] Audit : bascule tableau/chronologie sans perte de filtre
- [ ] Paramètres : navigation entre les 6 onglets fluide (HTMX swap, pas de rechargement)
- [ ] Modale quorum : aperçu en temps réel
- [ ] Modale template courriel : variables cliquables insèrent dans le textarea
- [ ] `<ag-tz-picker>` filtre correctement les fuseaux

---

## Phase 10 — Visite guidée et intégration finale

**Objectif** : le système de visite guidée, l'intégration bout-en-bout, et la passe accessibilité finale.

### 10.1 Visite guidée

**`ag-guided-tour`** — `public/assets/js/components/ag-guided-tour.js`

- [ ] Overlay semi-transparent + spotlight (découpe CSS `clip-path` ou `box-shadow` géant) sur l'élément ciblé
- [ ] Bulle d'aide positionnée (auto top/bottom/left/right) avec titre, texte, boutons Précédent/Suivant/Terminer
- [ ] Ciblage DOM via attributs `data-tour="nom"` sur les éléments existants
- [ ] 7 parcours prédéfinis :
  1. Dashboard (3 étapes)
  2. Wizard création (4 étapes)
  3. Opérateur live (5 étapes)
  4. Membres (3 étapes)
  5. Hub séance (3 étapes)
  6. Statistiques (2 étapes)
  7. PostSession (3 étapes)
- [ ] 23 étapes au total, définies en JSON dans un fichier `tours.js`
- [ ] Navigation clavier (flèches, Escape pour quitter)
- [ ] Barre de progression (dots ou fraction N/M)
- [ ] Scroll automatique vers l'élément ciblé (`scrollIntoView({ behavior: 'smooth' })`)
- [ ] Persistance : `localStorage('tour-{name}-done')` pour ne pas relancer

### 10.2 Intégration bout-en-bout

- [ ] Toutes les routes sidebar connectées aux bonnes pages
- [ ] Données réelles cohérentes entre les pages (API PHP)
- [ ] Notifications reliées aux pages cibles (clic notification → navigation HTMX)
- [ ] Recherche globale (Ctrl+K) indexe toutes les 16 pages
- [ ] Bannière session timeout fonctionnelle (timer inactivité 15 min, alerte à 2 min)
- [ ] `hx-push-url` correct partout (historique navigateur cohérent)

### 10.3 Accessibilité finale (RGAA 97%)

- [ ] `aria-label` sur tous les éléments interactifs (boutons icônes, liens)
- [ ] `aria-live="polite"` sur régions dynamiques (toasts, résultats vote, KPI live)
- [ ] `aria-live="assertive"` sur les erreurs de formulaire
- [ ] Focus-trap vérifié sur toutes les modales et drawers
- [ ] Tab order logique sur chaque page (pas de `tabindex` > 0)
- [ ] Skip-link fonctionnel vers `#main-content`
- [ ] Contrastes WCAG AA vérifiés (clair + sombre) sur tous les textes et composants
- [ ] `role="alert"` sur les messages d'erreur
- [ ] `role="navigation"` sur sidebar et bottom nav
- [ ] `role="dialog"` sur les modales

### 10.4 Responsive final

- [ ] 4 breakpoints vérifiés : `>1024px`, `768-1024px`, `480-768px`, `<480px`
- [ ] Grilles adaptatives : 4→2→1 colonnes selon le breakpoint
- [ ] Sidebar : rail desktop → drawer mobile (< 768px)
- [ ] Bottom nav mobile visible < 768px
- [ ] Tableaux : scroll horizontal sur mobile (`overflow-x: auto`)
- [ ] Graphiques (barres, donut) : taille adaptée, légendes visibles

### 10.5 Print

- [ ] `@media print` : masquer sidebar, header, toasts, bottom nav, boutons action
- [ ] Fond blanc forcé, texte noir
- [ ] Tableaux et graphiques imprimables
- [ ] Page breaks avant les sections principales

### Livrable

Application frontend complète, conforme au wireframe « Acte Officiel » sur les 16 pages, accessible, responsive, imprimable.

### Validation finale

- [ ] Parcours complet : Landing → Dashboard → Wizard → Hub → Opérateur → PostSession → Archives
- [ ] Les 203 interactions du wireframe fonctionnent
- [ ] Mode sombre sur toutes les pages
- [ ] Responsive sur toutes les pages (4 breakpoints)
- [ ] RGAA 97%+ vérifié (audit aXe ou Lighthouse)
- [ ] Visite guidée fonctionnelle sur les 7 parcours
- [ ] Print correct sur les pages clés (Dashboard, Stats, Audit)
- [ ] Performance : First Contentful Paint < 1.5s (pas de build, fichiers légers)

---

## Matrice de dépendances

```
Phase 1 (Design Tokens)
  └── Phase 2 (Shell)
        └── Phase 3 (Composants partagés)
              ├── Phase 4 (Landing, Dashboard, Aide)
              ├── Phase 5 (Séances, Membres, Utilisateurs, Archives)
              ├── Phase 6 (Wizard, Hub)
              ├── Phase 7 (Opérateur, Votant, Écran)    ← le plus complexe
              ├── Phase 8 (PostSession, Stats)
              └── Phase 9 (Audit, Paramètres)
        └── Phase 10 (Visite guidée, intégration, a11y)
```

Les phases 4 à 9 sont **parallélisables** après la phase 3. L'ordre proposé va du plus simple au plus complexe.

**Pas de Phase 0.** Aucune installation d'outillage nécessaire — la pile technique est déjà en place.

---

## Estimation par phase

| Phase | Pages | Composants modifiés/créés | Complexité | Fichiers principaux |
|-------|-------|--------------------------|------------|-------------------|
| 1 | 0 | 0 | Moyenne | `design-system.css`, `<link>` fonts |
| 2 | 0 | 0 | Haute | `shell.js`, `app.css`, tous les `*.htmx.html` |
| 3 | 0 | 8 restylés + 10 créés | Haute | `components/*.js`, `design-system.css` |
| 4 | 3 | 0 | Faible | `login.htmx.html`, `admin.htmx.html`, `help.htmx.html` |
| 5 | 4 | 0 | Moyenne | `meetings.htmx.html`, `members.htmx.html`, `archives.htmx.html` |
| 6 | 2 | 0 | Haute | Wizard + Hub templates et JS |
| 7 | 3 | 0 | Très haute | `operator.htmx.html`, `vote.htmx.html`, écran |
| 8 | 2 | 0 | Moyenne | `postsession.htmx.html`, `analytics.htmx.html` |
| 9 | 2 | 0 | Haute | `trust.htmx.html`, paramètres |
| 10 | 0 | 1 (`ag-guided-tour`) | Haute | `ag-guided-tour.js`, `tours.js` |

---

## Correspondance technologique wireframe → production

| Wireframe (React) | Production (PHP + HTMX + vanilla) |
|-------------------|----------------------------------|
| `useState()`, `useEffect()` | Variables JS locales, `setInterval`, event listeners |
| `onClick={() => setPage(x)}` | `hx-get="/page"` + `hx-push-url` |
| `{data.map(item => <Row />)}` | PHP `foreach` dans le template HTMX |
| React component (`<Modal />`) | Web Component (`<ag-modal>`) |
| `useRef()` + DOM manipulation | `document.querySelector()` direct |
| Context / Zustand store | `localStorage` + `CustomEvent` + `data-*` attributs |
| `fetch()` + `useEffect` | `hx-get` / `hx-post` + `hx-trigger` |
| CSS Modules / styled-components | Shadow DOM CSS (composants) ou BEM dans `design-system.css` |
| React Router | PHP routing + HTMX `hx-push-url` + `hx-target` |
| `Portal` (modales, tooltips) | Web Component avec `position: fixed` (sort du flow) |

---

## Règle de commit

Chaque sous-tâche (`[ ]`) est un commit atomique. Message format :

```
feat(phase-N): description courte

- Détail 1
- Détail 2
```

Exemples :
- `feat(phase-1): replace CSS design tokens with Acte Officiel palette`
- `feat(phase-2): refactor sidebar from fixed 210px to rail 58px/expanded 252px`
- `feat(phase-3): add ag-modal Web Component with focus-trap and backdrop blur`
- `feat(phase-7): update operator page with vote control and live progress bars`
