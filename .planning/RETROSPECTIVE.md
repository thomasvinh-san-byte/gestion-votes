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

## Milestone: v3.0 — Session Lifecycle

**Shipped:** 2026-03-18
**Phases:** 13 (9 core + 4 UI polish + 2 gap closure) | **Plans:** 37 | **Requirements:** 26/26

### What Was Built
- Wizard creates real sessions with atomic member+motion persistence in PostgreSQL transactions
- Multi-consumer SSE infrastructure with Redis fan-out, per-consumer queues, nginx buffering-off
- Live vote flow: operator opens/closes motions, voters cast ballots, real-time tally via SSE
- Post-session 4-step stepper: verified results, consolidation, PV PDF generation via Dompdf, archival
- Total DEMO_ eradication: renamed LOAD_DEMO_DATA to LOAD_SEED_DATA across all config/scripts/docs
- Every API call site has loading/error/empty states (ag-spinner + error+retry + Shared.emptyState)
- Hub→operator meeting_id propagation and frozen→live SSE transition wiring

### What Worked
- **Integration checker agent** — Caught 2 real cross-phase wiring bugs (hub→operator meeting_id, frozen→live SSE) that phase-level verification missed. Precise file/line references made fixes trivial.
- **Milestone re-audit cycle** — First audit found gaps, Phase 23 fixed them, re-audit confirmed fixes + found 1 new latency issue, Phase 24 fixed that. Iterative auditing converges on zero gaps.
- **Tiny gap closure phases** — Phases 23 and 24 were 1-plan, 2-task phases that completed in minutes. Small, focused phases are more effective than bundling fixes into a large remediation phase.
- **Code-level verification by integration checker** — PST-01-04 were confirmed correct by the integration checker without needing E2E tests. Reduced verification overhead for wiring-fix phases.

### What Was Inefficient
- **4 phases without VERIFICATION.md** — Phases 20, 20.1, 20.3, 21 never got VERIFICATION.md during execute-phase. The verifier should have been spawned for every phase, not just some.
- **REQUIREMENTS.md tracker drift** — HUB-01/HUB-02 were verified correct in Phase 16 VERIFICATION.md but the tracker still showed "Pending". PST-01-04 same issue. Manual tracker sync is unreliable.
- **UI polish phases (20.1-20.4) consumed 24/37 plans** — 65% of plans were CSS/visual alignment. Future UI milestones should consider fewer, larger plans per wireframe page group.
- **motionOpened SSE gap introduced by Phase 23** — The frozen→live fix called operator_open_vote.php which didn't fire motionOpened SSE. Should have been caught during Phase 23 planning, not by re-audit.

### Patterns Established
- **tryLoad(attempt) pattern** for retry logic (ES5-compatible, promise-based, with setTimeout fallback)
- **Shared.emptyState()** as universal empty state renderer
- **MeetingContext.set()/get()** as cross-page meeting_id propagation via sessionStorage + URL params
- **Per-consumer Redis lists** for SSE fan-out (`sse:consumers:{meetingId}` SET + per-consumer RPUSH)
- **Stale response guard** — snapshot meeting_id before async fetch, discard if meeting switched

### Key Lessons
1. **Spawn verifier for every phase** — Skipping verification for "simple" phases creates audit gaps. The 30-second cost of verification is negligible vs the re-audit overhead.
2. **Integration checker should run mid-milestone** — Catching the hub→operator handoff gap at Phase 20 (not Phase 22) would have saved 2 gap-closure phases.
3. **Audit-fix-reaudit converges fast** — The iterative cycle (audit → gap phase → re-audit) closed all gaps in 2 iterations. Don't fear re-auditing.
4. **Requirements tracker needs automation** — Manual checkbox sync drifts. Consider a script that cross-references VERIFICATION.md statuses with REQUIREMENTS.md checkboxes.

### Cost Observations
- Model mix: ~15% opus (orchestration, planning), ~80% sonnet (execution, verification, research), ~5% haiku
- 56 feat commits across 2 days (2026-03-16 to 2026-03-18)
- Notable: Phase 20.4 had 12 plans (1 per page CSS audit) — highest plan count per phase
- Gap closure phases (23, 24) completed in ~5 minutes each — fast turnaround on precise fixes

---

## Milestone: v5.0 — Quality & Production Readiness

**Shipped:** 2026-03-30
**Phases:** 6 | **Plans:** 18 | **Requirements:** 29/29

### What Was Built
- Migration audit: all 23 SQL files cleaned of SQLite syntax, dry-run validation script with --syntax-only mode and two-pass idempotency test
- Docker hardening: healthcheck PORT runtime fix (sh -c), envsubst nginx template, structured JSON health endpoint
- 233+ unit tests for 10 services with 574+ assertions; ControllerTestCase base class for execution-based controller testing
- 40 controller test files; Services 90.8%, Controllers 64.6% (structural limit from exit()-based controllers)
- 18 Playwright E2E specs updated for v4.3/v4.4 rebuilds; 177 total tests (chromium + mobile + tablet)
- 7-job CI/CD pipeline: validate, lint-js, migrate-check, coverage, build, e2e, integration

