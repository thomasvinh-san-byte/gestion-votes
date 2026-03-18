# Phase 21: Post-Session & PV - Research

**Researched:** 2026-03-18
**Domain:** Post-session workflow wiring (PHP + vanilla JS), Dompdf PDF generation, meeting state machine
**Confidence:** HIGH

## Summary

The post-session page UI is **fully built** (Phase 11) with a 4-step stepper, complete HTML structure, and comprehensive JS controller. The backend API endpoints all exist. The work is pure **wiring and bug-fixing** -- connecting the existing frontend to the correct backend endpoints and fixing specific broken references.

Four concrete issues need fixing: (1) Step 1 calls a non-existent `meeting_motions.php` instead of the existing `motions_for_meeting.php`; (2) Step 2 does not call the consolidation endpoint before transitioning to validated; (3) Step 3's PDF generation is functional but requires `composer install` for Dompdf and the `generateReport` method requires meeting to be validated first -- the "Generate" button in step 3 calls `meeting_generate_report.php` which will fail if step 2 hasn't been completed; (4) Step 4's archive button calls `meetings_archive.php` which routes to a GET listing endpoint rather than performing an archive action, and the `exportCorrespondance` link references a non-existent endpoint.

**Primary recommendation:** Fix the four endpoint/wiring bugs in postsession.js, remove the Correspondance export link from HTML, and ensure Dompdf vendor is installed.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PST-01 | Step 1 displays verified vote results (fix endpoint motions_for_meeting) | postsession.js line 118 calls `/api/v1/meeting_motions.php` which does NOT exist. Must change to `/api/v1/motions_for_meeting.php`. The `listForMeeting` action on MotionsController returns items with `votes_for`, `votes_against`, `votes_abstain`, `decision` fields -- matches what `loadResultsTable()` expects. |
| PST-02 | Step 2 triggers consolidation then closed->validated transition | `doValidate()` calls `meeting_transition.php` with `to_status: validated` but does NOT call `meeting_consolidate.php` first. The consolidation endpoint exists and works (MeetingWorkflowController::consolidate). Must add consolidation call before transition. |
| PST-03 | Step 3 generates downloadable PV PDF via Dompdf | MeetingReportsController::generatePdf() is fully implemented with Dompdf. Requires `composer install` for vendor. The `btnExportPDF` link correctly points to `meeting_generate_report_pdf.php`. The `btnGenerateReport` calls `meeting_generate_report.php` which requires `validated_at` to be set. |
| PST-04 | Step 4 archive action works, export_correspondance link removed | Archive button calls `meetings_archive.php` which routes to `MeetingsController::archive()` -- a GET listing endpoint, NOT an archive action. Must change to use `meeting_transition.php` with `to_status: archived`. The `exportCorrespondance` link in HTML references non-existent `export_correspondance.php` -- must be removed from HTML. |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Dompdf | ^3.1 | PDF generation from HTML | Already in composer.json, already used in MeetingReportsController |
| Vanilla JS | ES5+ | Frontend controller | Project convention -- no frameworks |
| PHP 8.4 | 8.4+ | Backend API | Project standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| window.api() | built-in | Fetch wrapper | All API calls from JS |
| Shared.btnLoading() | built-in | Button loading state | Already used throughout postsession.js |
| Shared.openModal() | built-in | Confirmation modals | Archive confirmation already uses this |
| Utils.escapeHtml() | built-in | XSS prevention | All user content rendering |

## Architecture Patterns

### Existing Post-Session Architecture
```
postsession.htmx.html          # Full 4-step stepper UI (498 lines)
  |
  +-- postsession.js            # Controller (585 lines, all 4 steps implemented)
  |     |
  |     +-- Step 1: loadVerification()   -> /api/v1/meeting_motions.php (BROKEN)
  |     +-- Step 2: loadValidation()     -> /api/v1/meeting_workflow_check.php (OK)
  |     |           doValidate()         -> /api/v1/meeting_transition.php (MISSING consolidation)
  |     +-- Step 3: loadPV()             -> /api/v1/meetings.php?id= (OK)
  |     |           btnGenerateReport    -> /api/v1/meeting_generate_report.php (OK)
  |     |           btnExportPDF         -> /api/v1/meeting_generate_report_pdf.php (OK)
  |     +-- Step 4: loadSendArchive()    -> Sets export links (BROKEN correspondance)
  |                 btnArchive           -> /api/v1/meetings_archive.php (WRONG ENDPOINT)
  |
  +-- Backend Controllers:
        MeetingReportsController  -> generateReport(), generatePdf(), report(), sendReport()
        MeetingWorkflowController -> transition(), consolidate(), workflowCheck()
        MotionsController         -> listForMeeting()
        MeetingsController        -> archive() (GET listing -- NOT archive action!)
```

