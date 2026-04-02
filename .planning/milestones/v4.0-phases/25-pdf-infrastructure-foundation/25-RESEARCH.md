# Phase 25: PDF Infrastructure Foundation - Research

**Researched:** 2026-03-18
**Domain:** PHP file upload, authenticated file serving, Docker persistent storage, PDF.js Web Component, FilePond, SSE event wiring
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**PDF Viewer Experience**
- Mobile (voter): Bottom sheet — slide-up panel from bottom of screen. Voter stays in voting context. Draggable to full-screen.
- Desktop (wizard, hub, operator): Side panel (slide-over from right). Page context remains visible alongside the PDF.
- Viewer tech: Native browser viewer first (`<iframe>` or `<embed>`). Zero dependency. Defer PDF.js custom rendering to v5+ only if native proves insufficient.
- Download policy: Voter = read-only, no download. Operator/admin = can download.

**Upload Workflow**
- Multiple PDFs per resolution — one resolution can have several documents (e.g., annexe A, annexe B)
- Upload timing: maximum flexibility — attach during wizard creation, from the hub before meeting, AND from the operator console during a live session. Never lock the user out of adding documents.
- Upload UX: FilePond with drag-and-drop zone + browse button. PDF-only, 10MB max per file.
- Post-upload display: Card per file — PDF icon + filename + file size + preview button. Delete with ag-confirm confirmation dialog (no accidental deletion).
- Inline error messages: FilePond shows validation errors inline before submission (wrong type, too large).

**Storage & Security**
- Storage location: Persistent Docker volume at configurable path via `AGVOTE_UPLOAD_DIR` env var (default: `/var/agvote/uploads/`). Migrate from current hardcoded `/tmp/ag-vote/`.
- Access control: Only authenticated users who are members of the meeting (operator + voters of that specific session) can view PDFs. Serve endpoint validates auth + tenant + meeting membership.
- Security headers on serve: `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Disposition: inline`.
- PDF.js version: Pin >= 4.2.67 (CVE-2024-4367 closed). If using PDF.js prebuilt viewer, set `isEvalSupported: false`.

**Hub & Operator Integration**
- Hub display: Badge on each resolution in the hub checklist — "📎 2 documents joints" or "Aucun document". Click opens the side panel viewer.
- Operator console: Can add/remove PDFs from the motions panel during live session. No lock after freeze.
- SSE notification: New `documentAdded` SSE event when operator adds a PDF during live session. Voter view shows/updates "Consulter le document" button in real-time.

