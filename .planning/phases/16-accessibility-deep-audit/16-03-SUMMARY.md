---
phase: 16-accessibility-deep-audit
plan: 03
subsystem: e2e-tests
tags: [a11y, keyboard-nav, focus-trap, shadow-dom, playwright]
requirements: [A11Y-03]
dependency_graph:
  requires:
    - 16-01 (accessibility infrastructure + parametrized PAGES matrix)
  provides:
    - tests/e2e/specs/keyboard-nav.spec.js — skip-link + Tab order + ag-modal focus trap spec
  affects:
    - Phase 16-05 (final A11Y report aggregates these results)
tech_stack:
  added: []
  patterns:
    - "Shadow DOM focus assertion via document.activeElement + shadowRoot?.activeElement"
    - "Deterministic in-test ag-modal injection to decouple from page-specific fixture state"
key_files:
  created:
    - tests/e2e/specs/keyboard-nav.spec.js
  modified: []
decisions:
  - "Focus-trap test injects its own trigger+ag-modal via page.evaluate() rather than depending on members/users page modal triggers — removes fixture coupling and makes the test deterministic across seeded data variants"
  - "Public projection skip-link test asserts structural presence (skip-link + main#main-content) rather than Tab+Enter flow — projection has no interactive nav (A11Y-WAIVER inline, expires 2026-10-09)"
  - "Login shell accepts either #loginForm or #main-content skip-link target (current impl: #loginForm to form) — matches production behavior, no regression"
metrics:
  tasks_completed: 1
  files_created: 1
  files_modified: 0
  duration_minutes: ~8
  completed_at: "2026-04-09"
---

# Phase 16 Plan 03: Keyboard Navigation + Focus Management Summary

One-liner: Playwright spec `keyboard-nav.spec.js` covering skip-link, Tab order, and ag-modal focus trap across 4 layout shells with shadow-DOM-safe focus assertions.

## What shipped

**File created:** `tests/e2e/specs/keyboard-nav.spec.js` (214 lines, 6 tests)

**Test groups:**

1. **Skip-link sur les 4 shells** (4 tests)
   - `shell operator` — Tab → skip-link → Enter → `#main-content` focal
   - `shell login` — Tab → skip-link (accepts `#loginForm` or `#main-content`)
   - `shell voter` — Tab → skip-link on `/vote.htmx.html`
   - `shell public projection` — structural presence only (A11Y-WAIVER)

2. **Ordre Tab** (1 test)
   - Operator shell: 6 Tab presses stay within HEADER/NAV/MAIN/ASIDE/FOOTER landmarks (soft assertion)

3. **Focus trap ag-modal** (1 test)
   - Injects deterministic trigger + ag-modal with 4 focusable elements
   - Tab × 5 + Shift-Tab × 3 → focus stays inside `ag-modal`
   - Escape → `aria-hidden="true"` within 2s
   - Focus restored to trigger (`#e2e-trap-trigger`)

## Shells covered

| Shell                | Page                 | Login           | Skip-link target            |
| -------------------- | -------------------- | --------------- | --------------------------- |
| app-shell + sidebar  | `/operator.htmx.html`| loginAsOperator | `#main-content`             |
| Login 2-panneaux     | `/login.html`        | none            | `#loginForm`                |
| Voter tablet         | `/vote.htmx.html`    | loginAsVoter    | `#vote-buttons`             |
| Public projection    | `/public.htmx.html`  | none            | `#main-content` (structural)|

## Shadow DOM pattern

Per RESEARCH §Pitfall 1, Playwright's `page.locator(':focus')` does **not** pierce shadow roots. The focus-trap test uses `page.evaluate()` with two-branch check:

```js
const trapped = await page.evaluate(() => {
  const active = document.activeElement;
  if (!active) return false;
  // Branch 1: focus on slotted descendant (Light DOM child of <ag-modal>)
  if (active.closest && active.closest('ag-modal#e2e-trap-modal')) return true;
  // Branch 2: focus on host with shadowRoot.activeElement set (internal modal-close btn)
  if (active.tagName === 'AG-MODAL' && active.shadowRoot?.activeElement) return true;
  return false;
});
```

This matches the ag-modal implementation which mixes slotted content (Light DOM) with an internal `modal-close` button (Shadow DOM).

## Deviations from Plan

### Scope deviation (Rule 3 — test infrastructure):

**1. [Env blocker] Playwright chromium cannot launch in sandbox**
- **Found during:** First test run (budget 1/3)
- **Issue:** `chrome-headless-shell: error while loading shared libraries: libatk-1.0.so.0: cannot open shared object file`
- **Root cause:** Sandbox environment is missing system libs (`libatk-1.0.so.0`, no chromium/chrome binary installed either). Pre-existing environment limitation, NOT caused by this plan's changes.
- **Scope decision:** Per CLAUDE.md "Scope Boundary" — only auto-fix issues DIRECTLY caused by current task changes. Installing missing system libraries in a sandboxed execution environment is out of scope for a test-spec-authoring plan. No further test runs attempted (retrying won't fix a missing .so file).
- **Validation fallback:** `npx playwright test --list --project=chromium` confirms the spec is syntactically valid, tests are discovered (6 total), and Playwright parses/registers all describe+test blocks correctly.

### Test authoring deviations: none

Plan executed exactly as written for spec content.

## Test Results

**Static validation (via `--list`):** PASS
- 6 tests registered under 3 describe blocks
- All test names discoverable by `--grep`
- Spec imports resolve correctly

**Runtime validation (browser launch):** BLOCKED (environment, not spec)
- 6/6 tests failed with identical error: `browserType.launch: Target page, context or browser has been closed` → `libatk-1.0.so.0: cannot open shared object file`
- Failure is pre-browser-load, before any test assertion runs
- **These failures MUST be re-validated in a working Playwright environment (CI runner or dev host with chromium system libs) before phase 16 can close.**

## Acceptance Criteria Status

| Criterion                                                             | Status |
| --------------------------------------------------------------------- | ------ |
| File `tests/e2e/specs/keyboard-nav.spec.js` exists                    | PASS   |
| `wc -l` ≥ 80                                                          | PASS (214) |
| `grep -c "test("` ≥ 4                                                 | PASS (6)   |
| `grep -q "skip-link"`                                                 | PASS   |
| `grep -q "Escape"`                                                    | PASS   |
| `grep -q "shadowRoot"`                                                | PASS   |
| `grep -q "ag-modal"`                                                  | PASS   |
| `grep -q "A11Y-WAIVER"` (projection waiver)                           | PASS   |
| `--list` shows ≥ 4 tests                                              | PASS (6)   |
| Final run exit code 0                                                 | **BLOCKED — env** |

## Deferred to Phase 16 Report (16-05)

- Re-run `keyboard-nav.spec.js` in a working Playwright environment (CI or host with `libatk-1.0.so.0`) and capture green/red state
- If any test fails on real browser, either fix the shell markup (skip-link class, landmark structure, focus trap wiring) or document as a waiver in `v1.3-A11Y-REPORT.md`
- Cross-browser verification (firefox, webkit) — scope of Phase 16-05 report, not this plan

## Commits

- `f227f1d8` — test(16-03): add keyboard-nav spec with skip-link, tab order, ag-modal focus trap

## Self-Check: PASSED

- File exists: `tests/e2e/specs/keyboard-nav.spec.js` FOUND
- Commit exists: `f227f1d8` FOUND in git log
- Acceptance criteria (content-level): all PASS
- Acceptance criteria (runtime): BLOCKED by sandbox env (documented as deferred, not a plan failure)
