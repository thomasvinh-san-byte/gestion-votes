# AG-VOTE Roadmap

## Milestones

- ✅ **v1.1 through v1.5** — Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign (Acte Officiel)** — Phases 4-15 (shipped 2026-03-16)
- ✅ **v3.0 Session Lifecycle** — Phases 16-24 (shipped 2026-03-18)
- ✅ **v4.0 Clarity & Flow** — Phases 25-29 (shipped 2026-03-18)
- 🔄 **v4.1 Design Excellence** — Phases 30-34 (active)

## Phases

<details>
<summary>✅ v1.1 through v1.5 (Phases 1-3) — SHIPPED</summary>

- [x] Phase 1-3: E2E tests, CI, security hardening, code quality, coverage expansion

</details>

<details>
<summary>✅ v2.0 UI Redesign (Phases 4-15) — SHIPPED 2026-03-16</summary>

- [x] Phase 4: Design Tokens & Theme (2/2 plans) — completed 2026-03-12
- [x] Phase 5: Shared Components (4/4 plans) — completed 2026-03-12
- [x] Phase 6: Layout & Navigation (3/3 plans) — completed 2026-03-12
- [x] Phase 7: Dashboard & Sessions (3/3 plans) — completed 2026-03-13
- [x] Phase 8: Session Wizard & Hub (3/3 plans) — completed 2026-03-13
- [x] Phase 9: Operator Console (3/3 plans) — completed 2026-03-13
- [x] Phase 10: Live Session Views (2/2 plans) — completed 2026-03-13
- [x] Phase 11: Post-Session & Records (3/3 plans) — completed 2026-03-15
- [x] Phase 12: Analytics & User Management (2/2 plans) — completed 2026-03-15
- [x] Phase 13: Settings & Help (2/2 plans) — completed 2026-03-15
- [x] Phase 14: Integration + API Wiring (3/3 plans) — completed 2026-03-13
- [x] Phase 15: Operator Wiring + Tech Debt (6/6 plans) — completed 2026-03-16

</details>

<details>
<summary>✅ v3.0 Session Lifecycle (Phases 16-24) — SHIPPED 2026-03-18</summary>

- [x] Phase 16: Data Foundation (2/2 plans) — completed 2026-03-16
- [x] Phase 17: Demo Data Removal (2/2 plans) — completed 2026-03-16
- [x] Phase 18: SSE Infrastructure (1/1 plan) — completed 2026-03-16
- [x] Phase 19: Operator Console Wiring (1/1 plan) — completed 2026-03-16
- [x] Phase 20: Live Vote Flow (2/2 plans) — completed 2026-03-17
- [x] Phase 20.1: Refonte UI Wireframe Alignment (4/4 plans) — completed 2026-03-17
- [x] Phase 20.2: Deep UI Wireframe Alignment (4/4 plans) — completed 2026-03-17
- [x] Phase 20.3: Page Layout Wireframe Alignment (4/4 plans) — completed 2026-03-18
- [x] Phase 20.4: Design System Enforcement (12/12 plans) — completed 2026-03-18
- [x] Phase 21: Post-Session & PV (1/1 plan) — completed 2026-03-18
- [x] Phase 22: Final Audit (2/2 plans) — completed 2026-03-18
- [x] Phase 23: Integration Wiring Fixes (1/1 plan) — completed 2026-03-18
- [x] Phase 24: Final Wiring Polish (1/1 plan) — completed 2026-03-18

</details>

<details>
<summary>✅ v4.0 Clarity & Flow (Phases 25-29) — SHIPPED 2026-03-18</summary>

- [x] **Phase 25: PDF Infrastructure Foundation** — Secure upload, serve endpoint, storage env var, PDF.js, FilePond, ag-pdf-viewer
- [x] **Phase 26: Guided UX Components** — ag-empty-state, help panels (no Driver.js), status-aware dashboard cards, disabled-button explanations, term popovers (completed 2026-03-18)
- [x] **Phase 27: Copropriete Transformation** — Vocabulary cleanup, lot field removal, openKeyModal removal, weighted-vote regression test (completed 2026-03-18)
- [x] **Phase 28: Wizard & Session Hub UX Overhaul** — Named-step wizard, autosave, review card, template picker, hub checklist, quorum bar (completed 2026-03-18)
- [x] **Phase 29: Operator Console, Voter View & Visual Polish** — Console layout, voter ballot card, result cards, CSS @layer, animations, PC-first validation (completed 2026-03-18)

