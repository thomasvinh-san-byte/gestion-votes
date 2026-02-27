# AG-Vote v3.19.2 — Plan directeur de refonte « Acte Officiel »

> **Méthode Linus** : petits incréments, chaque phase compilable et testable, zéro régression, fondations d'abord.
>
> **Pile technique** : PHP 8.4 MVC + HTMX + Web Components vanilla + CSS pur. Aucun outil de build.
>
> **Wireframe de référence** : `docs/wireframe/ag_vote_v3_19_2.html` (16 pages, 2830 lignes, React 18 standalone).

---

## 0. État des lieux

### 0.1 Ce qui existe (v3.19.2)

| Couche | Technologie | Inventaire |
|--------|------------|------------|
| Backend | PHP 8.4 custom MVC | 38 controllers, 33 repositories, 18 services, 100+ endpoints API |
| Frontend | HTMX + Web Components | 14 pages `.htmx.html`, 8 composants `ag-*` (Shadow DOM) |
| CSS | Design system + pages | `design-system.css` (2 702 l.), `app.css` (814 l.), 16 fichiers page |
| JS | Shell + utilitaires | `shell.js` (502 l.), `utils.js` (799 l.), 24 scripts page |
| BDD | PostgreSQL | 40+ tables, pgcrypto, audit hash chain SHA-256 |
| Temps réel | EventBroadcaster Redis | WebSocket queue + file fallback |
| Auth | Session + RBAC | 4 rôles système + 3 rôles meeting, CSRF, rate limiting |

### 0.2 Cible wireframe « Acte Officiel »

| Aspect | Actuel | Cible wireframe |
|--------|--------|-----------------|
| Polices | Inter, JetBrains Mono | Bricolage Grotesque, Fraunces (display), JetBrains Mono |
| Accent | Indigo `#4f46e5` | Bleu encre `#1650E0` |
| Fond | Slate `#f8fafc` | Parchemin `#EDECE6` |
| Sidebar | Fixe 210px | Rail 58px → 252px hover/pin |
| Header | Solide | Glassmorphisme `backdrop-filter: blur()` |
| Mobile | Hamburger seul | Bottom nav 5 boutons + drawer |
| Pages | 14 | 16 (+ Landing, Écran salle) |
| Composants | 8 Web Components | 8 existants + 12 nouveaux |
| Thème sombre | Slate-based | Tokens dédiés complets |
| Accessibilité | Basique | RGAA 97%, skip-link, focus-trap, aria-live |

### 0.3 Décision architecturale

Le remaniement est un **restyling progressif du frontend existant**. Le backend PHP, l'API REST, les templates HTMX et la structure Web Components sont conservés. Aucun outil de build n'est ajouté.

### 0.4 Avancement Phase 1 (FAIT)

Les design tokens « Acte Officiel » ont été appliqués :
- [x] 52+ CSS custom properties (clair + sombre) dans `design-system.css`
- [x] Polices Bricolage Grotesque + Fraunces dans 21 templates HTML
- [x] Ombres, géométrie, layout tokens
- [x] `::selection`, `h1/h2` en `font-display`
- [x] Google Fonts mis à jour partout

---

## 1. Principes

1. **Chaque phase produit un livrable fonctionnel** — pas de phase « préparatoire » sans résultat visible.
2. **Chaque phase est autonome** — on peut s'arrêter et avoir un produit utilisable.
3. **Fondations d'abord** — Tokens → Shell → Composants → Pages simples → Pages complexes.
4. **Une seule chose à la fois** — périmètre clair et borné par phase.
5. **On travaille avec l'existant** — modifier, pas réécrire depuis zéro.
6. **Zéro dépendance externe** — CSS pur, vanilla JS, Shadow DOM.

---

## 2. Correspondance des noms de tokens

Le wireframe utilise des noms courts, le projet des noms longs. On conserve les noms longs.

| Wireframe | Production | Notes |
|-----------|-----------|-------|
| `--bg` | `--color-bg` | |
| `--surface` | `--color-surface` | |
| `--surface-alt` | `--color-bg-subtle` | |
| `--surface-raised` | `--color-surface-raised` | |
| `--glass` | `--color-glass` | nouveau |
| `--border` | `--color-border` | |
| `--border-soft` | `--color-border-subtle` | |
| `--border-dash` | `--color-border-dash` | nouveau |
| `--accent` | `--color-primary` | |
| `--accent-hover` | `--color-primary-hover` | |
| `--accent-light` | `--color-primary-subtle` | |
| `--accent-dark` | `--color-primary-active` | |
| `--accent-glow` | `--color-primary-glow` | nouveau |
| `--sidebar-bg` | `--sidebar-bg` | identique |
| `--text-dark` | `--color-text-dark` | nouveau |
| `--text` | `--color-text` | |
| `--text-muted` | `--color-text-muted` | |
| `--text-light` | `--color-text-light` | nouveau |
| `--danger` | `--color-danger` | |
| `--success` | `--color-success` | |
| `--warn` | `--color-warning` | |
| `--purple` | `--color-purple` | nouveau |
| `--tag-bg` | `--tag-bg` | nouveau |
| `--tag-text` | `--tag-text` | nouveau |
| `--font` | `--font-sans` | |
| `--font-display` | `--font-display` | nouveau |
| `--font-mono` | `--font-mono` | |
| `--tr` | _(inline)_ | `.15s ease` |
| `--sidebar-rail` | `--sidebar-rail` | nouveau |
| `--sidebar-expanded` | `--sidebar-expanded` | nouveau |

