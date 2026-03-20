# Roadmap: AG-VOTE v4.2 Visual Redesign

## Milestones

- ✅ **v1.x Hardening** - Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign** - Phases 4-15 (shipped 2026-03-16)
- ✅ **v3.0 Session Lifecycle** - Phases 16-24 (shipped 2026-03-18)
- ✅ **v4.0 Clarity & Flow** - Phases 25-29 (shipped 2026-03-18)
- ✅ **v4.1 Design Excellence** - Phases 30-34 (shipped 2026-03-19)
- 🚧 **v4.2 Visual Redesign** - Phases 35-41 (in progress)

## Phases

<details>
<summary>✅ v1.x through v4.1 (Phases 1-34) - SHIPPED</summary>

See MILESTONES.md for full history.

</details>

### 🚧 v4.2 Visual Redesign (In Progress)

**Milestone Goal:** Every page looks top 1% — professionally designed, intuitive, self-explanatory. Page-by-page visual redesign with concrete references (Linear, Notion, Clerk, Stripe). Visible before/after impact in the browser on every page.

- [x] **Phase 35: Entry Points** - Dashboard and Login — the two pages every user sees first, setting the visual bar for the entire app (completed 2026-03-19)
- [x] **Phase 36: Session Creation Flow** - Wizard and Hub — the two connected pages that take a user from intent to a live session (completed 2026-03-20)
- [x] **Phase 37: Live Session Conduct** - Operator Console and Voter ballot — real-time operational pages with dense, high-stakes UI (completed 2026-03-20)
- [x] **Phase 38: Results & History** - Post-session, Analytics, and Meetings list — read-only pages for reviewing outcomes and trends (completed 2026-03-20)
- [x] **Phase 39: Admin Data Tables** - Members, Users, Audit log, and Archives — the four table-heavy admin pages (completed 2026-03-20)
- [ ] **Phase 40: Configuration Cluster** - Settings/Admin, Email templates, and Help/FAQ — grouped by low-frequency but high-trust interactions
- [ ] **Phase 41: Public & Utility Pages** - Landing, Public/Projector display, Report/PV, and Trust/Validate/Doc utilities

## Phase Details

### Phase 35: Entry Points
**Goal**: Users land on a dashboard and login page that immediately signal "professional governance tool" — design quality visible from the first screen
**Depends on**: Nothing (first phase of v4.2)
**Requirements**: UX-01, UX-02, CORE-01, SEC-01
**Success Criteria** (what must be TRUE):
  1. The dashboard KPI strip, session list, and aside communicate all key information at a glance without scrolling on a 1080p screen — composition feels intentional, not auto-generated
  2. The login page uses a centered card with clear branding, prominent CTA, and no unnecessary chrome — visually indistinguishable from a Clerk or Linear auth page in quality
  3. Every complex dashboard element (KPI delta, quorum indicator, session status badge) has a tooltip that explains itself on hover — no guided tour needed
  4. A developer opening the dashboard side-by-side with the pre-v4.2 screenshot can immediately point to 5 visual improvements without prompting
  5. Dark mode on both pages is visually equivalent in quality to light mode — no washed-out tokens or broken contrast
**Plans:** 3/3 plans complete
Plans:
- [ ] 35-01-PLAN.md — Dashboard visual redesign (KPI cards, session cards, aside shortcuts, tooltips)
- [ ] 35-02-PLAN.md — Login page visual redesign (background, branding, button, trust signal, errors)
- [ ] 35-03-PLAN.md — Visual verification checkpoint (human review of both pages)

### Phase 36: Session Creation Flow
**Goal**: Users can create a session and navigate to their hub through pages that feel as polished as Linear's issue creation flow — clear progression, generous whitespace, field-level guidance
**Depends on**: Phase 35
**Requirements**: CORE-02, CORE-04
**Success Criteria** (what must be TRUE):
  1. The wizard stepper is visually prominent and communicates progress — active/complete/pending states are immediately distinguishable at a glance
  2. Each wizard form field has a tooltip or inline label explaining its purpose — no field is ambiguous to a first-time user
  3. The Hub's stepper sidebar, quorum bar, and session checklist are visually hierarchical — the user knows exactly what step they are on and what is blocking them
  4. Micro-interactions on wizard fields (focus ring, autosave indicator) are smooth and feel premium, not jarring
  5. Both pages produce a clear before/after screenshot contrast — whitespace, typography weight, and component spacing are visibly improved
**Plans:** 3/3 plans complete
Plans:
- [x] 36-01-PLAN.md — Wizard visual redesign (stepper hierarchy, form labels, tooltips, sections, micro-interactions)
- [x] 36-02-PLAN.md — Hub visual redesign (identity badges, status bar, stepper, quorum hero, checklist, action CTA)
- [x] 36-03-PLAN.md — Visual verification checkpoint (human review of both pages)

### Phase 37: Live Session Conduct
**Goal**: Operators and voters experience pages optimized for high-stakes, real-time use — dense information for the operator, focused simplicity for the voter
**Depends on**: Phase 36
**Requirements**: CORE-03, SEC-05
**Success Criteria** (what must be TRUE):
  1. The operator console sidebar agenda, status bar, and live panel are visually distinct zones — an operator can identify each zone instantly without reading labels
  2. Action tooltips on every operator button explain what it does and its current state (e.g., "Vote open — click to close") — no ambiguity under pressure
  3. The voter ballot on mobile renders with 72px+ touch targets, clear candidate separation, and a visible confirmation state — a first-time voter can cast without hesitation
  4. Live SSE indicators (connectivity dot, delta badge) are visually obvious but do not compete with the primary action area
  5. Both pages look dramatically improved versus the pre-v4.2 version when screenshot side-by-side
