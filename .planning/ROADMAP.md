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

### v1.8 Refonte UI et Coherence Visuelle

**Milestone Goal:** Corriger les 67 problemes UI identifies par l'audit -- palette moderne, classes CSS coherentes, versions unifiees, patterns consolides.

- [x] **Phase 1: Palette et Tokens** - Migrer la palette beige/parchment vers gris neutre, tokens stone vers slate, hex vers oklch (completed 2026-04-20)
- [x] **Phase 2: Classes CSS et Inline Cleanup** - Wizard field-input vers form-input, 42 inline styles vers classes, shell.js drawer (completed 2026-04-20)
- [x] **Phase 3: Coherence Cross-Pages** - Version unique, footer accent, modales consolidees (completed 2026-04-20)
- [ ] **Phase 4: Layout Fixes** - Landing hero compact, radio vers select, KPI dead code
- [ ] **Phase 5: Validation Gate** - Zero inline style, zero field-input, version unique confirmee

## Phase Details

### Phase 1: Palette et Tokens
**Goal**: L'application utilise une palette de couleurs moderne gris neutre au lieu de beige/parchment, avec des tokens coherents
**Depends on**: Nothing (first phase)
**Requirements**: UI-01, UI-02, UI-03
**Success Criteria** (what must be TRUE):
  1. Les fonds de page utilisent un gris neutre (#f8f9fa ou similaire) au lieu de beige (#EDECE6) sur toutes les pages
  2. Les tokens text/border utilisent la gamme slate (cool) au lieu de stone (warm) dans design-system.css
  3. Les couleurs persona (admin, operator, etc.) sont definies en oklch au lieu de hex brut Tailwind
  4. Le mode sombre transpose correctement les nouveaux tokens (pas de regression dark mode)
**Plans:** 1 plan

Plans:
- [ ] 01-01-PLAN.md — Migrate stone palette to slate, update light+dark semantic tokens, persona oklch

### Phase 2: Classes CSS et Inline Cleanup
**Goal**: Tous les champs de formulaire utilisent les classes design-system standard et aucun style inline ne subsiste dans le HTML/JS
**Depends on**: Phase 1
**Requirements**: UI-04, UI-05, UI-06
**Success Criteria** (what must be TRUE):
  1. Le wizard utilise form-input/form-select sur tous les champs (zero occurrence de field-input dans le wizard)
  2. Les 42 inline styles identifies sont remplaces par des classes CSS dans les 15 fichiers HTML concernes
  3. Le drawer shell.js utilise des classes design-system au lieu de styles inline hardcodes
  4. Aucun champ de formulaire visible n'est non-style (zero champ sans padding/border/radius)
**Plans:** 3 plans

Plans:
- [ ] 02-01-PLAN.md — Wizard class migration (field-input to form-input/form-select)
- [ ] 02-02-PLAN.md — Inline style cleanup (display:none to hidden, complex styles to CSS classes)
- [ ] 02-03-PLAN.md — Shell.js drawer refactoring (inline styles to drawer-* CSS classes)

### Phase 3: Coherence Cross-Pages
**Goal**: L'interface presente une identite coherente sur toutes les pages -- version unique, texte correct, modales uniformes
**Depends on**: Phase 2
**Requirements**: UI-07, UI-08, UI-09
**Success Criteria** (what must be TRUE):
  1. Une seule version est affichee sur toutes les pages (plus de v3.19, v4.3, v4.4 ou v5.0 simultanees)
  2. Le footer affiche "Accessibilite" avec accent sur les 13 pages concernees
  3. Les modales utilisent un seul pattern (modal-backdrop + modal role=dialog) au lieu de 6 patterns differents
**Plans:** 3/3 plans complete

Plans:
- [ ] 03-01-PLAN.md — Version unification (PHP constant, placeholder injection, 22 HTML files)
- [ ] 03-02-PLAN.md — Footer accent fix (Accessibilite -> Accessibilite in 13 pages)
- [ ] 03-03-PLAN.md — Modal unification (validate, trust, email-templates, meetings)

### Phase 4: Layout Fixes
**Goal**: Les pages cles ont un layout optimal -- hero compact, controles de formulaire adaptes, zero dead code CSS
**Depends on**: Phase 3
**Requirements**: UI-10, UI-11, UI-12
**Success Criteria** (what must be TRUE):
  1. La landing page montre du contenu utile sans scroll sur un ecran 1080p (hero ne prend plus 100vh)
  2. Le type de seance utilise un select au lieu de radio buttons sur les pages operator, meetings et wizard
  3. Le dead code KPI dans design-system.css est supprime (plus de regles overridees par pages.css)
**Plans**: TBD

### Phase 5: Validation Gate
**Goal**: Confirmation automatisee que toutes les corrections UI sont en place et sans regression
**Depends on**: Phase 4
**Requirements**: UI-13
**Success Criteria** (what must be TRUE):
  1. Zero inline style residuel dans les fichiers HTML (grep confirme)
  2. Zero occurrence de la classe field-input dans le codebase (remplacee par form-input)
  3. Une seule version affichee (source unique, grep confirme)
  4. Les tests E2E existants passent sans regression
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Infrastructure Redis | v1.0 | 2/2 | Complete | 2026-04-07 |
| 2. Optimisations Memoire et Requetes | v1.0 | 2/2 | Complete | 2026-04-07 |
| 3. Refactoring Controllers et Tests Auth | 3/3 | Complete   | 2026-04-20 | 2026-04-07 |
| 4. Tests et Decoupage Controllers | v1.0 | 3/3 | Complete | 2026-04-07 |
| 5. JS Audit et Wiring Repair | v1.1 | 1/1 | Complete | 2026-04-08 |
| 6. Application Design Tokens | v1.1 | 4/4 | Complete | 2026-04-08 |
| 7. Playwright Coverage | v1.1 | 4/4 | Complete | 2026-04-08 |
| 8. Test Infrastructure Docker | v1.2 | 3/3 | Complete | 2026-04-08 |
| 9. Tests E2E par Role | v1.2 | 5/5 | Complete | 2026-04-08 |
| 10. Validation Manuelle Bout-en-Bout | v1.2 | — | Complete | 2026-04-08 |
| 11. Backend Wiring Fixes | v1.2 | 7/7 | Complete | 2026-04-08 |
| 12. Page-by-Page MVP Sweep | v1.2 | 20/21 | Complete | 2026-04-09 |
| 13. MVP Validation Finale | v1.2 | — | Complete | 2026-04-09 |
| 14. Visual Polish | v1.3 | 4/4 | Complete | 2026-04-09 |
| 15. Multi-Browser Tests | v1.3 | — | Complete | 2026-04-09 |
| 16. Accessibility Deep Audit | v1.3 | 5/5 | Complete | 2026-04-09 |
| 17. Loose Ends Phase 12 | v1.3 | 3/3 | Complete | 2026-04-09 |
| 1. Contrast AA Remediation | v1.4 | 3/3 | Complete | 2026-04-10 |
| 2. Overlay Hittest Sweep | v1.4 | 2/2 | Complete | 2026-04-10 |
| 3. Trust Fixtures Deploy | v1.4 | 2/2 | Complete | 2026-04-10 |
| 4. HTMX 2.0 Upgrade | v1.4 | 2/2 | Complete | 2026-04-10 |
| 5. CSP Nonce Enforcement | v1.4 | 2/2 | Complete | 2026-04-10 |
| 6. Controller Refactoring | v1.4 | 3/3 | Complete | 2026-04-10 |
| 1. Nettoyage Codebase | v1.5 | 2/2 | Complete | 2026-04-10 |
| 2. Refactoring AuthMiddleware | v1.5 | 2/2 | Complete | 2026-04-11 |
| 3. Refactoring ImportService | v1.5 | 1/1 | Complete | 2026-04-12 |
| 4. Refactoring ExportService | v1.5 | 1/1 | Complete | 2026-04-13 |
| 5. Refactoring MeetingReportsService | v1.5 | 1/1 | Complete | 2026-04-15 |
| 6. Refactoring EmailQueueService | v1.5 | 1/1 | Complete | 2026-04-20 |
| 7. Validation Gate | v1.5 | 1/1 | Complete | 2026-04-20 |
| 1. JS Interaction Audit & Repair | v1.6 | 3/3 | Complete | 2026-04-20 |
| 2. Form Layout Modernization | v1.6 | 3/3 | Complete | 2026-04-20 |
| 3. Wizard Single-Page | v1.6 | 1/1 | Complete | 2026-04-20 |
| 4. Validation Gate | v1.6 | 1/1 | Complete | 2026-04-20 |
| 1. Audit et Classification | v1.7 | 1/1 | Complete | 2026-04-20 |
| 2. Gardes Backend | v1.7 | 2/2 | Complete | 2026-04-20 |
| 3. Frontend et Validation | v1.7 | 1/1 | Complete | 2026-04-20 |
| 1. Palette et Tokens | v1.8 | 1/1 | Complete | 2026-04-20 |
| 2. Classes CSS et Inline Cleanup | v1.8 | 3/3 | Complete | 2026-04-20 |
| 3. Coherence Cross-Pages | v1.8 | 3/3 | Complete | 2026-04-20 |
| 4. Layout Fixes | v1.8 | 0/? | Not started | - |
| 5. Validation Gate | v1.8 | 0/? | Not started | - |