---

## 3. Vue d'ensemble des phases

| # | Phase | Scope | Backend | Priorité |
|---|-------|-------|---------|----------|
| 1 | Design Tokens | CSS custom properties | Aucun | **FAIT** |
| 2 | Shell + CSS utilitaires | Sidebar, header, mobile, ~30 classes CSS | Aucun | Haute |
| 3 | Composants partagés | 8 restyling + 12 nouveaux Web Components | 2 endpoints notifications | Haute |
| 4 | Pages statiques | Landing, Dashboard, Aide | Aucun | Moyenne |
| 5 | Pages CRUD | Séances, Membres, Utilisateurs, Archives | 2 endpoints (calendar, duplicate) | Moyenne |
| 6 | Wizard + Hub | Création séance + Fiche séance | 1 migration BDD (timezone) | Haute |
| 7 | Pages live | Opérateur, Votant, Écran salle | 2 endpoints + 2 migrations | Critique |
| 8 | Post-session + Stats | Clôture, PV, Statistiques | 1 endpoint (archive ZIP) | Moyenne |
| 9 | Audit + Paramètres | Audit, Settings 6 onglets, pages outils | RBAC labels | Moyenne |
| 10 | Tour guidé + intégration | Tour, timeout, Ctrl+K, accessibilité finale | 1 endpoint (extend-session) | Basse |

### Matrice de dépendances

```
Phase 1 ──→ Phase 2 ──→ Phase 3 ──→ Phase 4
                              │
                              ├──→ Phase 5
                              ├──→ Phase 6
                              ├──→ Phase 7  (la plus critique)
                              ├──→ Phase 8
                              ├──→ Phase 9
                              └──→ Phase 10
```

Phases 4-9 peuvent être parallélisées après Phase 3. Phase 10 dépend de toutes les autres.

---

## 4. Détail par phase

---

### Phase 2 — Shell + CSS utilitaires du wireframe

**Objectif** : Sidebar rail/pin, header glassmorphisme, mobile bottom nav, et toutes les classes CSS utilitaires du wireframe.

#### 2.1 Shell — Sidebar

| Tâche | Fichier(s) |
|-------|-----------|
| Sidebar rail 58px, expand 252px au hover | `design-system.css`, `shell.js` |
| Position absolute (pas de layout shift) | `design-system.css` |
| Labels opacity 0 → 1 au hover/pin | `design-system.css` |
| Bouton pin (épingler la sidebar) | `shell.js` |
| Nav groups avec divider en mode rail | `design-system.css` |
| Nav badges (position absolute right) | `design-system.css` |
| Fade indicators (scroll top/bottom) | `design-system.css`, `shell.js` |
| Section devices + footer version | Templates HTMX |
| Scrollbar fine (3px, rgba blanc) | `design-system.css` |

#### 2.2 Shell — Header

| Tâche | Fichier(s) |
|-------|-----------|
| Glassmorphisme (`backdrop-filter: blur(20px)`) | `design-system.css` |
| Hauteur 56px desktop, 46px mobile | `design-system.css` |
| Logo Fraunces 17px + logo-mark carré accent | Templates, `design-system.css` |
| Hamburger visible uniquement mobile | `design-system.css` |
| Header context (session active) `.header-ctx` | `design-system.css` |

#### 2.3 Shell — Mobile

| Tâche | Fichier(s) |
|-------|-----------|
| Sidebar drawer off-screen left -260px | `design-system.css` |
| Overlay backdrop `.sidebar-overlay` | `shell.js` |
| Bottom nav 5 boutons `.mobile-bnav` | `design-system.css`, templates |
| `padding-bottom: 76px` sur `.main` en mobile | `design-system.css` |

#### 2.4 CSS utilitaires à extraire du wireframe

**30+ classes CSS à ajouter dans `design-system.css`** (extraites du wireframe lignes 123-1019) :

