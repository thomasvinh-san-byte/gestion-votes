# Phase 65: Attachment Upload & Serve - Research

**Researched:** 2026-04-01
**Domain:** PHP file upload, dual-auth file serving, FilePond integration, operator console wiring
**Confidence:** HIGH

## Summary

This phase wires together already-built backend infrastructure (MeetingAttachmentController upload/list/delete, MeetingAttachmentRepository, meeting_attachments table) with missing frontend and a missing `serve()` method. Every implementation pattern required already exists in the codebase and can be copied verbatim with parameter substitution.

The backend is ~80% complete. The gap is: (1) `MeetingAttachmentController::serve()` does not exist yet, (2) no route for it, (3) wizard step 1 has no FilePond section, and (4) the operator console "Séance" tab has no attachment management UI. All four gaps have exact mirrors to copy from.

One important divergence to watch: `MeetingAttachmentController::upload()` uses `api_file('file')` while the FilePond library sends files under the field name `filepond` by default. Either the controller must be updated to accept `filepond`, or FilePond's `name` option must be set to `file`. The resolution document upload already handles this correctly using `api_file('filepond')` — the meeting attachment controller should be aligned to match.

**Primary recommendation:** Copy ResolutionDocumentController::serve() verbatim, swap `resolutionDocument` repo calls for `meetingAttachment`, adjust file path from `/resolutions/{motion_id}/` to `/meetings/{meeting_id}/`, add route, then mirror wizard and operator console patterns.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- None specified — all implementation choices at Claude's discretion

### Claude's Discretion
- All implementation choices — infrastructure phase
- `MeetingAttachmentController` already has upload/list/delete (operator-only routes)
- Mirror `ResolutionDocumentController::serve()` dual-auth pattern (session OR vote token) for the new serve endpoint
- Mirror wizard step 2 FilePond pattern (`initResolutionPond`) for meeting attachments in step 1
- Mirror operator console resolution doc upload pattern (`addDocUploadToMotionCard`) for meeting attachment management
- Use existing `ag-pdf-viewer` Web Component for preview
- PDF-only upload (10MB max, matching existing MeetingAttachmentController limits)
- Route: `meeting_attachment_serve` with `role=public` + `rate_limit` (matching `resolution_document_serve` pattern)

### Deferred Ideas (OUT OF SCOPE)
- None
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ATTACH-01 | Operator can upload PDF attachments to a meeting from the wizard (step 1) | FilePond initResolutionPond pattern in wizard.js lines 530-588 is the exact template to mirror. File field name mismatch (see Pitfall 1) must be resolved. |
| ATTACH-02 | Operator can manage (view, add, delete) attachments from the operator console | addDocUploadToMotionCard pattern in operator-tabs.js lines 3306-3376 is the exact template. Target: operator.htmx.html tab-seance section after settings-grid. |
| ATTACH-05 | Secure serve endpoint allows voter access (session OR vote token auth) | ResolutionDocumentController::serve() lines 153-213 is the exact template. Path swap: `/resolutions/{motion_id}/` → `/meetings/{meeting_id}/`. Route: meeting_attachment_serve. |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| FilePond | 4.32.12 (CDN) | Drag-and-drop PDF upload with validation | Already in wizard.htmx.html head — no install needed |
| filepond-plugin-file-validate-type | 1.2.9 (CDN) | MIME type enforcement client-side | Already loaded in wizard.htmx.html |
| filepond-plugin-file-validate-size | 2.2.9 (CDN) | 10MB cap enforcement client-side | Already loaded in wizard.htmx.html |
| PHPUnit | project standard | Unit tests for serve() validation paths | See tests/Unit/ResolutionDocumentControllerTest.php as template |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ag-pdf-viewer | Web Component (local) | PDF iframe display with sheet/panel/inline modes | Operator console preview only (Phase 66 adds voter-side use) |
| AgToast | global JS | Toast notifications for upload/delete feedback | Already used in addDocUploadToMotionCard |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| FilePond (wizard) | Native file input | addDocUploadToMotionCard uses native input — for wizard, FilePond is already loaded and provides a better UX |
| Native fetch (console) | FilePond | Console mirrors addDocUploadToMotionCard which uses native fetch — simpler, no extra dependency in operator page |

