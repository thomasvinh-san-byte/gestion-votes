# AG-Vote — Plan de remaniement frontend

> **Méthode Linus** : petits incréments, chaque phase compilable et testable, zéro régression, fondations d'abord.

---

## 0. État des lieux

### Ce qui existe (v3.19.2)

| Couche | Technologie | Fichiers |
|--------|------------|----------|
| Backend | PHP 8.4 custom MVC | `app/Controller/` (37 controllers), `app/Core/`, `app/Repository/` |
| Frontend | HTMX + Web Components vanilla | `public/*.htmx.html` (14 pages), `public/assets/js/` |
| CSS | Fichiers séparés par page | `public/assets/css/` (19 fichiers), `design-system.css` commun |
| Composants JS | Web Components (`ag-*`) | `public/assets/js/components/` (8 composants) |
| Pages JS | Scripts par page | `public/assets/js/pages/` (24 scripts) |
| API | REST PHP | `app/api.php`, `app/routes.php` |

### Ce que le wireframe définit (cible)

| Couche | Technologie | Éléments |
|--------|------------|----------|
| Frontend | React 18 | 16 pages, 22 composants réutilisables, 42 patterns UX |
| Design system | 52 CSS tokens | Clair/sombre, 3 polices, 7 couleurs sémantiques |
| Temps réel | WebSocket | 10 événements, 3 écrans simultanés |
| Accessibilité | RGAA 97% | Skip-link, focus-trap, aria-live, prefers-reduced-motion |

### Décision architecturale

**Le remaniement est une réécriture progressive du frontend.**
Le backend PHP et l'API REST existants sont conservés. Le frontend HTMX est remplacé page par page par des composants React, en coexistence temporaire.

---

## Principes Linus

1. **Chaque phase produit un livrable fonctionnel.** Pas de phase "préparatoire" sans résultat visible.
2. **Chaque phase est autonome.** On peut s'arrêter après n'importe quelle phase et avoir un produit utilisable.
3. **Les fondations sont posées en premier.** Design system → Shell → Pages simples → Pages complexes.
4. **Une seule chose à la fois.** Chaque phase a un périmètre clair et borné.
5. **Tests à chaque phase.** Checklist de conformité wireframe avant de passer à la suivante.
6. **Pas de big-bang.** Coexistence HTMX / React pendant la transition.

---

## Phase 0 — Outillage et fondations React

**Objectif** : installer l'écosystème React dans le projet PHP existant, sans casser l'existant.

### 0.1 Installation

- [ ] Initialiser `package.json` à la racine
- [ ] Installer Vite + React 18 + TypeScript (optionnel mais recommandé)
- [ ] Configurer `vite.config.ts` avec proxy vers le backend PHP
- [ ] Créer `frontend/` (ou `src/frontend/`) comme répertoire React
- [ ] Configurer le build pour produire dans `public/assets/react/`
- [ ] Ajouter les scripts `dev`, `build`, `preview` dans `package.json`

### 0.2 Structure initiale

```
frontend/
├── src/
│   ├── main.tsx              # Point d'entrée React
│   ├── App.tsx               # Router + Shell
│   ├── design-system/        # Phase 1
│   ├── components/           # Phase 2
│   ├── pages/                # Phases 3-8
│   ├── hooks/                # Hooks métier
│   ├── services/             # API client, WebSocket
│   ├── stores/               # État global (Zustand ou Context)
│   └── types/                # Types TypeScript
├── public/                   # Assets statiques React
└── index.html                # Template HTML Vite
```

### 0.3 Coexistence

- [ ] Le serveur PHP sert toujours les pages HTMX existantes
- [ ] Les nouvelles pages React sont servies par Vite en dev, par `public/assets/react/` en prod
- [ ] Un flag `?react=1` ou une route `/app/*` distingue les deux mondes
- [ ] La bascule est progressive : une page à la fois

### Livrable

Application qui démarre avec React, affiche une page vide, sans casser le site HTMX existant.

### Validation

- [ ] `npm run dev` lance Vite sans erreur
- [ ] `npm run build` produit les assets dans `public/assets/react/`
- [ ] Les pages HTMX existantes fonctionnent toujours
- [ ] Hot reload fonctionne en développement

---

## Phase 1 — Design System

**Objectif** : reproduire les 52 tokens CSS du wireframe et les composants de base.