</details>

### v4.1 Design Excellence (Phases 30-34) — ACTIVE

- [x] **Phase 30: Token Foundation** — Reduce 265+ tokens to ~100 semantic tokens; establish primitive→semantic→component hierarchy, shadow scale, spacing aliases, radius scale, dark mode derivation, zero hardcoded hex (completed 2026-03-19) (completed 2026-03-19)
- [x] **Phase 31: Component Refresh** — Buttons, cards, tables, form inputs, modals, toasts, badges, steppers rebuilt to exact shadcn/Sonner/Polaris specs with correct heights, radii, transitions, and dark parity (completed 2026-03-19)
- [ ] **Phase 32: Page Layouts — Core Pages** — Dashboard, wizard, operator console, data tables, settings/admin, mobile voter rebuilt to FEATURES.md grid/flex specs with the three-depth background model
- [ ] **Phase 33: Page Layouts — Secondary Pages** — Hub, post-session, analytics, help/FAQ, email templates, meetings list rebuilt with consistent layout language and correct density
- [ ] **Phase 34: Quality Assurance Final Audit** — Every page verified against the 6 AI anti-patterns checklist, background layering, Fraunces usage, dark mode parity, and transitions/focus/inline-style standards

---

## Phase Details

### Phase 25: PDF Infrastructure Foundation
**Goal**: PDF documents can be securely uploaded, stored, served, and previewed — all two P0 security blockers resolved before any viewer UI is built
**Depends on**: Nothing (first v4.0 phase)
**Requirements**: PDF-01, PDF-02, PDF-03, PDF-04, PDF-05, PDF-06, PDF-07, PDF-08, PDF-09, PDF-10
**Success Criteria** (what must be TRUE):
  1. A PDF attached to a resolution in the wizard step 3 survives a server restart and is retrievable after deployment (persistent volume mount, not /tmp)
  2. A logged-in voter can view a resolution's PDF in the voter view via ag-pdf-viewer bottom sheet; an unauthenticated request to the serve endpoint returns 401
  3. PDF.js version is >= 4.2.67 (CVE-2024-4367 closed); isEvalSupported: false is set; serve endpoint sends X-Content-Type-Options: nosniff and Cache-Control: private, no-store
  4. The wizard step 3 FilePond upload enforces PDF-only and 10 MB max with inline error messages before submission
  5. Hub shows "Document joint" / "Aucun document" status per motion; clicking the indicator opens the inline ag-pdf-viewer
**Plans:** 4 plans (3 complete + 1 gap closure)
Plans:
- [x] 25-01-PLAN.md — Backend foundation: DB migration, controller, repository, serve endpoint, storage env var, Docker volume
- [x] 25-02-PLAN.md — Frontend components: ag-pdf-viewer Web Component, FilePond upload in wizard step 3
- [x] 25-03-PLAN.md — Page wiring: hub badges, operator upload, voter bottom sheet, SSE events

### Phase 26: Guided UX Components
**Goal**: The design itself guides users naturally — help panels replace tour buttons, empty states replace blank containers, disabled buttons explain themselves, and the dashboard shows lifecycle-aware CTAs
**Depends on**: Phase 25 (ag-pdf-viewer available for consistent component patterns)
**Requirements**: GUX-01, GUX-02, GUX-03, GUX-04, GUX-05, GUX-06, GUX-07, GUX-08
**Success Criteria** (what must be TRUE):
  1. Clicking the help button on any of the 8 pages opens a contextual help popover with page-relevant tips (not a sequential tour)
  2. Every list or table that can be empty shows a heading + description + secondary action (via ag-empty-state) instead of a blank container
  3. Every locked button displays a tooltip explaining why it is disabled (e.g., "Disponible apres ajout des resolutions")
  4. Each session card on the dashboard shows exactly one next-action CTA reflecting its current lifecycle state (draft / live / closed)
  5. Technical terms (majorite absolue, quorum, scrutin secret) have (?) click popovers with clear definitions
