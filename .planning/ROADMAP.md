# Roadmap: AG-VOTE v4.3 "Ground-Up Rebuild"

## Milestones

- ✅ **v1.x Foundations** - Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign** - Phases 4-15 (shipped 2026-03-16)
- ✅ **v3.0 Session Lifecycle** - Phases 16-24 (shipped 2026-03-18)
- ✅ **v4.0 Clarity & Flow** - Phases 25-29 (shipped 2026-03-18)
- ✅ **v4.1 Design Excellence** - Phases 30-34 (shipped 2026-03-19)
- ✅ **v4.2 Visual Redesign** - Phases 35-41.5 (shipped 2026-03-20)
- 🚧 **v4.3 Ground-Up Rebuild** - Phases 42-48 (in progress)

---

## 🚧 v4.3 Ground-Up Rebuild (In Progress)

**Milestone Goal:** Rebuild every critical page from scratch — HTML+CSS+JS together in one commit, fix all v4.2 regressions, wire backend properly, achieve genuine top 1% design quality.

**Approach:** Read existing JS before touching HTML. Rewrite HTML+CSS. Update JS if DOM changes. Verify backend connections. Test in browser before marking done. No broken intermediate states.

## Phases

- [x] **Phase 42: Stabilization** - Fix all v4.2 regressions before any rebuild work begins
- [x] **Phase 43: Dashboard Rebuild** - Complete HTML+CSS+JS rewrite, KPIs and session list wired to backend (completed 2026-03-20)
- [x] **Phase 44: Login Rebuild** - Complete HTML+CSS rewrite, auth flow wired, top 1% entry point (completed 2026-03-20)
- [x] **Phase 45: Wizard Rebuild** - Complete HTML+CSS+JS rewrite, all 4 steps wired, form submissions verified (completed 2026-03-22)
- [x] **Phase 46: Operator Console Rebuild** - Complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional (completed 2026-03-22)
- [x] **Phase 47: Hub Rebuild** - Complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional (completed 2026-03-22)
- [ ] **Phase 48: Settings/Admin Rebuild** - Complete HTML+CSS+JS rewrite, all settings save, admin KPIs wired

## Phase Details

### Phase 42: Stabilization
**Goal**: All v4.2 regressions are eliminated so subsequent rebuilds start from a working baseline
**Depends on**: Nothing (first phase of milestone)
**Requirements**: FIX-01, FIX-02
**Success Criteria** (what must be TRUE):
  1. Every page renders without broken layouts or misaligned elements that were introduced in v4.2
  2. All JavaScript event handlers (modals, tabs, dropdowns, HTMX triggers) that were broken by v4.2 HTML restructuring fire correctly
  3. No console errors caused by missing DOM targets or changed selectors on any page
  4. A developer can navigate the full session lifecycle (dashboard → hub → operator → voter) without hitting a JS exception
**Plans**: 1 plan

Plans:
- [x] 42-01-PLAN.md — Fix trust page kpiMotions crash and restore missing KPI stat elements

### Phase 43: Dashboard Rebuild
**Goal**: The dashboard is a fully rebuilt, top 1% page — new HTML structure, new CSS, JS verified, KPIs and session list wired to live backend data
**Depends on**: Phase 42
**Requirements**: REB-01, WIRE-01
**Success Criteria** (what must be TRUE):
  1. Dashboard KPI cards display live counts (sessions, members, votes) fetched from the real API — no mock data
  2. Session list renders with correct status badges, dates, and action buttons pointing to valid routes
  3. The page layout is horizontal-first, all interactive elements (filters, CTAs) are functional
  4. No JS console errors on page load; all DOM selectors resolve against the new HTML structure
**Plans**: 2 plans

Plans:
- [x] 43-01-PLAN.md — Rewrite dashboard HTML from scratch and rewrite all dashboard CSS in pages.css
- [ ] 43-02-PLAN.md — Fix dashboard.js urgent banner logic and browser verification

### Phase 44: Login Rebuild
**Goal**: The login page is a fully rebuilt, top 1% entry point — new HTML+CSS, auth flow end-to-end wired, field validation working
**Depends on**: Phase 42
**Requirements**: REB-02, WIRE-01
**Success Criteria** (what must be TRUE):
  1. A user can enter email and password, submit the form, and land on the dashboard with a valid session — no broken redirects
  2. Field validation messages appear inline (empty fields, wrong credentials) without a full page reload
  3. The login page passes visual comparison against Stripe/Clerk reference quality — clean, centered, no legacy artifacts
  4. The page renders correctly with no layout breakage at 1024px+ viewport widths
**Plans**: 2 plans

Plans:
- [ ] 44-01-PLAN.md — Rewrite login HTML+CSS from scratch with floating labels, gradient orb, 420px card
- [ ] 44-02-PLAN.md — Update login.js selectors for new DOM, add floating label JS support, browser verify

