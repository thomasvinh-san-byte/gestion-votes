# Phase 66: Voter Document Access - Research

**Researched:** 2026-04-01
**Domain:** Frontend wiring — PHP/vanilla JS, ag-pdf-viewer Web Component, public API route
**Confidence:** HIGH

## Summary

Phase 66 is a pure frontend wiring phase. The backend serve endpoint (`/api/v1/meeting_attachment_serve`) already exists from Phase 65, with full dual-auth (session OR vote token). The only backend work is exposing a public list endpoint for meeting attachments — currently the list route is operator-only. Everything else mirrors patterns that already work in production for resolution documents.

The hub page needs a new "Documents de la seance" section in the right column (`hub-main`) placed above the motions card. It calls the new public list endpoint, then renders clickable document rows. Clicking opens `ag-pdf-viewer` in panel mode (reusing `openDocViewer` pattern). The vote page needs a "Documents" button in `motion-card-footer` (alongside the existing `btnConsultDocument`), loaded once at init (not per-motion), opening `ag-pdf-viewer` in sheet mode with the vote token appended.

**Primary recommendation:** Add a `meeting_attachments_public` list route with role=public and rate limit. Wire hub and vote page following exact resolution document patterns. No new components, no new controllers — only route addition + JS wiring + HTML additions.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None — all implementation choices are at Claude's discretion.

### Claude's Discretion
- Hub: add "Documents de la seance" section above motions card
- Vote page: add "Documents" button for meeting attachments (separate from per-motion btnConsultDocument)
- Use ag-pdf-viewer panel mode on hub, sheet mode on vote page
- Serve endpoint: `/api/v1/meeting_attachment_serve?id={id}`
- Vote token appended for unauthenticated voters: `&token={token}`
- Meeting attachments list endpoint: needs public route or alternative strategy

### Deferred Ideas (OUT OF SCOPE)
None.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ATTACH-03 | Voters can consult meeting attachments from the hub ("Documents de la seance" section with ag-pdf-viewer) | Hub already has loadDocBadges/openDocViewer panel pattern; new section mirrors hub-motions-card structure; needs public list API |
| ATTACH-04 | Voters can consult meeting attachments from the vote page (Documents button with ag-pdf-viewer sheet mode) | Vote page already has loadMotionDocs/openVoterDocViewer sheet pattern; token appending already implemented; single button loaded once at page init |
</phase_requirements>

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| ag-pdf-viewer.js | project-local | PDF display in sheet/panel/inline modes | Already in use for resolution documents; same component handles all modes |
| vanilla JS (IIFE) | ES5+ | Hub and vote page scripting | Project convention — no framework, no bundler, IIFE closures |
| PHP 8.x | project | Public list endpoint addition | One route registration in routes.php + listForMeeting() already exists |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| window.api() | project | Fetch wrapper with JSON parsing | All API calls in hub.js and vote.js use this |
| MeetingAttachmentController::listForMeeting() | project | Return attachment list | Already exists; only auth guard change needed (public route) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| New public list route | Use serve endpoint listing | Serve is file-serving only; list is needed for filenames/IDs; new route is correct |
| Panel mode on hub | Sheet mode on hub | Panel (side slide) is already established for hub resolution docs; sheet is mobile-only |

**Installation:** No new packages.

---

## Architecture Patterns

### Recommended Project Structure
No new files needed except HTML/CSS additions within existing files:

```
public/
├── hub.htmx.html              -- add hub-attachments-card section in hub-main
├── assets/js/pages/hub.js     -- add loadMeetingAttachments(), renderAttachments(), openAttachmentViewer()
├── assets/css/hub.css         -- add .hub-attachments-card styles (mirror .hub-motions-card)
├── vote.htmx.html             -- add btnMeetingDocs button in motion-card-footer
└── assets/js/pages/vote.js    -- add loadMeetingAttachments(), wireMeetingDocsBtn()
app/
└── routes.php                 -- add public GET route for meeting_attachments_public
```

### Pattern 1: Public List Route

The existing `meeting_attachments` GET route is operator-only. A second route `meeting_attachments_public` is needed for voters.

