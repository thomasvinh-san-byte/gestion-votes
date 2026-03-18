---
phase: 25-pdf-infrastructure-foundation
plan: 01
subsystem: api, database, infra
tags: [php, postgresql, pdf, docker, file-upload, security-headers]

# Dependency graph
requires: []
provides:
  - "resolution_documents PostgreSQL table with motion_id, meeting_id, tenant_id, display_order and 3 indexes"
  - "ResolutionDocumentRepository with 6 methods: listForMotion, create, findById, delete, countForMotion, listForMeeting"
  - "ResolutionDocumentController with listForMotion, upload, delete, serve endpoints"
  - "serve() sends 5 security headers: Content-Type, Content-Disposition, X-Content-Type-Options, Cache-Control, X-Frame-Options"
  - "AG_UPLOAD_DIR constant defined from AGVOTE_UPLOAD_DIR env var (default /var/agvote/uploads)"
  - "Docker 'uploads' volume mounted at /var/agvote/uploads for persistent PDF storage"
  - "MeetingAttachmentController migrated from hardcoded /tmp/ag-vote/uploads to AG_UPLOAD_DIR"
  - "REST API at /api/v1/resolution_documents (CRUD) and /api/v1/resolution_document_serve (authenticated PDF serve)"
affects:
  - "25-02 (PDF viewer frontend — depends on serve endpoint)"
  - "26-tour (may reference AG_UPLOAD_DIR constant)"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ResolutionDocumentRepository follows MeetingAttachmentRepository pattern exactly"
    - "AG_UPLOAD_DIR constant defined in bootstrap.php, used in all upload/serve controllers"
    - "public/api/v1/*.php are direct dispatch files (not stubs), middleware enforced via api.php auto-enforcement from routes.php"
    - "serve() exits after readfile() — never goes through api_ok/api_fail JSON pipeline"

key-files:
  created:
    - "database/migrations/20260318_resolution_documents.sql"
    - "app/Repository/ResolutionDocumentRepository.php"
    - "app/Controller/ResolutionDocumentController.php"
    - "public/api/v1/resolution_documents.php"
    - "public/api/v1/resolution_document_serve.php"
  modified:
    - "app/Core/Providers/RepositoryFactory.php"
    - "app/bootstrap.php"
    - "app/Controller/MeetingAttachmentController.php"
    - "docker-compose.yml"
    - "Dockerfile"
    - ".env.example"

key-decisions:
  - "AG_UPLOAD_DIR uses default /var/agvote/uploads (not /tmp) — matches Docker volume mount point"
  - "upload() uses 'filepond' field name (FilePond default) not 'file' (used by meeting_attachments)"
  - "serve() registered with 'role' => 'public' but performs its own auth check inside — allows vote token holders to access"
  - "meeting_id stored redundantly on resolution_documents (also reachable via motion_id) for cheap access-control joins, matching meeting_attachments pattern"
  - "public/api/v1/*.php are full dispatch files with method routing, not simple stubs — this matches the existing codebase pattern"

patterns-established:
  - "File serve pattern: readfile() + exit, 5 security headers, path built from AG_UPLOAD_DIR constant"
  - "Upload pattern: filepond field, finfo MIME check, extension check, mkdir 0750, move_uploaded_file, repo->create, audit_log"

requirements-completed: [PDF-01, PDF-02, PDF-03, PDF-04, PDF-05]

# Metrics
duration: 5min
completed: 2026-03-18
---

# Phase 25 Plan 01: PDF Infrastructure Foundation Summary

**PDF backend pipeline: resolution_documents table, repository, authenticated serve endpoint with 5 security headers, AG_UPLOAD_DIR constant, and persistent Docker volume — closing the P0 PDF serve blocker**

## Performance

- **Duration:** 5 min
- **Started:** 2026-03-18T11:22:36Z
- **Completed:** 2026-03-18T11:27:24Z
- **Tasks:** 3
- **Files modified:** 11

