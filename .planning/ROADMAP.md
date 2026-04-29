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
- 🚧 **v2.0 Operateur Live UX** - Phases 1-4 (in progress)

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

### v2.0 Operateur Live UX (In Progress)

**Milestone Goal:** Ameliorer l'experience operateur en mode seance live — checklist de controle temps reel, interface epuree en mode execution, et feedback visuel anime sur les votes.

**Scope:** operator.htmx.html uniquement. SSE EventBroadcaster deja cable — les phases consomment l'infrastructure existante.

## Phase Details

### Phase 1: Checklist Operateur
**Goal**: L'operateur dispose d'une checklist en temps reel affichant l'etat de la seance (quorum, votes recus, connectivite SSE, votants connectes) avec alertes visuelles automatiques.
**Depends on**: Nothing (first phase)
**Requirements**: CHECK-01, CHECK-02, CHECK-03, CHECK-04, CHECK-05
**Success Criteria** (what must be TRUE):
  1. En mode live, une checklist visible affiche le ratio quorum avec indicateur vert/rouge selon l'atteinte ou non du seuil
  2. Le compteur de votes recus dans la checklist se met a jour sans rechargement de page quand un vote arrive via SSE
  3. L'indicateur reseau/SSE passe en rouge et affiche un badge "Deconnecte" quand la connexion SSE est interrompue
  4. Le nombre de votants connectes est visible dans la checklist et se met a jour en temps reel
  5. Quand un indicateur passe au rouge (quorum non atteint ou SSE coupe), une alerte visuelle apparait automatiquement sans action de l'operateur
**Plans:** 2 plans
Plans:
- [x] 01-01-PLAN.md — HTML structure + CSS styles du panneau checklist
- [x] 01-02-PLAN.md — JS wiring: SSE events, mode switching, data feeding
**Status**: ✅ Complete (verified 2026-04-29)

### Phase 2: Mode Focus
**Goal**: L'operateur peut basculer vers une vue epuree a 5 zones qui masque les informations secondaires et conserve uniquement les controles essentiels pour conduire le scrutin.
**Depends on**: Phase 1
**Requirements**: FOCUS-01, FOCUS-02, FOCUS-03
**Success Criteria** (what must be TRUE):
  1. En mode execution, l'interface affiche exactement 5 zones: titre motion, resultat vote, quorum status, chronometre, actions — les autres zones sont masquees
  2. Les boutons lancer vote, fermer scrutin et passer motion restent cliquables et visibles dans la vue focus sans scrolling
  3. Un toggle visible permet de passer de la vue complete a la vue focus et inversement, et l'etat persiste pendant la seance
**Plans**: TBD

### Phase 3: Animations Vote
**Goal**: Les compteurs et barres de vote s'animent fluidement a chaque nouveau vote recu via SSE, avec respect de la preference systeme prefers-reduced-motion.
**Depends on**: Phase 2
**Requirements**: ANIM-01, ANIM-02, ANIM-03
**Success Criteria** (what must be TRUE):
  1. Quand un vote arrive via SSE, les compteurs pour/contre/abstention s'incrementent avec une animation visible (pas de changement instantane)
  2. Les barres de progression des resultats glissent vers leur nouvelle valeur en transition CSS fluide sans saut brusque
  3. Sur un systeme avec prefers-reduced-motion: reduce active, les compteurs et barres se mettent a jour instantanement sans animation
**Plans**: TBD

### Phase 4: Validation Gate
**Goal**: Toutes les fonctionnalites v2.0 sont verifiees sans regression sur le reste de l'application.
**Depends on**: Phase 3
**Requirements**: CHECK-01, CHECK-02, CHECK-03, CHECK-04, CHECK-05, FOCUS-01, FOCUS-02, FOCUS-03, ANIM-01, ANIM-02, ANIM-03
**Success Criteria** (what must be TRUE):
  1. Les tests E2E operator-e2e.spec.js passent au vert sans modification des assertions existantes
  2. Aucune regression visuelle ou fonctionnelle sur les autres pages de l'application (dashboard, motions, scrutins)
  3. La syntaxe PHP de tous les fichiers modifies est valide (`php -l`)
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Checklist Operateur | v2.0 | 2/2 | ✅ Complete | 2026-04-29 |
| 2. Mode Focus | v2.0 | 0/? | Not started | - |
| 3. Animations Vote | v2.0 | 0/? | Not started | - |
| 4. Validation Gate | v2.0 | 0/? | Not started | - |