### 1.1 Tokens CSS

- [ ] Extraire les 52 variables CSS du wireframe (`:root` et `[data-theme="dark"]`)
- [ ] Créer `frontend/src/design-system/tokens.css`
- [ ] Polices : Bricolage Grotesque, Fraunces, JetBrains Mono
- [ ] Couleurs : bg, surface (4 niveaux), accent (5), sidebar (6), text (4), sémantique (4×3), tag (2)
- [ ] Ombres : 4 niveaux + focus ring
- [ ] Rayons : sm (6px), md (8px), lg (10px), full (999px)
- [ ] Transitions : `--tr: .15s ease`
- [ ] Layout : `--sidebar-rail: 58px`, `--sidebar-expanded: 252px`

### 1.2 Reset et base

- [ ] Créer `frontend/src/design-system/reset.css` (box-sizing, scrollbars, body)
- [ ] Classe `.app` (shell flex)
- [ ] Typographie de base (font-size 14px, line-height 1.6, antialiasing)

### 1.3 Composants atomiques

Chaque composant est un fichier React avec son CSS Module ou style inline :

| Composant | Fichier | Wireframe CSS class |
|-----------|---------|-------------------|
| `Button` | `Button.tsx` | `.btn`, `.btn-p`, `.btn-danger`, `.btn-success`, `.btn-warn`, `.btn-ghost`, `.btn-sm`, `.btn-lg` |
| `Input` | `Input.tsx` | `.field`, `.field-label`, `.field-input`, `.field-hint`, `.field-counter` |
| `Select` | `Select.tsx` | `select.field-input` |
| `Textarea` | `Textarea.tsx` | `textarea.field-input` + compteur caractères (`CTA` dans le wireframe) |
| `Tag` | `Tag.tsx` | `.tag`, `.tag-accent`, `.tag-danger`, `.tag-success`, `.tag-warn`, `.tag-purple` |
| `Chip` | `Chip.tsx` | `.chip`, `.chip.active` |
| `Card` | `Card.tsx` | `.card`, `.card:hover`, `.kpi` |
| `Alert` | `Alert.tsx` | `.alert`, `.alert-success`, `.alert-warn` |
| `Avatar` | `Avatar.tsx` | `.avatar`, `.avatar-sm`, `.avatar-lg` + couleurs `AVATAR_COLORS` |
| `ProgressBar` | `ProgressBar.tsx` | `.progress-bar`, `.progress-fill` |
| `Skeleton` | `Skeleton.tsx` | `.skeleton` + animation shimmer |
| `LiveDot` | `LiveDot.tsx` | `.live-dot` + animation pulse |

### 1.4 Mode sombre

- [ ] Toggle `[data-theme="dark"]` sur `<body>`
- [ ] Toutes les variables redéfinies sous `[data-theme="dark"]`
- [ ] Composant `ThemeToggle` (`.theme-toggle`)
- [ ] Détection automatique `prefers-color-scheme`

### 1.5 Accessibilité de base

- [ ] `prefers-reduced-motion` : désactiver toutes les animations
- [ ] Focus ring visible (`.shadow-focus`)
- [ ] `::selection` style

### Livrable

Page de démonstration (`/storybook` ou page dédiée) affichant tous les composants dans les 2 thèmes.

### Validation

- [ ] Les 52 tokens CSS sont identiques au wireframe
- [ ] Chaque composant ressemble visuellement au wireframe
- [ ] Le mode sombre fonctionne correctement
- [ ] `prefers-reduced-motion` désactive les animations
- [ ] Contraste suffisant (WCAG AA) vérifié sur les textes

---

## Phase 2 — Shell (sidebar, header, layout)

**Objectif** : reproduire la coquille applicative — sidebar, header, footer, navigation.

### 2.1 Header

- [ ] Logo AG-Vote (`.logo`, `.logo-mark`)
- [ ] Badge version `v3.19.2`
- [ ] Contexte page (`.header-ctx`) — nom de la page courante
- [ ] Bouton recherche globale (`.search-trigger`) — juste le déclencheur
- [ ] Bouton notifications (`.notif-bell` + `.notif-count`)
- [ ] Toggle thème clair/sombre
- [ ] Badge `WIREFRAME`
- [ ] Nom utilisateur + bouton déconnexion
- [ ] Hamburger mobile (`.hamburger`) — visible < 768px

