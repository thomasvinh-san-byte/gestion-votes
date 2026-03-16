---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: milestone
status: verifying
stopped_at: Completed 15-tech-debt-cleanup-01-PLAN.md
last_updated: "2026-03-16T10:29:58.163Z"
last_activity: 2026-03-13 -- Phase 10 verified, 12/12 must-haves passed
progress:
  total_phases: 18
  completed_phases: 14
  total_plans: 39
  completed_plans: 39
  percent: 100
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-12)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v2.0 UI Redesign -- All 10 phases complete

## Current Position

Phase: 10 of 10 (Live Session Views) — VERIFIED
Plan: 20 of 20 complete
Status: All phases verified
Last activity: 2026-03-13 -- Phase 10 verified, 12/12 must-haves passed

Progress: [##########] 100%

## Performance Metrics

**Velocity:**
- Total plans completed: 0 (v2.0 milestone)
- Average duration: -
- Total execution time: 0 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| - | - | - | - |

**Recent Trend:**
- Last 5 plans: n/a
- Trend: n/a
| Phase 04-design-tokens-theme P01 | 6 | 2 tasks | 2 files |
| Phase 04-design-tokens-theme P02 | 30 | 2 tasks | 1 files |
| Phase 04-design-tokens-theme P02 | 30 | 2 tasks | 1 files |
| Phase 05-shared-components P02 | 15 | 2 tasks | 4 files |
| Phase 05-shared-components P03 | 12 | 2 tasks | 2 files |
| Phase 05-shared-components P01 | 15 | 2 tasks | 3 files |
| Phase 06-layout-navigation P01 | 72 | 2 tasks | 2 files |
| Phase 06-layout-navigation P02 | 15 | 3 tasks | 20 files |
| Phase 06-layout-navigation P03 | 8 | 2 tasks | 5 files |
| Phase 07 P02 | 2 | 2 tasks | 2 files |
| Phase 07-dashboard-sessions P01 | 3 | 2 tasks | 3 files |
| Phase 07 P03 | 7 | 2 tasks | 2 files |
| Phase 08-session-wizard-hub P01 | 460 | 2 tasks | 3 files |
| Phase 08-session-wizard-hub P02 | 10 | 2 tasks | 3 files |
| Phase 08-session-wizard-hub P03 | 2 | 2 tasks | 4 files |
| Phase 09-operator-console P01 | 12 | 2 tasks | 4 files |
| Phase 09-operator-console P02 | 8 | 2 tasks | 2 files |
| Phase 09 P03 | 5min | 2 tasks | 3 files |
| Phase 10-live-session-views P01 | 2min | 2 tasks | 3 files |
| Phase 10-live-session-views P02 | 5min | 2 tasks | 4 files |
| Phase 11-post-session-records P02 | 8 | 2 tasks | 2 files |
| Phase 11-post-session-records P01 | 6 | 2 tasks | 3 files |
| Phase 11-post-session-records P03 | 5 | 2 tasks | 2 files |
| Phase 15-analytics-users-settings-help P04 | 1 | 3 tasks | 1 files |
| Phase 15-analytics-users-settings-help P01 | 3 | 4 tasks | 2 files |
| Phase 15-analytics-users-settings-help PP02 | 10 | 4 tasks | 3 files |
| Phase 15-analytics-users-settings-help P03 | 10 | 5 tasks | 4 files |
| Phase 14-wizard-hub-dashboard-api P01 | 5 | 2 tasks | 4 files |
| Phase 14-wizard-hub-dashboard-api P02 | 5 | 2 tasks | 2 files |
| Phase 15-tech-debt-cleanup P01 | 3 | 2 tasks | 2 files |

## Milestone History

### v1.5 — E2E Coverage Expansion & Release (COMPLETE)

| Phase | Status | Summary |
|-------|--------|---------|
| 1. Operator & Dashboard E2E | done | 15 tests across 2 new specs |
| 2. Report, Validate & Archives E2E | done | 14 tests across 3 new specs |
| 3. Version Bump | done | 1.1.0 -> 1.5.0, SW cache v1.5 |

### v1.4 — Test Coverage & Final Polish (COMPLETE)

3 phases: controller tests, Permissions-Policy, dead code audit.

### v1.3, v1.2, v1.1 (COMPLETE)

Code quality, security hardening, post-audit hardening. All shipped.

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- v2.0: Wireframe v3.19.2 is the source of truth for all UI targets
- v2.0: Align existing code, don't rewrite from scratch
- v2.0: Phase numbering continues from v1.5 (start at 4)
- [Phase 04-design-tokens-theme]: Keep --radius-md and --radius-xl usages in component styles: Phase 6 handles component CSS, not :root token removal
- [Phase 04-design-tokens-theme]: Add --color-surface-alt as explicit token alongside --color-bg-subtle for semantic elevation clarity
- [Phase 04-design-tokens-theme]: --transition: 150ms ease added as wireframe alias; granular duration/ease tokens retained for flexibility
- [Phase 04-design-tokens-theme]: Dark theme --color-surface-alt: #1B2030 added for token API parity with light theme
- [Phase 04-design-tokens-theme]: Sidebar button elements require explicit background:transparent in dark theme to prevent UA stylesheet bleed-through
- [Phase 04-design-tokens-theme]: Dark theme --color-surface-alt: #1B2030 added for token API parity with light theme
- [Phase 04-design-tokens-theme]: Sidebar button elements require explicit background:transparent to prevent UA stylesheet bleed-through in dark theme
- [Phase 05-shared-components]: ag-popover uses --color-surface-raised (not --color-surface) since popovers are elevated UI elements
- [Phase 05-shared-components]: CSS-only .progress-bar pattern at design-system level; ag-mini-bar handles multi-segment charts
- [Phase 05-shared-components]: .empty-state-description uses --color-text-muted not --color-text-secondary (secondary is near-black #151510)
- [Phase 05-shared-components]: Session expiry warning uses CSS class (session-expiry-warning) with two-button UX (Rester connecte + Deconnexion) replacing single inline-styled Prolonger button
- [Phase 05-shared-components]: Tour bubble uses --color-surface-raised and spotlight uses color-mix() for dark theme compatibility without explicit overrides
- [Phase 05-shared-components]: ag-confirm: inline SVG icons replace icon sprite pattern for critical overlay UI
- [Phase 05-shared-components]: ag-toast: static show() only sets duration attribute when caller explicitly passes value, type-based defaults applied in connectedCallback
- [Phase 05-shared-components]: warn variant alias added to ag-confirm for ergonomic API parity
- [Phase 06-layout-navigation]: nav-badge uses margin-left:auto (flex flow) for visible state, not position:absolute, for correct layout in expanded sidebar
- [Phase 06-layout-navigation]: nav-badge visibility driven by [data-count] attribute selector — no JS needed to show/hide
- [Phase 06-layout-navigation]: public.htmx.html and vote.htmx.html get app-footer with display:none (full-screen/voter layouts)
- [Phase 06-layout-navigation]: Footer pattern: placed inside .app-shell after </main> as static HTML (not JS injection)
- [Phase 06-layout-navigation]: Removed duplicate role=banner from hub-identity div — only header should carry banner role
- [Phase 06-layout-navigation]: Removed duplicate role=main from hub-action div — only main element should carry main role
- [Phase 07-dashboard-sessions]: Kept meeting-card-status CSS for JS-rendered status tags (used by Plan 03)
- [Phase 07-dashboard-sessions]: Responsive breakpoints: 1024px hides quorum/resolutions, 640px shows only date
- [Phase 07-dashboard-sessions]: Dynamic status dot/tag colors kept as inline styles (only acceptable inline styles in JS)
- [Phase 07-dashboard-sessions]: urgentCard.hidden=true replaces style.display='none' for semantic HTML visibility
- [Phase 07-dashboard-sessions]: Used existing api() global function (not Utils.apiGet) to match codebase conventions
- [Phase 07-dashboard-sessions]: Calendar events show inline as links with overflow badge for 3+ sessions per day
- [Phase 07-dashboard-sessions]: Popover menus use ag-popover web component with fixed positioning near button
- [Phase 08-session-wizard-hub]: wizard.css created as dedicated CSS file for wizard page (not extending meetings.css) per one-CSS-per-page pattern
- [Phase 08-session-wizard-hub]: Step 5 confirmation screen removed from wizard - redirect to hub.htmx.html is the confirmation per CONTEXT.md decision
- [Phase 08-session-wizard-hub]: hub classes kept in BOTH operator.css and hub.css because operator.htmx.html uses 39+ hub- class names
- [Phase 08-session-wizard-hub]: [Phase 08-session-wizard-hub]: hubPreviewBtn + hub-details-body use hidden attribute instead of style.display for semantic HTML
- [Phase 08-session-wizard-hub]: [Phase 08-session-wizard-hub]: HUB_STEPS colors updated from wireframe legacy tokens to design-system tokens
- [Phase 08-session-wizard-hub]: window.print() used for PDF export — browser-native, no library needed for vanilla JS app
- [Phase 08-session-wizard-hub]: hub.js loadData() falls back to demo data silently (console.warn only), preserving dev experience without backend
- [Phase 09-operator-console]: op-exec-header uses green border (color-success) for live state per wireframe v3.19.2
- [Phase 09-operator-console]: op-kpi-strip dedicated class: NOT extending exec-kpi-strip or hub-kpi, design independence from hub layout
- [Phase 09-operator-console]: viewExec hybrid structure: op-exec-header inline, content div lazy-loaded — header renders immediately on mode switch
- [Phase 09-operator-console]: op-split overridden from CSS grid (3-col) to flex (2-panel) per wireframe v3.19.2 — old exec-grid 3-column layout removed
- [Phase 09-operator-console]: op-agenda-circle: 3-state status circles (voted=green, current=pulsing blue, pending=empty) for sidebar agenda list
- [Phase 09]: Quorum modal uses direct DOM overlay instead of ag-confirm for simpler 3-button layout
- [Phase 10-live-session-views]: Bar fill uses style.width (%) not style.height (px) — percentage-based width works directly with CSS transition: width
- [Phase 10-live-session-views]: Footer utility classes added to public.css since public page only loads design-system.css + public.css (not operator.css)
- [Phase Phase 10-live-session-views]: apiPost returns response.json() directly (not {body, status}), result.ok is the correct check for attendances_upsert response
- [Phase Phase 10-live-session-views]: _isAbsent guard added to all setVoteButtonsEnabled enable-paths to enforce legal absent-voter restriction
- [Phase 11-post-session-records]: audit.htmx.html follows archives.htmx.html app shell pattern (same script chain, drawer, footer)
- [Phase 11-post-session-records]: Severity dots use 4 semantic classes (info/success/danger/warning) shared between table .audit-severity-dot and timeline .audit-timeline-dot
- [Phase 11-post-session-records]: Shared ps-footer-nav replaces all four per-panel ps-actions blocks
- [Phase 11-post-session-records]: eIDAS chips use existing .chip/.chip.active pattern from design system
- [Phase 11-post-session-records]: audit.js uses IIFE + var keyword per project conventions (hub.js reference pattern)
- [Phase 11-post-session-records]: archives.js pagination added at perPage=5 with filteredArchives state variable for pagination rerenders after filter changes
- [Phase 15-analytics-users-settings-help]: ROADMAP.md Phase 12 and 13 marked complete with 4/4 plans and 2026-03-15 completion date
- [Phase 15-analytics-users-settings-help]: Trend arrow classes (.trend-up/.trend-down/.trend-stable) use ::before content injection for arrow characters
- [Phase 15-analytics-users-settings-help]: Print CSS forces display:block on all .tab-content so PDF shows all sections regardless of active tab
- [Phase 15-analytics-users-settings-help]: renderUsersTable replaces div.user-row list with proper <tr> rows — same btn-edit-user delegated handler reused
- [Phase 15-analytics-users-settings-help]: getAvatarColor uses name hash modulo 8-color palette for deterministic consistent avatar colors
- [Phase 15-analytics-users-settings-help]: theme-init.js receives inline IIFE for text size and high contrast applied before first paint on every page
- [Phase 15-analytics-users-settings-help]: High contrast toggle uses data-high-contrast attribute on html element for CSS variable overrides
- [Phase 15-analytics-users-settings-help]: Text size persisted as symbolic names (normal/large/xlarge) in localStorage, mapped to CSS classes at runtime
- [Phase 14-wizard-hub-dashboard-api P01]: api('/api/v1/meetings', payload) without explicit 'POST' arg — api() infers POST when data != null
- [Phase 14-wizard-hub-dashboard-api P01]: Shared.showToast swaps argument order: caller passes (message, type), bridge forwards to AgToast.show(type, message)
- [Phase 14-wizard-hub-dashboard-api P01]: ag-toast.js loaded with type='module' because it uses ES module export syntax
- [Phase 14-wizard-hub-dashboard-api]: hub.js: Use /api/v1/wizard_status?meeting_id=X (not /api/v1/meetings.php?id=X) as single-meeting endpoint
- [Phase 14-wizard-hub-dashboard-api]: dashboard.js: Compute KPI values from meetings array by filtering on status — avoids depending on non-existent upstream_count/live_count fields
- [Phase 15-tech-debt-cleanup]: icon-pause uses rect elements (two vertical bars, stroke style) not polygon fill — consistent with existing Lucide stroke pattern
- [Phase 15-tech-debt-cleanup]: ?tab=notifications removed from notification Voir tout link — admin.js never reads tab param, removal simpler than adding dead URL-parsing code

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Open Issues

None — wizard.js meeting creation API wired in Phase 14 Plan 01.

## Session Continuity

Last session: 2026-03-16T10:29:58.160Z
Stopped at: Completed 15-tech-debt-cleanup-01-PLAN.md
Last session: 2026-03-13T15:00:00.000Z
Stopped at: Completed 14-wizard-hub-dashboard-api Plan 01 (SUMMARY created)
Resume file: None
