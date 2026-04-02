---
phase: 25-pdf-infrastructure-foundation
plan: 02
subsystem: ui
tags: [web-components, filepond, pdf-viewer, shadow-dom, file-upload]

# Dependency graph
requires:
  - phase: 25-01
    provides: Resolution document API endpoints (upload, delete, serve)

provides:
  - ag-pdf-viewer Web Component (inline, sheet, panel modes)
  - FilePond PDF-only upload zone in wizard step 3
  - Resolution document card UI (icon, name, size, preview, delete)

affects:
  - 25-03 (hub/operator/voter page integrations will use ag-pdf-viewer)
  - Any page integrating resolution document preview

# Tech tracking
tech-stack:
  added:
    - FilePond 4.32.12 (CDN)
    - filepond-plugin-file-validate-type 1.2.9 (CDN)
    - filepond-plugin-file-validate-size 2.2.9 (CDN)
  patterns:
    - Web Component with Shadow DOM following AgModal/AgConfirm pattern
    - Three viewer modes (inline/sheet/panel) via CSS :host([mode]) selectors
    - FilePond per-resolution initialization with _pondInitialized guard
    - Document cards with preview (ag-pdf-viewer panel) and delete (AgConfirm.ask)

key-files:
  created:
    - public/assets/js/components/ag-pdf-viewer.js
  modified:
    - public/assets/js/components/index.js
    - public/assets/css/design-system.css
    - public/wizard.htmx.html
    - public/assets/js/pages/wizard.js

key-decisions:
  - "ag-pdf-viewer uses CSS :host([mode][open]) selectors for transitions — no JS class toggling needed"
  - "Download button hidden by absence of allow-download attribute (voter mode PDF-10 requirement)"
  - "FilePond revert disabled — deletions handled via custom doc card delete button + AgConfirm.ask"
  - "backdrop inside Shadow DOM but positioned fixed; z-index: -1 relative to :host for panel/sheet overlay"

patterns-established:
  - "Web Component with three CSS-driven display modes controlled by attributes"
  - "FilePond guard: inputEl._pondInitialized prevents double-initialization on re-render"
  - "Resolution document cards: consistent preview via ag-pdf-viewer + delete via AgConfirm.ask"

requirements-completed: [PDF-06, PDF-07, PDF-09]

# Metrics
duration: 18min
completed: 2026-03-18
---

# Phase 25 Plan 02: Frontend Components — PDF Viewer and FilePond Upload Summary

**ag-pdf-viewer Shadow DOM Web Component with three display modes and FilePond PDF-only upload integrated in wizard step 3 with French validation errors and document card management**

## Performance

- **Duration:** 18 min
- **Started:** 2026-03-18T11:30:00Z
- **Completed:** 2026-03-18T11:48:00Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments
- Created ag-pdf-viewer Web Component: inline (page flow), sheet (mobile bottom-slide), panel (desktop side-slide) modes with CSS transitions and backdrop
- Registered ag-pdf-viewer in components/index.js with import, export, and AG_DEBUG log entry; host-level CSS added to design-system.css
- Integrated FilePond in wizard step 3 with PDF-only (application/pdf) and 10MB validation, all error messages in French
- Document cards show filename, size, preview (ag-pdf-viewer panel mode) and delete (AgConfirm.ask) per uploaded file
- Download button on ag-pdf-viewer controlled by allow-download attribute — absent hides it (voter mode requirement PDF-10)

## Task Commits

Each task was committed atomically:

1. **Task 1: ag-pdf-viewer Web Component** - `000983f` (feat)
2. **Task 2: FilePond upload integration** - `cef7e1e` (feat)

**Plan metadata:** (docs commit to follow)

## Files Created/Modified
- `public/assets/js/components/ag-pdf-viewer.js` - PDF viewer Web Component with inline/sheet/panel modes, Shadow DOM, Escape key + backdrop close, escapeHtml safety
- `public/assets/js/components/index.js` - Added ag-pdf-viewer import, export, and AG_DEBUG array entry
- `public/assets/css/design-system.css` - Host-level display:block selector for ag-pdf-viewer sheet/panel modes
- `public/wizard.htmx.html` - FilePond CSS (head) and JS script tags (body, plugins before core); inline .doc-card styles
- `public/assets/js/pages/wizard.js` - initResolutionPond(), renderDocCard(), loadExistingDocs(); renderResoList() extended to inject .resolution-documents per row

## Decisions Made
- ag-pdf-viewer uses CSS :host([mode][open]) attribute selectors for transitions — no JS class toggling needed, clean separation
- Download button hidden by attribute absence (not CSS hidden) — voter mode simply omits allow-download attribute
- FilePond revert set to null; document deletion is handled via custom card delete button + AgConfirm dialog
- Backdrop inside Shadow DOM with z-index: -1 relative to :host so it sits behind the viewer but above the page

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- ESLint 10.0.3 requires Node.js >= 20 but environment runs v18.19.1 — pre-existing environment constraint. Code reviewed manually and follows existing codebase patterns exactly (var declarations, IIFE, escapeHtml).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- ag-pdf-viewer component available globally via components/index.js and window.AgPdfViewer
- FilePond upload wired to /api/v1/resolution_documents (from Phase 25-01)
- Ready for Phase 25-03: hub/operator/voter page integrations using ag-pdf-viewer
- ag-pdf-viewer in panel mode used in wizard; hub/operator can reuse same component in panel or inline mode

---
*Phase: 25-pdf-infrastructure-foundation*
*Completed: 2026-03-18*