| Classe(s) | Catégorie | Lignes wireframe |
|-----------|-----------|-----------------|
| `.card`, `.card:hover`, `.card[cursor]` | Cards | 321-337 |
| `.irow`, `.irow:hover`, `.irow:active`, `.irow-title`, `.irow-arrow` | Interactive rows | 339-354 |
| `.kpi`, `.kpi:hover`, `.card-title` | KPI cards | 356-370 |
| `.btn`, `.btn-p`, `.btn-danger`, `.btn-success`, `.btn-warn`, `.btn-ghost`, `.btn-sm`, `.btn-lg`, `.btn-block` | Boutons | 372-438 |
| `.field`, `.field-label`, `.field-input`, `.field-hint`, `.field-counter`, `select.field-input`, `textarea.field-input` | Champs | 440-470 |
| `.tag`, `.tag-accent`, `.tag-danger`, `.tag-success`, `.tag-warn`, `.tag-purple` | Tags | 484-495 |
| `.chip`, `.chip:hover`, `.chip.active` | Chips | 497-506 |
| `.alert`, `.alert-success`, `.alert-warn` | Alertes | 508-517 |
| `.table-wrap`, `table`, `th`, `td`, `tbody tr:nth-child(even)`, `tbody tr:hover` | Tables | 519-548 |
| `.pagination`, `.pg-btn`, `.pg-active` | Pagination | 550-560 |
| `.progress-bar`, `.progress-fill` | Barres de progression | 562-564 |
| `.live-dot`, `@keyframes pulse` | Indicateur live | 566-568 |
| `.skeleton`, `@keyframes shimmer` | Skeleton loading | 570-572 |
| `.grid-2/3/4`, `.row`, `.flex-between`, `.flex-row`, `.flex-col` | Grilles | 574-582 |
| `.truncate`, `.line-clamp-2`, `.word-break` | Overflow text | 472-475 |
| `.avatar`, `.avatar-sm`, `.avatar-lg` | Avatars | 480-482 |
| `.op-tabs`, `.op-tab`, `.op-tab.active`, `.op-tab .notif` | Onglets opérateur | 587-599 |
| `.vote-btn`, `.vote-pour`, `.vote-contre`, `.vote-abst` + `.selected` | Boutons de vote | 601-617 |
| `.stepper`, `.step-dot`, `.step-dot.done/active` | Stepper horizontal | 619-623 |
| `.hub-stepper`, `.hub-step-*`, `.hub-action`, `.hub-identity` | Hub séance | 625-676 |
| `.overlay-backdrop`, `.modal`, `.modal-h`, `.modal-b`, `.modal-f` | Modales | 678-685 |
| `.popover-wrap`, `.popover`, `.popover .mi` | Popovers | 687-693 |
| `.theme-toggle` | Toggle thème | 695-699 |
| `.htip`, `.htip-portal` | Help tooltip | 701-703 |
| `.ctx-panel` | Panneau contextuel | 705-708 |
| `.device-bar`, `.tablet-frame`, `.votant-shell` | Frames devices | 710-731 |
| `.page-anim`, `@keyframes pageIn/fadeIn/slideUp` | Animations page | 733-737 |
| `.landing-*`, `.landing-header/body/footer` | Landing page | 739-743 |
| `.session-banner` | Bannière session | 745-746 |
| `.empty-state`, `.empty-state-icon` | État vide | 748-752 |
| `.app-footer` | Pied de page app | 754-755 |
| `.step-nav` | Navigation wizard | 757-758 |
| `.wizard-progress`, `.wiz-step`, `.wiz-num`, `.wiz-step-item`, `.wiz-snum` | Wizard stepper | 856-920 |
| `.ob-banner`, `.ob-icon`, `.ob-title`, `.ob-btn-*`, `.ob-close` | Onboarding banner | 875-894 |
| `.notif-bell`, `.notif-count`, `.notif-panel`, `.notif-item` | Notifications | 896-909 |
| `.search-trigger`, `.search-overlay`, `.search-box`, `.search-result-item` | Recherche globale | 922-937 |
| `.confirm-dialog`, `.confirm-icon-wrap` | Dialog confirmation | 940-944 |
| `.live-timer`, `.live-timer-val` | Timer live | 946-948 |
| `.search-bar-row`, `.search-bar-wrap` | Barre recherche | 950-953 |
| `.toast-container`, `.toast`, `.toast-icon`, `.toast-title`, `.toast-msg`, `.toast-close` | Toasts | 810-841 |
| `.tour-bubble`, `.tour-spotlight-ring`, `.tour-progress` | Tour guidé | 843-853 |
| `.scroll-top`, `.scroll-top.visible` | Retour en haut | 995-998 |
| `.op-kbd` | Hints clavier | 1000-1002 |
| `.tip-wrap`, `.tip-body` | Tooltips | 1004-1008 |
| `.skip-link`, `.skip-link:focus` | Skip link a11y | 966-968 |
| `:focus-visible` rules | Focus ring global | 970-973 |
| `.wf-step` | Step card wizard | 975-976 |
| `@media print` | Styles d'impression | 1010-1018 |
| `@media (max-width: 1024px/768px/480px)` | Responsive complet | 781-993 |

#### 2.5 Livrable Phase 2

- [ ] Shell navigable sur les 14 pages existantes
- [ ] Sidebar rail → expand sans layout shift
- [ ] Header glassmorphisme fonctionnel
- [ ] Mobile drawer + bottom nav
- [ ] Toutes les classes CSS utilitaires du wireframe intégrées
- [ ] Responsive vérifié sur 4 breakpoints (1024, 768, 480, print)

#### 2.6 Impact backend

Aucun. Phase 100% frontend.

---

### Phase 3 — Composants partagés

**Objectif** : Restyler les 8 composants existants + créer 12 nouveaux Web Components.

#### 3.1 Restyling des 8 composants existants

| Composant | Changements |
|-----------|------------|
| `ag-kpi` | Tokens Acte Officiel, `font-mono` pour valeurs, hover translateY(-2px) |
| `ag-badge` | → renommer en `.tag` CSS, variantes `-accent/-danger/-success/-warn/-purple` |
| `ag-spinner` | Couleur accent, taille configurable |
| `ag-toast` | Border-left coloré, icône sémantique, auto-dismiss 4200ms, `aria-live="polite"` |
| `ag-quorum-bar` | Threshold marker, couleurs sémantiques, mode compact |
| `ag-vote-button` | 2x2 grid, couleurs Pour/Contre/Abst du wireframe, animation selected |
| `ag-popover` | Shadow-lg, animation `popIn`, items avec icônes, séparateurs |
| `ag-searchable-select` | Input dans dropdown, highlight accent, `aria-expanded` |

#### 3.2 Nouveaux Web Components à créer