**Installation:** No new packages. All dependencies already present.

## Architecture Patterns

### Recommended Project Structure

No new files/directories needed. Changes are additions to existing files:

```
app/Controller/MeetingAttachmentController.php  ← add serve() method
app/routes.php                                  ← add meeting_attachment_serve route
public/wizard.htmx.html                         ← add attachment section in step 0 HTML
public/assets/js/pages/wizard.js                ← add initAttachmentPond() + loadExistingAttachments()
public/operator.htmx.html                       ← add attachment management section in tab-seance
public/assets/js/pages/operator-tabs.js         ← add loadMeetingAttachments() + add/delete wiring
tests/Unit/MeetingAttachmentControllerTest.php  ← add serve() test cases
```

### Pattern 1: serve() Dual-Auth (copy from ResolutionDocumentController)

**What:** GET endpoint returning a PDF binary. Accepts either session auth (operator/admin) or a vote token `?token=` query param. Verifies the attachment belongs to the token's meeting before serving.

**When to use:** Any time a file must be accessible to both operators AND voters.

**Exact source to copy — ResolutionDocumentController::serve() lines 153-213:**
```php
// Source: app/Controller/ResolutionDocumentController.php lines 153-213
public function serve(): void
{
    $id = api_query('id');
    if ($id === '' || !api_is_uuid($id)) {
        api_fail('missing_id', 400);
    }

    $userId = api_current_user_id();

    if ($userId !== null) {
        $tenantId = api_current_tenant_id();
    } else {
        $rawToken = api_query('token');
        if ($rawToken === '') {
            api_fail('authentication_required', 401);
        }
        $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);
        $tokenRow = $this->repo()->voteToken()->findByHash($tokenHash);
        if ($tokenRow === null) {
            api_fail('invalid_token', 401);
        }
        $tenantId = $tokenRow['tenant_id'];
        $tokenMeetingId = $tokenRow['meeting_id'];
    }

    // For meeting attachments: use meetingAttachment() repo, not resolutionDocument()
    $att = $this->repo()->meetingAttachment()->findById($id, $tenantId);
    if (!$att) {
        api_fail('not_found', 404);
    }

    // Cross-check: voter token must belong to same meeting as attachment
    if ($userId === null && isset($tokenMeetingId) && $tokenMeetingId !== $att['meeting_id']) {
        api_fail('access_denied', 403);
    }

    // Path: /meetings/{meeting_id}/{stored_name}  (not /resolutions/{motion_id}/)
    $path = AG_UPLOAD_DIR . '/meetings/' . $att['meeting_id'] . '/' . $att['stored_name'];
    if (!file_exists($path) || !is_readable($path)) {
        api_fail('file_not_found', 404);
    }

    $safeFilename = preg_replace('/[^\w\s\-\.]/', '', $att['original_name']);
    $safeFilename = basename($safeFilename) ?: 'document.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $safeFilename . '"');
    header('Content-Length: ' . (int) $att['file_size']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('X-Frame-Options: SAMEORIGIN');

    readfile($path);
    exit;
}
```

### Pattern 2: Route Registration (copy from resolution_document_serve)

**Source: app/routes.php lines 240-243**
```php
// Add in the "── Meeting attachments ──" block:
$router->map('GET', "{$prefix}/meeting_attachment_serve",
    MeetingAttachmentController::class, 'serve',
    ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]
);
```

Note: reuses same `doc_serve` rate limit bucket as resolution_document_serve — same 120/60 window.

### Pattern 3: FilePond in Wizard Step 0 (mirror initResolutionPond)

**What:** FilePond instance pointing to `/api/v1/meeting_attachments`, appending `meeting_id` after the meeting is created. In the wizard, the meeting does not exist until step 4 creation — so upload must be deferred OR the attachment section only activates after creation.

