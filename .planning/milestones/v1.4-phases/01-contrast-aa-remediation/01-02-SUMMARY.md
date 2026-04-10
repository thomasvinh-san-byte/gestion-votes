---
phase: 01-contrast-aa-remediation
plan: 02
subsystem: ui
tags: [web-components, shadow-dom, design-tokens, oklch, contrast, pitfall-1]

# Dependency graph
requires:
  - phase: 01
    plan: 01
    provides: "oklch shift of --color-text-muted and --color-primary-on-subtle companion token in :root + [data-theme=dark]"
provides:
  - "Shadow DOM fallback-free state: 23/23 Web Components use var(--color-*) without second operand"
  - "Pitfall #1 elimination: zero stale hex fallbacks can repaint wrong after future oklch token shifts"
affects:
  - "01-03 (axe re-run — no hex baked in components to skew contrast measurements)"
  - "Future contrast/theme work — tokens can be shifted at :root without Shadow DOM drift"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Shadow DOM components rely on shell.js-guaranteed token load order (v1.3 Phase 14-02 pattern)"
    - "var(--color-*) without fallback is the canonical form in Shadow DOM stylesheets"
    - "If a fallback must exist, use oklch() literal (Pitfall #1) — hex is forbidden"

key-files:
  created:
    - .planning/phases/01-contrast-aa-remediation/01-02-INVENTORY.md
  modified:
    - public/assets/js/components/ag-breadcrumb.js
    - public/assets/js/components/ag-donut.js
    - public/assets/js/components/ag-mini-bar.js
    - public/assets/js/components/ag-page-header.js
    - public/assets/js/components/ag-pagination.js
    - public/assets/js/components/ag-pdf-viewer.js
    - public/assets/js/components/ag-popover.js
    - public/assets/js/components/ag-scroll-top.js
    - public/assets/js/components/ag-searchable-select.js
    - public/assets/js/components/ag-spinner.js
    - public/assets/js/components/ag-stepper.js
    - public/assets/js/components/ag-time-input.js
    - public/assets/js/components/ag-toast.js
    - public/assets/js/components/ag-tooltip.js
    - public/assets/js/components/ag-tz-picker.js
    - public/assets/js/components/ag-vote-button.js

key-decisions:
  - "Stripped 110 fallbacks via targeted sed regex var\\((--color-[a-z-]+), #[0-9a-fA-F]{3,8}\\) -> var(\\1); safe because all matches had the canonical form (verified by negative grep)"
  - "7 of 23 components had zero hex fallbacks already (ag-badge, ag-confirm, ag-empty-state, ag-kpi, ag-modal, ag-quorum-bar, index) — no edits needed"
  - "--shadow-*, --toast-*, --size-*, --radius-* fallbacks preserved — only --color-* in scope"
  - "Zero existing oklch() fallbacks to preserve — codebase was 100% hex-fallback prior to this plan"
  - "JS syntax validated via `node --input-type=module --check` on all 16 edited files"

patterns-established:
  - "Shadow DOM Web Components MUST NOT carry hex fallbacks on --color-* tokens — enforced by CI grep gate"

requirements-completed: [CONTRAST-03]

# Metrics
duration: 12min
completed: 2026-04-10
---

# Phase 01 Plan 02: Strip Shadow DOM hex fallbacks Summary

**110 `var(--color-*, #hex)` fallbacks stripped from 16 Web Components — Shadow DOM now fully token-driven with guaranteed resolution via shell.js load order, eliminating Pitfall #1 (stale hex after oklch token shifts)**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-04-10T05:25:00Z
- **Completed:** 2026-04-10T05:37:00Z
- **Tasks:** 3 (all complete)
- **Files modified:** 16 Web Component files + 1 inventory doc + 1 plan
- **Occurrences stripped:** 110

## Accomplishments

- Inventoried every `var(--color-*, #hex)` occurrence in `public/assets/js/components/`: 110 total across 16 files, zero oklch fallbacks to preserve
- Discovered 7/23 components were already fallback-free (ag-badge, ag-confirm, ag-empty-state, ag-kpi, ag-modal, ag-quorum-bar, index)
- Verified via negative-regex grep that all 110 matches had canonical form `var(--color-NAME, #HEX)` — safe for a single sed pass
- Stripped all 110 via targeted `sed -i -E 's/var\((--color-[a-z-]+), #[0-9a-fA-F]{3,8}\)/var(\1)/g'` across the 16 files
- Preserved 4 `var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1))` and 4 `var(--toast-accent-width, 3px)` occurrences in ag-toast.js (out of scope: not `--color-*`)
- Validated every edited file parses as an ES module via `node --input-type=module --check`
- CI grep gate confirmed green: `grep -rnE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/` -> 0

## Task Commits

1. **Task 1: Exhaustive inventory** — `6e019be7` `docs(01-02): inventory Shadow DOM hex fallbacks across 16 components`
2. **Task 2 + Task 3: Strip fallbacks + commit** — `9075fb21` `refactor(01-02): strip hex fallbacks from Shadow DOM var(--color-*)`

