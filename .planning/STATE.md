---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: milestone
status: planning
stopped_at: Completed 08-session-wizard-hub-02-PLAN.md
last_updated: "2026-03-13T07:14:19.029Z"
last_activity: 2026-03-12 -- Roadmap created for v2.0 milestone (10 phases, 74 requirements)
progress:
  total_phases: 10
  completed_phases: 5
  total_plans: 14
  completed_plans: 14
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-12)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v2.0 UI Redesign -- Phase 4 (Design Tokens & Theme)

## Current Position

Phase: 4 of 13 (Design Tokens & Theme)
Plan: 0 of TBD in current phase
Status: Ready to plan
Last activity: 2026-03-12 -- Roadmap created for v2.0 milestone (10 phases, 74 requirements)

Progress: [..........] 0%

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

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Open Issues

- wizard.js TODO: meeting creation API not yet wired (intentional)

## Session Continuity

Last session: 2026-03-13T07:14:19.027Z
Stopped at: Completed 08-session-wizard-hub-02-PLAN.md
Resume file: None