### 2.2 Sidebar

- [ ] Rail 58px au repos, 252px au hover/pin (`.sidebar`)
- [ ] Position absolue, jamais dans le flux
- [ ] Pin button (`.sidebar-pin`)
- [ ] 5 groupes de navigation (`NAV` array du wireframe)
- [ ] Groupes collapsibles avec animation (`.nav-group`)
- [ ] Items actifs avec barre accent (`.nav-item.active::before`)
- [ ] Badges (`.nav-badge`) — ex: 12, 3
- [ ] Labels fade-in au hover (`.nav-label`)
- [ ] Section aperçu tablette (`.sidebar-device-section`)
- [ ] Lien Aide en footer
- [ ] Version en footer (`.sidebar-version`)
- [ ] Indicateurs de scroll (`.sidebar-fade::before/after`)

### 2.3 Layout

- [ ] `.body-wrap` : flex, padding-left sidebar-rail
- [ ] `.main` : zone de contenu scrollable
- [ ] Skip link accessibilité (`.skip-link`)
- [ ] Footer applicatif (`.app-footer`)

### 2.4 Mobile

- [ ] Sidebar en drawer (position fixed, left -260px → 0)
- [ ] Overlay (`.sidebar-overlay`)
- [ ] Bottom navigation mobile (`.mobile-bnav`) — 5 boutons
- [ ] Header compact (height 46px)

### 2.5 DeviceBar

- [ ] Composant `DeviceBar` — barre indicateur PC / Tablette
- [ ] Affichée sous le header pour indiquer le type d'interface

### Livrable

Shell complet navigable. Cliquer sur un item de sidebar change la route et affiche un placeholder pour chaque page.

### Validation