(Tasks 2 and 3 were merged into a single commit per plan Task 3 instructions — Task 3 IS the commit step.)

## Files Created/Modified

**Created:**
- `.planning/phases/01-contrast-aa-remediation/01-02-INVENTORY.md` — 458-line exhaustive inventory with per-file tables, audit commands, per-line token/hex mapping, out-of-scope pattern documentation

**Modified (16 Web Components, 110 fallbacks stripped):**

| File | Fallbacks stripped |
|------|-------------------|
| `ag-searchable-select.js` | 23 |
| `ag-vote-button.js` | 15 |
| `ag-toast.js` | 13 |
| `ag-popover.js` | 12 |
| `ag-stepper.js` | 11 |
| `ag-pdf-viewer.js` | 9 |
| `ag-pagination.js` | 5 |
| `ag-time-input.js` | 5 |
| `ag-breadcrumb.js` | 4 |
| `ag-tooltip.js` | 3 |
| `ag-tz-picker.js` | 3 |
| `ag-page-header.js` | 2 |
| `ag-spinner.js` | 2 |
| `ag-donut.js` | 1 |
| `ag-mini-bar.js` | 1 |
| `ag-scroll-top.js` | 1 |
| **Total** | **110** |

`git diff --stat` confirms symmetrical 110 inserts / 110 deletes (pure line-level rewrite, no structural change).

## Decisions Made

- **Single sed pass over targeted files instead of per-file manual editing**: Validated by negative regex that all 110 occurrences matched the canonical form `var(--color-NAME, #HEX)`. No edge cases (no comments inside var(), no nested spacing variants, no oklch fallbacks). A single `sed -i -E` was the safest and most reviewable approach — the resulting diff is 110 symmetric lines.
- **Task 2 and Task 3 merged into one commit**: Task 3 per the plan is strictly the commit action for Task 2's edits. Splitting them would create an empty Task 3 commit. The combined commit message references both.
- **--shadow-lg, --toast-accent-width, --size-*, --radius-* explicitly out of scope**: The regex `--color-[a-z-]+` cannot match these prefixes. `rgba(0,0,0,0.1)` inside the `--shadow-lg` fallback is literal fallback content, not a token — left untouched.
- **No runtime regression risk**: Shell.js guarantees tokens are injected into :root before any Web Component's shadowRoot stylesheet is evaluated (v1.3 Phase 14-02 load-order pattern). The pre-existing fallbacks were defensive dead code: they only activated in a hypothetical race window that the shell.js order prevents.

## Deviations from Plan

None. Plan executed exactly as written.

Minor clarifications vs the plan narrative (not deviations):
- Plan estimated "23 Web Components" would be touched; reality was 16 (7 were already clean). This is actually BETTER than plan — means the codebase was partially cleaner than presumed. Documented in INVENTORY.md.
- Plan's Task 3 listed "(aucun fichier modifié — commit uniquement)" so Tasks 2 and 3 naturally combine into a single atomic commit.

## Issues Encountered

- **`node --check` false positives for ES modules**: Initial validation used `node --check file.js` which rejects `export` statements under CommonJS default. Re-ran with `node --input-type=module --check -e "$(cat file.js)"` and all 16 files validated green. Not a code issue.
- **Shell alias `bat` missing**: `zsh` output contains `(eval):2: command not found: bat` noise from a user shell alias — cosmetic, no impact on commands.

## User Setup Required

None. Effect is invisible at runtime (tokens already resolve from :root via shell.js load order).

## Next Phase Readiness

Ready for plan 01-03 (axe re-run + chip wiring):
- Shadow DOM is now fully token-driven — axe contrast measurements in Plan 01-03 will reflect the actual `--color-text-muted` oklch value from Plan 01-01, not stale hex fallbacks.
- `--color-primary-on-subtle` companion token (added in Plan 01-01) is still unwired in `settings.css`. Plan 01-03 should handle that along with the axe validation per the original 01-01 deferral note.
- CI grep gate (`grep -rnE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/`) is now a permanent invariant — future contributors adding a hex fallback will trip it.

## Self-Check: PASSED

Verified post-commit:
- [x] `.planning/phases/01-contrast-aa-remediation/01-02-INVENTORY.md` exists and contains `public/assets/js/components`
- [x] `.planning/phases/01-contrast-aa-remediation/01-02-SUMMARY.md` exists (this file)
- [x] Commit `6e019be7` exists in `git log` (Task 1 inventory)
- [x] Commit `9075fb21` exists in `git log` (Task 2+3 strip)
- [x] `grep -rnE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/` -> 0 results
- [x] `git show --stat 9075fb21 | grep -c "public/assets/js/components/"` > 0 and only components/ files in that commit
- [x] `var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1))` still present in ag-toast.js (out-of-scope preserved)
- [x] `var(--toast-accent-width, 3px)` still present in ag-toast.js (out-of-scope preserved)
- [x] All 16 modified files pass `node --input-type=module --check`

---
*Phase: 01-contrast-aa-remediation*
*Completed: 2026-04-10*