### What Worked
- **ControllerTestCase pattern** — Base class with `injectRepos()` + `callController()` enabled rapid test creation for 40 controllers. Reflection-based RepositoryFactory injection solved the "final class" constraint.
- **Coverage-driven gap filling** — Measuring baseline first (Services 66%, Controllers 10%) then targeting specific gaps was more efficient than writing tests blind.
- **Parallel E2E + unit test tracks** — Phase 56 (E2E) ran independently from phases 53-55 (unit tests) since both depended only on Phase 52 infrastructure.
- **Rate-limit-safe auth setup** — clearRateLimit via docker exec redis-cli DEL before test runs eliminated flaky auth failures in E2E.
- **Single-day milestone execution** — All 6 phases with 18 plans completed in one day. Quality/test milestones execute faster than feature milestones because the scope is well-defined and testable.

### What Was Inefficient
- **9 plans for Phase 55** — Coverage gap-filling required iterative plan creation as new gaps were discovered. Should have run a coverage measurement pass before planning to know the full scope.
- **COV-02 90% target was unrealistic** — 3 controllers using exit() made 90% architecturally impossible. Should have audited exit()-usage before setting the target. Settled at 64.6% with documented rationale.
- **Phase SUMMARY one-liners not populated** — SUMMARY.md files lacked one_liner field, requiring manual extraction of accomplishments at milestone completion.
- **Nyquist validation completely skipped** — All 6 phases missing VALIDATION.md. The workflow was available but never invoked.

### Patterns Established
- **ControllerTestCase** base class with Reflection-based RepositoryFactory injection for all controller tests
- **coverage-check.sh** script with configurable thresholds for CI enforcement
- **Source inspection approach** for controllers using exit() or raw binary output (untestable via PHPUnit execution)
- **Rate-limit clearing** in Playwright globalSetup before auth runs
- **Cookie injection** via navigate-then-addCookies pattern for Playwright session auth

### Key Lessons
1. **Measure before planning coverage work** — Running baseline coverage measurement should be Plan 01 of any coverage phase. Knowing the gap map avoids iterative re-planning.
2. **Audit architectural constraints before setting targets** — exit()-based controllers made 90% impossible. Check for structural blockers before committing to numeric thresholds.
3. **Quality milestones are fast** — Well-defined success criteria (test pass/fail) enable rapid execution. 6 phases in 1 day vs v4.3's 7 phases over 2 days.
4. **Populate SUMMARY one-liners** — Missing one_liner fields created manual work at milestone completion. Executors should fill this field.

### Cost Observations
- Model mix: ~10% opus (orchestration), ~85% sonnet (execution, verification), ~5% haiku
- All 18 plans completed in single session
- Notable: Phase 55 had 9 plans (most in milestone) due to iterative coverage gap discovery

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.1-v1.5 | 1-3 | ~15 | Sequential execution, manual verification |
| v2.0 | 4-15 | 37 | Wave-based parallel execution, automated verification, milestone audit |
| v3.0 | 16-24 | 37 | Integration checker, iterative audit-fix-reaudit, tiny gap closure phases |
| v4.0-v4.4 | 25-51 | ~80 | Ground-up page rebuilds, ControllerTestCase pattern, design system enforcement |
| v5.0 | 52-57 | 18 | Coverage-driven testing, CI pipeline wiring, single-day quality sprint |

### Cumulative Quality

| Milestone | Requirements | Coverage | Gap Closure Phases | Test Count |
|-----------|-------------|----------|-------------------|------------|
| v1.5 | ~20 | 100% | 0 | — |
| v2.0 | 54 | 100% | 3 (Phase 14, 15x2) | — |
| v3.0 | 26 | 100% | 2 (Phase 23, 24) | — |
| v4.0-v4.4 | ~70 | 100% | 0 | — |
| v5.0 | 29 | 100% | 0 | 2305 unit + 177 E2E |

### Top Lessons (Verified Across Milestones)

1. Milestone audits catch real integration bugs that phase-level verification misses (v2.0: api() arg order; v3.0: hub→operator handoff, frozen→live SSE)
2. Parallel plan execution within waves dramatically reduces wall-clock time
3. A single wireframe/spec as source of truth eliminates design ambiguity
4. Iterative audit-fix-reaudit converges fast — 2 iterations closed all v3.0 gaps
5. Tiny gap closure phases (1-2 tasks) are more effective than bundled remediation
6. Measure baseline before planning coverage work — avoids iterative re-planning (v5.0: 9 plans in Phase 55)
7. Quality milestones execute fastest — well-defined pass/fail criteria enable single-day completion (v5.0: 6 phases, 18 plans, 1 day)