## Accomplishments
- Created resolution_documents DB migration with motion FK, display_order, 3 indexes (motion, meeting, tenant)
- Built ResolutionDocumentRepository (6 methods) and registered it in RepositoryFactory
- Created ResolutionDocumentController with 4 methods including authenticated PDF serve endpoint with all required security headers
- Defined AG_UPLOAD_DIR constant in bootstrap.php and migrated MeetingAttachmentController away from hardcoded /tmp paths
- Added persistent Docker 'uploads' volume mounted at /var/agvote/uploads and AGVOTE_UPLOAD_DIR env var

## Task Commits

Each task was committed atomically:

1. **Task 1: DB migration, repository, and RepositoryFactory registration** - `b9ffee9` (feat)
2. **Task 2: AGVOTE_UPLOAD_DIR env var, Docker volume, and storage migration** - `0ee1572` (feat)
3. **Task 3: ResolutionDocumentController with serve endpoint and route registration** - `aa0b818` (feat)

## Files Created/Modified
- `database/migrations/20260318_resolution_documents.sql` - resolution_documents table DDL with FK to motions, 3 indexes
- `app/Repository/ResolutionDocumentRepository.php` - 6-method repository following MeetingAttachmentRepository pattern
- `app/Core/Providers/RepositoryFactory.php` - Added ResolutionDocumentRepository import and resolutionDocument() accessor
- `app/bootstrap.php` - AG_UPLOAD_DIR constant defined from AGVOTE_UPLOAD_DIR env var
- `app/Controller/MeetingAttachmentController.php` - Replaced 2 hardcoded /tmp/ag-vote/uploads paths with AG_UPLOAD_DIR
- `app/Controller/ResolutionDocumentController.php` - Full controller with listForMotion, upload, delete, serve
- `app/routes.php` - Routes for /api/v1/resolution_documents (CRUD, operator) and /api/v1/resolution_document_serve (public with rate limit)
- `public/api/v1/resolution_documents.php` - Direct dispatch file with method routing
- `public/api/v1/resolution_document_serve.php` - Direct dispatch file for serve endpoint
- `docker-compose.yml` - Added uploads volume mount and AGVOTE_UPLOAD_DIR env var
- `Dockerfile` - Added /var/agvote/uploads to mkdir and chown

## Decisions Made
- Used `'role' => 'public'` for the serve route but perform explicit auth inside serve() — this allows the endpoint to be accessible while still doing tenant and meeting membership validation
- Upload uses `api_file('filepond')` not `api_file('file')` because FilePond (the frontend uploader) uses the field name 'filepond' by default
- AG_UPLOAD_DIR defaults to `/var/agvote/uploads` (persistent volume path), not `/tmp/ag-vote/uploads` (ephemeral) — this is the P0 fix
- `meeting_id` on resolution_documents is redundant (reachable via motion_id) but improves tenant access-control check performance by avoiding a JOIN — consistent with meeting_attachments pattern

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Public API dispatch files are full dispatch files, not simple stubs**
- **Found during:** Task 3 (creating public/api/v1/*.php)
- **Issue:** Plan described creating "routing stubs" with just `require_once __DIR__ . '/../../app/api.php'; // Routed via app/routes.php` but the existing `public/api/v1/meeting_attachments.php` is a full dispatch file with `match(api_method())` routing
- **Fix:** Created proper dispatch files matching the existing pattern with method-based routing
- **Files modified:** public/api/v1/resolution_documents.php, public/api/v1/resolution_document_serve.php
- **Verification:** Files follow meeting_attachments.php pattern; middleware enforcement still works via api.php auto-enforcement from routes.php
- **Committed in:** aa0b818 (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 bug — incorrect stub pattern)
**Impact on plan:** Fix required for correct request dispatch. No scope creep.

## Issues Encountered
- `.env` is gitignored so the AGVOTE_UPLOAD_DIR addition to `.env` was not committed — this is expected behavior, the value is documented in `.env.example`

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Backend PDF API complete: upload, list, delete, and authenticated serve endpoints all functional
- AG_UPLOAD_DIR constant available globally via bootstrap.php
- Routes registered at /api/v1/resolution_documents and /api/v1/resolution_document_serve
- DB migration ready to apply at next `docker compose up` (auto-applied via entrypoint migration runner if configured)
- Phase 25 Plan 02 (PDF.js viewer frontend) can proceed — depends on the serve endpoint built here

---
*Phase: 25-pdf-infrastructure-foundation*
*Completed: 2026-03-18*