**Plans:** 4 plans (3 complete + 1 gap closure)
Plans:
- [ ] 26-01-PLAN.md — ag-empty-state Web Component + empty state migration (meetings, archives, settings, members, users)
- [ ] 26-02-PLAN.md — Dashboard status-aware session cards with lifecycle CTAs
- [ ] 26-03-PLAN.md — Help panel popovers (8 pages), disabled button tooltips, technical term popovers

### Phase 27: Copropriete Transformation
**Goal**: The application uses generic AG vocabulary throughout the UI — no copropriete-specific language visible to users — while all weighted-vote calculations remain functionally identical
**Depends on**: Nothing (fully independent; can run parallel with Phase 26)
**Requirements**: CPR-01, CPR-02, CPR-03, CPR-04, CPR-05
**Success Criteria** (what must be TRUE):
  1. A search across all user-facing strings finds zero occurrences of "copropriete", "tantiemes", "lot", "milliemes", "cle de repartition" in rendered HTML
  2. The lot field is absent from the wizard member input form; submitting a member without a lot field produces no error and no DB change
  3. The "Cle de repartition" option is absent from the settings page; no broken stub JS runs when settings loads
  4. A PHPUnit test asserts that a session with two members having voting_power 3 and 1 tallies POUR:3, CONTRE:1 (not 1:1) — and this test passes both before and after the vocabulary changes
**Plans:** 2/2 plans complete
Plans:
- [ ] 27-01-PLAN.md — PHPUnit regression test + vocabulary renames + dead code removal across core UI files
- [ ] 27-02-PLAN.md — Seed data + documentation vocabulary cleanup + full codebase audit

### Phase 28: Wizard & Session Hub UX Overhaul
**Goal**: Operators can create a complete session and prepare a meeting entirely within the application without any confusion — the wizard guides step-by-step, nothing is lost on back-navigation, and the hub shows exactly what remains before going live
**Depends on**: Phase 26 (ag-guide, ag-hint, ag-empty-state available), Phase 25 (PDF attachment in wizard)
**Requirements**: WIZ-01, WIZ-02, WIZ-03, WIZ-04, WIZ-05, WIZ-06, WIZ-07, WIZ-08
**Success Criteria** (what must be TRUE):
  1. The wizard shows a named horizontal stepper (Informations -> Membres -> Resolutions -> Revision); navigating backward with the browser back button returns to the previous step with all entered data intact
  2. Step 4 displays a full review card of all session data with a "Modifier" link that jumps back to the relevant section; submitting from step 4 creates the session atomically
  3. Step 3 offers a motion template picker with 3 hardcoded templates; selecting one pre-fills the motion title and description fields
  4. Step 2's advanced voting settings are hidden behind a toggle ("Parametres de vote avances") and only appear when the toggle is activated
  5. The session hub pre-meeting checklist shows which items are blocked and why ("Disponible apres: resolutions ajoutees") with a quorum progress bar that animates from amber to green once quorum is reached
  6. Each motion in the hub displays "Document joint" or "Aucun document" as its document status
**Plans:** 4 plans (3 complete + 1 gap closure)
Plans:
- [ ] 28-01-PLAN.md — Wizard functional overhaul: named stepper, optional steps, review card, templates, progressive disclosure
- [ ] 28-02-PLAN.md — Hub enhancements: checklist blocked reasons, quorum bar, motions doc badges, convocation flow
- [ ] 28-03-PLAN.md — CSS rewrite: Notion-like aesthetic for wizard.css and hub.css + visual checkpoint