**Plans:** 3/3 plans complete
Plans:
- [x] 37-01-PLAN.md — Operator console redesign (compact status bar, agenda card items, tooltips, live panel, guidance panels)
- [x] 37-02-PLAN.md — Voter ballot redesign (1x4 stacked buttons, confirmation checkmark, waiting pulse, press feedback)
- [x] 37-03-PLAN.md — Visual verification checkpoint (human review of both pages)

### Phase 38: Results & History
**Goal**: Post-session, analytics, and meeting list pages present data with the clarity and density of Stripe's dashboard — results are readable, trends are scannable, history is navigable
**Depends on**: Phase 37
**Requirements**: CORE-05, DATA-05, DATA-06
**Success Criteria** (what must be TRUE):
  1. The post-session stepper and result cards show vote outcomes with clear visual verdicts (ADOPTE/REJETE) — readable from across a meeting room at 1080p
  2. The analytics page KPI cards and chart layout are visually balanced — no chart is clipped, truncated, or misaligned at standard viewport widths
  3. Metric tooltips on analytics KPIs explain what each number measures — no jargon left unexplained
  4. The meetings list session cards show status, date, and quorum in a scannable format — an admin can find a specific session in under 3 seconds
  5. The post-session archival progression steps communicate completion state — a user knows exactly whether archival is complete or pending
**Plans:** 3/3 plans complete
Plans:
- [x] 38-01-PLAN.md — Post-session redesign (pill stepper with glow, verdict badge prominence, ag-tooltip on steps, section spacing)
- [x] 38-02-PLAN.md — Analytics + Meetings redesign (KPI card migration, chart subtitles, period pills, session-card migration, hover-reveal CTAs)
- [x] 38-03-PLAN.md — Visual verification checkpoint (human review of all three pages)

### Phase 39: Admin Data Tables
**Goal**: Members, Users, Audit log, and Archives pages handle dense tabular data with the polish of Linear's table views — readable density, actionable rows, visible filters
**Depends on**: Phase 38
**Requirements**: DATA-03, DATA-04, DATA-01, DATA-02
**Success Criteria** (what must be TRUE):
  1. The members table/card view clearly differentiates member roles with visual indicators — an admin can see role distribution at a glance without opening each row
  2. Action tooltips on member and user row actions (edit, revoke, promote) explain what each icon does — no icon is a mystery
  3. The audit log toolbar (filters, search, date range) is visible and usable without instructions — timeline and table views are switchable with a single clear control
  4. The archives page card/table view with pagination lets an admin locate a specific past session with filters alone — no manual scrolling through many pages
  5. All four pages maintain visual consistency with each other — they share the same table density, header style, and action pattern
**Plans:** 3/3 plans complete
Plans:
- [x] 39-01-PLAN.md — Members + Users redesign (stats bar elevation, circular avatars, role panel, pill filters, hover-reveal actions, column headers)
- [x] 39-02-PLAN.md — Audit + Archives redesign (inline detail expansion, ag-tooltip headers, filter counts, KPI cleanup, status filters, hover-reveal cards)
- [ ] 39-03-PLAN.md — Visual verification checkpoint (human review of all four pages)

### Phase 40: Configuration Cluster
**Goal**: Settings, email templates, and help pages give administrators a trustworthy, Notion-quality configuration experience — clean section cards, clear explanations, no configuration anxiety
**Depends on**: Phase 39
**Requirements**: CORE-06, SEC-04, SEC-03
**Success Criteria** (what must be TRUE):
  1. The settings sidenav is visually scannable — an admin can find the right settings section in one look without reading every label
  2. Every settings field or toggle has an explanation tooltip that describes the impact of changing it — no setting is a black box
  3. The email template editor and preview panels are laid out in a clear two-pane grid — the user sees the effect of their edits without switching views
  4. The Help/FAQ accordion and category navigation are visually organized — a user can find an answer without scrolling through unrelated content
  5. All three pages feel part of the same visual system — section cards, typography, and spacing are consistent across settings, email editor, and help
**Plans**: TBD

### Phase 41: Public & Utility Pages
**Goal**: Public-facing pages (Landing, Projector display, Report/PV, utilities) make a strong first impression on external users and close off every remaining visual gap in the app
**Depends on**: Phase 40
**Requirements**: SEC-02, SEC-06, SEC-07, SEC-08
**Success Criteria** (what must be TRUE):
  1. The landing page has a clear hero, feature highlights, and a prominent CTA — a visitor understands the product and its purpose within 10 seconds without reading every word
  2. The public/projector display renders vote results in large, high-contrast typography suitable for display on a room screen — legible from 5 meters at 1080p
  3. The Report/PV page shows a clean preview with a visible download CTA and a timeline of generation steps — a user knows the document status without ambiguity
  4. The Trust/Validate/Doc utility pages are visually consistent with the rest of the app — no page looks like it was forgotten
  5. Every page in the application now meets top 1% visual quality — no page remains with the pre-v4.2 visual debt
**Plans**: TBD

## Progress

**Execution Order:** 35 → 36 → 37 → 38 → 39 → 40 → 41

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 35. Entry Points | 3/3 | Complete    | 2026-03-19 |
| 36. Session Creation Flow | 3/3 | Complete    | 2026-03-20 |
| 37. Live Session Conduct | 3/3 | Complete    | 2026-03-20 |
| 38. Results & History | 3/3 | Complete    | 2026-03-20 |
| 39. Admin Data Tables | 3/3 | Complete   | 2026-03-20 |
| 40. Configuration Cluster | 0/TBD | Not started | - |
| 41. Public & Utility Pages | 0/TBD | Not started | - |