**Key insight:** The wizard creates the meeting at the very end (btnCreate). FilePond for resolution documents in step 2 uses a `motionId` that is populated at resolution-creation time (after step 1 save). For meeting attachments in step 1, the `meeting_id` does not exist yet.

**Two valid approaches:**
1. **Deferred upload** — show the FilePond area in step 1 but only activate it after meeting creation (redirect to hub where operator can manage attachments)
2. **Post-creation only** — skip wizard FilePond, add attachments from operator console `tab-seance` instead

Given CONTEXT.md says "wizard step 1 using FilePond, persisted to DB and filesystem", approach 1 requires solving the chicken-and-egg problem. The cleanest resolution: add FilePond to wizard step 1 but disable upload until step 4 creates the meeting, then re-enable OR move the upload section to the review step 3 (after motions are created but before final submission). Alternatively: create the meeting draft at step 0 completion and immediately have a meetingId.

**Looking at the current wizard flow:** Step 0 (btnNext0) just calls `showStep(1)` — it does NOT save to the server. The meeting is only created at btnCreate (step 3 review). This means a FilePond in step 1 has no meetingId to send.

**Recommended approach for the planner:** Add FilePond to wizard step 1 but show a notice "Les pièces jointes peuvent être ajoutées après création depuis la console opérateur" if meetingId is absent, OR trigger meeting draft creation on step 0 completion so meetingId is available. The simpler path is: add a post-creation attachment section in step 3 (review) where `meeting_id` is available from a pre-creation save.

**Simplest functional approach:** Add the FilePond attachment section to wizard step 0 HTML but initialize it only after the meeting is created (end of step 3 creation flow), showing it as a "last step" before redirecting to hub. This avoids changing the creation flow.

### Pattern 4: Operator Console Attachment Management (mirror addDocUploadToMotionCard)

**What:** A section added to `tab-seance` in `operator.htmx.html`, showing a list of attachments with upload + delete buttons. Loaded by operator-tabs.js when a meeting is selected.

**Source: operator-tabs.js lines 3306-3376 (addDocUploadToMotionCard)**

For meeting attachments, the equivalent function is simpler — no per-motion scoping, just meeting-level:

```javascript
// To add to operator-tabs.js
function loadMeetingAttachments(meetingId) {
    window.api('/api/v1/meeting_attachments?meeting_id=' + encodeURIComponent(meetingId))
        .then(function(resp) {
            renderAttachmentList(resp && resp.attachments ? resp.attachments : []);
        }).catch(function() {});
}

function renderAttachmentList(attachments) {
    var listEl = document.getElementById('meetingAttachmentList');
    if (!listEl) return;
    // Render each attachment with a delete button
    // Same pattern as resolution doc badges
}
```

The upload button uses native file input + fetch (same as addDocUploadToMotionCard), posting to `/api/v1/meeting_attachments` with `meeting_id` and `file` field.

**CRITICAL: field name is `file` not `filepond`** — MeetingAttachmentController::upload() calls `api_file('file')` (line 39). This is correct for native file input. FilePond must be configured with `name: 'file'` if used.

### Anti-Patterns to Avoid

- **Don't rename the file field in native input**: `addDocUploadToMotionCard` sends `filepond` for resolution docs because that controller expects it. Meeting attachments controller expects `file`. Do not cross-wire these.
- **Don't bypass tenant check**: serve() must always scope `findById($id, $tenantId)` — never skip tenantId check.
- **Don't emit SSE events for meeting attachments**: ResolutionDocumentController emits SSE on upload/delete (document added/removed during live session). Meeting attachments are pre-session documents — no SSE needed (and no EventBroadcaster method exists for them).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Dual-auth file serving | Custom auth middleware | Copy ResolutionDocumentController::serve() | Handles session + token + tenant scoping + file headers correctly |
| File type validation | Custom MIME detector | Existing finfo + extension check already in upload() | Already implemented, PHP-level MIME is reliable |
| PDF drag-and-drop | Custom drag zone | FilePond already loaded in wizard.htmx.html | 3 CDN lines, tested, registered plugins |
| Rate limiting | Custom counter | Existing `['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]` config | Already works for resolution_document_serve |
| Repository UUID generation | `uniqid()` or `random_bytes()` | `$repo->generateUuid()` | Consistent with all other controllers |

