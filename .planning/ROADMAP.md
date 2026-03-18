# AG-VOTE Roadmap

## Milestones

- ✅ **v1.1 through v1.5** — Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign (Acte Officiel)** — Phases 4-15 (shipped 2026-03-16)
- ✅ **v3.0 Session Lifecycle** — Phases 16-24 (shipped 2026-03-18)
- 🔄 **v4.0 Clarity & Flow** — Phases 25-29 (active)

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

### v4.0 Clarity & Flow (Phases 25-29) — ACTIVE

- [x] **Phase 25: PDF Infrastructure Foundation** — Secure upload, serve endpoint, storage env var, PDF.js, FilePond, ag-pdf-viewer
- [x] **Phase 26: Guided UX Components** — ag-empty-state, help panels (no Driver.js), status-aware dashboard cards, disabled-button explanations, term popovers (completed 2026-03-18)
- [x] **Phase 27: Copropriete Transformation** — Vocabulary cleanup, lot field removal, openKeyModal removal, weighted-vote regression test (completed 2026-03-18)
- [x] **Phase 28: Wizard & Session Hub UX Overhaul** — Named-step wizard, autosave, review card, template picker, hub checklist, quorum bar (completed 2026-03-18)
- [x] **Phase 29: Operator Console, Voter View & Visual Polish** — Console layout, voter ballot card, result cards, CSS @layer, animations, PC-first validation (completed 2026-03-18)

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
**Plans:** 3/3 plans complete
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
**Plans:** 3/3 plans complete
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
**Plans:** 3/3 plans complete
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

## Progress Table

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 25. PDF Infrastructure Foundation | 3/3 | Complete    | 2026-03-18 |
| 26. Guided UX Components | 3/3 | Complete    | 2026-03-18 |
| 27. Copropriete Transformation | 2/2 | Complete    | 2026-03-18 |
| 28. Wizard & Session Hub UX Overhaul | 3/3 | Complete    | 2026-03-18 |
| 29. Operator Console, Voter View & Visual Polish | 7/7 | Complete    | 2026-03-18 |

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
