# Milestones

## v5.1 Operational Hardening (Shipped: 2026-03-31)

**Phases completed:** 4 phases, 8 plans, 0 tasks

**Key accomplishments:**
- (none recorded)

---

## v5.0 Quality & Production Readiness (Shipped: 2026-03-30)

**Scope:** Achieve 90%+ test coverage, fix infrastructure bugs, harden Docker/CI pipeline, make AG-VOTE production-ready
**Phases:** 6 phases (52-57), 18 plans
**Requirements:** 29/29 complete
**Codebase:** 143 files changed, +27,020 / -17,886 lines

**Key accomplishments:**
1. Migration audit — eliminated all SQLite syntax from 23 migration files, added dry-run validation script with two-pass idempotency test
2. Docker hardening — healthcheck PORT runtime evaluation fix (sh -c wrapper), envsubst nginx template for read-only FS, structured JSON health endpoint with database/redis/filesystem checks
3. Unit tests batch 1 — 142 tests for 5 critical services (QuorumEngine, VoteEngine, ImportService, MeetingValidator, NotificationsService) with 385 assertions
4. Unit tests batch 2 — 91 tests for 5 remaining services + ResolutionDocumentController with 189 assertions
5. Coverage tooling — ControllerTestCase base class, 40 controller test files, Services 90.8%, Controllers 64.6% (structural limit), coverage-check.sh with threshold enforcement
6. Playwright E2E — all 18 specs updated for v4.3/v4.4 rebuilds, 143 Chromium + 17 mobile-chrome + 17 tablet tests passing, rate-limit-safe auth setup
7. CI/CD pipeline — 7 GitHub Actions jobs (validate, lint-js, migrate-check, coverage, build, e2e, integration) with proper dependency graph

---

## v4.4 Complete Rebuild (Shipped: 2026-03-30)

**Scope:** Ground-up rebuild of all remaining 13 pages to v4.3 quality standard
**Phases:** 3 phases (49-51), 10 plans
**Requirements:** 15/15 complete

**Key accomplishments:**
1. Postsession page — v4.3 header pattern, all 42 JS selectors verified, last hardcoded hex removed
2. Analytics page — verified + minor fix pass (already built from prior phase 41.5), missing CSS class added
3. Meetings + Archives — complete HTML+CSS rewrite, filter pills, KPI grids, modals, pagination, 67 JS selectors verified
4. Audit page — timeline + table views rebuilt, 30 DOM IDs verified, CSV export wired
5. Members page — 3-tab structure, 6-card KPI grid, 318 token usages, 41 JS selectors verified
6. Users page — role distribution cards, CRUD modal, 117 token usages, 31 JS selectors verified
7. Vote/ballot page — French data-choice attributes, PDF viewer, cast() API mapping, 343 token usages
8. Help/FAQ — v4.3 header, FAQ accordion with smooth animation, 25 items preserved
9. Email templates — v4.3 header, two-panel editor, 21 DOM IDs preserved
10. Public/Report/Trust/Validate/Docs — verified correct, print styles added, htmx vendor removed

---

## v4.3 Ground-Up Rebuild (Shipped: 2026-03-22)

**Phases completed:** 7 phases, 14 plans, 2 tasks

**Key accomplishments:**
- (none recorded)

---

## v4.2 Visual Redesign (Shipped: 2026-03-20)

**Phases completed:** 12 phases, 34 plans, 14 tasks

**Key accomplishments:**
- (none recorded)

---

## v4.1 Design Excellence (Shipped: 2026-03-19)

**Phases completed:** 10 phases, 34 plans, 0 tasks

**Key accomplishments:**
- (none recorded)

---

## v4.0 Clarity & Flow (Shipped: 2026-03-18)

**Scope:** Transform AG-VOTE into a self-explanatory, visually impressive application — zero training, officiel et confiance design language
**Phases:** 5 phases (25-29), 18 plans
**Timeline:** 1 day (2026-03-18)
**Requirements:** 55/55 complete
**Codebase:** 118 files changed, +14,955 / -1,170 lines

**Key accomplishments:**
1. PDF resolution documents — FilePond upload, authenticated serve endpoint, ag-pdf-viewer (inline/sheet/panel), SSE documentAdded events, Docker persistent volume
2. Guided UX layer — ag-empty-state Web Component (11 migrations), status-aware dashboard cards with lifecycle CTAs, 8 contextual help panels, 7 disabled button tooltips
3. Copropriété → generic AG vocabulary — 45+ occurrences across 21 files transformed, lot field removed, openKeyModal stub removed, PHPUnit weighted-vote regression test
4. Wizard UX overhaul — named stepper, optional steps, review card with "Modifier" links, 3 motion templates, progressive disclosure for voting power, autosave on blur
5. Hub enhancements — checklist blocked reasons, ag-quorum-bar with threshold tick, motions list with doc badges, convocation send with confirmation
6. Operator console live indicators — SSE connectivity (live/reconnecting/offline), delta vote badge (+N ▲), post-vote/end-of-agenda guidance panels
7. Voter full-screen ballot — data-vote-state machine, 72px stacked cards, optimistic feedback < 50ms, confirmation state, inline irreversibility notice
8. Result cards — collapsible details/summary, CSS-only bar charts, ADOPTÉ/REJETÉ verdict, threshold display
9. Design system — @layer (base/components/v4), 10 color-mix() derived tokens, dark mode parity, @starting-style animations, View Transitions
10. All-page CSS polish — 15 page CSS files audited, tokens replacing hardcoded colors, transitions capped ≤200ms, inline style audit

---

## v3.0 Session Lifecycle (Shipped: 2026-03-18)

**Phases completed:** 13 phases, 37 plans, 4 tasks

**Key accomplishments:**
- (none recorded)

---

## v2.0 UI Redesign (Shipped: 2026-03-16)

**Scope:** Align all pages and components with AG-Vote v3.19.2 "Acte Officiel" wireframe
**Phases:** 12 feature phases (4-13) + 3 gap closure phases (14-15), 37 plans
**Timeline:** 24 days (2026-02-21 → 2026-03-16)
**Requirements:** 54/54 complete
**Codebase:** 119 HTML/CSS/JS files, 59,330 LOC

**Key accomplishments:**
1. Design system with 64 CSS tokens, dark/light theme switching
2. Component library: modal, toast, confirm dialog, popover, progress bar, guided tour, session banner
3. App shell: sidebar rail/expand, header with search/notifications, mobile bottom nav, ARIA landmarks
4. Full session lifecycle: dashboard KPIs, session list/calendar, 4-step create wizard, session hub
5. Operator console: live KPI strip, resolution sub-tabs, agenda sidebar, quorum modal, P/F shortcuts
6. Post-session workflow: 4-step stepper, archives with pagination, audit log with table/timeline + CSV export
7. Statistics page with KPI trends, charts, and PDF export
8. Users management with role panel, avatar table, and pagination
9. Settings with 4 tabs (rules, communication, security, accessibility) including text size and high contrast
10. Help/FAQ with category accordion and 9 guided tour launcher cards

---