**Key insight:** This is an integration/wiring phase. Every sub-problem has a solved template. Custom implementation is strictly worse.

## Common Pitfalls

### Pitfall 1: FilePond Field Name vs Controller Expectation
**What goes wrong:** FilePond sends files under field name `filepond` by default. `MeetingAttachmentController::upload()` calls `api_file('file')` (not `api_file('filepond')`). Result: `upload_error` 400 response.

**Why it happens:** The two controllers were written independently. ResolutionDocumentController uses `api_file('filepond')` (matching FilePond default). MeetingAttachmentController uses `api_file('file')`.

**How to avoid:** Two options:
1. Add FilePond option `name: 'file'` in the pond configuration to match the controller
2. Update MeetingAttachmentController::upload() to use `api_file('filepond')` to match the pattern

Option 2 is more consistent with the codebase pattern (ResolutionDocumentController uses `filepond`). But changing the controller affects the existing upload tests. If native file input in the operator console also uses `file`, keep `api_file('file')` in the controller and configure FilePond to use `name: 'file'`.

**Warning signs:** Upload always returns 400 `upload_error` even with a valid PDF.

### Pitfall 2: No meetingId at Wizard Step 1 Upload Time
**What goes wrong:** FilePond fires immediately on file selection. Meeting has not been created yet in step 0 — there is no `meeting_id` to send.

**Why it happens:** The wizard creates the meeting only at btnCreate (step 3). FilePond in step 1 would have an empty meetingId.

**How to avoid:** Two paths:
1. Disable the FilePond dropzone until meeting creation, then wire the upload section at step 3/review time
2. Save a meeting draft on btnNext0 to get a meetingId early (changes current flow)

The planner should decide which path to implement. Path 1 is safer and non-breaking.

**Warning signs:** Controller returns 400 `missing_meeting_id` because `meeting_id` is empty in the form data.

### Pitfall 3: Wrong File Path in serve()
**What goes wrong:** serve() uses `/resolutions/{motion_id}/` path instead of `/meetings/{meeting_id}/`. File not found even when attachment exists in DB.

**Why it happens:** Direct copy-paste from ResolutionDocumentController::serve() without path adjustment.

**How to avoid:** Use `$att['meeting_id']` for the path, not `$att['motion_id']` (meeting_attachments has no motion_id column).

**Warning signs:** serve() returns 404 `file_not_found` but `findById` succeeds.

### Pitfall 4: Missing SSE broadcast (inverse pitfall — don't ADD it)
**What goes wrong:** Adding `EventBroadcaster::documentAdded()` calls to meeting attachment upload/delete (mimicking resolution document controller too closely).

**Why it happens:** Copy-paste from ResolutionDocumentController without noticing the SSE calls.

**How to avoid:** Meeting attachments are NOT live-session documents. No SSE broadcast exists for them. The broadcaster has `documentAdded($meetingId, $motionId, ...)` — requires motionId. Meeting attachments have no motionId.

**Warning signs:** PHP fatal error on EventBroadcaster call with wrong argument count.

### Pitfall 5: test method count assertion fails after adding serve()
**What goes wrong:** `testControllerHasRequiredMethods()` in `MeetingAttachmentControllerTest.php` only asserts `['listForMeeting', 'upload', 'delete']`. After adding `serve()`, this test still passes but does not cover the new method.

**How to avoid:** Update the test to include `'serve'` in the method list, and add serve() validation tests mirroring `ResolutionDocumentControllerTest.php` lines 264-345.

## Code Examples

Verified patterns from official codebase:

### Route registration pattern (existing resolution_document_serve)
```php
// Source: app/routes.php lines 240-243
$router->map('GET', "{$prefix}/resolution_document_serve",
    ResolutionDocumentController::class, 'serve',
    ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]
);
// Add equivalent for meeting_attachment_serve:
$router->map('GET', "{$prefix}/meeting_attachment_serve",
    MeetingAttachmentController::class, 'serve',
    ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]
);
```

### FilePond initialization with correct field name
```javascript
// Source: wizard.js lines 540-586 (initResolutionPond), adapted for meeting attachments
var pond = FilePond.create(inputEl, {
  name: 'file',  // CRITICAL: matches api_file('file') in MeetingAttachmentController
  acceptedFileTypes: ['application/pdf'],
  maxFileSize: '10MB',
  allowMultiple: true,
  server: {
    process: {
      url: '/api/v1/meeting_attachments',
      method: 'POST',
      headers: function() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': meta ? meta.content : ''
        };
      },
      ondata: function(formData) {
        if (meetingId) formData.append('meeting_id', meetingId);
        return formData;
      },
      onload: function(response) {
        try {
          var data = JSON.parse(response);
          var listEl = containerEl.querySelector('.attachment-list');
          if (listEl && data.attachment) renderAttachmentCard(listEl, data.attachment);
          return data.attachment ? data.attachment.id : '';
        } catch (e) { return ''; }
      }
    },
    revert: null
  },
  labelIdle: 'Glissez un PDF ici ou <span class="filepond--label-action">parcourir</span>'
});
```

### Native file input upload (operator console)
```javascript
// Source: operator-tabs.js lines 3316-3368 (addDocUploadToMotionCard), adapted
// For operator console: use native file input, POST to meeting_attachments with field 'file'
var formData = new FormData();
formData.append('file', file);          // 'file' not 'filepond'
formData.append('meeting_id', meetingId);
fetch('/api/v1/meeting_attachments', { method: 'POST', headers: headers, body: formData })
```

### serve() test pattern (add to MeetingAttachmentControllerTest)
```php
// Source: tests/Unit/ResolutionDocumentControllerTest.php lines 268-345 (adapted)
public function testServeWithNoAuthRequiresToken(): void
{
    putenv('APP_AUTH_ENABLED=1');
    \AgVote\Core\Security\AuthMiddleware::reset();
    $this->setQueryParams(['id' => self::ATTACH_ID]);
    $repo = $this->createMock(MeetingAttachmentRepository::class);
    $this->injectRepos([MeetingAttachmentRepository::class => $repo]);

    try {
        $result = $this->callController(MeetingAttachmentController::class, 'serve');
    } finally {
        putenv('APP_AUTH_ENABLED=0');
        \AgVote\Core\Security\AuthMiddleware::reset();
    }
    $this->assertSame(401, $result['status']);
    $this->assertSame('authentication_required', $result['body']['error']);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| MeetingAttachmentController had no serve() | Add serve() mirroring ResolutionDocumentController | This phase | Enables voter access in Phase 66 |
| No attachment UI in wizard | Add FilePond section to step 0 | This phase | Fulfills ATTACH-01 |
| Placeholder "Aucun document attaché." in hub accordion | Wired to actual API | This phase (operator side) + Phase 66 (voter side) | Operator can manage attachments |

**Existing and correct:**
- `meeting_attachments` table and repository: fully implemented
- `MeetingAttachmentController::upload/list/delete`: fully implemented, operator-only routes registered
- Storage path: `AG_UPLOAD_DIR/meetings/{meeting_id}/{uuid}.pdf`

## Open Questions

1. **FilePond wizard step timing (meetingId chicken-and-egg)**
   - What we know: the wizard creates the meeting only at step 3 (btnCreate). Step 0 FilePond would have no meetingId.
   - What's unclear: should the planner (a) create meeting draft on step 0 completion, (b) show attachments only at step 3/review after creation, or (c) disable the pond until the meeting is created?
   - Recommendation: The planner should choose option (b) — add an attachment section to the wizard step 3 review panel that activates after meeting creation, before the redirect to hub. This is the least invasive approach. Alternatively, the CONTEXT.md says "step 1" — this may mean step 1 in user-facing numbering (which maps to step 0 in code, data-step="0"), implying attachments should be shown there but uploads blocked until the meeting is created.

2. **Rate limit bucket for meeting_attachment_serve**
   - What we know: resolution_document_serve uses `['doc_serve', 120, 60]` bucket.
   - What's unclear: should meeting_attachment_serve share the same bucket (combined 120/60) or have its own?
   - Recommendation: Share `doc_serve` bucket — simpler, same use case. If burst traffic becomes a concern, separate buckets can be introduced.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (project standard) |
| Config file | `phpunit.xml` (project root) |
| Quick run command | `php vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --no-coverage` |
| Full suite command | `php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ATTACH-01 | FilePond wizard HTML present in step 0 | smoke (manual) | Visual check in browser | N/A |
| ATTACH-01 | upload() accepts valid PDF | unit (existing) | `php vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --no-coverage` | Yes |
| ATTACH-02 | listForMeeting() returns attachments | unit (existing) | Same as above | Yes |
| ATTACH-02 | delete() removes attachment from DB | unit (existing) | Same as above | Yes |
| ATTACH-05 | serve() missing ID returns 400 | unit (new) | `php vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --filter testServe --no-coverage` | No — Wave 0 |
| ATTACH-05 | serve() unauthenticated returns 401 | unit (new) | Same as above | No — Wave 0 |
| ATTACH-05 | serve() invalid token returns 401 | unit (new) | Same as above | No — Wave 0 |
| ATTACH-05 | serve() valid session, doc not found returns 404 | unit (new) | Same as above | No — Wave 0 |
| ATTACH-05 | serve() token for wrong meeting returns 403 | unit (new) | Same as above | No — Wave 0 |

