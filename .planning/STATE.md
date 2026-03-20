---
gsd_state_version: 1.0
milestone: v4.3
milestone_name: Ground-Up Rebuild
status: planning
stopped_at: null
last_updated: "2026-03-20"
last_activity: 2026-03-20 — Milestone v4.3 started
progress:
  total_phases: 0
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-20)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v4.3 Ground-Up Rebuild — each page rebuilt from scratch with JS verification

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-03-20 — Milestone v4.3 started

## Accumulated Context

(Carried from v4.2)
- v4.2 introduced regressions: HTML restructuring broke JS interactions and some layouts
- Incremental CSS edits on old HTML cannot achieve top 1% — full page rewrites needed
- Each page must be rebuilt: HTML + CSS + JS together, verified in browser
- Backend wiring must be checked for every page

### Decisions

- **Ground-up approach** — no more patches. Each page gets a complete rewrite
- **JS compatibility first** — read JS before touching HTML, verify after
- **Backend wiring** — every API call, HTMX target, SSE connection tested
- **One page = one testable commit** — no broken intermediate states

### Pending Todos

None

### Blockers/Concerns

- v4.2 regressions need fixing before new work can build on them

## Session Continuity

Last session: 2026-03-20
Stopped at: null
Resume file: None
Next action: Define requirements → roadmap
