---
phase: 12-page-by-page-mvp-sweep
plan: 19
subsystem: testing
tags: [playwright, e2e, email-templates, critical-path, css-tokens, modal, editor]

# Dependency graph
requires:
  - phase: 12-page-by-page-mvp-sweep
    provides: email-templates.css (design-system tokens, no raw color literals)
provides:
  - Full-width applicative email-templates grid (1200px clamp removed)
  - Playwright critical-path gate for email-templates page (11 interactions, non-destructive)
affects: [ci, wave-5]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "SAFETY pattern: #btnSaveTemplate and #btnCreateDefaults never clicked — DB writes prevented"
    - "Editor modal: waitForFunction on classList.contains('active') for open/close"
    - "Conditional width assertion: grid > 1200px only when #templatesGrid visible (not empty state)"

key-files:
  created:
    - tests/e2e/specs/critical-path-email-templates.spec.js
  modified:
    - public/assets/css/email-templates.css

key-decisions:
  - "Remove max-width: 1200px from .templates-grid — applicative page uses full viewport width"
  - "Keep max-width: 1100px on .template-editor-content — legitimate modal component cap"
  - "Never click #btnSaveTemplate or #btnCreateDefaults — test only open/field/cancel flow"
  - "Conditional grid width assertion: only assert >1200px when templates are present; skip when empty state showing"

patterns-established:
  - "critical-path-email-templates.spec.js: non-destructive editor modal interaction pattern (open → fill → cancel, never save)"

requirements-completed: [MVP-01, MVP-02, MVP-03]

# Metrics
duration: 22min
completed: 2026-04-07
---

# Phase 12 Plan 19: Email Templates Page MVP Sweep Summary

**Full-width grid clamp removed + non-destructive Playwright gate: filter, editor open/fill/cancel via both close paths — btnSaveTemplate never clicked**

## Performance

- **Duration:** 22 min
- **Started:** 2026-04-07T~T00:00Z
- **Completed:** 2026-04-07
- **Tasks:** 2
- **Files modified:** 2 (1 modified, 1 created)

## Accomplishments

- Removed `max-width: 1200px` from `.templates-grid` in `public/assets/css/email-templates.css` (line 57). Grid now uses full viewport width via `repeat(auto-fill, minmax(320px, 1fr))`. Modal cap `max-width: 1100px` on `.template-editor-content` untouched (legitimate component cap).
- Confirmed token gate clean: 34 `var(--color-*)` usages, zero raw color literals (`oklch()`, `#hex`, `rgba()`) — grep returns empty.
- Created `tests/e2e/specs/critical-path-email-templates.spec.js` with 11 interaction assertions:
  1. Page mount + toolbar visible (filterType, btnNewTemplate, btnCreateDefaults)
  2. Filter dropdown changes value, grid handler runs
  3. btnCreateDefaults wired and enabled — not clicked
  4. btnNewTemplate opens editor modal (classList.contains('active'))
  5. Editor title "Nouveau template", visible inputs, empty templateId
  6. Fill name/subject/body and read back via inputValue()
  7. templateType select changes to 'reminder'
  8. previewFrame attached to DOM
  9. btnRefreshPreview click — no crash, stays on same page
  10. btnCancelEdit closes editor (active class removed)
  11. btnCloseEditor (×) also closes after re-open
  12. Width verification at 1920×1080: no horizontal overflow; grid > 1200px when visible
- Test run: **1 passed (4.8s)** via `bin/test-e2e.sh specs/critical-path-email-templates.spec.js` (Docker playwright container, chromium).

## Task Commits

1. **Task 1: Width fix + token audit** — `f7077134` (fix)
   - `public/assets/css/email-templates.css` — remove applicative 1200px clamp from .templates-grid

2. **Task 2: Function gate — Playwright spec** — `2324ca5d` (test)
   - `tests/e2e/specs/critical-path-email-templates.spec.js` — 11 non-destructive interaction assertions

## Files Created/Modified

- `public/assets/css/email-templates.css` — removed `max-width: 1200px` from `.templates-grid`, kept modal cap
- `tests/e2e/specs/critical-path-email-templates.spec.js` — critical-path Playwright spec, 11 interactions, no DB writes

## Decisions Made

- `.templates-grid` width clamp removed — applicative pages must use full viewport per MVP-01
- `.template-editor-content { max-width: 1100px }` preserved — modal caps are legitimate UX constraints
- `#btnSaveTemplate` is never clicked — would persist a template row to the DB
- `#btnCreateDefaults` is never clicked — would create default template rows in the DB
- Grid width assertion is conditional: `if (isGridVisible)` — avoids false assertion when empty state is showing and #templatesGrid has zero children

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written.

### Infrastructure Note

Playwright tests cannot run with the host Chromium binary due to missing `libatk-1.0.so.0` system library (no sudo access). Tests are run via `bin/test-e2e.sh` which uses the `mcr.microsoft.com/playwright:v1.59.1-jammy` Docker image — consistent with all other phase 12 E2E specs.

## CSS Audit Results

**Width gate:** PASS
- `.templates-grid` — `max-width: 1200px` REMOVED (was line 57)
- `.template-editor-content { max-width: 1100px }` — modal cap, kept (line 193)
- `@media (max-width: 768px)` — responsive breakpoints only, not applicative clamps

**Token gate:** PASS
- Zero raw color literals — grep returns empty
- 34 `var(--color-*)` usages — all colors via design-system tokens

## Safety Guarantee

- `grep -cE "page\.click.*btnSaveTemplate|page\.click.*btnCreateDefaults"` returns `0`
- `#btnSaveTemplate` and `#btnCreateDefaults` are never passed to `page.click()` in the spec
- Editor is always dismissed via `#btnCancelEdit` or `#btnCloseEditor`

## Test Run Output

```
Running 1 test using 1 worker
  1 passed (4.8s)
```

## Wave 5 Progress

Wave 5 (5 pages): trust, public, email-templates, docs, help

- email-templates: DONE (this plan)
- Other Wave 5 pages: status per their respective plans

---
*Phase: 12-page-by-page-mvp-sweep*
*Completed: 2026-04-07*
