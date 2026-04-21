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
- 🚧 **v1.9 UX Standards & Retention** - Phases 1-5 (in progress)

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

### v1.9 UX Standards & Retention (In Progress)

**Milestone Goal:** Mettre l'interface aux normes UX pour ne pas perdre les utilisateurs non-techniques — sidebar classique, typographie lisible, feedback clair, jargon elimine cote votant.

- [ ] **Phase 1: Typographie et Espacement** - Tokens fondation: base 16px, labels normaux, header 64px, spacing 20-24px
- [ ] **Phase 2: Sidebar Navigation** - Sidebar toujours ouverte ~200px, items filtres par role, touch targets 44px
- [ ] **Phase 3: Feedback et Etats Vides** - Messages etats vides, confirmation vote, indicateurs chargement, etat 0-resultats
- [ ] **Phase 4: Clarte et Jargon** - Jargon elimine cote votant, tooltips admin, confirmation simplifiee, descriptions exports
- [ ] **Phase 5: Validation Gate** - Verification NAV-04 (accueil) + validation cross-page zero regressions

## Phase Details

### Phase 1: Typographie et Espacement
**Goal**: Les textes sont lisibles et l'espacement est confortable sur toutes les pages — la fondation visuelle sur laquelle les phases suivantes s'appuient
**Depends on**: Nothing (first phase, foundation tokens)
**Requirements**: TYPO-01, TYPO-02, TYPO-03, TYPO-04
**Success Criteria** (what must be TRUE):
  1. Le texte courant sur toutes les pages s'affiche a 16px minimum (plus de 14px en base)
  2. Les labels de formulaire s'affichent en casse normale avec couleur lisible (plus d'UPPERCASE muted)
  3. Le header fait 64px avec breadcrumb + titre uniquement (plus de sous-titre ni barre decorative)
  4. L'espacement entre champs de formulaire et sections est de 20-24px (plus de 14px comprime)
**Plans**: TBD

### Phase 2: Sidebar Navigation
**Goal**: La navigation laterale est toujours visible et utilisable sans effort — chaque utilisateur voit uniquement les liens pertinents pour son role
**Depends on**: Phase 1 (typography tokens affect sidebar label rendering)
**Requirements**: NAV-01, NAV-02, NAV-03
**Success Criteria** (what must be TRUE):
  1. La sidebar est toujours ouverte (~200px) avec labels textuels visibles — plus de hover-to-expand ni rail d'icones
  2. Un votant connecte ne voit que les liens pertinents (Voter, Mon compte) — pas 16 liens admin
  3. Tous les boutons et liens de la sidebar font minimum 44x44px de zone cliquable (WCAG 2.5.8)
  4. La sidebar fonctionne correctement sur ecran 1366px minimum sans chevaucher le contenu
**Plans**: TBD

### Phase 3: Feedback et Etats Vides
**Goal**: L'utilisateur n'est jamais face a un ecran vide ou silencieux — chaque etat (vide, chargement, zero-resultat, apres-vote) a un message explicite en francais
**Depends on**: Phase 1 (typography tokens for message rendering)
**Requirements**: FEED-01, FEED-02, FEED-03, FEED-04
**Success Criteria** (what must be TRUE):
  1. Chaque liste/grille vide affiche un message actionnable en francais (ex: "Creez votre premiere seance") au lieu de skeletons suspendus
  2. Apres un vote, une confirmation persistante avec horodatage reste visible (pas un flash 3 secondes)
  3. Les filtres et recherches sans resultats affichent "Aucun resultat" avec un lien pour reinitialiser les filtres
  4. Les chargements affichent un indicateur en francais ("Chargement...") au lieu de skeletons silencieux
**Plans**: TBD

### Phase 4: Clarte et Jargon
**Goal**: L'interface parle la langue de l'utilisateur — zero terme technique cote votant, tooltips explicatifs cote admin, confirmations simples
**Depends on**: Phase 1 (typography for tooltip rendering)
**Requirements**: CLAR-01, CLAR-02, CLAR-03, CLAR-04
**Success Criteria** (what must be TRUE):
  1. L'interface votant n'affiche aucun terme technique visible (eIDAS, SHA-256, quorum, CNIL) — tous remplaces par des equivalents comprehensibles
  2. Les termes techniques cote admin/operateur ont des tooltips explicatifs en francais au survol
  3. Le pattern "tapez VALIDER" est remplace par un modal avec checkbox de confirmation + bouton Confirmer
  4. Chaque bouton d'export a une description d'une ligne expliquant le contenu du fichier genere
**Plans**: TBD

### Phase 5: Validation Gate
**Goal**: Confirmer que NAV-04 (page d'accueil) est conforme et que toutes les modifications cross-pages n'ont introduit aucune regression
**Depends on**: Phase 4
**Requirements**: NAV-04
**Success Criteria** (what must be TRUE):
  1. La page d'accueil affiche une carte centree avec logo AG-VOTE + formulaire de connexion (NAV-04 — verification de l'existant)
  2. L'ensemble des tests E2E existants passent sans regression sur les modifications des phases 1-4
  3. Les pages cles (login, dashboard, meetings, vote) sont visuellement coherentes apres les changements typographiques et de sidebar
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Typographie et Espacement | v1.9 | 0/0 | Not started | - |
| 2. Sidebar Navigation | v1.9 | 0/0 | Not started | - |
| 3. Feedback et Etats Vides | v1.9 | 0/0 | Not started | - |
| 4. Clarte et Jargon | v1.9 | 0/0 | Not started | - |
| 5. Validation Gate | v1.9 | 0/0 | Not started | - |
