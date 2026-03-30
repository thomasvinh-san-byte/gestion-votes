# Roadmap: AG-VOTE

## Milestones

- ✅ **v1.x Foundations** - Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign** - Phases 4-15 (shipped 2026-03-16)
- ✅ **v3.0 Session Lifecycle** - Phases 16-24 (shipped 2026-03-18)
- ✅ **v4.0 Clarity & Flow** - Phases 25-29 (shipped 2026-03-18)
- ✅ **v4.1 Design Excellence** - Phases 30-34 (shipped 2026-03-19)
- ✅ **v4.2 Visual Redesign** - Phases 35-41.5 (shipped 2026-03-20)
- ✅ **v4.3 Ground-Up Rebuild** - Phases 42-48 (shipped 2026-03-22)
- 🚧 **v4.4 Complete Rebuild** - Phases 49-51 (in progress)

---

## ✅ v4.3 Ground-Up Rebuild (Shipped: 2026-03-22)

<details>
<summary>7 phases, 14 plans — dashboard, login, wizard, operator, hub, settings/admin rebuilt from scratch</summary>

**Milestone Goal:** Rebuild every critical page from scratch — HTML+CSS+JS together in one commit, fix all v4.2 regressions, wire backend properly, achieve genuine top 1% design quality.

**Approach:** Read existing JS before touching HTML. Rewrite HTML+CSS. Update JS if DOM changes. Verify backend connections. Test in browser before marking done. No broken intermediate states.

### Phases

- [x] **Phase 42: Stabilization** - Fix all v4.2 regressions before any rebuild work begins
- [x] **Phase 43: Dashboard Rebuild** - Complete HTML+CSS+JS rewrite, KPIs and session list wired to backend (completed 2026-03-20)
- [x] **Phase 44: Login Rebuild** - Complete HTML+CSS rewrite, auth flow wired, top 1% entry point (completed 2026-03-20)
- [x] **Phase 45: Wizard Rebuild** - Complete HTML+CSS+JS rewrite, all 4 steps wired, form submissions verified (completed 2026-03-22)
- [x] **Phase 46: Operator Console Rebuild** - Complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional (completed 2026-03-22)
- [x] **Phase 47: Hub Rebuild** - Complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional (completed 2026-03-22)
- [x] **Phase 48: Settings/Admin Rebuild** - Complete HTML+CSS+JS rewrite, all settings save, admin KPIs wired (completed 2026-03-22)

### Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 42. Stabilization | 1/1 | Complete | 2026-03-20 |
| 43. Dashboard Rebuild | 2/2 | Complete | 2026-03-20 |
| 44. Login Rebuild | 2/2 | Complete | 2026-03-20 |
| 45. Wizard Rebuild | 2/2 | Complete | 2026-03-22 |
| 46. Operator Console Rebuild | 2/2 | Complete | 2026-03-22 |
| 47. Hub Rebuild | 3/3 | Complete | 2026-03-22 |
| 48. Settings/Admin Rebuild | 2/2 | Complete | 2026-03-22 |

</details>

---

## 🚧 v4.4 Complete Rebuild (In Progress)

**Milestone Goal:** Ground-up rebuild of all remaining pages (13 pages not rebuilt in v4.3) to the same top 1% quality standard — HTML+CSS+JS from scratch, backend wiring verified, browser tested.

**Approach:** Same as v4.3 — read existing JS first, rewrite HTML+CSS from scratch, update JS for new DOM, verify all API connections, browser test before marking done. No mock data. No dead endpoints.

**Target pages:**
- Secondary: postsession, analytics, meetings, archives, audit, members, users, vote/ballot
- Utility: help, email-templates, public/projector, report/PV, trust/validate/docs

## Phases

