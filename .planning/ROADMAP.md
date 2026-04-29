# Roadmap: AgVote

## Milestones

- ✅ **v1.0 Dette Technique** - Phases 1-4 (shipped 2026-04-07) — see `.planning/milestones/v1.0-ROADMAP.md`
- ✅ **v1.1 Coherence UI/UX et Wiring** - Phases 5-7 (shipped 2026-04-08) — see `.planning/milestones/v1.1-ROADMAP.md`
- ✅ **v1.2 Bouclage et Validation Bout-en-Bout** - Phases 8-13 (shipped 2026-04-09) — see `.planning/milestones/v1.2-ROADMAP.md`
- ✅ **v1.3 Polish Post-MVP** - Phases 14-17 (shipped 2026-04-09) — see `.planning/milestones/v1.3-ROADMAP.md`
- ✅ **v1.4 Regler Deferred et Dette Technique** - Phases 1-6 (shipped 2026-04-10) — see `.planning/milestones/v1.4-ROADMAP.md`
- ✅ **v1.5 Nettoyage et Refactoring Services** - Phases 1-7 (shipped 2026-04-20) — see `.planning/milestones/v1.5-ROADMAP.md`
- ✅ **v1.6 Reparation UI et Polish Fonctionnel** - Phases 1-4 (shipped 2026-04-20) — see `.planning/milestones/v1.6-ROADMAP.md`
- ✅ **v1.7 Audit Idempotence** - Phases 1-3 (shipped 2026-04-20) — see `.planning/milestones/v1.7-ROADMAP.md`
- ✅ **v1.8 Refonte UI et Coherence Visuelle** - Phases 1-5 (shipped 2026-04-20) — see `.planning/milestones/v1.8-ROADMAP.md`
- ✅ **v1.9 UX Standards & Retention** - Phases 1-5 (shipped 2026-04-21) — see `.planning/milestones/v1.9-ROADMAP.md`
- ✅ **v2.0 Operateur Live UX** - Phases 1-4 (shipped 2026-04-29) — see `.planning/milestones/v2.0-ROADMAP.md`
- ✅ **v2.1 Hardening Sécurité** - Phases 1-6 (shipped 2026-04-29) — see `.planning/milestones/v2.1-REQUIREMENTS.md` — 21 contremesures F02-F22
- 🚧 **v2.2 Refonte Visuelle & Cohérence** - Phases 1-4 (in progress) — Bleu République + dark redesign + role markers + layout refonte

## Phases

<details>
<summary>✅ v1.0 Dette Technique (Phases 1-4) - SHIPPED 2026-04-07</summary>

See `.planning/milestones/v1.0-ROADMAP.md` for full details.

</details>

<details>
<summary>✅ v1.1 Coherence UI/UX et Wiring (Phases 5-7) - SHIPPED 2026-04-08</summary>

See `.planning/milestones/v1.1-ROADMAP.md` for full details.

**Phases:** 3 (5, 6, 7)
**Plans:** 11
**Hotfixes delivered:** 3 (RateLimiter boot, nginx routing, login redesign)

</details>

<details>
<summary>✅ v1.2 Bouclage et Validation Bout-en-Bout (Phases 8-13) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.2-ROADMAP.md` for full details.

**Phases:** 6 (8-13)
**Plans:** 36
**Critical-path Playwright specs:** 23 GREEN x 3 runs zero flake
**Hotfixes delivered:** 5 (RateLimiter boot, nginx routing, login polish, cookie domain, HSTS preload)

</details>

<details>
<summary>✅ v1.3 Polish Post-MVP (Phases 14-17) - SHIPPED 2026-04-09</summary>

See `.planning/milestones/v1.3-ROADMAP.md` for full details.

**Phases:** 4 (14, 15, 16, 17)
**Plans:** 12 (4 + 1 + 5 + 3) — phase 15 executed inline without PLAN files
**Shipped:**
- Visual polish: toast system unifie, dark mode parity, role-specific sidebar, micro-interactions
- Cross-browser: chromium + firefox + webkit + mobile-chrome matrix (25/25 chromium, 25/25 firefox, 23/25 webkit, 21/25 mobile-chrome)
- Accessibility deep audit: 47 structural violations fixed, keyboard-nav spec (6/6), contrast audit produced (316 nodes DEFERRED to token remediation), WCAG 2.1 AA partial conformance
- Loose ends: settings loadSettings race fixed, eIDAS chip delegation fixed, Phase 12 SUMMARY audit ledger (6 findings, 3 deferred to v2)

**Deferred to v2:**
- CONTRAST-REMEDIATION (316 color-contrast nodes, design-token work)
- V2-OVERLAY-HITTEST (systematic `[hidden]`+flex overlay sweep)
- V2-TRUST-DEPLOY (trust.htmx.html auditor/assessor fixtures)
- V2-CSP-INLINE-THEME (strict CSP compatibility)

</details>

<details>
<summary>✅ v1.4 Regler Deferred et Dette Technique (Phases 1-6) — SHIPPED 2026-04-10</summary>

See `.planning/milestones/v1.4-ROADMAP.md` for full details.

**Phases:** 6 (1-6)
**Plans:** 14
**Requirements:** 24/24 satisfied
**Shipped:**
- Contrast AA remediation: 316 violations -> 0, WCAG 2.1 AA CONFORME
- Global [hidden] rule + codebase audit
- Auditor/assessor Playwright fixtures with seed endpoint
- htmx 2.0.6 upgrade with zero regressions (4 browsers)
- CSP nonce enforcement in report-only mode
- 4 controllers refactored from >500 to <300 LOC

</details>

<details>
<summary>✅ v1.5 Nettoyage et Refactoring Services (Phases 1-7) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.5-ROADMAP.md` for full details.

