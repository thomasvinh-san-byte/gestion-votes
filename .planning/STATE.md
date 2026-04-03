---
gsd_state_version: 1.0
milestone: v10.0
milestone_name: Visual Identity Evolution
status: ready_to_plan
stopped_at: null
last_updated: "2026-04-03T00:00:00.000Z"
last_activity: 2026-04-03
progress:
  total_phases: 3
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-02)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** Phase 82 — Token Foundation + Palette Shift (not started)

## Current Position

Phase: 82 of 84
Plan: Not started
Status: Ready to plan Phase 82
Last activity: 2026-04-03 — v10.0 roadmap created (phases 82-84)

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

- [v10.0 roadmap]: 3 phases derived from 14 requirements — token layer → component geometry → hardening
- [v10.0 roadmap]: Phase 82 changes design-system.css @layer base only; no per-page CSS files touched in this phase
- [v10.0 roadmap]: Dark mode [data-theme="dark"] block and critical-tokens inline styles must update in the same commit as any :root color primitive change (Pitfall 2 from research)
- [v10.0 roadmap]: Token names must never be renamed — add new alongside old to avoid Shadow DOM fallback staleness (Pitfall 3 from research)
- [v10.0 roadmap]: Phase 83 deferred skeleton shimmer scope requires pre-phase audit of which pages use spinners vs. HTMX-managed states
- [v9.0 roadmap]: AgConfirm.ask() is the universal confirmation pattern across all 7 page modules
- [Phase 81-fix-ux]: Shared.openModal() preserved for form-containing modals only

### Existing Infrastructure

- design-system.css: 5,258 lines, three @layer stack (base/components/v4), oklch values already present as trailing comments on every primitive
- 23 Web Components with Shadow DOM — inherit tokens but fallback hex literals require manual update after palette changes
- 25 per-page CSS files — ~15 hardcoded hex/rgba values identified: analytics.css, meetings.css, hub.css, vote.css, public.css, users.css
- critical-tokens inline styles in 22 HTML files — 6 hex values that prevent flash-of-wrong-color on load
- color-mix(in srgb, ...) calls in design-system.css — need upgrade to color-mix(in oklch, ...)

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% structural ceiling (3 exit()-based controllers)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

- Phase 83 skeleton shimmer: scope unknown until pre-phase audit of spinner vs. HTMX vs. empty-div loading patterns across all pages
- Phase 84 HARD-03: exact list of .htmx.html files with critical-tokens blocks should be verified before planning (research said 22 files)

## Session Continuity

Last session: 2026-04-03
Stopped at: Roadmap created for v10.0 — phases 82, 83, 84 defined, files written
Resume file: None