### Phase 45: Wizard Rebuild
**Goal**: The session creation wizard is fully rebuilt — all 4 steps fit the viewport, form submissions create real sessions, the stepper is functional, horizontal field layout throughout
**Depends on**: Phase 42
**Requirements**: REB-03, WIRE-01, WIRE-03
**Success Criteria** (what must be TRUE):
  1. A user can complete all 4 wizard steps and create a real session — the new session appears in the database and on the dashboard
  2. The stepper (named steps, active/complete/pending states) renders correctly and navigates between steps without page reload
  3. Each wizard step fits within the viewport at 1024px without vertical overflow — no scrolling required to reach the next button
  4. All form fields use horizontal layout where applicable; validation errors appear inline next to the relevant field
  5. Form submission failures (network error, validation) display a user-visible error message without losing entered data
**Plans**: 2 plans

Plans:
- [ ] 45-01-PLAN.md — Rewrite wizard HTML+CSS from scratch with 900px track, horizontal fields, slide transition CSS
- [ ] 45-02-PLAN.md — Update wizard.js for new DOM, add slide transitions and error banners, browser verify

### Phase 46: Operator Console Rebuild
**Goal**: The operator console is fully rebuilt — SSE connection live, vote panel functional, agenda sidebar operational, all action buttons wired with tooltips
**Depends on**: Phase 42
**Requirements**: REB-04, WIRE-01, WIRE-02
**Success Criteria** (what must be TRUE):
  1. The SSE connection establishes on page load and the live indicator shows "connected" — vote events update the display in real time without manual refresh
  2. An operator can open a vote, observe live vote counts update via SSE, and close the vote — the result is recorded correctly
  3. The agenda sidebar lists all motions; clicking a motion loads it into the main panel
  4. All action buttons (open vote, close vote, end session) show tooltips explaining their state when disabled
  5. The delta badge increments correctly when new votes arrive over SSE
**Plans**: 2 plans

Plans:
- [ ] 46-01-PLAN.md — Rewrite operator HTML+CSS with two-panel layout, inlined exec content, vote card centerpiece
- [ ] 46-02-PLAN.md — Update all 6 JS modules for new DOM, delta badge 3s timer, browser verify

### Phase 47: Hub Rebuild
**Goal**: The session hub is fully rebuilt — session lifecycle actions wired to real data, quorum bar showing live attendance, checklist reflecting actual state
**Depends on**: Phase 42
**Requirements**: REB-05, WIRE-01
**Success Criteria** (what must be TRUE):
  1. The quorum bar displays the current attendance count and threshold from the real API — it updates when attendance changes
  2. The session checklist items (convocation sent, quorum reached, agenda locked) reflect the actual session state from the database
  3. Lifecycle action buttons (send convocation, open session, navigate to operator) trigger the correct backend operations and update the UI on success
  4. The hub displays the correct blocked reasons when a lifecycle step cannot proceed
**Plans**: 3 plans

Plans:
- [x] 47-01-PLAN.md — Rewrite hub HTML+CSS with hero card, two-column layout, 3-item vertical checklist, quorum bar card
- [x] 47-02-PLAN.md — Update hub.js for new DOM, fix dead convocations endpoint, add workflow check blocked reasons, browser verify
- [ ] 47-03-PLAN.md — Gap closure: extend wizard_status API with date/place/type fields, load motions from motions_for_meeting

### Phase 48: Settings/Admin Rebuild
**Goal**: The settings and admin pages are fully rebuilt — all settings persist correctly, admin KPIs load from real data, user management CRUD is functional
**Depends on**: Phase 42
**Requirements**: REB-06, WIRE-01
**Success Criteria** (what must be TRUE):
  1. All settings tabs (rules, communication, security, accessibility) save their values and the changes persist after page reload
  2. Admin KPI cards display real counts (total members, sessions, votes) from the backend — no silent failures
  3. User management table loads, and an admin can create, edit, and deactivate users — changes reflect immediately
  4. No JS console errors on any settings or admin page load
**Plans**: 2 plans

Plans:
- [ ] 48-01-PLAN.md — Rewrite settings + admin HTML+CSS from scratch with sidebar tabs and KPI card row
- [ ] 48-02-PLAN.md — Create admin_settings.php backend, update admin.js KPIs, browser verify both pages

## Progress

**Execution Order:** 42 → 43 → 44 → 45 → 46 → 47 → 48

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 42. Stabilization | 1/1 | Complete    | 2026-03-20 |
| 43. Dashboard Rebuild | 2/2 | Complete    | 2026-03-20 |
| 44. Login Rebuild | 2/2 | Complete    | 2026-03-20 |
| 45. Wizard Rebuild | 2/2 | Complete    | 2026-03-22 |
| 46. Operator Console Rebuild | 2/2 | Complete    | 2026-03-22 |
| 47. Hub Rebuild | 3/3 | Complete    | 2026-03-22 |
| 48. Settings/Admin Rebuild | 0/2 | Not started | - |
