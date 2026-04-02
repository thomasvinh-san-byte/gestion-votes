---
phase: 80-pagination-quality
plan: 03
subsystem: ui
tags: [aria, wcag, accessibility, html, screen-reader]

# Dependency graph
requires: []
provides:
  - "WCAG 2.1 AA accessible names for all icon-only interactive elements across 10 pages"
  - "aria-label on 8 chart export buttons in analytics with descriptive chart-specific labels"
  - "aria-label on icon-only operator invitations and email-templates buttons"
  - "aria-label on 6 export download links in postsession"
  - "fieldset+legend on cnilLevel and textSize radio groups in settings"
  - "fieldset+legend on meetingType and invRecipients radio groups in operator"
  - "aria-label on archives ZIP export button"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Radio groups wrapped in fieldset+legend for group labeling (sr-only legend when card header provides visual context)"
    - "Icon-only buttons/links use aria-label with French action description"
    - "Chart export buttons use aria-label with specific chart title for disambiguation"

key-files:
  created: []
  modified:
    - public/analytics.htmx.html
    - public/operator.htmx.html
    - public/postsession.htmx.html
    - public/settings.htmx.html
    - public/archives.htmx.html

key-decisions:
  - "sr-only legend used for cnilLevel/textSize groups where card h2 already provides visual context — avoids duplicate visible label"
  - "Chart export aria-label includes the specific chart title for disambiguation between identical download icons"
  - "meetings.htmx.html, users.htmx.html, members.htmx.html, audit.htmx.html, wizard.htmx.html had no static icon-only gaps — all buttons have text or aria-label already"

patterns-established:
  - "fieldset+legend pattern: wrap radio group div in fieldset, use legend with form-label class (replaces bare label)"
  - "Icon disambiguation: when multiple identical icon buttons exist in same context, aria-label differentiates by subject (chart name, export type)"

requirements-completed:
  - QUAL-02

# Metrics
duration: 15min
completed: 2026-04-02
---

# Phase 80 Plan 03: ARIA Labels and Radio Group Semantics Summary

**WCAG 2.1 AA zero-critical fix: aria-label on all icon-only buttons/links and fieldset+legend on all radio groups across 10 pages**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-04-02T08:20:00Z
- **Completed:** 2026-04-02T08:37:00Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Added descriptive aria-label to 8 chart export buttons in analytics.htmx.html (each label includes specific chart title)
- Added aria-label to operator invitations schedule button and email-templates link, plus 6 export download links in postsession
- Wrapped cnilLevel and textSize radio groups in settings with fieldset+legend (sr-only legend, card h2 provides visual context)
- Wrapped meetingType and invRecipients radio groups in operator with fieldset+legend
- Added aria-label to archives ZIP export button (was title-only)
- Audited wizard, meetings, users, members, audit pages — confirmed no remaining static icon-only violations

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix icon-only buttons and links in analytics, operator, postsession** - `69e29b40` (feat)
2. **Task 2: Radio group fieldset/legend and remaining ARIA gaps** - `769d8590` (feat)

## Files Created/Modified
- `public/analytics.htmx.html` - 8 chart-export-btn buttons now have aria-label per chart title
- `public/operator.htmx.html` - btnScheduleInvitations + email-templates link get aria-label; meetingType and invRecipients wrapped in fieldset+legend
- `public/postsession.htmx.html` - 6 export download links (PV PDF, emargement, presences, votes, resultats, audit) get aria-label
- `public/settings.htmx.html` - cnilLevel and textSize radio groups wrapped in fieldset+legend (sr-only)
- `public/archives.htmx.html` - btnExportZip gets aria-label matching title

## Decisions Made
- Used `sr-only` legend for cnilLevel/textSize groups in settings — the card `<h2>` already provides the visual group name; adding a visible legend would be redundant
- operator radio groups use `<legend class="form-label">` directly (replacing the bare `<label>`) since there is no separate card heading providing visual context
- Chart export aria-labels include chart title (e.g., "Exporter le graphique Taux de participation") to disambiguate 8 identical download icons

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] archives.htmx.html btnExportZip had title but no aria-label**
- **Found during:** Task 2 systematic pass on remaining pages
- **Issue:** btnExportZip used only `title="Télécharger l'archive complète (ZIP)"` for its accessible name — title is not reliably announced by all screen readers
- **Fix:** Added matching `aria-label` attribute
- **Files modified:** public/archives.htmx.html
- **Verification:** Button now has both title and aria-label
- **Committed in:** 769d8590 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 missing critical)
**Impact on plan:** Required fix for WCAG compliance. No scope creep.

## Issues Encountered
None — all expected elements found at the line numbers specified in the plan.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- QUAL-02 requirement fulfilled: all icon-only interactive elements have accessible names
- All radio groups have proper group semantics via fieldset+legend
- Remaining WCAG gaps (if any) are in JS-injected DOM elements — out of scope for static HTML fixes

---
*Phase: 80-pagination-quality*
*Completed: 2026-04-02*