- [ ] Sidebar au rail (58px) sans hover, 252px au hover
- [ ] Pin fonctionne (persiste l'expansion)
- [ ] Mobile : drawer + bottom nav
- [ ] Navigation entre les 16 pages (routes)
- [ ] Header reflète la page courante
- [ ] Accessibilité : skip-link, aria-labels, focus trap sidebar

---

## Phase 3 — Composants partagés avancés

**Objectif** : composants utilisés sur plusieurs pages, pas encore liés à une page spécifique.

### 3.1 Modales

- [ ] `Modal` : backdrop blur, animation `modalIn`, focus-trap, Escape ferme
- [ ] `ConfirmDialog` : icône, titre, message, boutons Annuler/Confirmer
- [ ] Hook `useConfirm()` : `const ok = await confirm("Titre", "Message", "danger")`

### 3.2 Feedback

- [ ] `Toast` : 4 variantes (success, info, warn, error), auto-dismiss 4.2s, animation entrée/sortie
- [ ] Container fixed bottom-right, empilage vertical
- [ ] Hook `useToast()` → `addToast(msg, type, title)`

### 3.3 Navigation

- [ ] `Breadcrumb` : fil d'Ariane avec séparateurs `/`
- [ ] `Pagination` (`Pg`) : boutons numérotés, état actif
- [ ] `ScrollTop` : bouton flottant, visible après 300px scroll
- [ ] `PageHeader` (`PH`) : titre avec barre accent, sous-titre, actions à droite

### 3.4 Données

- [ ] `Popover` : menu contextuel positionné
- [ ] `HelpTip` : bulle d'aide avec portal (sort du DOM parent)
- [ ] `Tooltip` (`Tip`) : tooltip simple au hover
- [ ] `Donut` : graphique SVG circulaire avec segments
- [ ] `MiniBar` : graphique barres mini avec tooltips

### 3.5 Formulaires

- [ ] `TimeInput` : saisie HH:MM avec validation, navigation clavier, copier-coller
- [ ] `TZPicker` : sélecteur de fuseau horaire (60 fuseaux) avec recherche
- [ ] `CTA` : textarea avec compteur de caractères

### 3.6 Recherche globale

- [ ] `GlobalSearch` : overlay Ctrl+K, recherche dans les pages
- [ ] Navigation clavier (flèches, Enter, Escape)
- [ ] Index de recherche statique (`SEARCH_IDX` du wireframe)

### 3.7 Notifications

- [ ] Panel notifications (`.notif-panel`) : dropdown depuis la cloche
- [ ] Items avec dot coloré, message, timestamp
- [ ] Bouton "Tout lire"

### 3.8 Session timeout

- [ ] Bannière fixe en bas (`.session-banner`)
- [ ] Timer inactivité (15 min), alerte à 2 min
- [ ] Boutons "Rester connecté" / "Se déconnecter"

### Livrable

Tous les composants partagés fonctionnels, testables individuellement.

### Validation

- [ ] Focus-trap dans les modales
- [ ] Toasts s'empilent et disparaissent
- [ ] Recherche globale navigable au clavier
- [ ] TimeInput accepte copier-coller (`18:30`, `14h00`, `1830`)
- [ ] TZPicker filtre les 60 fuseaux

---

## Phase 4 — Pages statiques (Landing, Dashboard, Aide)

**Objectif** : les 3 pages les plus simples, sans interaction CRUD complexe.

### 4.1 Landing (`/`)

- [ ] Header landing (logo + liens Doc/Support)
- [ ] Section hero (titre, description, 3 fonctionnalités)
- [ ] Carte connexion (bouton "Entrer dans la démo" + spinner chargement)
- [ ] Footer landing
- [ ] **Exception wireframe** : en production, remplacer par vrais champs email/mot de passe

### 4.2 Dashboard (`/dashboard`)

- [ ] Bannière onboarding (`.ob-banner`) — dismissable
- [ ] Carte action urgente (bordure danger, cliquable)
- [ ] 4 KPI cards (cliquables, navigation)
- [ ] Grille 2 colonnes : prochaines séances + tâches en attente
- [ ] 3 raccourcis en cards
- [ ] Lien vers visite guidée

### 4.3 Aide (`/aide`)

- [ ] Section visites guidées (7 cartes, grille auto-fill)
- [ ] FAQ accordion (23 questions, 5 catégories)
- [ ] Recherche + filtres par catégorie
- [ ] Section exports (tableau dans `<details>`)
- [ ] Bouton "Contacter le support"

### Livrable

3 pages complètes, navigables depuis la sidebar.

### Validation

- [ ] Landing : connexion → redirect Dashboard
- [ ] Dashboard : toutes les cartes cliquables, navigation correcte
- [ ] Aide : FAQ filtrable et recherchable, accordion fonctionne
- [ ] Responsive sur les 3 pages

---

## Phase 5 — Pages CRUD (Séances, Membres, Utilisateurs, Archives)

**Objectif** : les pages de listes avec filtres, pagination, recherche, modales CRUD.

### 5.1 Séances (`/seances`)

- [ ] Toggle vue Liste / Calendrier
- [ ] Barre de recherche + tri (date, nom, statut)
- [ ] Filtres chips (Toutes, À venir, En cours, Terminées)
- [ ] Liste paginée (5 par page, hauteur stable)
- [ ] Popover actions par séance (Ouvrir, Modifier, Dupliquer, Supprimer)
- [ ] Vue calendrier (grille 7 colonnes, événements colorés)
- [ ] État vide (empty state)

### 5.2 Membres (`/membres`)

- [ ] 4 KPI cards (Membres, Poids total, Groupes, Inactifs)
- [ ] Filtres groupes (Tous, Lot A/B/C, Inactifs)
- [ ] Recherche + export CSV
- [ ] Tableau avec colonnes : Nom, Lot, Clé générale, Clé ascenseur, Statut
- [ ] Popover actions (Modifier, Historique, Désactiver)
- [ ] Modale ajout membre
- [ ] Modale détail membre (fiche + historique participation)
- [ ] État vide simulable
- [ ] Panel contextuel "Clés de répartition"

### 5.3 Utilisateurs (`/utilisateurs`)

- [ ] Tableau : Nom, Courriel, Rôle, Statut, Dernière connexion
- [ ] Modale ajout utilisateur (nom, email, rôle, statut)
- [ ] Panel contextuel "Rôles"
- [ ] Tags colorés par rôle (Admin=danger, Gestionnaire=warn, Opérateur=accent)

### 5.4 Archives (`/archives`)

- [ ] Recherche + filtre par type
- [ ] Tableau paginé : Séance, Date, Résolutions, Présents, Actions
- [ ] Modale détail archive (KPI + résolutions + téléchargements)
- [ ] Bouton téléchargement complet

### Livrable

4 pages avec données simulées, CRUD fonctionnel (modales), pagination, filtres.

### Validation

- [ ] Séances : bascule liste/calendrier sans perte de filtre
- [ ] Membres : état vide → ajout → liste
- [ ] Recherche filtre en temps réel
- [ ] Pagination stable (pas de saut de layout)

---

## Phase 6 — Wizard et Hub (séance)

**Objectif** : les 2 pages les plus complexes en termes de formulaires et navigation interne.

### 6.1 Wizard (`/seances/new`)

- [ ] Barre de progression 5 étapes (`.wiz-progress-wrap`)
- [ ] **Étape 1** — Infos générales : titre, type, date, TimeInput, lieu, quorum, alerte délai 21j
- [ ] **Étape 2** — Participants : import CSV/XLSX, ajout manuel, drag-drop zone, liste avec voix, total + quorum calculé
- [ ] **Étape 3** — Résolutions : formulaire ajout (titre, description, majorité, clé, vote secret), liste numérotée, panel guide majorités
- [ ] **Étape 4** — Récapitulatif : résumé 9 lignes, alerte "Prêt à créer", bouton "Créer la séance"
- [ ] **Étape 5** — Confirmation : succès, prochaine étape (convocations), liens navigation
- [ ] Navigation Précédent/Suivant avec panel contextuel par étape

### 6.2 Hub (`/seances/:id`)

- [ ] Identité séance (carte avec icône, titre, date, lieu, participants)
- [ ] Stepper vertical 6 étapes (`.hub-stepper`)
  - Préparer / Convoquer / Émarger / Voter / Clôturer / Archiver
- [ ] Carte action principale (`.hub-action`) — change selon l'étape
- [ ] Checklist avancement avec barre de progression
- [ ] Bouton action principal + bouton aperçu
- [ ] Accordéon détails (`.hub-details-toggle`)
  - 4 KPI : Participants, Résolutions, Quorum requis, Convocations
  - Documents (liste avec téléchargement)
  - Carte 2e convocation
- [ ] Simulation wireframe (boutons ←/→ pour changer d'étape)

### Livrable

Wizard 5 étapes et Hub 6 étapes, navigation interne fluide.

### Validation

- [ ] Wizard : navigation avant/arrière, champs obligatoires marqués
- [ ] Hub : changement d'étape met à jour l'action et la checklist
- [ ] Accordéon détails s'ouvre/ferme avec animation
- [ ] Responsive : formulaires empilés sur mobile

---

## Phase 7 — Pages en direct (Operator, Votant, Ecran)

**Objectif** : les 3 écrans temps réel — le cœur métier de l'application.

### 7.1 Operator (`/seances/:id/live`)

- [ ] Header séance : live dot, titre, chronomètre, boutons Salle/Clôturer/Guide
- [ ] 4 KPI en direct (Présents, Quorum, Ont voté, Résolution)
- [ ] Tags quorum + correspondance + procurations
- [ ] Barre de progression résolutions (segments colorés cliquables)
- [ ] Panel résolution principal :
  - Titre + méta (majorité, clé, secret)
  - 3 sous-onglets : Résultat / Avancé / Présences
  - **Résultat** : boutons Ouvrir/Fermer vote, Proclamer, barres Pour/Contre/Abstention, progression
  - **Avancé** : comptage manuel, N'ont pas voté, Passerelle 25-1, notes secrétaire
  - **Présences** : tableau participants avec statut, actions
- [ ] Barre d'action épinglée (Proclamer + Fermer) avec raccourcis clavier (P, F)
- [ ] Sidebar ordre du jour (liste résolutions avec statut)
- [ ] Demandes de parole (compteur + noms)
- [ ] Barre de guidance contextuelle
- [ ] Modale quorum non atteint (3 options : Reporter, Suspendre, Continuer)
- [ ] Modale procuration
- [ ] Modale unanimité
- [ ] Auto-avancement après proclamation (transition animée)

### 7.2 Votant (`/vote/:token`)

- [ ] Frame tablette (`.tablet-frame`) avec DeviceBar
- [ ] Header votant : résolution N/M, timer dégressif, bouton grossir/réduire
- [ ] Barre progression résolutions (dots)
- [ ] Titre résolution + majorité
- [ ] 4 boutons de vote (Pour, Contre, Abstention, NSP) en grille 2×2
- [ ] Confirmation en 2 temps (sélection → confirmer → valider)
- [ ] Écran post-vote (check animé, "Merci")
- [ ] Poids votant + lot + procurations
- [ ] Demande de parole (3 états : idle, waiting, speaking)

### 7.3 Ecran (`/seances/:id/ecran`)

- [ ] Plein écran sans sidebar/header
- [ ] Toggle clair/sombre
- [ ] Toggle vue vote/résultat
- [ ] Header : bouton retour, live dot, chronomètre
- [ ] Titre séance + 3 KPI (Présents, Quorum, Résolution)
- [ ] Barre quorum avec seuil visuel
- [ ] Vue vote : titre résolution, barres Pour/Contre/Abstention, compteur voix
- [ ] Vue résultat : badge ADOPTÉE/REJETÉE, barres détail
- [ ] Sidebar ordre du jour fixe (240px)
- [ ] Lien de vote en bas
- [ ] Tailles responsive (`clamp()`)

### Livrable

3 écrans temps réel avec données simulées et interactions complètes.

### Validation

- [ ] Operator : ouvrir vote → voir progression → proclamer → auto-avance
- [ ] Votant : sélection → confirmation → validation → merci
- [ ] Ecran : bascule vote/résultat, clair/sombre
- [ ] Raccourcis clavier Operator (P, F)
- [ ] Quorum non atteint : les 3 options fonctionnent
- [ ] Passerelle art. 25-1 déclenchable

---

## Phase 8 — PostSession et Stats

**Objectif** : les pages de clôture et d'analyse.

### 8.1 PostSession (`/seances/:id/cloture`)

- [ ] Barre 4 étapes (Vérification, Validation, PV, Envoi)
- [ ] **Étape 1** : tableau résultats (Adoptée/Rejetée avec tags)
- [ ] **Étape 2** : récapitulatif + boutons Valider/Refuser (irréversible)
- [ ] **Étape 3** : champs signataires, observations, réserves, signature eIDAS (3 modes)
- [ ] **Étape 4** : carte PV avec bouton envoi, exports (7 formats), archivage
- [ ] Navigation Précédent/Suivant épinglée en bas

### 8.2 Stats (`/stats`)

- [ ] 4 KPI avec tendance (flèches ↑↓ vs année précédente)
- [ ] Donut répartition votes (Pour/Contre/Abstention)
- [ ] Graphique participation par séance (barres horizontales)
- [ ] Résolutions par majorité (barres)
- [ ] Durée moyenne des séances (barres)
- [ ] Séances par mois (bar chart vertical avec tooltips)
- [ ] Filtre année + export PDF

### Livrable

2 pages complètes avec visualisations de données.

### Validation

- [ ] PostSession : parcours complet étape 1→4
- [ ] Stats : toutes les visualisations affichées
- [ ] Donut : segments proportionnels aux données
- [ ] Responsive : graphiques lisibles sur mobile

---

## Phase 9 — Audit et Paramètres

**Objectif** : les pages de contrôle et configuration système.

### 9.1 Audit (`/audit`)

- [ ] 4 KPI (Intégrité, Événements, Anomalies, Dernière séance)
- [ ] Filtres par catégorie (Tous, Votes, Présences, Sécurité, Système)
- [ ] Toggle vue Tableau / Chronologie
- [ ] Tableau avec sélection multiple + export sélection
- [ ] Vue chronologie (timeline verticale avec dots colorés)
- [ ] Modale détail événement (SHA-256 complet)
- [ ] Pagination

### 9.2 Paramètres (`/parametres`)

- [ ] Navigation verticale 6 onglets
- [ ] **Règles de vote** : majorités disponibles, politiques de quorum, modale création quorum
- [ ] **Clés de répartition** : liste avec alertes incomplet, bouton nouvelle clé
- [ ] **Sécurité** : niveau CNIL, plafond procurations, séparation identité/bulletin, double vote, double auth
- [ ] **Courrier** : 5 templates éditables, modale édition avec variables
- [ ] **Général** : fuseau horaire (TZPicker), email support, SMTP, logo, RGPD
- [ ] **Accessibilité** : déclaration RGAA complète, corrections, mesures en place, non-conformités, contact

### Livrable

2 pages complexes avec toutes les interactions de configuration.

### Validation

- [ ] Audit : bascule tableau/chronologie sans perte de filtre
- [ ] Paramètres : navigation entre les 6 onglets fluide
- [ ] Modale quorum : aperçu en temps réel
- [ ] Modale template courriel : variables cliquables

---

## Phase 10 — Visite guidée et intégration finale

**Objectif** : le système de visite guidée et les derniers raccords.

### 10.1 Visite guidée

- [ ] Composant `GuidedTour` : overlay + spotlight + bulle
- [ ] Ciblage DOM via `data-tour` attributes
- [ ] 7 parcours (dashboard, wizard, operator, membres, hub, stats, postsession)
- [ ] 23 étapes au total
- [ ] Navigation clavier (flèches, Escape)
- [ ] Barre de progression
- [ ] Scroll automatique vers l'élément ciblé

### 10.2 Intégration

- [ ] Toutes les routes connectées
- [ ] Données de démo cohérentes entre les pages
- [ ] Notifications reliées aux pages cibles
- [ ] Recherche globale indexe toutes les pages

### 10.3 Accessibilité finale

- [ ] Audit RGAA complet (97% minimum)
- [ ] aria-labels sur tous les éléments interactifs
- [ ] aria-live sur les régions dynamiques (toasts, résultats temps réel)
- [ ] Focus-trap sur toutes les modales
- [ ] Tab order logique sur chaque page
- [ ] Contraste vérifié clair + sombre

### 10.4 Responsive final

- [ ] 4 breakpoints : 1024px, 768px, 480px, print
- [ ] Grilles adaptatives (4→2→1 colonnes)
- [ ] Sidebar : rail desktop → drawer mobile
- [ ] Bottom nav mobile
- [ ] Print : masquer sidebar/header/toasts

### Livrable

Application frontend complète, conforme au wireframe sur les 16 pages.

### Validation finale

- [ ] Parcours complet : Landing → Dashboard → Wizard → Hub → Operator → PostSession → Archives
- [ ] Les 203 interactions du wireframe fonctionnent
- [ ] Mode sombre sur toutes les pages
- [ ] Responsive sur toutes les pages
- [ ] RGAA 97%+ vérifié
- [ ] Visite guidée fonctionnelle sur les 7 parcours

---

## Matrice de dépendances

```
Phase 0 (Outillage)
  └── Phase 1 (Design System)
        └── Phase 2 (Shell)
              ├── Phase 3 (Composants partagés)
              │     ├── Phase 4 (Landing, Dashboard, Aide)
              │     ├── Phase 5 (Séances, Membres, Utilisateurs, Archives)
              │     ├── Phase 6 (Wizard, Hub)
              │     ├── Phase 7 (Operator, Votant, Ecran)    ← le plus complexe
              │     ├── Phase 8 (PostSession, Stats)
              │     └── Phase 9 (Audit, Paramètres)
              └── Phase 10 (Visite guidée, intégration, a11y)
```

Les phases 4 à 9 sont **parallélisables** après la phase 3. L'ordre proposé va du plus simple au plus complexe.

---

## Estimation par phase

| Phase | Pages | Composants | Complexité |
|-------|-------|-----------|------------|
| 0 | 0 | 0 | Faible — config uniquement |
| 1 | 0 | 12 | Moyenne — fidélité visuelle |
| 2 | 0 | 6 | Haute — sidebar complexe |
| 3 | 0 | 14 | Moyenne — composants isolés |
| 4 | 3 | 0 | Faible — pages statiques |
| 5 | 4 | 0 | Moyenne — CRUD + filtres |
| 6 | 2 | 2 | Haute — wizard + stepper |
| 7 | 3 | 3 | Très haute — temps réel |
| 8 | 2 | 2 | Moyenne — visualisations |
| 9 | 2 | 0 | Haute — nombreuses interactions |
| 10 | 0 | 1 | Haute — intégration + a11y |

---

## Règle de commit

Chaque sous-tâche (`[ ]`) est un commit atomique. Message format :

```
feat(phase-N): description courte

- Détail 1
- Détail 2
```

Exemples :
- `feat(phase-1): add CSS design tokens (52 variables, light + dark)`
- `feat(phase-2): implement sidebar with rail/expanded/pinned states`
- `feat(phase-7): add operator page with vote control and live progress`