### Phase 29: Operator Console, Voter View & Visual Polish
**Goal**: The live session experience is flawless under pressure — operators see real-time status at a glance, voters cast ballots in one tap with instant feedback, results are unambiguous, and every page meets measurable visual quality criteria
**Depends on**: Phase 26 (guided UX components), Phase 25 (PDF viewer for voter bottom sheet), Phase 28 (wizard/hub complete)
**Requirements**: OPC-01, OPC-02, OPC-03, OPC-04, OPC-05, VOT-01, VOT-02, VOT-03, VOT-04, VOT-05, VOT-06, RES-01, RES-02, RES-03, RES-04, RES-05, VIS-01, VIS-02, VIS-03, VIS-04, VIS-05, VIS-06, VIS-07, VIS-08
**Success Criteria** (what must be TRUE):
  1. The operator console status bar shows SSE connectivity state with colour + icon + label (never colour alone); the live vote count displays a delta indicator ("+N votes in last 30s") alongside the absolute count
  2. When a vote is open, the voter screen hides all navigation and chrome, shows only the ballot card with full-width options (min 72px height); tapping an option registers a visual selection within 50ms and submits in the background with rollback on error
  3. A result card shows absolute numbers, percentages, the required threshold, and the ADOPTE/REJETE verdict as the largest element; a bar chart breaks down POUR/CONTRE/ABSTENTION; the card is collapsible with only the headline visible by default
  4. The post-session stepper (Resultats -> Validation -> PV -> Archivage) shows a checkmark on each completed step
  5. The design system CSS has a @layer declaration (base, components, v4); all new component color variations use color-mix() derived tokens; every new token has a dark variant committed in the same change
  6. All pages pass measurable done criteria: transitions <= 200ms, CLS = 0 (Lighthouse), focus rings >= 3:1 contrast (axe-core), zero inline style="" in production HTML; voter screen verified at 375px
**Plans:** 7/7 plans complete
Plans:
- [ ] 29-01-PLAN.md — Design system @layer + color-mix tokens + dark parity
- [ ] 29-02-PLAN.md — Operator console SSE indicator, delta badge, guidance panels
- [ ] 29-03-PLAN.md — Voter full-screen ballot, optimistic commit, state management
- [ ] 29-04-PLAN.md — Collapsible result cards with bar charts, stepper enhancement
- [ ] 29-05-PLAN.md — @starting-style animations, View Transitions, Anime.js KPI count-up
- [ ] 29-06-PLAN.md — All-page CSS polish (15 files), token audit, hover transitions
- [ ] 29-07-PLAN.md — VIS-08 inline style audit + dark mode parity verification

---

### Phase 30: Token Foundation
**Goal**: Every CSS token in the system is purposeful, named semantically, and derives dark mode automatically — giving all subsequent phases a trustworthy foundation to build on
**Depends on**: Nothing (first v4.1 phase)
**Requirements**: TKN-01, TKN-02, TKN-03, TKN-04, TKN-05, TKN-06, TKN-07, TKN-08
**Success Criteria** (what must be TRUE):
  1. The design-system.css :root block contains ~100 tokens or fewer, organized in three visible layers (primitive → semantic → component aliases), with every primitive prefixed to distinguish it from semantic tokens
  2. Applying `data-theme="dark"` switches the visual appearance via ~20-30 overrides only — no page CSS file requires a duplicate dark block because semantic tokens derive dark values automatically
  3. Every page CSS file passes a grep for hardcoded hex/rgb/hsl values and returns zero matches — all color references use design-system tokens
  4. Setting `--text-base` to 14px in one place causes all UI chrome labels to shrink correctly while body reading text stays at 16px — the typography scale is semantically layered
  5. Named shadow levels (xs through xl) visually differentiate cards from modals from tooltips — a developer can identify component type by shadow alone without inspecting the element
**Plans:** 4/4 plans complete
Plans:
- [ ] 30-01-PLAN.md — Core token restructuring: primitives, semantic colors, shadows, spacing, radius, typography, transitions
- [ ] 30-02-PLAN.md — Typography 14px migration: audit text-base usage, protect reading text, flip base size
- [ ] 30-03-PLAN.md — Hardcoded hex sweep: replace all standalone color values in page CSS with tokens
- [ ] 30-04-PLAN.md — Gap closure: fix section-title weight, add spacing aliases, fix radius values, add component aliases layer