**What:** New GET route with role=public exposing `listForMeeting()`. The controller method already handles tenant resolution via `api_current_tenant_id()` — but for public (unauthenticated) callers this will return null. The controller needs a public-aware variant that resolves tenant from the meeting ID directly (or from the vote token), OR the approach is simpler: pass the meeting ID and resolve tenant from the DB without requiring session auth.

**Resolution:** The safest pattern is to add a `listPublic()` method to `MeetingAttachmentController` that mirrors `ResolutionDocumentController::listForMotion()` but accepts a vote token OR session for tenant resolution — exactly like `serve()`. This avoids exposing all tenant data.

**Alternative (simpler):** Since `meeting_attachment_serve` already handles dual auth per attachment, and voters know which meeting they are in (from SSE state / vote token), the list endpoint can resolve tenant from the vote token the same way `serve()` does. The voter passes `?meeting_id=X&token=Y` and the endpoint verifies the token belongs to that meeting, then returns the list.

**Recommended approach:** Add `listPublic()` to `MeetingAttachmentController` with the same dual-auth pattern as `serve()` — session OR vote token — filtered to the token's meeting. Register as `GET /api/v1/meeting_attachments_public` with role=public.

### Pattern 2: Hub — "Documents de la seance" Section

**What:** New card in `hub-main` column (right column), placed above `hubMotionsSection`.
**When to use:** Always when meeting has attachments. Hidden when empty (mirroring hubMotionsSection hidden behavior).

**HTML addition in hub.htmx.html** (before `hubMotionsSection`):
```html
<!-- Meeting attachments card -->
<div class="hub-attachments-card" id="hubAttachmentsSection" hidden>
  <div class="hub-attachments-header">
    <span class="hub-attachments-title">Documents de la s&eacute;ance</span>
    <span class="badge badge--neutral" id="hubAttachmentsCount">0</span>
  </div>
  <div id="hubAttachmentsList">
    <!-- Populated by hub.js loadMeetingAttachments() -->
  </div>
</div>
```

**JS addition in hub.js** (after `loadDocBadges` block):
```javascript
// Source: mirrors loadDocBadges/openDocViewer pattern (hub.js lines 253-304)
function loadMeetingAttachments(meetingId) {
  if (!meetingId) return;
  window.api('/api/v1/meeting_attachments_public?meeting_id=' + encodeURIComponent(meetingId))
    .then(function(resp) {
      var attachments = (resp && resp.attachments) ? resp.attachments : [];
      renderMeetingAttachments(meetingId, attachments);
    })
    .catch(function() {
      renderMeetingAttachments(meetingId, []);
    });
}

function renderMeetingAttachments(meetingId, attachments) {
  var section = document.getElementById('hubAttachmentsSection');
  var list = document.getElementById('hubAttachmentsList');
  var countEl = document.getElementById('hubAttachmentsCount');
  if (!section || !list) return;
  if (!attachments || !attachments.length) {
    section.setAttribute('hidden', '');
    return;
  }
  section.removeAttribute('hidden');
  if (countEl) countEl.textContent = String(attachments.length);
  var html = '';
  attachments.forEach(function(att) {
    html += '<div class="hub-attachment-row" data-attach-id="' + escapeHtml(att.id) + '" data-attach-name="' + escapeHtml(att.original_name || 'document.pdf') + '" style="cursor:pointer">'
      + svgIcon('file', 14) + ' '
      + '<span>' + escapeHtml(att.original_name || 'document.pdf') + '</span>'
      + '</div>';
  });
  list.innerHTML = html;
  list.querySelectorAll('.hub-attachment-row').forEach(function(row) {
    row.addEventListener('click', function() {
      openAttachmentViewer(row.dataset.attachId, row.dataset.attachName, attachments);
    });
  });
}

function openAttachmentViewer(attachId, attachName, allAttachments) {
  // Reuse or create panel viewer — distinct from resolution doc viewer
  var viewer = document.getElementById('meetingAttachViewer') || document.createElement('ag-pdf-viewer');
  if (!viewer.id) {
    viewer.id = 'meetingAttachViewer';
    viewer.setAttribute('mode', 'panel');
    viewer.setAttribute('allow-download', '');
    document.body.appendChild(viewer);
  }
  viewer.setAttribute('src', '/api/v1/meeting_attachment_serve?id=' + encodeURIComponent(attachId));
  viewer.setAttribute('filename', attachName || 'document.pdf');
  if (typeof viewer.open === 'function') viewer.open();
}
```

