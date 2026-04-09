# Phase 17: Loose Ends Phase 12 - Context

**Gathered:** 2026-04-09
**Status:** Ready for planning
**Mode:** --auto (scope is unambiguous — 3 concrete bugs + SUMMARY audit)

<domain>
## Phase Boundary

Fix three "documented but not blocking" issues from Phase 12, and audit Phase 12's SUMMARY files for any other unresolved notes. Closeout phase before v1.3 ship.

**In scope:**
- **LOOSE-01** Settings.js `loadSettings` race condition — `#settQuorumThreshold` does not populate after reload. Fix the timing issue, add a Playwright regression assertion.
- **LOOSE-02** Postsession eIDAS chip click delegation — replace fragile `page.evaluate` workaround in tests with a robust real click path (fix the delegation bug in the page, not the test).
- **LOOSE-03** Grep audit of `.planning/phases/12-*/12-*-SUMMARY.md` files for `documented but not fixed`, `TODO`, `known issue`, `to revisit` markers. Each finding: either fix inline, reclassify as v2 deferred (explicit note), or promote to its own v1.3 todo.

**Out of scope:**
- New features / refactoring beyond the specific bugs
- Design changes
- Performance work
- Anything in Phase 13+ SUMMARY files

</domain>

<decisions>
## Implementation Decisions

### LOOSE-01 — Settings race condition
- **D-01:** Root cause likely: `loadSettings` fetches from API and populates inputs, but the `input#settQuorumThreshold` value may be overwritten by a concurrent HTMX swap or the input may not exist in DOM yet when `loadSettings` runs. Investigation first, then minimal fix.
- **D-02:** Fix location: `public/assets/js/pages/settings.js` — likely adjust load sequence or await DOM ready before populating.
- **D-03:** Regression test: extend an existing settings Playwright spec (or add one) that loads settings page, waits for network idle, asserts `#settQuorumThreshold` has non-empty value.

### LOOSE-02 — Postsession eIDAS chip click delegation
- **D-04:** Root cause likely: chip is rendered dynamically after DOMContentLoaded, and click handler was attached to the specific element rather than delegated through a stable ancestor. Investigate in `public/assets/js/pages/postsession.js` or equivalent.
- **D-05:** Fix: use event delegation on a stable parent (`document` or the container) with a selector check, so newly-rendered chips automatically get the click behavior.
- **D-06:** Test: remove the `page.evaluate` workaround in the postsession spec and use a plain `page.click('[data-chip-type="eidas"]')` or similar natural click. If the test passes with a real click, LOOSE-02 is satisfied.

### LOOSE-03 — Phase 12 SUMMARY audit
- **D-07:** Grep pattern: `grep -rEn "documented but not|TODO|known issue|to revisit|not blocking|déféré" .planning/phases/12-*/12-*-SUMMARY.md`
- **D-08:** For each finding, classify in a ledger `.planning/phases/17-loose-ends-phase-12/17-AUDIT-LEDGER.md`:
  - **Fix now:** Small enough to fix in this phase → open mini-task, commit
  - **V2 deferred:** Too large for closeout → add explicit "deferred to v2" note in original SUMMARY, document reason
  - **Promote to todo:** Needs its own planning → add `/gsd:add-todo` entry
- **D-09:** Final acceptance: no SUMMARY file contains the "documented but not fixed" phrasing without a matching resolution link.

### Claude's Discretion
- Exact file paths / line numbers for the fixes (investigation required)
- Playwright spec file to extend vs create new
- Batch ordering of LOOSE-01 vs LOOSE-02 vs LOOSE-03 — all 3 are independent, planner decides wave grouping

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing issue trail
- `.planning/phases/12-page-by-page-mvp-sweep/12-01-SUMMARY.md` — LOOSE-01 source (settings race condition note)
- `.planning/phases/12-page-by-page-mvp-sweep/12-15-SUMMARY.md` — LOOSE-02 source (postsession eIDAS chip fragility)
- All `.planning/phases/12-*/12-*-SUMMARY.md` files — LOOSE-03 audit scope

### Code touchpoints
- `public/assets/js/pages/settings.js` — LOOSE-01 fix location
- `public/assets/js/pages/postsession.js` (or similar) — LOOSE-02 fix location
- `tests/e2e/specs/settings.*.spec.js` — LOOSE-01 regression
- `tests/e2e/specs/postsession.*.spec.js` — LOOSE-02 test cleanup

### Prior phase artifacts
- `.planning/phases/14-visual-polish/14-CONTEXT.md` — design system decisions that affect settings UI
- `.planning/phases/16-accessibility-deep-audit/16-02-SUMMARY.md` — 16-02 touched settings aria-labels, verify no conflict

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable assets
- Playwright specs in `tests/e2e/specs/` — spec conventions, loginAs* helpers, axeAudit helper.
- `bin/test-e2e.sh` Docker runner — use this, NOT host-native npx (host missing libatk).
- HTMX swap patterns — settings page uses HTMX for some panels; race conditions are typical around swap timing.

### Established patterns
- Event delegation via `document.addEventListener('click', handler)` with `target.matches(selector)` check — used elsewhere in the codebase.
- Settings page state: loaded once, not reactive — need to ensure populate-after-fetch happens after DOM is stable.

### Integration points
- Docker stack (app/db/redis) must be up for all Playwright tests
- `docker-compose.override.yml` from 16-02 bind-mounts `public/` so JS edits take effect immediately
- Settings has tests in `tests/e2e/specs/settings.htmx.spec.js` (likely) — integrate LOOSE-01 assertion there

</code_context>

<specifics>
## Specific Ideas

- **Minimal touch philosophy** — this is a closeout phase. Don't refactor code beyond the specific bugs. A bug fix doesn't need surrounding code cleaned up.
- **Regression tests are mandatory** — LOOSE-01 and LOOSE-02 both need a test that would have caught the original issue, to prevent reopening.
- **v2 deferrals must be explicit** — for LOOSE-03, "deferred to v2" is an acceptable outcome but requires a written note in the original SUMMARY linking to a v2 requirement ID or backlog entry.

</specifics>

<deferred>
## Deferred Ideas

- **Performance profiling of settings page** — not in scope, would be a new phase
- **Full UX review of postsession flow** — beyond LOOSE-02 scope
- **Phase 11 or 13 SUMMARY audit** — LOOSE-03 is scoped to Phase 12 SUMMARY files only

</deferred>

---

*Phase: 17-loose-ends-phase-12*
*Context gathered: 2026-04-09*