### Pattern: API Call with Loading/Error States
```javascript
// Source: postsession.js existing pattern
async function doValidate() {
    var btn = document.getElementById('btnValidate');
    Shared.btnLoading(btn, true);
    try {
        var res = await window.api('/api/v1/endpoint.php', { meeting_id: meetingId });
        var d = res.body;
        if (d && d.ok) {
            setNotif('success', 'Message');
        } else {
            setNotif('error', d.error || 'Erreur');
        }
    } catch (e) {
        setNotif('error', 'Erreur reseau');
    } finally {
        Shared.btnLoading(btn, false);
    }
}
```

### Pattern: Meeting State Transition
```javascript
// Source: MeetingWorkflowController::transition()
// POST /api/v1/meeting_transition.php
// Body: { meeting_id: "uuid", to_status: "validated" }
// Response: { ok: true, data: { meeting_id, from_status, to_status, transitioned_at, warnings } }
```

### Pattern: Consolidation Before Validation
```javascript
// Source: MeetingWorkflowController::consolidate()
// POST /api/v1/meeting_consolidate.php
// Body: { meeting_id: "uuid" }
// Response: { ok: true, data: { updated_motions: N } }
// MUST be called before transition to 'validated'
```

### Anti-Patterns to Avoid
- **Calling non-existent endpoints:** `meeting_motions.php` does not exist; use `motions_for_meeting.php`
- **Skipping consolidation:** The transition to validated works without consolidation, but official results won't be up-to-date
- **Using wrong endpoint for actions:** `meetings_archive.php` is a GET listing, not a POST action

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| PDF generation | Custom PDF library | Dompdf (already integrated in MeetingReportsController) | Complete template with styled HTML already exists |
| State transitions | Custom status update queries | meeting_transition.php endpoint | Has locking, auth checks, audit logging, SSE broadcast |
| Results consolidation | Custom tally aggregation | meeting_consolidate.php endpoint | OfficialResultsService handles weighted votes, policies |
| Modal confirmations | Custom dialog | Shared.openModal() | Already used for archive confirmation |

## Common Pitfalls

### Pitfall 1: Wrong Endpoint Name
**What goes wrong:** Step 1 calls `/api/v1/meeting_motions.php` which returns 404
**Why it happens:** The endpoint was named `motions_for_meeting.php` in Phase 19 but postsession.js was written earlier in Phase 11 using a different convention
**How to avoid:** Change the URL in `loadVerification()` to `/api/v1/motions_for_meeting.php`
**Warning signs:** Step 1 results table stays on "Chargement..." forever, verify alert never shows

### Pitfall 2: Archive Uses Wrong Endpoint
**What goes wrong:** Clicking "Archiver" calls `meetings_archive.php` which is a GET listing endpoint
**Why it happens:** The endpoint file exists but routes to `MeetingsController::archive()` which is a listing method
**How to avoid:** Change the archive action to call `meeting_transition.php` with `to_status: 'archived'`
**Warning signs:** Archive button returns a list of archived meetings instead of archiving the current one

### Pitfall 3: Missing Consolidation Before Validation
**What goes wrong:** Meeting transitions to validated but official results (official_for, official_against, etc.) may not be up-to-date
**Why it happens:** `doValidate()` only calls `meeting_transition.php` but doesn't call `meeting_consolidate.php` first
**How to avoid:** Add consolidation call before transition in `doValidate()`
**Warning signs:** PV PDF shows stale or zero values for vote results

### Pitfall 4: Dompdf Not Installed
**What goes wrong:** `meeting_generate_report_pdf.php` returns a 500 error
**Why it happens:** `composer install` has not been run; vendor directory doesn't exist
**How to avoid:** Run `composer install` as a prerequisite
**Warning signs:** Class 'Dompdf\Dompdf' not found error

### Pitfall 5: Export Correspondance Link Dead
**What goes wrong:** Clicking the Correspondance export link returns 404
**Why it happens:** `export_correspondance.php` does not exist and is explicitly out of scope
**How to avoid:** Remove the entire `exportCorrespondance` export item from the HTML
**Warning signs:** 404 error on click

### Pitfall 6: Generate Report Requires Validated State
**What goes wrong:** "Generate" button in step 3 fails with `meeting_not_validated` error
**Why it happens:** `MeetingReportsController::generateReport()` checks `validated_at !== null`
**How to avoid:** Ensure step 2 (validation) is completed before step 3 is accessible. The current stepper already enforces sequential flow via the `btnSuivant.disabled` logic.
**Warning signs:** 409 error when clicking Generate before completing validation

## Code Examples

### Fix 1: Step 1 Endpoint (PST-01)
```javascript
// BEFORE (broken):
var res = await window.api('/api/v1/meeting_motions.php?meeting_id=' + encodeURIComponent(meetingId));

// AFTER (correct):
var res = await window.api('/api/v1/motions_for_meeting.php?meeting_id=' + encodeURIComponent(meetingId));
```

