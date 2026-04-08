---
phase: 11-backend-wiring-fixes
plan: "04"
subsystem: frontend-html
tags: [cleanup, orphan-removal, mvp-discipline, regression-lock]
dependency_graph:
  requires: [11-01, 11-02, 11-03]
  provides: [orphan-free-html, regression-guard]
  affects: [public/trust.htmx.html, public/meetings.htmx.html, public/settings.htmx.html]
tech_stack:
  added: []
  patterns: [regression-lock-test, mvp-discipline-removal]
key_files:
  created:
    - tests/Unit/HtmlOrphanCleanupTest.php
  modified:
    - public/trust.htmx.html
    - public/meetings.htmx.html
    - public/settings.htmx.html
    - public/assets/js/pages/settings.js
decisions:
  - "btnExportSelection removed from trust page even though audit.js handles it — audit.js is not loaded in trust.htmx.html"
  - "settHighContrast JS block removed from settings.js — localStorage-based contrast theme works without the settings UI element"
  - "auditExportBar div removed entirely (contained both orphan CSV/JSON export buttons)"
metrics:
  duration_minutes: 15
  completed_date: "2026-04-08T11:15:14Z"
  tasks_completed: 2
  files_changed: 4
---

# Phase 11 Plan 04: Orphan Button & Dead Settings Cleanup Summary

One-liner: Removed 4 orphan buttons (trust + meetings) and 3 dead settings fields (settings), locked by 4 PHPUnit regression assertions.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Remove orphan buttons from trust + meetings HTML | 67ba6ebf | public/trust.htmx.html, public/meetings.htmx.html |
| 2 | Remove dead settings fields + regression test | 04cf4b28 | public/settings.htmx.html, public/assets/js/pages/settings.js, tests/Unit/HtmlOrphanCleanupTest.php |

## Removed Elements

### trust.htmx.html (3 IDs removed)

- `btnExportSelection` — button in audit log card header; audit.js handles it but audit.js is not loaded in trust.htmx.html, making it orphan in this context
- `btnExportSelectedCsv` — inside auditExportBar div; no handler anywhere in codebase
- `btnExportSelectedJson` — inside auditExportBar div; no handler anywhere in codebase
- The entire `<div class="audit-export-bar" id="auditExportBar">` wrapper was also removed (only contained the two orphan buttons)

### meetings.htmx.html (1 ID removed)

- `btnStartTour` — onboarding banner button; no handler in meetings.js or anywhere; tour feature deferred per MVP scope

### settings.htmx.html (3 IDs removed + JS cleanup)

- `settMaxLoginAttempts` — number input in security section; no backend consumer in admin_settings.php
- `settPasswordMinLength` — number input in security section; no backend consumer
- `settHighContrast` — toggle in accessibility panel; JS called admin_settings.php but backend ignores the key; no-op API call
- Corresponding JS block in settings.js also removed (listener + localStorage sync for this element)
- The entire "Contraste élevé" card was removed (card became empty after toggle removal)

### settings.js (JS cleanup)

- Removed localStorage read block that synced ag_high_contrast into the now-removed settHighContrast DOM element
- Removed full highContrastToggle event listener block (20 lines)

## Regression Test

File: `tests/Unit/HtmlOrphanCleanupTest.php`

| Test | Asserts |
|------|---------|
| testTrustHasNoOrphanExportButtons | btnExportSelection, btnExportSelectedCsv, btnExportSelectedJson absent from trust.htmx.html |
| testMeetingsHasNoStartTourButton | btnStartTour absent from meetings.htmx.html |
| testSettingsHasNoDeadFields | settMaxLoginAttempts, settPasswordMinLength, settHighContrast absent from settings.htmx.html |
| testSettingsStillHasWorkingFields | settSmtpHost, settVoteMode, settQuorumThreshold, settMajority present (positive guard) |

Result: 4 tests, 11 assertions, all pass.

## Verification

```
grep -c "btnExportSelection|btnExportSelectedCsv|..." public/trust.htmx.html → 0
grep -c "btnStartTour" public/meetings.htmx.html → 0
grep -c "settMaxLoginAttempts|settPasswordMinLength|settHighContrast" public/settings.htmx.html → 0
grep -c "settSmtpHost|settVoteMode|settQuorumThreshold|settMajority" public/settings.htmx.html → 8
php vendor/bin/phpunit tests/Unit/HtmlOrphanCleanupTest.php → OK (4 tests, 11 assertions)
```

## Deviations from Plan

None — plan executed exactly as written.

Note: btnExportAudit was listed as a possible orphan in the plan's orphan_inventory — confirmed wired in trust.js (line 461) and left in place as instructed.

## Self-Check: PASSED

- public/trust.htmx.html: verified clean (0 orphan IDs)
- public/meetings.htmx.html: verified clean (0 orphan IDs)
- public/settings.htmx.html: verified clean (0 dead field IDs)
- tests/Unit/HtmlOrphanCleanupTest.php: exists and passes
- Commits 67ba6ebf and 04cf4b28: confirmed in git log
