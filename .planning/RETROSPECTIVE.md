# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v2.0 — UI Redesign (Acte Officiel)

**Shipped:** 2026-03-16
**Phases:** 12 feature + 3 gap closure | **Plans:** 37 | **Requirements:** 54/54

### What Was Built
- Complete design system with 64 CSS tokens, dark/light theme switching
- Component library: modal, toast, confirm, popover, progress bar, guided tour, session banner (Web Components)
- App shell: sidebar rail/expand, header with search/notifications, mobile bottom nav, footer, ARIA landmarks
- Full session lifecycle UI: dashboard, sessions list/calendar, 4-step wizard, session hub
- Operator console: live KPI strip, resolution sub-tabs, agenda sidebar, quorum modal, P/F keyboard shortcuts
- Post-session workflow: 4-step stepper, archives with search/pagination, audit log with table/timeline + CSV export
- Statistics with KPI trends, charts, PDF export; users management with role panel and pagination
- Settings (4 tabs: rules, communication, security, accessibility) and help/FAQ with 9 guided tour launchers

### What Worked
- **Wave-based parallel execution** — Plans within a wave executed concurrently, cutting total wall-clock time significantly
- **Phase-then-verify pattern** — Building first, then running verification caught real integration bugs (api() argument order, toast wiring, timer lifecycle) that unit-level checks would have missed
- **One CSS per page convention** — Clean separation avoided cascade conflicts; each page owns its styles
- **Wireframe as single source of truth** — No design ambiguity; every token, layout, and component had a clear reference
- **Gap closure phases** — Phases 14-15 caught and fixed real wiring issues found by milestone audit

### What Was Inefficient
- **ROADMAP.md accumulated stale entries** — Duplicate phase 11/12/13 entries, unchecked plan checkboxes, phases 10.1/10.2 that were never executed (superseded by 14-15). Cleanup needed at milestone end.
- **Requirements traceability drift** — 10 requirements showed "Pending" in traceability but were actually complete. Checkbox sync lagged behind actual completion.
- **Phase 15 numbering collision** — Three different "Phase 15" directories (analytics-users-settings-help, operator-wiring-verification, tech-debt-cleanup) created confusion. Decimal phase insertion would have been cleaner.
- **Plan 15-02 incorrect premise** — Planned to remove `type="module"` from inline scripts, but no inline scripts had it. The type="module" was on external ES module scripts and was correct. Wasted a plan slot.

### Patterns Established
- **IIFE + var pattern** for all page JS (not ES modules for inline scripts)
- **Web Components** (ag-*) for shared UI elements loaded via `type="module"` external scripts
- **Design token hierarchy**: bg → surface → surface-alt → surface-raised → glass
- **Responsive breakpoints**: 1024px hides secondary columns, 640px switches to mobile layout
- **Semantic color tokens**: danger/success/warn/purple with -bg/-border variants in both themes
- **hidden attribute** preferred over style.display for toggling visibility

### Key Lessons
1. **Run milestone audit before declaring complete** — The v2.0 audit caught api() argument inversion, missing toast wiring, and frozen timer. These would have shipped as bugs.
2. **Keep ROADMAP.md clean during execution** — Stale/duplicate entries accumulate fast. Consider automated roadmap hygiene after each phase.
3. **Verify cross-phase integration early** — Phase-level verification passes but cross-phase wiring fails. Integration checks should run mid-milestone, not just at the end.
4. **Scope gap closure precisely** — Phases 10.1/10.2 were planned but never used because 14/15 did the same work with different scope. Better to plan gap closure once after full audit.

### Cost Observations
- Model mix: ~20% opus (orchestration), ~75% sonnet (execution, verification), ~5% haiku (quick checks)
- Parallel execution within waves saved significant time vs sequential
- Notable: Phase 8 Plan 01 took 460 minutes (wizard complexity); most other plans completed in 2-15 minutes

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.1-v1.5 | 1-3 | ~15 | Sequential execution, manual verification |
| v2.0 | 4-15 | 37 | Wave-based parallel execution, automated verification, milestone audit |

### Cumulative Quality

| Milestone | Requirements | Coverage | Gap Closure Phases |
|-----------|-------------|----------|-------------------|
| v1.5 | ~20 | 100% | 0 |
| v2.0 | 54 | 100% | 3 (Phase 14, 15x2) |

### Top Lessons (Verified Across Milestones)

1. Milestone audits catch real integration bugs that phase-level verification misses
2. Parallel plan execution within waves dramatically reduces wall-clock time
3. A single wireframe/spec as source of truth eliminates design ambiguity
