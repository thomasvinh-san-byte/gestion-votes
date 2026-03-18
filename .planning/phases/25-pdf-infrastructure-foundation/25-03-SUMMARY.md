---
phase: 25-pdf-infrastructure-foundation
plan: 03
subsystem: ui
tags: [pdf, sse, event-broadcaster, ag-pdf-viewer, filepond, voter-ui, operator-ui, hub-ui]

# Dependency graph
requires:
  - phase: 25-01
    provides: ResolutionDocumentController API endpoints and DB layer
  - phase: 25-02
    provides: ag-pdf-viewer Web Component (sheet/panel/inline modes)
provides:
  - EventBroadcaster::documentAdded and documentRemoved static SSE methods
  - document.added and document.removed registered in event-stream.js
  - Hub document badges per motion (loadDocBadges, renderDocBadge, openDocViewer)
  - Operator console upload button per resolution card with PDF/10MB validation
  - Voter "Consulter le document" button with read-only bottom sheet (no download)
  - SSE real-time document.added/removed updates in voter view
affects: [26-guided-tour, 27-post-session-pv, voter-view, operator-console, hub]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - SSE event fan-out for document lifecycle (documentAdded/documentRemoved in EventBroadcaster)
    - Native FormData upload in operator console (no FilePond dependency for live session)
    - ag-pdf-viewer mode=sheet for voter (read-only), mode=panel for hub (with download)
    - _currentMotionDocs state at IIFE module level in vote.js for SSE reactivity

key-files:
  created: []
  modified:
    - app/WebSocket/EventBroadcaster.php
    - app/Controller/ResolutionDocumentController.php
    - public/assets/js/core/event-stream.js
    - public/hub.htmx.html
    - public/assets/js/pages/hub.js
    - public/operator.htmx.html
    - public/assets/js/pages/operator-tabs.js
    - public/assets/js/pages/operator-motions.js
    - public/vote.htmx.html
    - public/assets/js/pages/vote.js

key-decisions:
  - "Voter document consultation uses ag-pdf-viewer mode=sheet — consistent with PDF-10 requirement (no download)"
  - "Hub badge click opens ag-pdf-viewer mode=panel with allow-download (operator/admin context)"
  - "Operator upload uses native FormData + fetch, not FilePond — avoids CDN dependency in live session console"
  - "loadMotionDocs only re-fetches when motionId changes (not on every SSE event) — avoids redundant API calls"
  - "SSE document.added/removed handled inline in onEvent without triggering full refresh() — lower latency"
  - "clearMotionDocs closes open ag-pdf-viewer on motion.closed to prevent stale PDF display"

requirements-completed: [PDF-08, PDF-10]

# Metrics
duration: 25min
completed: 2026-03-18
---

# Phase 25 Plan 03: PDF UI Integration Summary

**End-to-end PDF pipeline wired: SSE document events, per-motion hub badges with panel viewer, operator live upload, and voter read-only bottom-sheet consultation with real-time updates**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-03-18T12:15:00Z
- **Completed:** 2026-03-18T12:40:00Z
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments

- SSE document.added and document.removed events broadcast from upload/delete actions and registered in event-stream.js
- Hub page now shows per-motion document badges ("N documents joints" / "Aucun document") with click-to-preview in panel mode
- Operator console gets a native-upload "Document" button per resolution card with PDF-only and 10MB validation
- Voter view shows "Consulter le document" button when a motion has documents, opening ag-pdf-viewer in read-only sheet mode (no download)
- SSE events update voter button in real-time without triggering a full page refresh

## Task Commits

Each task was committed atomically:

1. **Task 1: SSE event, hub document badges, and operator upload wiring** - `9b5852b` (feat)
2. **Task 2: Voter view PDF consultation with read-only bottom sheet** - `03ded6e` (feat)

**Plan metadata:** _(docs commit follows)_

## Files Created/Modified

- `app/WebSocket/EventBroadcaster.php` — Added documentAdded() and documentRemoved() static methods
- `app/Controller/ResolutionDocumentController.php` — Wired EventBroadcaster calls in upload() and delete(), added use import
- `public/assets/js/core/event-stream.js` — Registered 'document.added' and 'document.removed' in eventTypes array
- `public/hub.htmx.html` — Added doc-badge CSS (doc-badge--empty, doc-badge--has-docs)
- `public/assets/js/pages/hub.js` — Added loadDocBadges(), renderDocBadge(), openDocViewer() for per-motion badge rendering
- `public/assets/js/pages/operator-tabs.js` — Added addDocUploadToMotionCard(), updateOperatorDocBadge(), exposed via OpS.fn
- `public/assets/js/pages/operator-motions.js` — Wired OpS.fn.addDocUploadToMotionCard into renderResolutions() card loop
- `public/vote.htmx.html` — Added btnConsultDocument button (initially hidden) near motion card
- `public/assets/js/pages/vote.js` — Added _currentMotionDocs state, loadMotionDocs(), openVoterDocViewer(), wireConsultDocBtn(), clearMotionDocs(), extended SSE onEvent for document events

## Decisions Made

- **Voter mode uses `mode="sheet"`** — per PDF-10 requirement, bottom sheet on mobile/tablet without download
- **Hub uses `mode="panel"` with `allow-download`** — operator/admin context where download is permitted
- **Native file input + FormData for operator console** — avoids FilePond dependency in a live session context; validates type and size inline
- **SSE document events handled without full refresh** — `document.added` and `document.removed` update the `_currentMotionDocs` array and button state directly, avoiding the cost of re-fetching `current_motion.php`
- **Motion doc loading on ID change only** — `loadMotionDocs` is called only when `_currentMotionId` changes in `refresh()`, not on every SSE event

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

- The automated verify check `! grep -q "allow-download" public/assets/js/pages/vote.js` would have caught doc comments containing the literal string. Rewrote the comments to avoid the false-positive.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Complete PDF pipeline is end-to-end: upload (Plan 01) → viewer component (Plan 02) → UI integration (Plan 03)
- Phase 25 is complete; Phase 26 (guided tour) and Phase 27 (post-session PV) can proceed
- The ag-pdf-viewer is reusable for any future document-display needs in the app

---
*Phase: 25-pdf-infrastructure-foundation*
*Completed: 2026-03-18*