### Phase 31: Component Refresh
**Goal**: Every shared UI component renders with intentional, differentiated visual specs — no two component types share the same radius, shadow, or spacing values, and all components use Phase 30 tokens exclusively
**Depends on**: Phase 30 (token system must be correct before component specs are applied)
**Requirements**: CMP-01, CMP-02, CMP-03, CMP-04, CMP-05, CMP-06, CMP-07, CMP-08
**Success Criteria** (what must be TRUE):
  1. A button, a card, a badge, and a modal placed side by side each display a visually distinct border-radius — no two of the four share the same value
  2. A card at rest shows a subtle shadow + border; on hover it lifts 1px with a stronger shadow and a lighter border — the elevation model is perceptible without color change
  3. A table row at 48px height with a sticky 40px header shows right-aligned numeric columns in JetBrains Mono with a hover highlight — the data density matches the Linear/Jira reference
  4. A form input at 36px height shows a double-ring focus state (2px surface gap + 4px primary ring) and a red border on validation error — the focus and error states are unambiguous
  5. Toast notifications stack with 8px gap, display a 3px left-border accent stripe in the semantic status color, and slide in from the correct edge — Sonner-pattern behavior is complete
**Plans:** 3/3 plans complete
Plans:
- [ ] 31-01-PLAN.md — CSS component specs: add tokens, update buttons/cards/tables/forms/modals/toasts/badges/steppers in design-system.css
- [ ] 31-02-PLAN.md — Web Component reconciliation: tokenize Shadow DOM styles in ag-modal, ag-toast, ag-badge, ag-stepper

### Phase 32: Page Layouts — Core Pages
**Goal**: The six highest-traffic pages (dashboard, wizard, operator console, data tables, settings, mobile voter) are rebuilt to FEATURES.md grid specs with the three-depth background model and correct density for each page's use case
**Depends on**: Phase 31 (components must be refreshed before pages are composed)
**Requirements**: LAY-01, LAY-02, LAY-03, LAY-04, LAY-05, LAY-06
**Success Criteria** (what must be TRUE):
  1. The dashboard shows a 4-column KPI row at the top, sessions as a vertical list below, and a sticky 280px aside on the right — the three-depth background (body/surface/raised) is visible as three distinct tonal layers
  2. The wizard centers its form track at 680px with fields capped at 480px wide, a stepper above the form card, and a sticky footer with back/next controls — no sidebar competes for attention
  3. The operator console displays a 280px agenda sidebar beside a fluid main area with a fixed status bar at top and tab nav below it — the layout is implemented as a 3-row CSS grid
  4. Data table pages (audit, archives, members, users) all share the same toolbar/table/pagination structure with sticky 40px headers and right-aligned numeric columns — visual consistency is immediate
  5. The mobile voter screen fills 100dvh, vote option buttons are minimum 72px tall, and text scales fluidly with clamp() — the layout works without horizontal scrolling at 375px viewport width
**Plans:** 2 plans
Plans:
- [ ] 31-01-PLAN.md — CSS component specs: add tokens, update buttons/cards/tables/forms/modals/toasts/badges/steppers in design-system.css
- [ ] 31-02-PLAN.md — Web Component reconciliation: tokenize Shadow DOM styles in ag-modal, ag-toast, ag-badge, ag-stepper

### Phase 33: Page Layouts — Secondary Pages
**Goal**: The six supporting pages (hub, post-session, analytics, help, email templates, meetings list) adopt the same layout language established in Phase 32 — coherent density, consistent background layering, and no page feeling like an afterthought
**Depends on**: Phase 32 (core page layout patterns established as reference)
**Requirements**: LAY-07, LAY-08, LAY-09, LAY-10, LAY-11, LAY-12
**Success Criteria** (what must be TRUE):
  1. The hub page shows a sidebar stepper on the left and the meeting checklist as the main content — the quorum progress bar is the most visually prominent element on the page, not buried below the fold
  2. The post-session page shows the four-step stepper (Resultats/Validation/PV/Archivage) with checkmarks on completed steps and result cards that are collapsible with consistent inter-section spacing
  3. The analytics page renders chart areas and KPI cards in a responsive grid — the layout does not produce a single-column stack at 1024px viewport width
  4. The help/FAQ page uses accordion components with correct padding (not raw details/summary defaults) — expanding an item does not cause layout shift on surrounding items
  5. The meetings list page applies the same card-or-table density pattern as the dashboard sessions list, with status badges using the semantic badge component from Phase 31
