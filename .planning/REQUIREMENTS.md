# Requirements: AgVote v2.2 Refonte Visuelle & Cohérence

**Defined:** 2026-04-29
**Core Value:** L'application doit dégager le sérieux civique de sa promesse à chaque écran. Le designer du produit doit ressentir la cohérence visuelle (palette harmonisée, dark mode pensé, identité par rôle), et l'utilisateur doit savoir instantanément où il est et ce qu'il peut/doit faire — sans lire de documentation.

**Source des requirements :** synthèse des conversations design (palette Bleu République, lentille Norman/Zhuo, audit cohérence couleurs/copy, refonte visuelle proposée, matrice personas) consolidées en PRs orchestrées en pyramide.

---

## v2.2 Requirements

### Phase 1 — Design Tokens (la fondation)

- [x] **DESIGN-T01**: `--color-primary` migrée à `oklch(0.45 0.180 265)` = `#2c468f` (Bleu République). Plus profond, ton institutionnel, cousin du DSFR sans le copier.
- [x] **DESIGN-T02**: Sémantiques harmonisées au brand (chroma 0.13-0.18, lightness 0.45-0.62) — `success` vert sénat (hue 165), `danger` rouge huissier (désat 0.165), `warning` ocre archive (hue 75), `info` bleu instruction (hue 230). Ne pas reprendre Material Default.
- [x] **DESIGN-T03**: Surfaces light tendant vers blanc sans être blanc pur (modern tech) — `--color-bg = oklch(0.985 0.001 0)` (#fbfbfb), neutre vrai (hue 0, chroma 0). `--color-surface-raised = #ffffff` est le SEUL vrai blanc, réservé aux popovers/modals.
- [x] **DESIGN-T04**: Dark mode redesigné indépendamment, pas un light inversé. 5 niveaux d'élévation (overlay/sunken/base/raised/floating), hue 260 (légèrement bleutée), saturation des accents réduite ~25%, lightness inversée (primary lift 0.45 → 0.62), aucun noir pur (le plus profond est `oklch(0.13)`).
- [x] **DESIGN-T05**: 6 tokens `--role-*` définis dans le spectre froid (240°-330°), différenciés par lightness/saturation, jamais par teintes opposées : admin (305° violet profond), president (240° bleu-acier), operator (265° brand), auditor (255° bleu cendré), voter (280° indigo), public (320° mauve doux).
- [x] **DESIGN-T06**: `@media (prefers-color-scheme: dark)` détecte automatiquement la préférence OS via `:where(:root:not([data-theme="light"]):not([data-theme="dark"]))` — spécificité 0. Toggle JS utilisateur explicite reste prioritaire sur l'OS.
- [x] **DESIGN-T07**: `DESIGN.md` à la racine sert de source de vérité unique. Templates `email_*.php` utilisent les hex documentés dans la table de correspondance (compat clients email old-school qui ne lisent pas les variables CSS).
- [x] **DESIGN-T08**: `tests/Visual/tokens.html` rend la palette en grille avec toggle Light/Dark/Auto pour validation à l'œil avant chaque PR de phase suivante.

### Phase 2 — Components (les briques de base)

- [ ] **DESIGN-C01**: Boutons disabled ne sont plus gris monochromes. Variant `.btn-primary--disabled` qui préserve la teinte primary légère + `opacity: 0.45-0.6` + `cursor: not-allowed`. Signifie "ce bouton EST primary, mais pas maintenant" plutôt que "élément désactivé indéterminé".
- [ ] **DESIGN-C02**: Cards utilisent `--radius-lg = 10px` et `--shadow-md` ; modals utilisent `--radius-lg + --shadow-lg` ; drawers harmonisés sur les mêmes tokens. Suppression des shadows fluffy/glow ; max 3 niveaux (sm/md/lg).
- [ ] **DESIGN-C03**: Modales et drawers ont une coquille (`shell`) unifiée — header, body, footer cohérents, fermeture systématiquement disponible (X + Escape). Plus de mélange entre `<dialog>` natif et custom-element.
- [ ] **DESIGN-C04**: Forms (`.field-input`, `.field-label`, `.field-error`, `.helper-text`) consistants, helper text avec convention de ponctuation appliquée (point final pour phrases ≥ 8 mots, sans point pour fragments courts).
- [ ] **DESIGN-C05**: Toasts utilisent les couleurs sémantiques harmonisées et ont un bouton fermeture explicite + auto-dismiss 5-8s. Alignés avec les KPI cards (vert sénat = succès partout).

### Phase 3 — Personas (Role Markers + Isolation)

- [ ] **DESIGN-P01**: Une bande de 3 px en haut de chaque page authentifiée est colorée par `var(--role-X)` correspondant au rôle de l'utilisateur connecté. Permanente, discrète, immédiatement reconnaissable.
- [ ] **DESIGN-P02**: La sidebar affiche un badge persona (texte du rôle en français : "Admin", "Opérateur", "Président", "Auditeur", "Votant", "Public") avec la couleur correspondante au-dessus du nom utilisateur.
- [ ] **DESIGN-P03**: Attribut `data-persona` posé sur `<body>` côté serveur depuis la session, lu par CSS pour appliquer la couleur via `[data-persona="operator"] .role-bar { background: var(--role-operator); }`. Pas de logique JS pour la couleur — purement déclaratif.
- [ ] **DESIGN-P04**: `tests/Security/PersonaIsolationTest.php` couvre : (a) un voteur connecté qui GET /dashboard reçoit 403, (b) un auditeur qui POST n'importe quel endpoint mutateur reçoit 403, (c) la sidebar HTML rendue ne contient aucun item dont `data-requires-role` ne match pas le rôle courant.

### Phase 4 — Layout & Lexique (le ressenti final)

- [ ] **DESIGN-L01**: Vue exécution opérateur affiche une **barre santé séance** unique de ~56 px en haut avec 4 indicateurs (Quorum / SSE / Votants connectés / Résolution actuelle) — chacun avec sa couleur sémantique persistante (vert/rouge). Si un indicateur passe rouge, la barre entière prend une bordure danger animée.
- [ ] **DESIGN-L02**: Page `/vote` adopte une typographie minimum 18 px (`--text-lg`), boutons ≥ 96 px de haut, palette désaturée (on ne pousse pas à voter Pour visuellement). Aucun élément admin/opérateur visible dans cette vue.
- [ ] **DESIGN-L03**: Pages `/audit`, `/trust`, `/archives`, et le rendu PV utilisent la police serif **Newsreader** pour le contenu (largeur de lecture plafonnée à 720 px), Inter pour les contrôles UI, JetBrains Mono pour les hashes/UUID/codes. Traitement éditorial qui dégage le sérieux légal.
- [ ] **DESIGN-L04**: Dashboard simplifié — une seule "hero card" avec la séance la plus urgente, 3 KPI cards (pas 4), actions rapides reléguées en bas avec `--surface-sunken` pour secondariser.
- [ ] **DESIGN-L05**: Login/setup réduit le panel marketing à un strict minimum — logo + tagline + UN bénéfice. Le formulaire prend la place. Plus d'orbe animé.

#### Lexique (groupé avec layout pour cohérence d'expérience)

- [ ] **DESIGN-X01**: Convention écrite et appliquée pour les 3 termes humains : "membre" (inscrit à l'organisation) / "participant" (membre présent à la séance) / "votant" (participant éligible au scrutin courant). Distinctions sémantiques claires, pas mélangées.
- [ ] **DESIGN-X02**: Convention pour les verbes de finalisation : "confirmer" (réversible) / "valider" (engageant mais réversible jusqu'au verrouillage) / "verrouiller-archiver" (irréversible). "Approuver" banni (ambiguë juridiquement).
- [ ] **DESIGN-X03**: Migration grep+remplace ciblée appliquée à toutes les pages `public/*.htmx.html` + templates `app/Templates/*.php` + dictionnaire `app/Services/ErrorDictionary.php`. Pas de migration de code (les classes/IDs restent en `motion-card` etc.).
- [ ] **DESIGN-X04**: Test dans `tests/Security/CopyConventionsTest.php` qui scanne le HTML rendu et vérifie qu'aucun terme banni ("copropriété", "syndic", "approuver" pour finalisation) n'apparaît, et qu'aucun mélange membre↔votant n'a regressé.

---

## v2.3+ Requirements (deferred)

### Évolutions design

- **DESIGN-NEXT-1**: Switch font invitation token SHA-256 → HMAC-SHA256 (lié sécurité, déjà tracé en v2.1 tech debt)
- **DESIGN-NEXT-2**: Migration progressive des templates `field()` → `fieldFor(method, path)` pour activer F10 (CSRF scopé) sur tous les forms
- **DESIGN-NEXT-3**: Audit complet des 8 méthodes MotionRepository à `tenantId = ''` optionnel, conversion en paramètre requis
- **DESIGN-NEXT-4**: Animation système (transitions cross-page, skeleton loading enrichi) — uniquement après que la base layout est stable

---

## Out of Scope (v2.2)

| Feature | Reason |
|---------|--------|
| Refonte du backend / logique métier | v2.2 est purement visuel + cohérence — aucune logique modifiée |
| Nouveaux écrans / fonctionnalités utilisateur | UX existante stabilisée d'abord, features après |
| Migration framework (Vue, React, Symfony) | Refactoring incrémental seulement |
| Refonte mobile dédiée | Responsive existant suffisant pour ce milestone |
| A/B testing visuel | Hors scope — ce milestone établit une seule direction |

---

## Traceability

Quel finding mappe à quelle phase, mis à jour pendant l'exécution.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DESIGN-T01 | Phase 1 | ✓ Complete (PR #256) |
| DESIGN-T02 | Phase 1 | ✓ Complete |
| DESIGN-T03 | Phase 1 | ✓ Complete |
| DESIGN-T04 | Phase 1 | ✓ Complete |
| DESIGN-T05 | Phase 1 | ✓ Complete |
| DESIGN-T06 | Phase 1 | ✓ Complete |
| DESIGN-T07 | Phase 1 | ✓ Complete |
| DESIGN-T08 | Phase 1 | ✓ Complete |
| DESIGN-C01 | Phase 2 | Pending |
| DESIGN-C02 | Phase 2 | Pending |
| DESIGN-C03 | Phase 2 | Pending |
| DESIGN-C04 | Phase 2 | Pending |
| DESIGN-C05 | Phase 2 | Pending |
| DESIGN-P01 | Phase 3 | Pending |
| DESIGN-P02 | Phase 3 | Pending |
| DESIGN-P03 | Phase 3 | Pending |
| DESIGN-P04 | Phase 3 | Pending |
| DESIGN-L01 | Phase 4 | Pending |
| DESIGN-L02 | Phase 4 | Pending |
| DESIGN-L03 | Phase 4 | Pending |
| DESIGN-L04 | Phase 4 | Pending |
| DESIGN-L05 | Phase 4 | Pending |
| DESIGN-X01 | Phase 4 | Pending |
| DESIGN-X02 | Phase 4 | Pending |
| DESIGN-X03 | Phase 4 | Pending |
| DESIGN-X04 | Phase 4 | Pending |

**Coverage:**
- v2.2 requirements: 26 total
- Mapped to phases: 26 (8 done in Phase 1, 18 pending)
- Unmapped: 0 ✓

---

*Requirements defined: 2026-04-29*
*Last updated: 2026-04-29 — Phase 1 in PR #256, awaiting review*