### Fix 2: Consolidation Before Validation (PST-02)
```javascript
// Source: pattern from MeetingWorkflowController::consolidate()
async function doValidate() {
    var btn = document.getElementById('btnValidate');
    Shared.btnLoading(btn, true);
    try {
        // Step A: Consolidate official results first
        var consRes = await window.api('/api/v1/meeting_consolidate.php', { meeting_id: meetingId });
        if (!consRes.body || !consRes.body.ok) {
            setNotif('error', consRes.body.error || 'Erreur de consolidation');
            return;
        }

        // Step B: Transition to validated
        var res = await window.api('/api/v1/meeting_transition.php', { meeting_id: meetingId, to_status: 'validated' });
        var d = res.body;
        if (d && d.ok) {
            setNotif('success', 'Seance validee avec succes');
            var btnNext = document.getElementById('btnSuivant');
            if (btnNext) btnNext.disabled = false;
            loadValidation(); // refresh state
        } else {
            setNotif('error', d.error || 'Erreur de validation');
        }
    } catch (e) {
        setNotif('error', 'Erreur reseau');
    } finally {
        Shared.btnLoading(btn, false);
    }
}
```

### Fix 3: Archive Action (PST-04)
```javascript
// BEFORE (wrong -- calls a GET listing endpoint):
var res = await window.api('/api/v1/meetings_archive.php', { meeting_id: meetingId });

// AFTER (correct -- uses the state machine transition endpoint):
var res = await window.api('/api/v1/meeting_transition.php', { meeting_id: meetingId, to_status: 'archived' });
```

### Fix 4: Remove Correspondance Export (PST-04)
```html
<!-- REMOVE this entire block from postsession.htmx.html -->
<div class="ps-export-item">
  <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-mail"></use></svg>
  <span class="flex-1">Correspondance</span>
  <a class="btn btn-secondary btn-sm" id="exportCorrespondance" href="#">
    <svg class="icon icon-text" aria-hidden="true"><use href="/assets/icons.svg#icon-download"></use></svg>
  </a>
</div>
```

Also remove `exportCorrespondance` from the `links` object in `loadSendArchive()`:
```javascript
// REMOVE this line from the links object:
exportCorrespondance: '/api/v1/export_correspondance.php?meeting_id=',
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| meeting_motions.php | motions_for_meeting.php | Phase 19 (2026-03-16) | Endpoint naming was standardized; postsession.js not updated |
| meetings_archive.php for archiving | meeting_transition.php with to_status:archived | Phase 20 (2026-03-17) | State machine handles all transitions uniformly |

## Open Questions

1. **Hub checklist validation state**
   - What we know: The hub checklist tracks preparation items (title, date, members, resolutions, convocations, documents). It does NOT track validated/archived states.
   - What's unclear: PST-02 says "the hub checklist reflects the validated state" -- does this mean adding a new checklist item, or is the status bar sufficient?
   - Recommendation: The hub already has a `renderStatusBar()` that shows the current step. If the meeting status is reflected in the wizard_status API response, the status bar will show it. The existing status bar may already be sufficient. If a checklist update is needed, add a "Seance validee" item to `CHECKLIST_ITEMS`.

2. **Composer install for Dompdf**
   - What we know: `composer.json` has `dompdf/dompdf: ^3.1` but vendor directory doesn't exist in this environment
   - What's unclear: Whether `composer install` should be part of this phase's tasks or is assumed to be done
   - Recommendation: Include `composer install` as a prerequisite step in the plan. The PDF generation code is already complete.

3. **Generate button vs Export PDF button in Step 3**
   - What we know: Two paths exist: `btnGenerateReport` calls `meeting_generate_report.php` (returns HTML preview), `btnExportPDF` links to `meeting_generate_report_pdf.php` (returns PDF download). Both require validated meeting.
   - What's unclear: Whether the "Generate" button should also work before validation (preview mode)
   - Recommendation: The PDF endpoint already supports `?preview=1` for draft mode. The HTML generate endpoint does not. For simplicity, keep current behavior (require validation before generate).

## Sources

### Primary (HIGH confidence)
- `public/assets/js/pages/postsession.js` -- Full JS controller, all 4 steps implemented
- `public/postsession.htmx.html` -- Complete HTML with 4-step stepper, all panels
- `app/Controller/MeetingReportsController.php` -- generateReport(), generatePdf(), sendReport()
- `app/Controller/MeetingWorkflowController.php` -- transition(), consolidate(), workflowCheck()
- `app/Controller/MotionsController.php` -- listForMeeting() with vote counts
- `app/Controller/MeetingsController.php` -- archive() is a GET listing, summary()
- `public/api/v1/` -- All endpoint files verified for existence

### Secondary (MEDIUM confidence)
- `public/exports/pv.html` + `pv-print.js` -- Separate print-friendly PV view (may be legacy)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries already in composer.json, all controllers already exist
- Architecture: HIGH - Full codebase inspection of every relevant file
- Pitfalls: HIGH - Every broken endpoint verified by checking file existence

**Research date:** 2026-03-18
**Valid until:** 2026-04-18 (stable project, no external dependencies changing)