**Plans:** 2 plans
Plans:
- [ ] 31-01-PLAN.md — CSS component specs: add tokens, update buttons/cards/tables/forms/modals/toasts/badges/steppers in design-system.css
- [ ] 31-02-PLAN.md — Web Component reconciliation: tokenize Shadow DOM styles in ag-modal, ag-toast, ag-badge, ag-stepper

### Phase 34: Quality Assurance Final Audit
**Goal**: Every page in the application passes an objective checklist that distinguishes intentional premium design from AI-generated uniformity — the refonte is verifiably complete
**Depends on**: Phase 33 (all page layouts must be complete before auditing)
**Requirements**: QA-01, QA-02, QA-03, QA-04, QA-05
**Success Criteria** (what must be TRUE):
  1. Running the 6 AI anti-patterns checklist against every page finds zero violations — no uniform shadows, no uniform radii, spatial hierarchy present on all pages, color used for signal not decoration, weight contrast visible between heading levels, hover states use transform not color alone
  2. Every page shows exactly three tonal background levels (body/surface/raised) — a screenshot with eyedropper confirms three distinct values, not two or one
  3. The Fraunces display font appears exactly once per page (the page title `<h1>`) and never on section headings, card titles, or subheadings — the font usage log is clean
  4. Switching to dark mode on every page produces an intentionally designed appearance — no pure black backgrounds, no invisible borders, no washed-out text — verified by visual inspection of each page in dark mode
  5. Automated checks confirm: all transitions are 200ms or under, all focus rings meet 3:1 contrast ratio, and zero `style=""` attributes appear in any production-rendered HTML response
**Plans:** 2 plans
Plans:
- [ ] 31-01-PLAN.md — CSS component specs: add tokens, update buttons/cards/tables/forms/modals/toasts/badges/steppers in design-system.css
- [ ] 31-02-PLAN.md — Web Component reconciliation: tokenize Shadow DOM styles in ag-modal, ag-toast, ag-badge, ag-stepper

---

## Progress Table

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 25. PDF Infrastructure Foundation | 3/3 | Complete    | 2026-03-18 |
| 26. Guided UX Components | 3/3 | Complete    | 2026-03-18 |
| 27. Copropriete Transformation | 2/2 | Complete    | 2026-03-18 |
| 28. Wizard & Session Hub UX Overhaul | 3/3 | Complete    | 2026-03-18 |
| 29. Operator Console, Voter View & Visual Polish | 7/7 | Complete    | 2026-03-18 |
| 30. Token Foundation | 4/4 | Complete   | 2026-03-19 |
| 31. Component Refresh | 3/3 | Complete    | 2026-03-19 |
| 32. Page Layouts — Core Pages | 0/? | Not started | - |
| 33. Page Layouts — Secondary Pages | 0/? | Not started | - |
| 34. Quality Assurance Final Audit | 0/? | Not started | - |

---

### Future Milestones (Backlog)

**v5.0+ Feature Ideas:**
- **AI-assisted PV generation** — AI-assisted minutes generation for post-session PV
- **ClamAV PDF scanning** — Virus scanning for uploaded resolution documents
- **Per-tenant motion templates** — Move hardcoded templates to per-tenant DB storage
- **Multi-page cross-session tours** — Guided tours spanning multiple pages with persistent state
- **Electronic signature upload/validation** — Deferred; significant new capability
- **Votes pour collectivites territoriales** — Voting mode for syndicats, communes, conseils de communes, departements
- **Suivi budget & documents PDF pour votants** — Budget tracking with PDF document distribution
