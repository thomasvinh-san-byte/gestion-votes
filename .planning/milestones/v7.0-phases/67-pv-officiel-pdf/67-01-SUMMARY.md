---
phase: 67-pv-officiel-pdf
plan: 01
subsystem: api
tags: [pdf, dompdf, php, phpunit, loi-1901, pv, tenant-settings]

# Dependency graph
requires:
  - phase: 25-pdf-infrastructure-foundation
    provides: Dompdf integration and MeetingReportsController::generatePdf() skeleton
  - phase: 50-secondary-pages-part-2
    provides: MeetingReportRepository upsertFull and snapshot storage

provides:
  - generatePdf() with asso loi 1901 template (org header, quorum block, dual signatures)
  - Inline PDF viewing via ?inline=1 Content-Disposition toggle
  - Text-only vote labels (no emoji) safe for Dompdf/DejaVu Sans rendering
  - org_name fetched from tenant_settings via SettingsRepository::get()

affects:
  - 67-02 (postsession JS wiring — uses inline endpoint added here)
  - 68-email-queue (sendReport flow sends PDF — template quality affects email content)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Fetch org_name from SettingsRepository::get(tenantId, 'org_name') before HTML build"
    - "api_query('inline') toggle for Content-Disposition inline vs attachment"
    - "Meeting-level quorum computed from attendance voting_power sum (presentPower/totalPower)"
    - "Dual signature table: President named, Secretaire blank for handwritten signature"

key-files:
  created: []
  modified:
    - app/Controller/MeetingReportsController.php
    - tests/Unit/MeetingReportsControllerTest.php

key-decisions:
  - "Secretary signature is a blank line (no secretary_name column in meetings table — loi 1901 practice)"
  - "Meeting-level quorum displays ratio fact only, no threshold assertion (policies are per-motion)"
  - "Inline mode is a separate flag from preview/draft — preview adds BROUILLON watermark, inline must not"
  - "Emoji removed from PDF (checkmark/cross/circle) to ensure DejaVu Sans compatibility in Dompdf"

patterns-established:
  - "Pattern: org header from tenant_settings in PDF generation"
  - "Pattern: api_query('inline') for iframe-embeddable PDF"
  - "Pattern: meeting-level quorum section from attendance data aggregation"

requirements-completed: [PV-01, PV-02, PV-03]

# Metrics
duration: 12min
completed: 2026-04-01
---

# Phase 67 Plan 01: PV Officiel PDF — loi 1901 Template Summary

**generatePdf() upgraded to asso loi 1901 standard with org header from tenant_settings, meeting-level quorum block, dual President+Secretaire signature table, text-only vote labels, and ?inline=1 Content-Disposition toggle for iframe embedding**

## Performance

- **Duration:** 12 min
- **Started:** 2026-04-01T15:05:00Z
- **Completed:** 2026-04-01T15:17:00Z
- **Tasks:** 2 (TDD: 1 RED commit + 1 GREEN commit)
- **Files modified:** 2

## Accomplishments
- Added 5 failing tests (RED) covering all PV-01/PV-02/PV-03 requirements via source introspection
- Upgraded `generatePdf()` to fetch org_name from `SettingsRepository::get()` and render as top-level h1 heading
- Added meeting-level quorum block showing ratio of represented voting power
- Replaced single President signature div with two-column table adding blank Secretaire line
- Replaced all emoji characters (✅ ❌ ⚪ ✓ ✗ ⚠) with text-only labels safe for Dompdf/DejaVu Sans
- Added `$isInline = api_query('inline') === '1'` flag and conditional Content-Disposition header
- All 55 tests pass (50 existing + 5 new)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Wave 0 tests for PV-01/PV-02/PV-03 requirements (RED)** - `262d8892` (test)
2. **Task 2: Upgrade generatePdf() to loi 1901 template with inline mode (GREEN)** - `a3bc2d92` (feat)

**Plan metadata:** _(docs commit follows)_

_Note: TDD tasks have two commits (test → feat) per TDD protocol_

## Files Created/Modified
- `app/Controller/MeetingReportsController.php` — generatePdf() upgraded: org header, quorum block, dual signatures, text labels, inline mode
- `tests/Unit/MeetingReportsControllerTest.php` — 5 new test methods for loi 1901 template requirements

## Decisions Made
- Secretary signature rendered as blank line — no `secretary_name` column in meetings table; loi 1901 practice is handwritten signature
- Meeting-level quorum displays ratio as a fact (`X% des voix représentées`) without asserting a threshold — quorum policies are per-motion, not per-meeting
- `?inline=1` is a separate flag from `?preview=1` — preview adds BROUILLON watermark, inline viewing of final PV must not add watermark
- Decision label strings use ASCII text (ADOPTEE/REJETEE) rather than Unicode accents in `match` values to avoid Dompdf font rendering issues

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

4 pre-existing test failures in `EmailControllerTest` and `EmailTemplateServiceTest` — unrelated to MeetingReportsController. These failures existed before this plan and are out of scope per deviation rules.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `generatePdf()` now produces a legally-compliant asso loi 1901 PV with org header, quorum, and dual signatures
- Inline mode endpoint ready for postsession.js wiring (plan 67-02 if it exists, or direct browser testing)
- No blockers for subsequent phases

---
*Phase: 67-pv-officiel-pdf*
*Completed: 2026-04-01*