### Claude's Discretion
- DB migration schema details (indexes, constraints)
- ag-pdf-viewer internal implementation (iframe vs embed tag)
- FilePond plugin configuration specifics
- Docker volume mount path in docker-compose.yml
- Error handling patterns for upload failures

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PDF-01 | resolution_documents DB table and migration (tenant_id, meeting_id, motion_id, stored_name, mime_type, file_size, uploaded_by) | Schema pattern from `meeting_attachments` migration is the exact template; architecture doc provides full DDL |
| PDF-02 | ResolutionDocumentController with upload, list, delete, and authenticated serve endpoint | `MeetingAttachmentController` is the copy-and-extend template; serve() is the new method |
| PDF-03 | Secure serve endpoint — validates auth + tenant + meeting membership, serves with correct security headers | Headers: `Content-Type: application/pdf`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store`, `Content-Disposition: inline` |
| PDF-04 | AGVOTE_UPLOAD_DIR env var replacing hardcoded /tmp/ag-vote path in all upload controllers | Replace `'/tmp/ag-vote/uploads/'` in MeetingAttachmentController lines 63 and 118; add constant to bootstrap |
| PDF-05 | Docker volume mount for persistent PDF storage | docker-compose.yml currently mounts `app-storage:/tmp/ag-vote`; must add new named volume at `/var/agvote/uploads` |
| PDF-06 | FilePond drag-and-drop upload in wizard step 3 (PDF only, 10MB max) | FilePond v4.32.12 CDN + two plugins; `FilePond` global via script tag; field name `filepond`; IIFE integration pattern documented |
| PDF-07 | ag-pdf-viewer Web Component with inline mode (desktop) and bottom-sheet mode (mobile) | `AgXxx extends HTMLElement` pattern; `mode="inline"` and `mode="sheet"` attributes; CSS bottom sheet via `position: fixed; inset: auto 0 0 0` |
| PDF-08 | PDF viewer wired to wizard (upload + preview), hub (doc status + preview), voter view (consultation bottom sheet) | Integration points: wizard.js step 3, hub.js checklist, vote.js; SSE `document.added` event to trigger voter UI update |
| PDF-09 | PDF.js v5.5.207+ self-hosted, pinned above CVE-2024-4367 threshold (>= 4.2.67) | Decision: native iframe first (zero dependency); if PDF.js programmatic API needed, v5.5.207 is current; `isEvalSupported: false` is mandatory |
| PDF-10 | Voter PDF consultation is read-only (no download link) in bottom-sheet overlay | ag-pdf-viewer hides download button for voter role; `Content-Disposition: inline` prevents browser download prompt |
</phase_requirements>

---

## Summary

Phase 25 is the PDF infrastructure foundation for AG-VOTE v4.0. It delivers the complete pipeline: `resolution_documents` DB table, `ResolutionDocumentController` (upload/list/delete/serve), persistent Docker volume storage, FilePond upload UX in wizard step 3, `ag-pdf-viewer` Web Component with bottom-sheet (mobile) and side-panel (desktop) modes, wiring into hub/operator/voter views, and a new `document.added` SSE event.

The phase closes two P0 security blockers confirmed by direct codebase inspection: (1) no authenticated serve endpoint exists — files are uploaded but unretrievable; (2) the storage path is hardcoded to `/tmp/ag-vote` (ephemeral on container restart). Both must be fixed before any viewer UI is useful. PDF.js CVE-2024-4367 (arbitrary JS execution in malicious PDFs) is the third P0 — mitigated by pinning pdfjs-dist >= 4.2.67 and setting `isEvalSupported: false`.

All backend patterns already exist in the codebase. `ResolutionDocumentController` is a direct copy-and-extend of `MeetingAttachmentController`. The `ag-pdf-viewer` component follows the `AgXxx extends HTMLElement` pattern used by 20 existing Web Components. The `EventBroadcaster` already supports adding new named events with a single static method. This phase is primarily wiring existing infrastructure together, not inventing new patterns.

**Primary recommendation:** Build in dependency order — DB migration first, then controller + repository + routes, then ag-pdf-viewer component, then page integrations (wizard, hub, operator, vote), then SSE event. Do not start viewer UI before the serve endpoint is tested and returning correct security headers.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP finfo | Built-in | Server-side MIME type detection | Already used in MeetingAttachmentController; more reliable than `$_FILES['type']` (client-supplied) |
| FilePond | 4.32.12 | Drag-and-drop file upload UI | MIT, IIFE/CDN global, ~21 KB gzipped, accessible ARIA, inline validation errors |
| FilePond validate-type plugin | 1.2.9 | Enforces PDF-only before upload | Inline error before submission (as required) |
| FilePond validate-size plugin | 2.2.9 | Enforces 10 MB max before upload | Inline error before submission (as required) |
| Native browser PDF renderer | Built-in | PDF display via `<iframe>` | Zero dependency; decision locked: defer PDF.js custom rendering to v5+ |
| pdfjs-dist | 5.5.207 | If native iframe proves insufficient (v5+ only) | Pinned above CVE-2024-4367; `isEvalSupported: false` mandatory |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ag-confirm (existing WC) | In codebase | Delete confirmation dialog | Delete button on uploaded file cards (prevents accidental deletion) |
| ag-toast (existing WC) | In codebase | Upload success/error feedback | After FilePond upload completes or fails |
| EventBroadcaster (existing) | In codebase | SSE event queuing | Add `document.added` event in serve of new document upload |
| EventStream (existing) | In codebase | SSE client listener | Add `document.added` to vote.js event type listener array |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Native `<iframe>` PDF | PDF.js programmatic API | API gives custom toolbar but requires ~800 KB worker; decision locked to native first |
| FilePond | Dropzone.js v6 | Dropzone is ~22 KB gzipped, also MIT; FilePond preferred for polished default UI matching v4.0 quality goals |
| FilePond | Hand-rolled drag-drop | 20+ hours for accessible equivalent; no justification |

**Installation (CDN, no npm):**
```html
<!-- FILE UPLOAD — wizard.htmx.html only -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/filepond@4.32.12/dist/filepond.min.css">
<script src="https://cdn.jsdelivr.net/npm/filepond@4.32.12/dist/filepond.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-type@1.2.9/dist/filepond-plugin-file-validate-type.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/filepond-plugin-file-validate-size@2.2.9/dist/filepond-plugin-file-validate-size.min.js"></script>
```

No new Composer packages. No new npm packages. PHP is vanilla with `finfo` (built-in).

---

## Architecture Patterns

### Recommended File Structure (new files only)

```
database/migrations/
  YYYYMMDD_resolution_documents.sql   # new: resolution_documents table

app/Controller/
  ResolutionDocumentController.php    # new: upload, list, delete, serve

app/Repository/
  ResolutionDocumentRepository.php    # new: CRUD for resolution_documents

app/Core/Providers/
  RepositoryFactory.php               # modified: add resolutionDocument() accessor

app/routes.php                        # modified: add 4-5 new routes

public/api/v1/
  resolution_documents.php            # new: GET/POST/DELETE routing stub
  resolution_document_serve.php       # new: GET authenticated file serve

public/assets/js/components/
  ag-pdf-viewer.js                    # new: Web Component with mode="inline"|"sheet"
  index.js                            # modified: register ag-pdf-viewer

public/assets/css/
  design-system.css                   # modified: add bottom-sheet + viewer CSS tokens

