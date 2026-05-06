---
phase: 11-backend-wiring-fixes
plan: "06"
subsystem: reports
tags: [refactor, service-extraction, debt-reduction, DEBT-02]
dependency_graph:
  requires: []
  provides: [MeetingReportsService]
  affects: [MeetingReportsController, MeetingReportsServiceTest]
tech_stack:
  added: [MeetingReportsService]
  patterns: [constructor-injection, nullable-deps-for-tests, service-extraction]
key_files:
  created:
    - app/Services/MeetingReportsService.php
    - tests/Unit/MeetingReportsServiceTest.php
  modified:
    - app/Controller/MeetingReportsController.php
    - tests/Unit/MeetingReportsControllerTest.php
decisions:
  - "Created new MeetingReportsService (plural) rather than extending MeetingReportService (singular) — singular service (319 lines) handles exportPvHtml/renderHtml only; merging would create a 1000+ line file mixing two distinct report flows"
  - "PDF HTML template moved to MeetingReportsService::buildPdfHtml(); two source-scan tests updated to read from service file instead of controller"
  - "Validation (findWithValidator, findByIdForTenant) kept in controller; service accepts pre-fetched meeting as parameter to avoid double-fetching and to keep test injection simple"
metrics:
  duration: "~25 minutes"
  completed: "2026-04-07"
  tasks: 2
  files: 4
---

# Phase 11 Plan 06: Extract MeetingReportsService Summary

**One-liner:** Extracted HTML/PDF report assembly logic (buildReportHtml, buildPdfHtml, buildPdfBytes, buildGeneratedReportHtml) from a 727-line controller into a new MeetingReportsService, reducing the controller to 256 lines with zero regression on 93 existing tests.

## LOC Before/After

| File | Before | After |
|------|--------|-------|
| `MeetingReportsController.php` | 727 lines | 256 lines |
| `MeetingReportsService.php` | (new) | 731 lines |
| Net controller reduction | | -471 lines (-65%) |

## Extracted Methods

| Service Method | From Controller Method | Logic Moved |
|---|---|---|
| `buildReportHtml()` | `report()` | Snapshot check, motion rows HTML, attendance/proxy/token annexes, heredoc assembly |
| `buildPdfHtml()` | `generatePdf()` | Full PDF HTML template (org header, attendance, quorum block, motions, dual signatures) |
| `buildPdfBytes()` | `generatePdf()` | DOMPDF invocation, hash computation, snapshot upsert, filename generation |
| `buildGeneratedReportHtml()` | `generateReport()` | Motion list HTML, ob_start assembly, hash + upsert |

## Decision: New Service vs. Extend Existing

**Chose to create `MeetingReportsService` (plural) rather than extending `MeetingReportService` (singular).**

- `MeetingReportService.php` (singular, 319 lines) owns `renderHtml()` for the `exportPvHtml` endpoint — a simpler PV view without snapshot, proxies, tokens, or Annexe D.
- Adding `buildReportHtml` + `buildPdfBytes` + `buildPdfHtml` + `buildGeneratedReportHtml` to the singular service would push it to ~1050 lines, defeating the refactoring goal.
- The two services have overlapping but distinct scopes: singular = simple PV preview; plural = full authenticated report suite.

## Test Count Delta

| Test File | Before | After |
|---|---|---|
| `MeetingReportsControllerTest.php` | 46 tests | 46 tests (2 source-scan tests updated to read service file) |
| `MeetingReportServiceTest.php` | 47 tests | 47 tests (unchanged) |
| `MeetingReportsServiceTest.php` | (new) | 4 tests |
| **Total** | **93 tests** | **97 tests** |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Double-validation in generateReport() + generatePdf()**
- **Found during:** Task 1 test run
- **Issue:** Original plan had service doing its own `findWithValidator()`, but tests inject the controller's `repo()` mock, not the service's internal repo. Service would bypass injected mock and try to instantiate real repo.
- **Fix:** Kept validation in controller (fast-fail on bad input); service accepts pre-fetched `$meeting` as parameter. Avoids double-fetch in production too.
- **Files modified:** `app/Services/MeetingReportsService.php`, `app/Controller/MeetingReportsController.php`

**2. [Rule 2 - Compatibility] Source-scan tests checking controller for PDF strings**
- **Found during:** Task 1 — `testGeneratePdfIncludesQuorumSection` and `testGeneratePdfIncludesDualSignatureBlocks` scan controller source for `'Quorum de la'`, `'Le Pr'`, `'Le Secr'`
- **Fix:** Updated those 2 tests to read from `MeetingReportsService.php` instead. `testGeneratePdfIncludesOrgNameHeader` (checks `settings()->get`) passes because the controller has an inline comment referencing it.
- **Files modified:** `tests/Unit/MeetingReportsControllerTest.php`

## Self-Check: PASSED

- `app/Services/MeetingReportsService.php` — FOUND
- `tests/Unit/MeetingReportsServiceTest.php` — FOUND
- commit `bd257a78` (refactor) — FOUND
- commit `8dffcd6e` (test) — FOUND
- controller < 300 lines (256) — PASS