**Phases:** 7 (1 cleanup + 5 refactoring + 1 validation gate)
**Plans:** 9
**Requirements:** 18/18 satisfied
**Shipped:**
- Codebase cleanup: 50+ console.log removed, dead code purged, superglobals migrated
- 5 services refactored from >600 LOC to <300 LOC each
- 7 new extracted classes (SessionManager, RbacEngine, CsvImporter, XlsxImporter, ValueTranslator, ReportGenerator, RetryPolicy)
- Zero regressions confirmed (routes unchanged, unit tests green, E2E specs intact)

</details>

<details>
<summary>✅ v1.6 Reparation UI et Polish Fonctionnel (Phases 1-4) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.6-ROADMAP.md` for full details.

**Phases:** 4 (1 JS audit + 1 form modernization + 1 wizard + 1 validation gate)
**Plans:** 8
**Requirements:** 9/9 satisfied
**Shipped:**
- JS interaction audit: 8 broken selectors fixed across 21 pages
- Form layout modernization: multi-column grids on 16 pages, field classes normalized
- Wizard compaction: CSS spacing reduced for 1080p viewport fit
- Zero regressions confirmed

</details>

<details>
<summary>✅ v1.7 Audit Idempotence (Phases 1-3) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.7-ROADMAP.md` for full details.

**Phases:** 3 (1 audit + 1 backend guards + 1 frontend/tests)
**Plans:** 4
**Requirements:** 7/7 satisfied
**Shipped:**
- 73 routes audited, 13 Critique targets identified and protected
- IdempotencyGuard on all critical creation/import/email routes
- Workflow transitions idempotent (launch/close)
- HTMX X-Idempotency-Key header on all POST/PATCH
- IdempotencyGuard JSON deserialize bug fixed

</details>

<details>
<summary>✅ v1.8 Refonte UI et Coherence Visuelle (Phases 1-5) - SHIPPED 2026-04-20</summary>

See `.planning/milestones/v1.8-ROADMAP.md` for full details.

**Phases:** 5
**Plans:** 9
**Requirements:** 13/13 satisfied
**Shipped:**
- Palette stone->slate (beige->gris neutre moderne)
- Wizard field-input->form-input, 33 inline styles elimines, drawer CSS classes
- Version v2.0 unifiee, footer accent fixe, modales standardisees
- Hero compact, radio->select, KPI dead code supprime

</details>

<details>
<summary>✅ v1.9 UX Standards & Retention (Phases 1-5) - SHIPPED 2026-04-21</summary>

See `.planning/milestones/v1.9-ROADMAP.md` for full details.

**Phases:** 5 (typography + sidebar + feedback + jargon + validation gate)
**Plans:** 9
**Requirements:** 16/16 satisfied
**Shipped:**
- Typography: base 16px, labels normal case, header 64px, spacing 20-24px
- Sidebar: always-open 200px, pin removed, voter sees only Voter + Mon compte, 44px touch targets
- Feedback: persistent vote confirmation with timestamp, "Chargement..." labels, ag-empty-state on all pages, filter reset
- Clarity: voter jargon eliminated, admin tooltips, checkbox confirmation, export descriptions
- Validation: NAV-04 verified, PHP syntax clean, visual coherence approved

</details>

<details>
<summary>✅ v2.0 Operateur Live UX (Phases 1-4) - SHIPPED 2026-04-29</summary>

See `.planning/milestones/v2.0-ROADMAP.md` for full details.