# Modified pages (no new files):
#   wizard.htmx.html + wizard.js (step 3 FilePond)
#   hub.htmx.html + hub.js (document badge, side-panel trigger)
#   operator.htmx.html + operator-motions.js (upload + remove during live)
#   vote.htmx.html + vote.js (Consulter document button + sheet trigger)
#   app/WebSocket/EventBroadcaster.php (documentAdded method)
#   public/assets/js/core/event-stream.js (add document.added to eventTypes)
#   docker-compose.yml (new named volume)
#   MeetingAttachmentController.php (replace hardcoded /tmp path)
```

### Pattern 1: DB Migration (resolution_documents)

**What:** PostgreSQL migration using `20260219_meeting_attachments.sql` as the exact template.
**When to use:** Always first — all other patterns depend on this table.

```sql
-- Source: database/migrations/20260219_meeting_attachments.sql (template)
CREATE TABLE IF NOT EXISTS resolution_documents (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    motion_id uuid NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
    original_name text NOT NULL,
    stored_name text NOT NULL,       -- UUID-based filename, no user-controlled path
    mime_type text NOT NULL DEFAULT 'application/pdf',
    file_size bigint NOT NULL DEFAULT 0,
    display_order integer NOT NULL DEFAULT 0,
    uploaded_by uuid REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_resolution_docs_motion
    ON resolution_documents(motion_id);
CREATE INDEX IF NOT EXISTS idx_resolution_docs_meeting
    ON resolution_documents(meeting_id);
CREATE INDEX IF NOT EXISTS idx_resolution_docs_tenant
    ON resolution_documents(tenant_id);
```

`meeting_id` is intentionally redundant (also reachable via `motion_id → motions.meeting_id`) — it avoids a JOIN for the common access-control check (tenant_id + meeting_id). This matches the existing `meeting_attachments` pattern.

### Pattern 2: Storage Path — AGVOTE_UPLOAD_DIR

**What:** Replace every hardcoded `/tmp/ag-vote/` with env-driven constant. Both upload controllers must use the same constant.
**When to use:** In the same task that migrates storage; before building serve endpoint.

```php
// Recommended location: app/bootstrap.php or a config helper
define('AG_UPLOAD_DIR', rtrim((string)(getenv('AGVOTE_UPLOAD_DIR') ?: '/var/agvote/uploads'), '/'));

// In ResolutionDocumentController::upload()
$uploadDir = AG_UPLOAD_DIR . '/resolutions/' . $motionId;

// In MeetingAttachmentController::upload() (modified)
$uploadDir = AG_UPLOAD_DIR . '/meetings/' . $meetingId;
```

Storage layout:
```
/var/agvote/uploads/
  meetings/{meeting_id}/{uuid}.pdf       # existing MeetingAttachmentController
  resolutions/{motion_id}/{uuid}.pdf     # new ResolutionDocumentController
```

### Pattern 3: ResolutionDocumentController (copy-and-extend template)

**What:** Direct copy of `MeetingAttachmentController` extended with `listForMotion()` and `serve()` methods.
**When to use:** After DB migration. Before any viewer UI.

```php
// Source: app/Controller/MeetingAttachmentController.php (exact template)
final class ResolutionDocumentController extends AbstractController {
    public function listForMotion(): void {
        $motionId = api_query('motion_id');
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('missing_motion_id', 400);
        }
        $tenantId = api_current_tenant_id();
        $items = $this->repo()->resolutionDocument()->listForMotion($motionId, $tenantId);
        api_ok(['documents' => $items]);
    }

    public function upload(): void {
        // Identical validation chain to MeetingAttachmentController::upload()
        // finfo MIME check + extension check + 10MB limit
        // Stores at AG_UPLOAD_DIR . '/resolutions/' . $motionId . '/' . $uuid . '.pdf'
        // Requires: meeting_id + motion_id in POST body
        // Verifies motion belongs to tenant via motion repo
        // Audit log: 'resolution_document_uploaded'
    }

    public function delete(): void {
        // Identical to MeetingAttachmentController::delete()
        // Verifies tenant isolation before unlink() + DB delete
        // Audit log: 'resolution_document_deleted'
        // After delete: broadcast EventBroadcaster::documentRemoved() if in-session
    }

    public function serve(): void {
        // NEW method — no equivalent in MeetingAttachmentController
        // See Pattern 4
    }
}
```

### Pattern 4: Secure Serve Endpoint

**What:** Auth-gated `readfile()` endpoint. The core P0 blocker to fix. Voters call this URL; the browser renders the PDF inline.
**When to use:** After upload pattern is working. Required before any viewer can display PDFs.

```php
// Source: app/Controller/ResolutionDocumentController.php::serve()
public function serve(): void {
    $id = api_query('id');
    if ($id === '' || !api_is_uuid($id)) {
        api_fail('missing_id', 400);
    }

    $tenantId = api_current_tenant_id();
    $doc = $this->repo()->resolutionDocument()->findById($id, $tenantId);
    if (!$doc) {
        api_fail('not_found', 404);
    }

    // Meeting membership check:
    // For operator/admin: any meeting in their tenant
    // For voter (vote_token): only the specific meeting they are attending
    // api_current_user_id() returns null for voter tokens — use token meeting_id check
    $this->checkMeetingAccess($doc['meeting_id'], $tenantId);

    $path = AG_UPLOAD_DIR . '/resolutions/' . $doc['motion_id'] . '/' . $doc['stored_name'];
    if (!file_exists($path) || !is_readable($path)) {
        api_fail('file_not_found', 404);
    }

    // Sanitize original_name for Content-Disposition header
    $safeFilename = preg_replace('/[^\w\s\-\.]/', '', $doc['original_name']);
    $safeFilename = basename($safeFilename) ?: 'document.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $safeFilename . '"');
    header('Content-Length: ' . $doc['file_size']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('X-Frame-Options: SAMEORIGIN');

    readfile($path);
    exit;
}
```

**Key constraint:** The serve endpoint must handle BOTH authenticated users (session cookie) AND vote token holders. The voter arrives at `vote.htmx.html` with a token, not a full session. The `checkMeetingAccess()` method must call the appropriate auth check for each context.

### Pattern 5: Routes Registration

**What:** New routes added to `app/routes.php` using existing middleware shorthand constants.
**When to use:** After controller is written.

```php
// Source: app/routes.php (existing meeting_attachments pattern lines 225-229)

// ── Resolution documents ──
$router->mapMulti("{$prefix}/resolution_documents", [
    'GET' => [ResolutionDocumentController::class, 'listForMotion', $op],
    'POST' => [ResolutionDocumentController::class, 'upload',       $op],
    'DELETE' => [ResolutionDocumentController::class, 'delete',     $op],
]);
// Serve endpoint: accessible to operator AND vote token holders (public role)
$router->map('GET', "{$prefix}/resolution_document_serve",
    ResolutionDocumentController::class, 'serve',
    ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]
);
```

Note: `'role' => 'public'` does not mean unauthenticated. It means the controller handles its own auth verification (like `ballots_cast`, `meeting_stats`). The serve endpoint's own code enforces auth.

### Pattern 6: FilePond IIFE Integration (wizard step 3)

**What:** FilePond upload zone per resolution card in wizard step 3. Multiple ponds for multiple resolutions.
**When to use:** After the upload endpoint is functional. Wizard page only — not globally.

```javascript
// Source: .planning/research/STACK.md §3 FilePond IIFE Integration Pattern
// In wizard.js IIFE — resolution step section
(function () {
  'use strict';

  function initResolutionPond(inputEl, motionId, meetingId) {
    FilePond.registerPlugin(
      FilePondPluginFileValidateType,
      FilePondPluginFileValidateSize
    );

    return FilePond.create(inputEl, {
      acceptedFileTypes: ['application/pdf'],
      labelFileTypeNotAllowed: 'Seuls les fichiers PDF sont acceptés',
      maxFileSize: '10MB',
      labelMaxFileSizeExceeded: 'Le fichier dépasse 10 Mo',
      allowMultiple: true,       // multiple PDFs per resolution
      server: {
        url: '/api/v1/resolution_documents',
        process: {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          data: { motion_id: motionId, meeting_id: meetingId },
          onload: function (response) {
            var data = JSON.parse(response);
            return data.document.id;  // FilePond stores this as server ID
          }
        },
        revert: {
          url: '/api/v1/resolution_documents',
          method: 'DELETE',
        }
      },
      labelIdle: 'Glissez un PDF ici ou <span class="filepond--label-action">parcourir</span>',
    });
  }
})();
```

**FilePond field name is `filepond`** — the PHP controller calls `api_file('filepond')`, not `api_file('file')`.

### Pattern 7: ag-pdf-viewer Web Component

**What:** New custom element following the `AgXxx extends HTMLElement` pattern. `mode` attribute controls layout.
**When to use:** After serve endpoint is verified. Shared by wizard, hub, operator, voter pages.

```javascript
// Source pattern: public/assets/js/components/ag-modal.js
class AgPdfViewer extends HTMLElement {
  static get observedAttributes() {
    return ['src', 'filename', 'mode', 'open'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  // open() — sets 'open' attribute → CSS transition triggers slide-in
  // close() — removes 'open' attribute → CSS transition triggers slide-out
  // src attribute change → update <iframe> src
  // mode="sheet" → bottom sheet CSS (position: fixed, inset: auto 0 0 0)
  // mode="inline" → embedded in page flow (default for desktop)
}
customElements.define('ag-pdf-viewer', AgPdfViewer);
```

**Internal iframe vs embed:** Use `<iframe>` — it provides better cross-browser control and allows `sandbox` attribute for security. The iframe `src` points to the serve endpoint URL, e.g.:
```
/api/v1/resolution_document_serve?id={uuid}
```

The browser's native PDF renderer handles display inside the iframe. No PDF.js needed for v4.0.

**Voter (read-only):** No download button rendered. The `Content-Disposition: inline` header prevents the browser from showing its own download link in the PDF viewer toolbar (in some browsers). To fully prevent download in the ag-pdf-viewer, the component does not expose a download button for voter role.

### Pattern 8: SSE documentAdded Event

**What:** Add `documentAdded()` static method to `EventBroadcaster` and register `document.added` in `event-stream.js`.
**When to use:** After upload endpoint is functional. Enables live voter notification during sessions.

```php
// Source: app/WebSocket/EventBroadcaster.php (follow existing motionOpened pattern)
public static function documentAdded(string $meetingId, string $motionId, array $docData = []): void {
    self::toMeeting($meetingId, 'document.added', [
        'motion_id' => $motionId,
        'document' => $docData,  // id, original_name, file_size
    ]);
}
```

```javascript
// Source: public/assets/js/core/event-stream.js lines 79-89
// Add to eventTypes array:
var eventTypes = [
  // ... existing events ...
  'document.added',   // new — voter shows/updates "Consulter le document" button
];
```

### Pattern 9: Docker Volume for Persistent Storage

**What:** Add named volume `uploads` in `docker-compose.yml` mounting to `/var/agvote/uploads`.
**When to use:** Infrastructure task — parallel with or before controller work.

```yaml
# docker-compose.yml — current state:
#   volumes:
#     - app-storage:/tmp/ag-vote    # logs, cache PDF, fonts

# Add new named volume mount in app service:
volumes:
  - app-storage:/tmp/ag-vote
  - uploads:/var/agvote/uploads     # NEW: persistent PDF storage

# Add to volumes: section at bottom:
volumes:
  pgdata:
    driver: local
  app-storage:
    driver: local
  uploads:              # NEW
    driver: local
```

**Critical docker-compose constraint:** The app container uses `read_only: true` with explicit `tmpfs` mounts. A named volume mount at `/var/agvote/uploads` is an allowed exception to the read-only filesystem — named volumes are writable bind mounts, not part of the container image layer.

**Also update Dockerfile** to create the directory:
```dockerfile
RUN mkdir -p /var/agvote/uploads && chown www-data:www-data /var/agvote/uploads
```

### Pattern 10: RepositoryFactory Registration

**What:** Add `resolutionDocument()` accessor following the exact existing single-line pattern.
**When to use:** After `ResolutionDocumentRepository.php` is created.

```php
// Source: app/Core/Providers/RepositoryFactory.php lines 75-104 (existing pattern)
// Add after meetingAttachment() accessor:
public function resolutionDocument(): ResolutionDocumentRepository {
    return $this->get(ResolutionDocumentRepository::class);
}
// And add the use statement at top of file.
```

### Anti-Patterns to Avoid

- **Serving files via nginx directly:** Do NOT add `location /uploads/` to nginx.conf. This bypasses all PHP auth. Files must only be accessible via the `/api/v1/resolution_document_serve` PHP endpoint.
- **Storing files in public/:** The upload directory must be outside `/var/www/public`. Never write to any path reachable as a static URL.
- **Using `$_FILES['type']` for MIME check:** This is client-supplied and trivially spoofed. Always use `finfo(FILEINFO_MIME_TYPE)` on the actual uploaded file.
- **Loading FilePond globally:** Add CDN script tags only to `wizard.htmx.html` (and later hub/operator pages). Not in the shell or layout template.
- **Using `<embed>` or `<object>` for PDF display:** Use `<iframe>` only. `<embed>` bypasses any PDF.js security hardening and has unpredictable Chrome behavior.
- **Setting `serve` route to require operator role:** Voters must be able to call this endpoint. Use `'role' => 'public'` and enforce auth inside the controller.
- **Calling EventBroadcaster::documentAdded in a list/GET request:** Only call it from the upload controller after a successful `move_uploaded_file` and DB insert. Not from list endpoints.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Drag-and-drop file upload with inline validation | Custom drop zone + XHR + validation | FilePond v4.32.12 | Accessible ARIA, loading states, inline errors, revert (delete) — 20+ hours of edge cases |
| File type/size client validation | Custom JS type check | FilePond validate-type + validate-size plugins | Already handles spoofed extensions, proper UX, i18n error messages |
| PDF inline display component | Custom iframe wrapper with loading/error states | ag-pdf-viewer Web Component | Consistent behavior across wizard/hub/operator/voter; mode="sheet" is non-trivial CSS |
| SSE event fan-out | New messaging mechanism | EventBroadcaster::documentAdded() | Full Redis + file fallback already implemented; single static method addition |
| Auth-gated file streaming | Custom streaming logic | `readfile()` with headers in PHP | readfile() is the correct PHP pattern; no custom stream wrappers needed |

**Key insight:** Every hard part of this phase already exists in the codebase. The work is configuration and assembly, not invention.

---

## Common Pitfalls

### Pitfall 1: serve() endpoint — voter auth check

**What goes wrong:** The controller calls `api_current_user_id()` which returns the logged-in user UUID. Voters authenticated via vote token do NOT have a user UUID — they have a token. The auth check for serve() must handle both paths.

**Why it happens:** `MeetingAttachmentController` only handles operator/admin — it never has to deal with vote tokens. The new serve endpoint is the first endpoint that must accept both session auth and vote token auth.

**How to avoid:** Check how existing public-role endpoints (e.g., `ballot_result`, `meeting_stats`) verify voter access. They call the vote token verification helper or check `$_SESSION['vote_token']` or the token-based middleware. Use the same pattern — do not invent a new auth path.

**Warning signs:** A 401 returned when a voter with a valid vote token tries to load a resolution PDF.

### Pitfall 2: docker-compose.yml read_only + named volume conflict

**What goes wrong:** The app container uses `read_only: true`. A developer adds a new bind mount (relative host path) instead of a named volume, and it fails because the container filesystem is read-only.

**Why it happens:** Confusion between named volumes (writable) and the container's own read-only image filesystem.

**How to avoid:** Use a named volume (`uploads: driver: local`) in the `volumes:` section and reference it as `- uploads:/var/agvote/uploads`. Named volumes are writable even in read-only containers. Relative bind mounts (e.g., `- ./uploads:/var/agvote/uploads`) also work but are less portable.

### Pitfall 3: nginx CSP blocks iframe serving PDF

**What goes wrong:** The current nginx CSP is `frame-ancestors 'self'` which controls who can iframe THE APP, not what the app can iframe. However, `object-src` is not listed in the existing CSP — it defaults to `default-src 'self'`. An `<iframe>` serving `/api/v1/resolution_document_serve` is same-origin, so this is NOT blocked. But if anyone adds an `object-src 'none'` to harden the CSP, it would break `<embed>` and `<object>` (another reason to use `<iframe>` not `<embed>`).

**How to avoid:** Use `<iframe>` (not `<embed>` or `<object>`). Verify with `curl -I` that the serve endpoint response does not include `X-Frame-Options: DENY` (the existing nginx setting is `SAMEORIGIN` which allows same-origin iframes — this is correct).

### Pitfall 4: Content-Disposition filename injection

**What goes wrong:** `original_name` is stored in the DB as `basename($file['name'])`. When used in the `Content-Disposition` header, a crafted filename containing `\r\n` or `;` can inject additional headers.

**How to avoid:** The serve endpoint MUST sanitize the filename before putting it in the header:
```php
$safeFilename = preg_replace('/[^\w\s\-\.]/', '', $doc['original_name']);
$safeFilename = basename($safeFilename) ?: 'document.pdf';
header('Content-Disposition: inline; filename="' . $safeFilename . '"');
```

### Pitfall 5: FilePond revert deletes the wrong file

**What goes wrong:** FilePond's `revert` config calls the DELETE endpoint with the server ID (UUID) it received from the upload response. If the DELETE endpoint doesn't verify tenant isolation, a crafted revert request could delete another tenant's document.

**How to avoid:** The DELETE endpoint already enforces `tenant_id` matching (same as `MeetingAttachmentController::delete()`). The `findById($id, $tenantId)` call fails for cross-tenant requests.

### Pitfall 6: FilePond field name mismatch

**What goes wrong:** FilePond sends the file under the field name `filepond` (default). If the PHP controller calls `api_file('file')` (as a naive copy from MeetingAttachmentController which uses `api_file('file')`), the upload silently fails with UPLOAD_ERR_NO_FILE.

**How to avoid:** Call `api_file('filepond')` not `api_file('file')` in `ResolutionDocumentController::upload()`. Or configure FilePond's `name` option to `'file'` to match the existing pattern.

---

## Code Examples

### Complete serve() headers pattern

```php
// Source: verified against ARCHITECTURE.md serve endpoint spec + PITFALLS.md §Pitfall 10
$safeFilename = preg_replace('/[^\w\s\-\.]/', '', $doc['original_name']);
$safeFilename = basename($safeFilename) ?: 'document.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $safeFilename . '"');
header('Content-Length: ' . (int)$doc['file_size']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('X-Frame-Options: SAMEORIGIN');

readfile($path);
exit;
```

### Hub document badge (HTML pattern)

```html
<!-- hub.htmx.html — resolution card, document status indicator -->
<span class="doc-badge" data-motion-id="{uuid}">
  <!-- Populated by hub.js after listForMotion API call -->
  <!-- "2 documents joints" or "Aucun document" -->
</span>
```

```javascript
// hub.js IIFE — after loading motions
function renderDocBadge(motionId, docCount) {
  var badge = document.querySelector('[data-motion-id="' + motionId + '"]');
  if (!badge) return;
  if (docCount === 0) {
    badge.textContent = 'Aucun document';
    badge.className = 'doc-badge doc-badge--empty';
  } else {
    badge.textContent = docCount + ' document' + (docCount > 1 ? 's joints' : ' joint');
    badge.className = 'doc-badge doc-badge--has-docs';
    badge.style.cursor = 'pointer';
    badge.addEventListener('click', function () {
      openDocViewer(motionId);
    });
  }
}
```

### voter view — SSE document.added handler

```javascript
// vote.js IIFE — add to existing SSE event handler
case 'document.added':
  if (data.motion_id === currentMotionId) {
    var btn = document.getElementById('btnConsultDocument');
    if (btn) {
      btn.hidden = false;
      btn.dataset.docId = data.document.id;
    }
  }
  break;
```

### ag-pdf-viewer bottom sheet CSS (design-system.css @layer v4)

```css
/* Source: ARCHITECTURE.md §2 Inline PDF Viewer Integration */
@layer v4 {
  ag-pdf-viewer[mode="sheet"] {
    position: fixed;
    inset: auto 0 0 0;
    height: 80dvh;
    background: var(--color-surface);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    z-index: var(--z-modal);
    transform: translateY(100%);
    transition: transform var(--duration-slow) var(--ease-out);
    box-shadow: 0 -4px 24px rgba(0,0,0,0.18);
  }

  ag-pdf-viewer[mode="sheet"][open] {
    transform: translateY(0);
  }

  ag-pdf-viewer[mode="inline"] {
    display: block;
    width: 100%;
    height: var(--pdf-viewer-height, 600px);
    border-radius: var(--radius);
    overflow: hidden;
  }
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `/tmp/ag-vote/` hardcoded | `AGVOTE_UPLOAD_DIR` env var → named Docker volume | Phase 25 | Files survive container restart; self-hosted deployments can configure path |
| No serve endpoint (files uploaded but invisible) | `GET /api/v1/resolution_document_serve?id=` with auth + security headers | Phase 25 | Voters can view PDFs for the first time |
| meeting-level attachments only | motion-level `resolution_documents` table | Phase 25 | Operators can attach specific PDFs to individual agenda items |
| No PDF in voter view | ag-pdf-viewer bottom sheet in vote.htmx.html | Phase 25 | Voters can consult resolution documents before casting ballots |

**Deprecated/outdated (to fix in this phase):**
- `MeetingAttachmentController` lines 63 and 118: hardcoded `/tmp/ag-vote/uploads/meetings/` — replace with `AG_UPLOAD_DIR . '/meetings/'`
- `docker-compose.yml` volume mount `app-storage:/tmp/ag-vote` — keep for backwards compat (logs, fonts) but add new `uploads:/var/agvote/uploads` volume

---

## Open Questions

1. **Voter auth path in serve()**
   - What we know: `ballot_result` and `meeting_stats` use `'role' => 'public'` and do their own auth check; vote tokens are validated against the `vote_tokens` table
   - What's unclear: The exact function call to verify a vote token holder's meeting membership — whether it's `api_current_vote_token_meeting_id()` or checked via `$_SESSION['vote_token']`
   - Recommendation: Read `VotePublicController` and `BallotsController::cast()` before writing `serve()` to find the exact voter auth helper pattern

2. **Hub side panel vs operator side panel**
   - What we know: Both desktop surfaces should show `mode="inline"` in a side panel; ag-pdf-viewer needs to open in a slide-over from right
   - What's unclear: Whether the side panel is a new CSS pattern or reuses ag-modal with a `position: fixed; right: 0` variant
   - Recommendation: Check if ag-modal has a `variant="slide-right"` or similar, otherwise define `ag-pdf-viewer[mode="panel"]` CSS in design-system.css alongside `mode="sheet"`

3. **operator console upload during live session — motion freeze state**
   - What we know: CONTEXT.md says "No lock after freeze" — documents can be added anytime
   - What's unclear: Whether the motion CRUD API has a freeze check that would need to be bypassed for document uploads specifically
   - Recommendation: Verify MotionsController/motions table for any `frozen_at` or `status` guard; if present, confirm document upload endpoint does NOT go through that guard

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` (root) |
| Quick run command | `vendor/bin/phpunit --testsuite Unit tests/Unit/ResolutionDocumentControllerTest.php` |
| Full suite command | `vendor/bin/phpunit` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PDF-01 | DB migration creates table with correct columns + indexes | manual (DB inspection) | n/a — migration SQL verified manually | n/a |
| PDF-02 | Upload rejects non-PDF MIME types | unit | `vendor/bin/phpunit tests/Unit/ResolutionDocumentControllerTest.php --filter testRejectsBadMime` | ❌ Wave 0 |
| PDF-02 | Upload rejects files > 10 MB | unit | `vendor/bin/phpunit tests/Unit/ResolutionDocumentControllerTest.php --filter testRejectsOversizeFile` | ❌ Wave 0 |
| PDF-02 | Upload stores file and returns UUID | unit | `vendor/bin/phpunit tests/Unit/ResolutionDocumentControllerTest.php --filter testUploadStoresFile` | ❌ Wave 0 |
| PDF-02 | Delete verifies tenant isolation | unit | `vendor/bin/phpunit tests/Unit/ResolutionDocumentControllerTest.php --filter testDeleteTenantIsolation` | ❌ Wave 0 |
| PDF-03 | Serve returns 401 for unauthenticated request | unit | `vendor/bin/phpunit tests/Unit/ResolutionDocumentControllerTest.php --filter testServeRequiresAuth` | ❌ Wave 0 |
| PDF-03 | Serve emits correct security headers | unit | `vendor/bin/phpunit tests/Unit/ResolutionDocumentControllerTest.php --filter testServeSecurityHeaders` | ❌ Wave 0 |
| PDF-04 | AGVOTE_UPLOAD_DIR constant reads env var | unit | `vendor/bin/phpunit tests/Unit/UploadSecurityTest.php --filter testUploadDirEnvVar` | ❌ Wave 0 |
| PDF-05 | Docker volume mount is writable at /var/agvote/uploads | manual (docker inspect) | n/a | n/a |
| PDF-06 | FilePond plugins registered before pond creation | unit (JS — manual-only) | manual | n/a |
| PDF-07 | ag-pdf-viewer defines 'open' observed attribute | unit (JS — manual-only) | manual | n/a |
| PDF-08 | SSE document.added triggers voter UI update | unit | `vendor/bin/phpunit tests/Unit/EventBroadcasterTest.php --filter testDocumentAddedEvent` | ❌ Wave 0 |
| PDF-09 | CVE check: pdfjs version >= 4.2.67 if used | manual (package inspection) | n/a | n/a |
| PDF-10 | Voter role sees no download button in ag-pdf-viewer | manual | manual | n/a |

### Sampling Rate

- **Per task commit:** `vendor/bin/phpunit --testsuite Unit tests/Unit/ResolutionDocumentControllerTest.php`
- **Per wave merge:** `vendor/bin/phpunit --testsuite Unit`
- **Phase gate:** `vendor/bin/phpunit` (full suite green before `/gsd:verify-work`)

### Wave 0 Gaps

- [ ] `tests/Unit/ResolutionDocumentControllerTest.php` — covers PDF-02, PDF-03 (upload/serve validation + security headers)
- [ ] `tests/Unit/EventBroadcasterTest.php` needs `testDocumentAddedEvent` — covers PDF-08 (if test file exists, add test case only)
- [ ] `tests/Unit/UploadSecurityTest.php` needs `testUploadDirEnvVar` — covers PDF-04 (file exists, add test)

Note: `tests/Unit/UploadSecurityTest.php` already exists and tests the existing MeetingAttachmentController upload security. Add new test cases there rather than creating a separate file for PDF-04.

---

## Sources

### Primary (HIGH confidence)

- Direct codebase: `app/Controller/MeetingAttachmentController.php` — exact template to copy; hardcoded `/tmp` confirmed at lines 63, 118
- Direct codebase: `app/Repository/MeetingAttachmentRepository.php` — repository method signatures to replicate
- Direct codebase: `database/migrations/20260219_meeting_attachments.sql` — migration DDL template
- Direct codebase: `public/api/v1/meeting_attachments.php` — routing stub pattern
- Direct codebase: `app/routes.php` — route registration syntax; `meeting_attachments` block at lines 225-229
- Direct codebase: `app/Core/Providers/RepositoryFactory.php` — accessor registration pattern lines 75-104
- Direct codebase: `app/WebSocket/EventBroadcaster.php` — `documentAdded()` static method pattern (follows `motionOpened` at line 49)
- Direct codebase: `public/assets/js/core/event-stream.js` — `eventTypes` array at lines 79-89 where `document.added` must be added
- Direct codebase: `deploy/nginx.conf` — CSP and security header inheritance behavior confirmed; no static uploads location block
- Direct codebase: `deploy/php.ini` — `upload_max_filesize = 10M`, `post_max_size = 12M` already set (no changes needed)
- Direct codebase: `docker-compose.yml` — current `app-storage:/tmp/ag-vote` volume + `read_only: true` constraint confirmed
- Direct codebase: `public/assets/js/components/ag-modal.js` — Web Component pattern (Shadow DOM, connectedCallback, open/close, custom events)
- Direct codebase: `phpunit.xml` — PHPUnit 10.5 config, Unit + Integration suites
- `.planning/research/STACK.md` §3 — FilePond CDN URLs, IIFE integration, PHP backend pattern (HIGH confidence, researched 2026-03-18)
- `.planning/research/ARCHITECTURE.md` §1, §2 — resolution_documents schema, serve endpoint spec, ag-pdf-viewer component API (HIGH confidence, verified via codebase)
- `.planning/research/PITFALLS.md` — CVE-2024-4367 details, security checklist, nginx CSP analysis (HIGH confidence, verified against official sources)

### Secondary (MEDIUM confidence)

- `.planning/research/STACK.md` §2 — PDF.js v5.5.207 bundle size estimates (size unconfirmed; version confirmed from npm)
- nginx.conf CSP analysis — `frame-src` not explicitly set; `default-src 'self'` governs iframes; same-origin iframes allowed — verified by reading nginx.conf CSP string directly

### Tertiary (LOW confidence)

- Vote token auth pattern for serve() endpoint — inferred from `'role' => 'public'` pattern; exact helper function name requires reading VotePublicController before implementing

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — FilePond and native browser PDF are locked decisions; versions verified from CDN listings in project research
- Architecture: HIGH — all patterns verified by direct codebase inspection; no assumptions
- Pitfalls: HIGH — P0 blockers confirmed by direct code inspection (lines 63, 118 of MeetingAttachmentController; missing serve endpoint confirmed)

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable domain; FilePond, PHP finfo, Docker volumes are not fast-moving)