**Call site in `loadData()`**: add `loadMeetingAttachments(sessionId)` alongside the existing `setupConvocationBtn` call.

**Note on ag-pdf-viewer conflict:** The existing `openDocViewer` in hub.js queries `document.querySelector('ag-pdf-viewer')` without ID discrimination. The new viewer uses a distinct `id="meetingAttachViewer"` and queries by ID to avoid collision with the resolution doc viewer.

### Pattern 3: Vote Page — "Documents" Button

**What:** New button in `motion-card-footer`, alongside `btnConsultDocument`. Loaded once at page init (meeting attachments don't change per motion). Opens `ag-pdf-viewer` in sheet mode.
**When to use:** When meeting has attachments (button hidden otherwise).

**HTML addition in vote.htmx.html** (in `.motion-card-footer`, after `btnConsultDocument`):
```html
<button id="btnMeetingDocs" class="btn btn--outline btn--sm" hidden type="button"
  aria-label="Consulter les documents de la s&eacute;ance">
  <svg class="icon icon-text" aria-hidden="true" width="14" height="14" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
    <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
    <path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
  </svg>
  Documents
</button>
```

**JS addition in vote.js** (after `wireConsultDocBtn`):
```javascript
// Source: mirrors loadMotionDocs/openVoterDocViewer pattern (vote.js lines 839-898)
var _meetingAttachments = [];

function loadMeetingAttachments(meetingId) {
  _meetingAttachments = [];
  var btn = document.getElementById('btnMeetingDocs');
  if (btn) btn.hidden = true;
  if (!meetingId) return;

  var urlToken = new URLSearchParams(window.location.search).get('token');
  var url = '/api/v1/meeting_attachments_public?meeting_id=' + encodeURIComponent(meetingId);
  if (urlToken) url += '&token=' + encodeURIComponent(urlToken);

  window.api(url).then(function(resp) {
    if (resp && resp.attachments && resp.attachments.length > 0) {
      _meetingAttachments = resp.attachments;
      var b = document.getElementById('btnMeetingDocs');
      if (b) b.hidden = false;
    }
  }).catch(function() {
    // Silently fail — documents are supplementary
  });
}

function openMeetingAttachViewer(attachId, attachName) {
  var viewer = document.getElementById('meetingAttachViewer') || document.createElement('ag-pdf-viewer');
  if (!viewer.parentElement) {
    viewer.id = 'meetingAttachViewer';
    viewer.setAttribute('mode', 'sheet');
    // Voter read-only — no allow-download
    document.body.appendChild(viewer);
  }
  var serveUrl = '/api/v1/meeting_attachment_serve?id=' + encodeURIComponent(attachId);
  var urlToken = new URLSearchParams(window.location.search).get('token');
  if (urlToken) serveUrl += '&token=' + encodeURIComponent(urlToken);
  viewer.setAttribute('src', serveUrl);
  viewer.setAttribute('filename', attachName || 'document.pdf');
  if (typeof viewer.open === 'function') viewer.open();
}

function wireMeetingDocsBtn() {
  var btn = document.getElementById('btnMeetingDocs');
  if (!btn) return;
  btn.addEventListener('click', function() {
    if (!_meetingAttachments.length) return;
    // If only one document: open directly.
    // If multiple: open the first (multi-doc list is future scope).
    openMeetingAttachViewer(_meetingAttachments[0].id, _meetingAttachments[0].original_name || 'document.pdf');
  });
}
```

**Call sites:**
- `wireMeetingDocsBtn()` called once during init (alongside `wireConsultDocBtn`)
- `loadMeetingAttachments(meetingId)` called when meeting context is established (when meetingId becomes known, same timing as other meeting-scoped calls)

**Note on ag-pdf-viewer collision on vote page:** The vote page HTML contains `<ag-pdf-viewer id="resoPdfViewer">` which is the resolution doc inline viewer. The new meeting attachments sheet viewer uses `id="meetingAttachViewer"` appended to body, distinct from the resolution doc sheet viewer (which also uses `document.querySelector('ag-pdf-viewer')` in `openVoterDocViewer`). To avoid collision, `openMeetingAttachViewer` should query by ID, NOT by `document.querySelector('ag-pdf-viewer')`. The existing `openVoterDocViewer` is safe to leave as-is since it uses `document.querySelector` which will find `resoPdfViewer` first.

### Pattern 4: Backend — Public List Method

**What:** New `listPublic()` method on `MeetingAttachmentController`, new route.

```php
// In MeetingAttachmentController, add after listForMeeting():
public function listPublic(): void {
    $meetingId = api_query('meeting_id');
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('missing_meeting_id', 400);
    }

    // Dual auth: session users OR vote token holders
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
        // Verify token belongs to the requested meeting
        if ($tokenRow['meeting_id'] !== $meetingId) {
            api_fail('access_denied', 403);
        }
        $tenantId = $tokenRow['tenant_id'];
    }

    $items = $this->repo()->meetingAttachment()->listForMeeting($meetingId, $tenantId);
    // Return only safe fields (no stored_name)
    $safe = array_map(fn($a) => [
        'id' => $a['id'],
        'original_name' => $a['original_name'],
        'file_size' => $a['file_size'],
        'created_at' => $a['created_at'],
    ], $items);
    api_ok(['attachments' => $safe]);
}
```

**Route registration in routes.php** (in the Meeting attachments block):
```php
$router->map('GET', "{$prefix}/meeting_attachments_public",
    MeetingAttachmentController::class, 'listPublic',
    ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]
);
```

Note: `stored_name` is intentionally excluded from the public response — it would expose internal file paths. Only `id`, `original_name`, `file_size`, `created_at` are returned.

### Anti-Patterns to Avoid

- **Using `document.querySelector('ag-pdf-viewer')` for the new viewer:** Will collide with existing resolution document viewers. Always use `getElementById` with a named ID for the new meeting attachment viewers.
- **Calling `loadMeetingAttachments` per-motion on the vote page:** Meeting attachments are meeting-scoped, not motion-scoped. Load once when meeting context is established.
- **Exposing `stored_name` in the public list endpoint:** Internal path info — never return it to public callers.
- **Adding `allow-download` to the vote page viewer:** Vote page is voter read-only (PDF-10 rule). Hub viewer can have `allow-download` for operators.
- **Using the operator-only `meeting_attachments` route from the vote page:** Will 401 for unauthenticated voters. Always use `meeting_attachments_public`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF display | Custom iframe wrapper | ag-pdf-viewer Web Component | Already handles sheet/panel/inline, backdrop, close, keyboard, download |
| Dual-auth pattern | New auth logic | Copy `serve()` dual-auth verbatim | Exact same pattern tested in Phase 65 |
| API fetch | Custom XHR | `window.api()` helper | Project standard; handles JSON parsing, error propagation |
| Rate limiting | Custom throttle | Route-level `doc_serve` bucket | Already configured at 120/60s |

---

## Common Pitfalls

### Pitfall 1: ag-pdf-viewer querySelector Collision
**What goes wrong:** `document.querySelector('ag-pdf-viewer')` finds the first element — could be `resoPdfViewer` (inline, already hidden) or the resolution sheet viewer. Setting `src` on the wrong element causes a no-op open or wrong PDF.
**Why it happens:** The existing `openDocViewer` (hub) and `openVoterDocViewer` (vote) both use `querySelector` without ID filtering.
**How to avoid:** New meeting attachment viewers are always created/found by `getElementById('meetingAttachViewer')`. Never use `querySelector` for the new viewer.
**Warning signs:** PDF opens but shows wrong document, or viewer silently does nothing.

### Pitfall 2: Token Not Forwarded to List Endpoint
**What goes wrong:** `loadMeetingAttachments` called without `?token=` — backend returns 401 for unauthenticated voter, button stays hidden even if documents exist.
**Why it happens:** Forgetting to append the URL token to the list call (easy to do since `serve()` needs it but the list call does too).
**How to avoid:** Always extract `urlToken` from `window.location.search` before calling the list endpoint; append if present. Match the `openVoterDocViewer` pattern exactly.
**Warning signs:** Button never appears for token-authenticated voters; 401 in network tab.

### Pitfall 3: Exposing stored_name in Public List
**What goes wrong:** Returning the full attachment row (including `stored_name`) allows an attacker to infer internal storage paths.
**Why it happens:** Copying `listForMeeting()` return value without filtering.
**How to avoid:** Map to safe-fields-only array in `listPublic()`.

### Pitfall 4: Hub Section Placement
**What goes wrong:** Attachments section placed inside `hub-sidebar` (checklist column) instead of `hub-main` (right column).
**Why it happens:** Confusing the two-column layout.
**How to avoid:** Insert `hub-attachments-card` div inside `<div class="hub-main">`, before `hubMotionsSection`. Hub sidebar holds only the checklist card.

### Pitfall 5: loadMeetingAttachments Called Before meetingId is Known (vote page)
**What goes wrong:** Called with null/undefined meetingId, API call fails, button never shown.
**Why it happens:** Calling at wrong init time before meeting context is loaded.
**How to avoid:** Call `loadMeetingAttachments(meetingId)` only after `meetingId` is established in vote.js state — same timing guard used by `loadMotionDocs`.

---

## Code Examples

### Public List Endpoint — Dual Auth Pattern
```php
// Source: MeetingAttachmentController::serve() — Phase 65, exact same pattern
$userId = api_current_user_id();
if ($userId !== null) {
    $tenantId = api_current_tenant_id();
} else {
    $rawToken = api_query('token');
    if ($rawToken === '') { api_fail('authentication_required', 401); }
    $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);
    $tokenRow = $this->repo()->voteToken()->findByHash($tokenHash);
    if ($tokenRow === null) { api_fail('invalid_token', 401); }
    if ($tokenRow['meeting_id'] !== $meetingId) { api_fail('access_denied', 403); }
    $tenantId = $tokenRow['tenant_id'];
}
```

### Hub — Opening ag-pdf-viewer (Panel Mode)
```javascript
// Source: hub.js openDocViewer() lines 282-304 — adapted for meeting attachments
var viewer = document.getElementById('meetingAttachViewer') || document.createElement('ag-pdf-viewer');
if (!viewer.id) {
  viewer.id = 'meetingAttachViewer';
  viewer.setAttribute('mode', 'panel');
  viewer.setAttribute('allow-download', '');
  document.body.appendChild(viewer);
}
viewer.setAttribute('src', '/api/v1/meeting_attachment_serve?id=' + encodeURIComponent(attachId));
viewer.setAttribute('filename', attachName || 'document.pdf');
if (typeof viewer.open === 'function') viewer.open();
```

### Vote Page — Token-Appended Serve URL
```javascript
// Source: vote.js openVoterDocViewer() lines 875-879 — exact same token pattern
var serveUrl = '/api/v1/meeting_attachment_serve?id=' + encodeURIComponent(attachId);
var urlToken = new URLSearchParams(window.location.search).get('token');
if (urlToken) serveUrl += '&token=' + encodeURIComponent(urlToken);
```

### Route Registration Pattern
```php
// Source: routes.php lines 233-236 — exact same pattern for meeting_attachment_serve
$router->map('GET', "{$prefix}/meeting_attachments_public",
    MeetingAttachmentController::class, 'listPublic',
    ['role' => 'public', 'rate_limit' => ['doc_serve', 120, 60]]
);
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Operator-only attachment list | Public list via token OR session | Phase 66 | Voters can discover document IDs without operator session |
| Resolution docs only on vote page | Meeting attachments + resolution docs | Phase 66 | Voters see both meeting-level and motion-level PDFs |

---

## Open Questions

1. **Multiple attachments on vote page — how to display them?**
   - What we know: `openVoterDocViewer` opens a single doc; no multi-doc list UI exists on vote page.
   - What's unclear: If a meeting has 3 attachments, does tapping "Documents" open only the first, or show a list?
   - Recommendation: Open the first attachment directly (consistent with resolution doc behavior). Multi-doc list is deferred. If `_meetingAttachments.length > 1`, the button label could say "Documents (3)" but still opens doc 1. This keeps the implementation minimal and consistent with the existing single-doc pattern.

2. **ag-pdf-viewer script tag on hub.htmx.html**
   - What we know: `hub.htmx.html` does NOT currently include `ag-pdf-viewer.js` in its script tags (checked — only ag-toast, ag-popover, ag-quorum-bar, ag-confirm are loaded as modules).
   - What's unclear: Is ag-pdf-viewer auto-loaded by another mechanism, or must it be added?
   - Recommendation: Add `<script type="module" src="/assets/js/components/ag-pdf-viewer.js"></script>` to hub.htmx.html. Verify the resolution doc panel already works on hub (it does per existing hub.js code) — if it works today without the script tag, there is already a loading mechanism. Investigate before adding a duplicate.

3. **ag-pdf-viewer on vote page — module tag**
   - What we know: `vote.htmx.html` also does not have an explicit `ag-pdf-viewer` module import in its script tags. Yet vote.js uses it. Likely loaded implicitly via `vote-ui.js` or another mechanism.
   - Recommendation: Check `vote-ui.js` for the import. Do not add a duplicate module tag.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (project-standard, see tests/Unit/) |
| Config file | phpunit.xml (project root) |
| Quick run command | `./vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php` |
| Full suite command | `./vendor/bin/phpunit tests/Unit/` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ATTACH-03 | `listPublic()` returns 200 + attachments for session auth | unit | `./vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --filter testListPublic` | ❌ Wave 0 |
| ATTACH-03 | `listPublic()` returns 200 + attachments for valid vote token | unit | `./vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --filter testListPublicToken` | ❌ Wave 0 |
| ATTACH-03 | `listPublic()` returns 401 with no auth | unit | `./vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --filter testListPublicNoAuth` | ❌ Wave 0 |
| ATTACH-03 | `listPublic()` returns 403 when token meeting_id != requested meeting_id | unit | `./vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --filter testListPublicWrongMeeting` | ❌ Wave 0 |
| ATTACH-03 | `listPublic()` excludes stored_name from response | unit | `./vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php --filter testListPublicExcludesStoredName` | ❌ Wave 0 |
| ATTACH-04 | Hub documents section renders when attachments exist | manual | Manual browser check | N/A |
| ATTACH-04 | Vote page Documents button shows when attachments exist | manual | Manual browser check | N/A |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Unit/MeetingAttachmentControllerTest.php`
- **Per wave merge:** `./vendor/bin/phpunit tests/Unit/`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/MeetingAttachmentControllerTest.php` needs new test methods for `listPublic()` (file exists; add methods)
- [ ] No frontend unit tests needed — pure wiring, manual verification suffices

---

## Sources

### Primary (HIGH confidence)
- Direct code read: `app/Controller/MeetingAttachmentController.php` — serve() dual-auth pattern, listForMeeting() method
- Direct code read: `app/Controller/ResolutionDocumentController.php` — serve() dual-auth pattern to mirror
- Direct code read: `app/routes.php` — route registration patterns, public/operator role configuration
- Direct code read: `public/assets/js/pages/hub.js` lines 253-304 — loadDocBadges, renderDocBadge, openDocViewer
- Direct code read: `public/assets/js/pages/vote.js` lines 839-898 — loadMotionDocs, openVoterDocViewer, wireConsultDocBtn
- Direct code read: `public/assets/js/components/ag-pdf-viewer.js` — full component API: modes, attributes, open()/close()
- Direct code read: `public/hub.htmx.html` — hub-main layout, existing section structure
- Direct code read: `public/vote.htmx.html` — motion-card-footer, existing button structure
- Direct code read: `app/Repository/MeetingAttachmentRepository.php` — listForMeeting() signature and columns
- Direct code read: `.planning/phases/66-voter-document-access/66-CONTEXT.md` — constraints and integration points

### Secondary (MEDIUM confidence)
None needed — all findings come from direct code inspection.

### Tertiary (LOW confidence)
- Open question about ag-pdf-viewer script loading on hub/vote pages: not directly verified, flagged for investigation in Wave 0.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries already in use and inspected directly
- Architecture: HIGH — exact patterns copied from working production code
- Pitfalls: HIGH — identified from direct code analysis (querySelector collision, token forwarding, stored_name exposure)
- Backend: HIGH — `listPublic()` is a straightforward composition of `serve()` auth + `listForMeeting()` data
- Open questions: MEDIUM — ag-pdf-viewer loading mechanism not fully traced

**Research date:** 2026-04-01
**Valid until:** 2026-05-01 (stable codebase — no external dependencies changing)