| Composant | Source wireframe | Rôle |
|-----------|-----------------|------|
| `ag-modal` | `.modal`, `.overlay-backdrop` | Dialog accessible, focus-trap, Escape, backdrop click |
| `ag-pagination` | `.pagination`, `.pg-btn` | Pagination numérotée |
| `ag-breadcrumb` | `.breadcrumb`, `.bc-item` | Fil d'Ariane |
| `ag-scroll-top` | `.scroll-top` | Bouton retour en haut (300px seuil) |
| `ag-page-header` | `.page-title`, `.page-sub`, `.bar` | En-tête de page avec barre accent |
| `ag-donut` | SVG `Donut` component | Graphique donut SVG |
| `ag-mini-bar` | `MiniBar` component | Mini barres inline avec tooltips |
| `ag-tooltip` | `.tip-wrap`, `.tip-body` | Tooltip CSS pur |
| `ag-time-input` | `TimeInput` component | Input HH:MM avec auto-advance et paste |
| `ag-tz-picker` | `TZPicker` component | Sélecteur timezone (58 fuseaux) |
| `ag-stepper` | `.stepper`, `.step-dot` | Stepper horizontal |
| `ag-confirm` | `.confirm-dialog` | Dialog confirmation Promise-based |

#### 3.3 Extensions shell.js

| Fonctionnalité | Source wireframe |
|---------------|-----------------|
| Notifications dropdown | `.notif-bell`, `.notif-panel`, `.notif-item` |
| Recherche globale Ctrl+K | `.search-overlay`, `.search-box`, `SEARCH_IDX` |
| Scroll-to-top listener | `.scroll-top.visible` au-delà de 300px |

#### 3.4 Impact backend

| Endpoint | Méthode | Statut |
|----------|---------|--------|
| `/api/v1/notifications` | GET | `NotificationRepository` existe, controller à créer |
| `/api/v1/notifications_read` | PUT | À créer |

#### 3.5 Livrable Phase 3

- [ ] 8 composants existants restylés Acte Officiel
- [ ] 12 nouveaux Web Components fonctionnels
- [ ] Notifications dropdown avec données API
- [ ] Recherche globale Ctrl+K (index client-side)
- [ ] Palette Acte Officiel end-to-end sur tous les composants

---

### Phase 4 — Pages statiques (Landing, Dashboard, Aide)

**Objectif** : Les 3 pages sans CRUD complexe.

#### 4.1 Landing / Login (`public/index.html`)

- Hero section avec tagline + 3 bullets features
- Carte login centrée (logo 64px, formulaire, bouton primaire)
- Header landing (logo + liens Documentation/Support)
- Footer landing
- Classes : `.landing`, `.landing-header`, `.landing-body`, `.landing-footer`

#### 4.2 Dashboard (`public/admin.htmx.html`)

- Onboarding banner (`.ob-banner`, dismissible, `localStorage`)
- Carte action urgente (border danger, navigation directe)
- 4 KPIs cliquables (AG à venir, En cours, Convocations, PV)
- Grille 2 colonnes : Prochaines séances + Tâches en attente
- 3 raccourcis (Créer séance, Piloter vote, Consulter audit)
- Interactive rows (`.irow`) avec hover et navigation

#### 4.3 Aide (`public/help.htmx.html`)