**Phases:** 4 (Checklist + Focus Mode + Animations + Validation Gate)
**Plans:** 6
**Requirements:** 11/11 satisfied
**Audit:** PASS (cross-phase integration verified — `.planning/milestones/v2.0-MILESTONE-AUDIT.md`)
**Shipped:**
- Checklist temps reel: quorum/votes/SSE/connected voters avec alertes visuelles
- Mode Focus: vue 5-zones epuree avec toggle + persistance sessionStorage
- Animations Vote: compteurs RAF + bar transitions, respect prefers-reduced-motion
- Hotfix securite (PR #247): /setup 404 + CSRF strict (finding F1)
- CI repair (PR #248): lint-js + migrate-check verts, validate massivement ameliore

</details>

<details>
<summary>✅ v2.1 Hardening Sécurité (Phases 1-6) — SHIPPED 2026-04-29</summary>

See `.planning/milestones/v2.1-REQUIREMENTS.md` for full archive (phases moved to `.planning/milestones/v2.1-phases/`).

**Phases:** 6 (Sprint 0 finition + Vote intégrité + Périmètre/SSRF + Uploads + Headers/cookies + Tests/monitoring)
**Plans:** 22
**Requirements:** 21/21 satisfied (F02-F22)
**Shipped:**
- Sprint 0 finition (F02-F05): ClientIp + idempotence tally + audit per-member + SSE auth-first
- Vote intégrité (F06-F10): atomic consume + hash invitations + IDOR repos + resetDemo lockdown + CSRF scopé
- Périmètre & SSRF (F11-F13): UrlValidator + rate limits constant-time + AccountLockout exponential
- Uploads & contenu (F14-F16): magic bytes PDF + formula injection + dompdf hardening
- Headers/cookies (F17-F19): CSP_STRICT_MODE + SameSite=Strict default + prod-debug refusé
- Tests/monitoring (F20-F22): testsuite Security 11 tripwires + SecuritySignal + SECURITY.md

</details>

### 🚧 v2.2 Refonte Visuelle & Cohérence (In Progress)

**Milestone Goal:** Faire passer AgVote de "fonctionnellement bon mais émotionnellement neutre" à un produit qui dégage immédiatement le sérieux civique de sa promesse — palette Bleu République harmonisée, dark mode redesigné comme un mode à part entière, identité visuelle par rôle utilisateur (admin/président/opérateur/auditeur/votant/public), refonte des écrans clés (cockpit santé opérateur, vote, PV éditorial), et lexique unifié.

**Scope:** Couches visuelles + cohérence terminologique. Aucune logique métier touchée. Pyramide en 4 phases — base solide d'abord (tokens), puis composants, puis personas, puis layout. Une PR par étage.

**Strategy:** 1 PR par phase. Pas de stack — chaque PR autonome, mergeable indépendamment de la suivante. Garantie : aucune phase ne doit casser visuellement ce qui existe avant elle.

## Phase Details

### Phase 1: Design Tokens (la fondation)

**Goal**: Établir une palette OKLCH cohérente et accessible (light + dark) avec tokens explicites pour chaque concept (couleur sémantique, surface élévation, rôle utilisateur, espacement, typo, radius, shadow), de manière à ce que les phases suivantes n'aient plus à inventer de valeur en dur.

**Depends on**: Nothing (first phase)
**Requirements**: DESIGN-T01, T02, T03, T04, T05, T06, T07, T08
**Success Criteria** (what must be TRUE):
  1. `--color-primary` est passé à `oklch(0.45 0.180 265)` (Bleu République, #2c468f) ; les sémantiques success/danger/warning/info sont harmonisées (chroma 0.13-0.18, lightness 0.45-0.62) sans tomber dans Material Default.
  2. Les surfaces light tendent vers blanc sans être blanc pur — `--color-bg = oklch(0.985 0.001 0)` (#fbfbfb), `--color-surface-raised = #ffffff` est le SEUL vrai blanc, réservé aux popovers/modals.
  3. Le dark mode est designé indépendamment : 5 niveaux d'élévation, hue 260 (légèrement bleutée), saturation des accents réduite ~25%, lightness inversée (primary lift 0.45 → 0.62), aucun noir pur (le plus profond est `oklch(0.13)`).
  4. 6 tokens `--role-*` définis dans le spectre froid (240°-330°), différenciés par lightness/saturation, jamais par teintes opposées.
  5. `@media (prefers-color-scheme: dark)` détecte automatiquement la préférence OS via `:where(:root:not([data-theme]))` ; un toggle JS utilisateur explicite reste prioritaire.
  6. `DESIGN.md` à la racine sert de source de vérité unique ; les templates email_*.php utilisent les hex documentés dans la table de correspondance.
  7. `tests/Visual/tokens.html` rend la palette en grille avec toggle Light/Dark/Auto pour validation à l'œil.
**Plans:** 1 plan (livré inline via PR #256)
**Status**: ✓ Implementation in PR #256 (awaiting review/merge)

### Phase 2: Components

**Goal**: Migrer les composants atomiques (boutons, cards, modales, drawers, forms, toasts) sur les nouveaux tokens et corriger les états sémantiquement creux (notamment le `disabled` qui devient gris uniforme aujourd'hui — il devrait rester teinté primary pour signifier "ce bouton EST primary, mais pas maintenant").

**Depends on**: Phase 1
**Requirements**: DESIGN-C01, C02, C03, C04, C05
**Success Criteria** (what must be TRUE):
  1. Boutons disabled ne sont plus gris monochromes — variant teinté primary (`opacity: 0.45` ou similaire) qui préserve l'identité du bouton.
  2. Cards utilisent `--radius-lg = 10px` et `--shadow-md` ; modals utilisent `--radius-lg` + `--shadow-lg` ; drawers harmonisés sur les mêmes tokens.
  3. Forms : `.field-input`, `.field-label`, `.field-error` consistants, helper text avec convention de ponctuation appliquée.
  4. Toasts utilisent les couleurs sémantiques harmonisées (success vert sénat, danger rouge huissier, etc.) — alignées avec les KPI cards.
  5. `tests/Visual/components.html` rend tous les composants en grille pour validation visuelle.

### Phase 3: Personas (Role Markers + Isolation)

**Goal**: Introduire la signature visuelle par rôle — bande 3px persona-colored au top de chaque page authentifiée + badge persona dans la sidebar — pour que l'utilisateur sache instantanément "je suis en mode admin/opérateur/auditeur/etc.". Compléter par des tests d'isolation qui empêchent la régression côté backend (un voteur qui GET /dashboard reçoit 403, etc.).

**Depends on**: Phase 1 (tokens --role-*)
**Requirements**: DESIGN-P01, P02, P03, P04
**Success Criteria** (what must be TRUE):
  1. Une bande de 3px en haut de chaque page authentifiée est colorée par `var(--role-X)` correspondant au rôle de l'utilisateur connecté.
  2. La sidebar affiche un badge persona (texte "Admin"/"Opérateur"/etc.) avec la couleur correspondante au-dessus du nom utilisateur.
  3. Attribut `data-persona` posé sur `<body>` côté serveur depuis la session, lu par CSS pour appliquer la couleur via `[data-persona="operator"] .role-bar { background: var(--role-operator); }`.
  4. `tests/Security/PersonaIsolationTest.php` (nouveau) couvre : voteur sur /dashboard → 403, auditeur sur tout endpoint mutateur → 403, sidebar HTML matche le rôle (pas d'item "Admin" pour un opérateur).

### Phase 4: Layout & Lexique (le ressenti final)

**Goal**: Refondre les écrans à plus haute valeur émotionnelle — cockpit santé opérateur en exécution, vote screen typographie augmentée, PV/Trust/Archives traitement éditorial Newsreader, dashboard simplifié, login moins marketing — et clore le travail de cohérence avec une convention lexicale unique (membre/participant/votant + valider/verrouiller/archiver) appliquée à tout le copy.

**Depends on**: Phase 2 (composants), Phase 3 (personas en place)
**Requirements**: DESIGN-L01, L02, L03, L04, L05, X01, X02, X03, X04
**Success Criteria** (what must be TRUE):
  1. La vue exécution opérateur affiche une **barre santé séance** unique de ~56px en haut avec 4 indicateurs (Quorum / SSE / Votants connectés / Résolution actuelle) — chacun avec sa couleur sémantique persistante. Si un indicateur passe rouge, la barre entière prend une bordure danger animée.
  2. La page `/vote` adopte une typographie minimum 18px (`--text-lg`), boutons ≥ 96px, palette désaturée (on ne pousse pas à voter Pour visuellement). Aucun élément admin/opérateur visible dans cette vue.
  3. Les pages `/audit`, `/trust`, `/archives`, et le rendu PV utilisent la police serif Newsreader pour le contenu (largeur de lecture plafonnée à 720px), Inter pour les contrôles UI, JetBrains Mono pour les hashes/UUID/codes.
  4. Convention lexicale écrite et appliquée : "membre" (inscrit) / "participant" (présent) / "votant" (éligible au scrutin courant) — distinctions sémantiques claires. Idem pour "confirmer" / "valider" / "verrouiller-archiver".
  5. Test Playwright captures les 6 personas sur les 3 pages clés (/dashboard, /operator, /audit) — 18 screenshots qui doivent visuellement révéler les bandes persona et les différences de densité voulues.

</details>

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Design Tokens | v2.2 | 1/1 | ◆ In review (PR #256) | - |
| 2. Components | v2.2 | 0/? | ○ Not started | - |
| 3. Personas (Role Markers + Isolation) | v2.2 | 0/? | ○ Not started | - |
| 4. Layout & Lexique | v2.2 | 0/? | ○ Not started | - |