### Sampling Rate
- **Per task commit:** `php vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --no-coverage`
- **Per wave merge:** `php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full unit suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/MeetingAttachmentControllerTest.php` — add `testServe*` methods covering ATTACH-05 (file exists, add to existing test class)
- [ ] `testControllerHasRequiredMethods()` — update to include `'serve'` in the methods list

*(Existing test infrastructure covers upload/list/delete. Only serve() test cases are missing.)*

## Sources

### Primary (HIGH confidence)
- `app/Controller/MeetingAttachmentController.php` — full upload/list/delete implementation, file field name `file`
- `app/Controller/ResolutionDocumentController.php` — serve() dual-auth pattern lines 153-213
- `app/Repository/MeetingAttachmentRepository.php` — DB schema, available methods (listForMeeting, create, findById, delete)
- `app/routes.php` — existing meeting_attachments routes, resolution_document_serve pattern
- `public/assets/js/pages/wizard.js` — initResolutionPond() lines 530-588, FilePond config
- `public/assets/js/pages/operator-tabs.js` — addDocUploadToMotionCard() lines 3306-3376
- `public/wizard.htmx.html` — FilePond CDN versions loaded, step 0 HTML structure
- `public/operator.htmx.html` — tab-seance structure (lines 413-517), hubDocuments accordion
- `tests/Unit/MeetingAttachmentControllerTest.php` — existing test coverage and patterns
- `tests/Unit/ResolutionDocumentControllerTest.php` — serve() test pattern to replicate

### Secondary (MEDIUM confidence)
- `.planning/phases/65-attachment-upload-serve/65-CONTEXT.md` — implementation constraints and integration points
- `.planning/REQUIREMENTS.md` — ATTACH-01, ATTACH-02, ATTACH-05 scope definitions

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all dependencies already present in the project
- Architecture: HIGH — exact source templates verified from existing codebase files
- Pitfalls: HIGH — identified from direct code inspection of field names and constructor paths
- Test patterns: HIGH — existing ResolutionDocumentControllerTest.php provide verbatim templates

**Research date:** 2026-04-01
**Valid until:** 2026-05-01 (stable PHP codebase, no external dependencies being added)
