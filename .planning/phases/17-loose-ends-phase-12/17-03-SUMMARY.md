---
phase: 17-loose-ends-phase-12
plan: 03
subsystem: planning-audit
tags: [audit, loose-ends, closeout, deferrals, v2-backlog]
dependency_graph:
  requires: [17-01, 17-02]
  provides: [17-audit-ledger]
  affects: [REQUIREMENTS.md v2 section, 12-02/12-17/12-18 SUMMARY back-links]
tech_stack:
  added: []
  patterns: [grep-classify-defer-or-fix audit pattern]
key_files:
  created:
    - .planning/phases/17-loose-ends-phase-12/17-AUDIT-LEDGER.md
  modified:
    - .planning/REQUIREMENTS.md
    - .planning/milestones/v1.2-phases/12-page-by-page-mvp-sweep/12-02-SUMMARY.md
    - .planning/milestones/v1.2-phases/12-page-by-page-mvp-sweep/12-17-SUMMARY.md
    - .planning/milestones/v1.2-phases/12-page-by-page-mvp-sweep/12-18-SUMMARY.md
decisions:
  - "Grep across all 21 Phase 12 SUMMARY files with extended pattern set surfaced 6 unique findings (2 already fixed by 17-01/17-02, 3 non-trivial v2 deferrals, 0 fix-now, 0 promoted todos)"
  - "Zero inline fixes — every remaining unresolved note requires CSS / CSP / container-build work outside closeout scope"
  - "Three new v2 requirement IDs created (V2-OVERLAY-HITTEST, V2-TRUST-DEPLOY, V2-CSP-INLINE-THEME) to carry the deferrals forward"
metrics:
  duration: ~15m
  completed: 2026-04-09
  tasks_completed: 1
  files_created: 1
  files_modified: 4
  test_runs: 0
requirements: [LOOSE-03]
---

# Phase 17 Plan 03: Phase 12 SUMMARY Audit Ledger Summary

Greped all 21 Phase 12 SUMMARY files for unresolved-issue markers, classified 6 unique findings, confirmed two were already fixed by Wave 1 of Phase 17, and explicitly deferred three non-trivial items to v2 with back-links in the originals.

## Outcome

| Bucket                           | Count |
| -------------------------------- | ----- |
| Resolved by 17-01 (settings race) | 1     |
| Resolved by 17-02 (eIDAS chip)    | 1     |
| Fixed now (inline)                | 0     |
| Deferred to v2                    | 3     |
| Promoted to backlog todo          | 0     |

**Total unique actionable findings:** 6
**Noise matches (deliberate design / already-resolved-inline):** grouped under noise row in ledger

## Deferred v2 Items

1. **V2-OVERLAY-HITTEST** — Hidden modal overlays (`#opQuorumOverlay` in operator, trust severity modal) still participate in Playwright's hit-test despite `hidden` attribute. Current mitigation: `force: true` / `page.evaluate(el.click())`. Root cause: CSS `display: flex` / `position: fixed` specificity beats `[hidden] { display: none }`. Affects 12-02 and 12-17.

2. **V2-TRUST-DEPLOY** — `agvote-app` Docker image baked April 8 serves a minified trust.js that predates commit `68329786` (audit chip + view-toggle handlers). Playwright can only verify structural wiring until the image is rebuilt. Affects 12-17.

3. **V2-CSP-INLINE-THEME** — Strict CSP blocks the inline `<script>` that bootstraps `data-theme='dark'` on public pages. Current test accepts `['dark', null]`. Proper fix needs nonce-based CSP or an external theme bootstrap. Affects 12-18.

## Accomplishments

- Full Phase 12 SUMMARY sweep completed in one grep pass (two pattern batches)
- Ledger at `.planning/phases/17-loose-ends-phase-12/17-AUDIT-LEDGER.md` with Summary, Findings Table, Deferred, Promoted, Acceptance sections
- `REQUIREMENTS.md` v2 section augmented with 3 explicit IDs
- Three original Phase 12 SUMMARYs annotated under a `## Post-milestone audit` section with back-links to the ledger — no rewrites
- LOOSE-03 marked complete in REQUIREMENTS.md and traceability table

## Task Commits

1. **Task 1: Grep audit + ledger creation + v2 deferrals** — `4cb690bd` (docs)

## Decisions Made

- **Zero fix-now items.** The plan permits up to 3 small inline fixes, but every surviving finding after de-duplication needed non-trivial work. Taking any of them would have risked scope creep in a closeout plan.
- **Three v2 IDs rather than one bucket.** Each deferral has a distinct technical root cause (CSS hit-test, Docker image rebuild, CSP policy), so they are tracked independently to avoid bundling unrelated work into a single v2 ticket.
- **No promoted todos.** Every deferral belongs to the next milestone, not v1.3 — the promotion bucket would have been an intermediate layer with no practical difference from the v2 backlog.

## Deviations from Plan

None — plan executed exactly as written. The plan budgets up to 3 fix-now items; the audit produced zero, which is a legitimate outcome for a closeout phase where the two largest documented risks were already separately carved out as 17-01 and 17-02.

## Issues Encountered

None. No tests run (pure documentation audit, CLAUDE.md test budget untouched).

## Verification

- `test -f .planning/phases/17-loose-ends-phase-12/17-AUDIT-LEDGER.md` → FOUND
- `grep -c "resolved by 17-01\|resolved by 17-02" .../17-AUDIT-LEDGER.md` → 5 (covers both cross-refs in table + deferred section)
- `grep -l "Post-milestone audit" .../12-*-SUMMARY.md` → 12-02, 12-17, 12-18 (exactly the three deferred files)

## Next Phase Readiness

- LOOSE-03 closed — Phase 17 all three plans complete
- Phase 17 ready for verification / milestone rollup
- v2 backlog pre-seeded with three concrete technical debts carried forward from v1.2 Phase 12

## Self-Check: PASSED

- `.planning/phases/17-loose-ends-phase-12/17-AUDIT-LEDGER.md` — FOUND
- `.planning/phases/17-loose-ends-phase-12/17-03-SUMMARY.md` — FOUND (this file)
- Commit `4cb690bd` in git log — FOUND
- LOOSE-03 marked `[x]` in REQUIREMENTS.md — FOUND
- LOOSE-03 row in traceability table = `Complete` — FOUND
- Three `## Post-milestone audit` back-links in Phase 12 archive — FOUND

---
*Phase: 17-loose-ends-phase-12*
*Completed: 2026-04-09*