- [ ] **Phase 49: Secondary Pages Part 1** - Ground-up rebuild of postsession, analytics, meetings, archives — all API connections verified
- [ ] **Phase 50: Secondary Pages Part 2** - Ground-up rebuild of audit, members, users, vote/ballot — all API connections verified
- [ ] **Phase 51: Utility Pages** - Ground-up rebuild of help, email-templates, public, report/PV, trust/validate/docs — print and projection layouts verified

## Phase Details

### Phase 49: Secondary Pages Part 1
**Goal**: Postsession, analytics, meetings list, and archives are fully rebuilt — each page has new HTML+CSS+JS from scratch, no legacy structure remaining, all data connections live
**Depends on**: Phase 48 (v4.3 complete)
**Requirements**: REB-01, REB-02, REB-03, REB-04, WIRE-01, WIRE-02
**Success Criteria** (what must be TRUE):
  1. The post-session stepper advances through all 4 steps (results, consolidation, PV PDF, archival) and each step triggers the correct backend operation — no dead buttons
  2. The analytics page displays real statistics (vote counts, participation rates, trends) fetched from the backend — chart area and KPI grid render with live data
  3. The meetings list renders session cards with correct status badges and filters — a user can filter by status and navigate to any session
  4. The archives table has a sticky header, working search, and pagination — a user can find a past session and reach its detail view
  5. No JS console errors on any of the 4 pages; all form submissions (PV generation, archival confirmation) persist data correctly
**Plans**: 3 plans
Plans:
- [ ] 49-01-PLAN.md — Postsession page ground-up rebuild (HTML+CSS+JS wiring)
- [ ] 49-02-PLAN.md — Analytics page ground-up rebuild (HTML+CSS+JS wiring)
- [ ] 49-03-PLAN.md — Meetings + Archives pages ground-up rebuild (HTML+CSS+JS wiring)

### Phase 50: Secondary Pages Part 2
**Goal**: Audit log, members management, users management, and vote/ballot are fully rebuilt — CRUD operations functional, CSV exports working, ballot flow end-to-end verified
**Depends on**: Phase 49
**Requirements**: REB-05, REB-06, REB-07, REB-08, WIRE-01, WIRE-02
**Success Criteria** (what must be TRUE):
  1. The audit log displays events in both timeline and table view, and the CSV export downloads a valid file containing the displayed records
  2. The members page supports card and table view toggle, CSV import creates real member records, and role assignment persists after page reload
  3. The users table loads with pagination, and an admin can create, edit, and deactivate a user — changes reflect immediately without a full page reload
  4. A voter on the ballot page can read the motion, cast a vote, receive optimistic feedback within 50ms, and view the PDF resolution document — the vote is recorded in the database
  5. No JS console errors on any of the 4 pages; all API connections point to real endpoints with no mock data
**Plans**: TBD

### Phase 51: Utility Pages
**Goal**: Help/FAQ, email templates, public/projector display, report/PV, and trust/validate/docs are fully rebuilt — print layout correct, projection display optimized, all interactions functional
**Depends on**: Phase 50
**Requirements**: UTL-01, UTL-02, UTL-03, UTL-04, UTL-05, WIRE-01, WIRE-02
**Success Criteria** (what must be TRUE):
  1. The help/FAQ page renders all accordion sections with correct spacing and the search field filters visible FAQ items in real time — no JS errors
  2. The email templates editor shows a live preview panel that updates as the user edits template content — saved templates persist after page reload
  3. The public/projector display renders results in a projection-optimized layout at full screen — result cards are legible from a distance with large typography
  4. The report/PV page renders a print-ready layout at 880px — triggering browser print produces a clean output with no navigation chrome or broken layouts
  5. The trust/validate/docs pages display correct verification status fetched from the backend — no hardcoded or mock status values
**Plans**: TBD

## Progress

**Execution Order:** 49 → 50 → 51

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 49. Secondary Pages Part 1 | 2/3 | In Progress|  |
| 50. Secondary Pages Part 2 | 0/TBD | Not started | - |
| 51. Utility Pages | 0/TBD | Not started | - |