- 7 cartes tour guidé (avec icône, nom de page, nombre d'étapes, durée)
- FAQ accordion avec recherche et filtres par catégorie (5 chips)
- 22 questions/réponses
- Tableau exports de référence (8 types, format, disponibilité)
- Bouton support

#### 4.4 Impact backend

Aucun. `DashboardController::index()` et `AuthController::login()` existent.

---

### Phase 5 — Pages CRUD (Séances, Membres, Utilisateurs, Archives)

**Objectif** : 4 pages liste avec filtres, pagination, recherche, modales CRUD.

#### 5.1 Séances (`public/meetings.htmx.html`)

- Toggle liste/calendrier
- Barre recherche + tri (date, nom, statut)
- Chips filtre : Toutes, À venir, En cours, Terminées
- Pagination (5 par page, hauteur stable)
- Cards session avec status dot + popover actions (Ouvrir, Modifier, Dupliquer, Supprimer)
- Vue calendrier : grille 7 colonnes, événements colorés
- État vide

#### 5.2 Membres (`public/members.htmx.html`)

- 4 KPIs (Membres, Voix totales, Groupes, Inactifs)
- Chips filtre par groupe/lot
- Recherche + export CSV
- Table : Nom, Lot, Clé générale, Clé ascenseur, Statut, Actions
- Modal ajout membre
- Modal détail membre (historique de participation)
- État vide

#### 5.3 Utilisateurs (`public/admin.htmx.html` onglet)

- Panneau explication des rôles
- Table : Nom (avatar), Courriel, Rôle, Statut, Dernière connexion
- Modal ajout utilisateur (Nom, Courriel, Rôle, Statut)
- Tags rôle colorés

#### 5.4 Archives (`public/archives.htmx.html`)

- Recherche + filtre type (AG Ord., AG Extra., Conseil)
- Table paginée : Séance, Date, Résolutions, Présents, Actions
- Modal détail archive (KPIs, résolutions, téléchargements)
- Bouton téléchargement complet (ZIP)

#### 5.5 Impact backend

| Endpoint | Méthode | Statut |
|----------|---------|--------|
| `/api/v1/meetings?view=calendar&month=2026-02` | GET | Filtre à ajouter sur `MeetingsController` |
| `/api/v1/meetings/:id/duplicate` | POST | À créer |

---

### Phase 6 — Wizard + Hub

**Objectif** : Les 2 pages de navigation interne les plus complexes.

#### 6.1 Wizard — Création de séance (5 étapes)

| Étape | Contenu | Composants |
|-------|---------|-----------|
| 1. Infos générales | Titre*, Type*, Date*, Heure* (`ag-time-input`), Lieu, Quorum policy, alerte deadline 21j | `.wiz-step`, `.field-*`, `.alert-warn` |
| 2. Participants | Import CSV/XLSX drag-drop, ajout manuel, table participants + poids par clé, résumé voix/quorum | Upload zone, `.tag-success` |
| 3. Résolutions | Formulaire ajout (titre*, description, majorité*, clé répartition*, vote secret), liste avec édition | `.chip`, `.tag-purple` |
| 4. Récapitulatif | Tableau vérification, alertes, bouton créer | `.alert-success`, `.alert-warn` |
| 5. Confirmation | Écran succès, liens suivants (envoyer convocations, mes séances) | Pas un vrai step wizard |

- Stepper : `.wiz-progress-wrap`, `.wiz-step-item`
- Persistence brouillon : `sessionStorage`
- Validation client-side à chaque étape

#### 6.2 Hub — Fiche séance

- Bandeau identité session (nom, date, lieu, participants, edit)
- Stepper vertical 6 étapes : Préparer → Convoquer → Pointer → Piloter → Clôturer → Archiver
- Carte action principale (encadré accent, bouton CTA)
- Checklist avec barre de progression
- Accordion détails : 4 KPIs, documents, option 2e convocation
- Classes : `.hub-stepper`, `.hub-step-*`, `.hub-action`, `.hub-identity`

#### 6.3 Impact backend

| Changement | Type | Détail |
|-----------|------|--------|
| `meetings.timezone` | Migration | `ALTER TABLE meetings ADD COLUMN timezone VARCHAR(50) DEFAULT 'Europe/Paris'` |
| Mapping 8 états → 6 labels | Frontend | `draft/scheduled/frozen` → Préparation, `live` → Vote, `paused` → Suspendue, `closed` → Clôturée, `validated` → Validée, `archived` → Archivée |

---

### Phase 7 — Pages live (Opérateur, Votant, Écran salle)

**Objectif** : Le cœur métier — 3 écrans temps réel. Phase la plus critique.

#### 7.1 Opérateur (`public/operator.htmx.html`)

**Layout** :
- Header compact : live-dot, chrono `setInterval`, boutons Salle/Clôturer/Guide
- Strip 4 KPIs : Présents, Quorum, Ont voté, Résolution n/total
- Tags contextuels : quorum atteint, correspondance, procurations
- Barre progression résolutions (segments vert/bleu/gris cliquables)
- Split principal : panneau résolution (gauche) + ordre du jour (droite)

**Panneau résolution — 3 sous-onglets** (`.op-tabs`) :

| Onglet | Contenu |
|--------|---------|
| Résultat | Ouvrir/Fermer vote, barres Pour/Contre/Abst animées, pourcentages, bouton Proclamer |
| Avancé | Comptage papier (inputs nombre), votants manquants (liste + poids), passerelle 25-1, unanimité, procuration, notes secrétaire |
| Présences | KPIs présence, table participants avec toggle présent/absent, clé active |

**Barre d'actions sticky** :
- Bouton Proclamer (raccourci clavier `P`)
- Bouton Fermer/Rouvrir vote (raccourci clavier `F`)

**Barre de guidance contextuelle** :
- Adapte le message en temps réel selon l'état du vote

**Interactions critiques** :
- Auto-advance : après proclamation → transition 600ms → résolution suivante
- Égalité de vote : détection `pctP === pctC` → voix prépondérante président (art. 22)
- Quorum non atteint : modal bloquant avec 3 options (Reporter / Suspendre 30min / Continuer sous réserve)
- Modal procuration
- Modal unanimité

#### 7.2 Votant (`public/vote.htmx.html`)

- Frame tablette (`.tablet-frame`, 780px max)
- Header : dots progression, timer countdown, zoom toggle
- Question résolution (titre, majorité, description)
- Grille 2x2 boutons vote (`.vote-pour/contre/abst`) avec animation selected
- Confirmation en 2 étapes (sélection → validation)
- Post-vote : animation succès colorée
- Footer : identité votant (nom, lot, poids, procurations)
- Demande de parole (idle → waiting → speaking)

#### 7.3 Écran salle (`public/projector.htmx.html` ou route dédiée)

- Plein écran (fixed, sans sidebar/header)
- Toggle clair/sombre indépendant
- Toggle mode vote/résultat
- KPIs : Présents, Quorum, Résolution
- Barre quorum avec marqueur seuil
- Mode vote : barres animées Pour/Contre/Abst
- Mode résultat : résumé des résolutions votées
- Tracker résolutions (pills bottom)
- `clamp()` pour scaling projecteur

#### 7.4 Impact backend

| Changement | Type | Détail |
|-----------|------|--------|
| `meetings.est_2e_convocation` | Migration | `BOOLEAN DEFAULT FALSE` |
| `meetings.seance_parent_id` | Migration | `UUID NULL REFERENCES meetings(id)` |
| Passerelle art. 25-1 | Endpoint | `POST /api/v1/motions/:id/passerelle_25_1` — si vote art. 25 échoue avec >= 1/3, re-vote en art. 24 |
| 2e convocation | Endpoint | `POST /api/v1/meetings/:id/second_convocation` — report avec quorum réduit 33% |
| Vérifier | Existant | `VoteEngine` supporte art. 24/25/26/26-1 ✓ |
| Vérifier | Existant | `QuorumEngine` supporte quorum réduit ✓ |
| Vérifier | Existant | `SpeechService` file d'attente parole ✓ |
| Vérifier | Existant | `EventBroadcaster` events vote/quorum/motion ✓ |
| Vérifier | Existant | `BallotsController::manualVote()` comptage papier ✓ |

---

### Phase 8 — Post-session + Statistiques

**Objectif** : Clôture en 4 étapes et page analytics.

#### 8.1 Post-session (`public/postsession.htmx.html`) — 4 étapes

| Étape | Contenu |
|-------|---------|
| 1. Vérification | Table résultats (résolution, adopté/rejeté, Pour/Contre/Abst, majorité) |
| 2. Validation | Avertissement irréversibilité, KPIs résumé, boutons Valider/Rejeter |
| 3. PV | Signataires (président, secrétaire, scrutateurs), observations/réserves textarea, signature eIDAS (3 modes) |
| 4. Envoi + Archivage | Envoyer PV (67 destinataires), 7 exports (PV PDF, émargement, correspondance, présences CSV, votes CSV, résultats CSV, audit CSV), archiver |

#### 8.2 Statistiques (`public/analytics.htmx.html`)

- Filtre année, export PDF
- 4 KPIs avec tendance (↑/↓ vs année précédente)
- Donut SVG : répartition Pour/Contre/Abstention
- Barres participation par séance
- Barres résolutions par majorité (art. 24/25/26/unanimité)
- Barres durée moyenne (AG Ord., AG Extra., Conseil)
- Barres sessions par mois

#### 8.3 Impact backend

| Changement | Type | Détail |
|-----------|------|--------|
| Archive ZIP | Endpoint | `POST /api/v1/meetings/:id/archive_zip` — PV + émargement + votes + audit, SHA-256 |
| Signature eIDAS | Stub | Interface pour intégration future (Yousign, DocuSign) — stub avec 3 modes |
| Vérifier | Existant | `MeetingReportService::renderHtml()` ✓ |
| Vérifier | Existant | `ExportController` CSV/XLSX ✓ |
| Vérifier | Existant | `AnalyticsRepository` KPIs + tendances ✓ |

---

### Phase 9 — Audit + Paramètres

**Objectif** : Pages contrôle et configuration système.

#### 9.1 Audit (`public/trust.htmx.html`)

- 4 KPIs : Intégrité (100%), Événements, Anomalies, Dernière séance
- Chips filtre catégorie : Tous, Votes, Présences, Sécurité, Système
- Toggle tableau/timeline
- Tableau : checkbox selection, #, Horodatage, Événement, Utilisateur, SHA-256
- Timeline : événements chronologiques avec dots sévérité
- Export sélection multiple + export complet
- Modal détail événement (SHA-256 complet)

#### 9.2 Paramètres (`public/admin.htmx.html` section) — 6 onglets

| Onglet | Contenu |
|--------|---------|
| Règles de vote | Types majorité (art. 24/25/26/26-1, passerelle 25-1), politiques quorum, créer politique |
| Clés de répartition | Clés existantes (Général, Ascenseur, Chauffage, Parking), lots, base, total, défaut |
| Sécurité | Niveau CNIL (3), plafond procurations, délai contestation, 2e convocation, séparation identité/bulletin, durée lien vote, double vote, double auth |
| Courrier | 5 templates email (Convocation, Rappel, Résultats, PV, Mise en demeure), éditeur avec variables |
| Général | Timezone (`ag-tz-picker`), email support, SMTP, logo, RGPD (export données, supprimer compte) |
| Accessibilité | Déclaration RGAA 4.1/WCAG 2.2 AA, taux conformité 97%, dates audit, corrections, non-conformités résiduelles |

#### 9.3 Pages outils à restyler

- Templates email (`public/email-templates.htmx.html`)
- Documentation (`public/docs.htmx.html`)
- Validation vote (`public/validate.htmx.html`)
- Rapports (`public/report.htmx.html`)

#### 9.4 Impact backend — RBAC

**Gap identifié** : le wireframe montre 7 rôles mais le backend en a 4 système + 3 meeting-level.

| Rôle wireframe | Rôle backend existant | Action |
|---------------|----------------------|--------|
| Administrateur | `admin` | ✓ OK |
| Gestionnaire | `operator` | Renommer label frontend uniquement |
| Opérateur | `operator` | Même rôle, label contextuel « opérateur en séance » |
| Président | `president` (meeting role) | ✓ OK |
| Secrétaire | `assessor`/`trust` (meeting role) | Renommer label frontend |
| Scrutateur | `assessor`/`trust` (meeting role) | Même rôle, 2 slots sur PV |
| Votant | `voter` (meeting role) / `public` | ✓ OK |

**Décision** : pas de refonte RBAC backend. Les 7 rôles du wireframe sont des **labels d'affichage** mappés sur les rôles backend existants. Seul le frontend traduit les labels. Table de mapping dans `utils.js` :

```javascript
const ROLE_LABELS = {
  admin: 'Administrateur',
  operator: 'Gestionnaire',
  auditor: 'Auditeur',
  viewer: 'Observateur',
  president: 'Président',
  assessor: 'Secrétaire / Scrutateur',
  voter: 'Votant'
};
```

---

### Phase 10 — Tour guidé + Intégration finale

**Objectif** : Tour guidé, intégration end-to-end, accessibilité finale.

#### 10.1 Tour guidé (`ag-guided-tour`)

- 7 tours, 22 étapes au total
- Ciblage DOM via `data-tour` attributes
- Spotlight clip-path avec ring accent
- Bulle positionnée dynamiquement (au-dessus/dessous)
- Navigation clavier (← → Escape)
- Barre de progression
- Persistence `localStorage` (« déjà vu »)

| Page | Étapes |
|------|--------|
| Dashboard | 4 (action urgente, KPIs, tâches, raccourcis) |
| Wizard | 1 (formulaire étape 1) |
| Operator | 8 (header, KPIs, track, résolution, onglets, actions, OdJ, guidance) |
| Membres | 1 (table) |
| Hub | 4 (stepper, action, KPIs, préparation) |
| Stats | 2 (KPIs, graphiques) |
| Post-session | 1 (stepper clôture) |

#### 10.2 Intégration end-to-end

| Tâche | Détail |
|-------|--------|
| Routes sidebar | Toutes les 16 pages connectées via `hx-push-url` |
| Données cohérentes | Toutes les pages alimentées par les vrais endpoints |
| Notifications liées | Actions → toasts + panel notifications |
| Recherche Ctrl+K | Index des 16 pages (client-side, `SEARCH_IDX`) |
| Session timeout | 15 min inactivité, banner alerte 2 min avant, auto-logout |
| Onboarding banner | Dashboard, dismissible, `localStorage` |

#### 10.3 Accessibilité RGAA 97%

- [ ] `aria-label` sur tous les boutons iconiques
- [ ] `aria-live="polite"` sur toasts et compteurs temps réel
- [ ] `aria-expanded` sur accordéons, dropdowns, sidebar groups
- [ ] `aria-current="page"` dans breadcrumbs
- [ ] Focus-trap dans toutes les modales
- [ ] Tab order logique (sidebar → header → main → footer)
- [ ] Skip-link (`Aller au contenu principal`)
- [ ] Contraste WCAG AA vérifié sur les 2 thèmes
- [ ] `role="dialog"`, `aria-modal="true"` sur modales
- [ ] `prefers-reduced-motion` : toutes animations désactivées

#### 10.4 Responsive vérification

| Breakpoint | Vérifications |
|-----------|--------------|
| > 1024px | Layout complet, sidebar rail, grilles 4 colonnes |
| 768-1024px | Grilles 2 colonnes, landing responsive |
| 480-768px | Sidebar drawer, bottom nav, grilles 1-2 colonnes, padding réduit |
| < 480px | Grilles 1 colonne, boutons compacts, breadcrumbs 11px |
| Print | Sidebar/header cachés, cards `break-inside: avoid`, fond blanc |

#### 10.5 Impact backend

| Endpoint | Méthode | Détail |
|----------|---------|--------|
| `/api/v1/auth_extend_session` | POST | Prolonger la session PHP de 15 min |

---

## 5. Inventaire backend complet

### 5.1 Ce qui existe et couvre le wireframe (95%)

| Domaine wireframe | Controllers backend | Status |
|-------------------|-------------------|--------|
| Login/Auth | `AuthController` (login, logout, whoami, csrf) | ✓ Complet |
| Dashboard | `DashboardController` (index, wizardStatus) | ✓ Complet |
| Séances CRUD | `MeetingsController` (index, create, update, delete, archive, status, summary) | ✓ Complet |
| Workflow séance | `MeetingWorkflowController` (transition, launch, readyCheck, consolidate) | ✓ Complet |
| Membres | `MembersController` + `MemberGroupsController` | ✓ Complet |
| Résolutions | `MotionsController` (CRUD, open, close, tally, reorder) | ✓ Complet |
| Votes | `BallotsController` (cast, cancel, result, manualVote, paperBallot, incident) | ✓ Complet |
| Quorum | `QuorumController` + `PoliciesController` | ✓ Complet |
| Présences | `AttendancesController` (list, upsert, bulk, presentFrom) | ✓ Complet |
| Procurations | `ProxiesController` (list, upsert, delete) | ✓ Complet |
| Demandes de parole | `SpeechController` (request, grant, end, queue) | ✓ Complet |
| Opérateur | `OperatorController` (workflowState, openVote, anomalies) | ✓ Complet |
| Projecteur | `ProjectorController` (state) | ✓ Complet |
| Vote public | `VotePublicController` + `VoteTokenController` | ✓ Complet |
| Invitations | `InvitationsController` (create, list, redeem, stats) | ✓ Complet |
| Email | `EmailController` + `EmailTemplatesController` + tracking | ✓ Complet |
| Import/Export | `ImportController` + `ExportController` (CSV/XLSX) | ✓ Complet |
| Rapports/PV | `MeetingReportsController` (report, generatePdf, send) | ✓ Complet |
| Audit | `AuditController` (timeline, export, meetingAudit) | ✓ Complet |
| Statistiques | `AnalyticsController` (analytics, reportsAggregate) | ✓ Complet |
| Devices | `DevicesController` (list, heartbeat, block, kick) | ✓ Complet |
| Admin | `AdminController` (users, roles, systemStatus) | ✓ Complet |
| Confiance | `TrustController` (anomalies, checks) | ✓ Complet |
| Urgences | `EmergencyController` (procedures, checkToggle) | ✓ Complet |
| Documentation | `DocController` (index, view) | ✓ Complet |
| Pièces jointes | `MeetingAttachmentController` (list, upload, delete) | ✓ Complet |
| Rappels | `ReminderController` (list, upsert, delete) | ✓ Complet |
| Temps réel | `EventBroadcaster` (Redis queue, 6+ event types) | ✓ Complet |

### 5.2 Ce qui manque (5%)

| Endpoint à créer | Phase | Effort |
|-----------------|-------|--------|
| `GET /api/v1/notifications` | 3 | Faible — `NotificationRepository` existe |
| `PUT /api/v1/notifications_read` | 3 | Faible |
| `GET /api/v1/meetings?view=calendar` | 5 | Faible — filtre sur existant |
| `POST /api/v1/meetings/:id/duplicate` | 5 | Moyen |
| `POST /api/v1/meetings/:id/second_convocation` | 7 | Moyen |
| `POST /api/v1/motions/:id/passerelle_25_1` | 7 | Moyen — logique dans `VoteEngine` |
| `POST /api/v1/meetings/:id/archive_zip` | 8 | Moyen — agrégation exports existants |
| `POST /api/v1/auth_extend_session` | 10 | Faible |

**Total : 8 endpoints à créer**, le reste est couvert.

### 5.3 Migrations BDD

| Table | Colonne | Phase | SQL |
|-------|---------|-------|-----|
| `meetings` | `timezone` | 6 | `ALTER TABLE meetings ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'Europe/Paris'` |
| `meetings` | `est_2e_convocation` | 7 | `ALTER TABLE meetings ADD COLUMN IF NOT EXISTS est_2e_convocation BOOLEAN DEFAULT FALSE` |
| `meetings` | `seance_parent_id` | 7 | `ALTER TABLE meetings ADD COLUMN IF NOT EXISTS seance_parent_id UUID REFERENCES meetings(id)` |

Les tables `meeting_reports`, `motions`, `ballots` existent déjà avec les colonnes nécessaires (vérifié).

---

## 6. Interactions wireframe non couvertes par l'ancien plan

Ces micro-interactions sont maintenant intégrées dans les phases ci-dessus :

| Interaction | Phase | Détail |
|------------|-------|--------|
| Session timeout 15 min | 10 | Banner alerte 2 min avant, auto-logout |
| Auto-advance opérateur | 7 | Après proclamation → transition 600ms → résolution suivante |
| Égalité de vote | 7 | `pctP === pctC && p > 0` → voix prépondérante président (art. 22) |
| Passerelle art. 25-1 | 7 | Si art. 25 échoue avec >= 1/3, proposer 2nd vote en art. 24 |
| Vote par correspondance | 7 | Bulletins reçus avant séance, intégrés au comptage |
| Guidance contextuelle | 7 | Message adaptatif selon état du vote dans l'opérateur |
| Quorum non atteint | 7 | Modal bloquant avec 3 options (Reporter/Suspendre/Continuer) |
| Confirmation en 2 étapes | 7 | Votant : sélection → validation → confirmé |
| Demande de parole | 7 | États : idle → waiting → speaking |
| Keyboard shortcuts | 7 | P = Proclamer, F = Fermer/Rouvrir vote |
| Validation irréversible | 8 | Modal warning, verrouillage BDD définitif |
| Onboarding banner | 4 | Dismissible, `localStorage`, lien vers tour guidé |
| Scroll-to-top | 3 | Apparaît après 300px de scroll dans `.main` |
| Search Ctrl+K | 3 | Overlay avec résultats navigables au clavier |
| Theme toggle persistence | 2 | `localStorage` + `data-theme` attribute |

---

## 7. Estimation par phase

| Phase | Complexité | Fichiers impactés |
|-------|-----------|-------------------|
| ~~1. Design Tokens~~ | ~~Faible~~ | ~~FAIT~~ |
| 2. Shell + CSS utilitaires | **Élevée** | `design-system.css` (+800 lignes), `shell.js`, 14 templates |
| 3. Composants partagés | **Élevée** | 8 fichiers JS existants + 12 nouveaux, `shell.js` |
| 4. Pages statiques | Moyenne | 3 pages HTMX, 3 scripts page |
| 5. Pages CRUD | **Élevée** | 4 pages HTMX, 4 scripts page, 2 endpoints |
| 6. Wizard + Hub | **Élevée** | 2 pages HTMX, 2 scripts page, 1 migration |
| 7. Pages live | **Très élevée** | 3 pages HTMX, 3 scripts page, 2 endpoints, 2 migrations |
| 8. Post-session + Stats | Moyenne | 2 pages HTMX, 2 scripts page, 1 endpoint |
| 9. Audit + Paramètres | Moyenne | 6 pages HTMX, mapping RBAC labels |
| 10. Tour guidé + intégration | Moyenne | 1 Web Component, `shell.js`, 1 endpoint |

---

## 8. Règles de commit

```
<type>(<scope>): <description courte>

Types : feat, fix, refactor, style, docs, test, chore
Scopes : phase-N, design-tokens, shell, components, page-xxx, backend, a11y
```

Exemples :
- `feat(phase-2): sidebar rail 58px/252px avec pin`
- `style(phase-2): intégrer 30 classes CSS wireframe`
- `feat(phase-3): ag-modal Web Component avec focus-trap`
- `feat(phase-7): auto-advance opérateur après proclamation`

---

## 9. Checklist pré-démarrage Phase 2

- [x] Phase 1 terminée (design tokens appliqués)
- [x] Wireframe analysé exhaustivement (16 pages, 2830 lignes)
- [x] Backend inventorié (38 controllers, 100+ routes, 95% couvert)
- [x] Écarts documentés (8 endpoints à créer, 3 migrations, RBAC = labels only)
- [x] Plan directeur unifié et nettoyé
- [ ] **Prêt pour Phase 2**
