# Architecture Research

**Domain:** Full-stack session lifecycle wiring — no-framework PHP + vanilla JS voting platform
**Researched:** 2026-03-16
**Confidence:** HIGH (direct codebase inspection; no external sources required)

## Standard Architecture

### System Overview

```
┌──────────────────────────────────────────────────────────────────────────┐
│                          BROWSER (Vanilla JS)                             │
│                                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐  │
│  │  wizard.js   │  │   hub.js     │  │ operator-    │  │postsession  │  │
│  │  (IIFE+var)  │  │  (IIFE+var)  │  │ tabs/exec/   │  │   .js       │  │
│  │              │  │              │  │ motions/     │  │ (IIFE+var)  │  │
│  │  4-step form │  │  6-step      │  │ realtime.js  │  │ 4-step PV   │  │
│  │  localStorage│  │  checklist   │  │  (ES const)  │  │  workflow   │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  └──────┬──────┘  │
│         │                 │                  │                  │         │
│  ┌──────▼─────────────────▼──────────────────▼──────────────────▼──────┐  │
│  │          Core JS Infrastructure (utils.js / shared.js / shell.js)    │  │
│  │  window.api(url, data)  |  MeetingContext  |  EventStream (SSE)      │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│         │ HTTP + CSRF                                SSE (EventSource)      │
└─────────┼──────────────────────────────────────────────────────────────────┘
          │ /api/v1/*                                  /api/v1/events.php
┌─────────▼──────────────────────────────────────────────────────────────────┐
│                          PHP BACKEND (index.php → Router)                   │
│                                                                              │
│  ┌────────────────┐  ┌──────────────┐  ┌───────────────────────────────┐   │
│  │MeetingsCtrl    │  │MotionsCtrl   │  │OperatorCtrl / WorkflowCtrl    │   │
│  │createMeeting() │  │createOrUpdate│  │workflowState() / transition() │   │
│  │index()         │  │open() close()│  │openVote() / consolidate()     │   │
│  │summary() etc   │  │tally()       │  │                               │   │
│  └───────┬────────┘  └──────┬───────┘  └──────────────┬────────────────┘   │
│          │                  │                          │                    │
│  ┌───────▼──────────────────▼──────────────────────────▼────────────────┐  │
│  │              Services Layer (VoteEngine, WorkflowService, etc.)       │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  RepositoryFactory → 27 Repositories (PDO / PostgreSQL)              │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │  EventBroadcaster → Redis list sse:events:{meeting_id} → events.php  │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Location |
|-----------|----------------|----------|
| `wizard.js` | 4-step meeting creation form; localStorage draft; API POST on step 4 | `public/assets/js/pages/wizard.js` |
| `hub.js` | Reads `?id=` param; fetches `wizard_status`; renders 6-step checklist, KPIs, documents panel | `public/assets/js/pages/hub.js` |
| `dashboard.js` | Fetches `GET /api/v1/dashboard`; renders KPI counts and sessions list | `public/assets/js/pages/dashboard.js` |
| `operator-tabs.js` | Tab container + meeting selector + mode switch setup/exec; OpS bridge for sub-modules | `public/assets/js/pages/operator-tabs.js` |
| `operator-exec.js` | KPI strip, quorum modal, proclaim/vote-toggle actions, agenda sidebar; uses OpS bridge | `public/assets/js/pages/operator-exec.js` |
| `operator-motions.js` | Loads motions list from `motions_for_meeting`; renders resolution cards | `public/assets/js/pages/operator-motions.js` |
| `operator-realtime.js` | SSE connection via `EventStream.connect()`; polling fallback; updates exec view on events | `public/assets/js/pages/operator-realtime.js` |
| `postsession.js` | 4-step post-session: verification (results table), validation, PV generation, send/archive | `public/assets/js/pages/postsession.js` |
| `MeetingsController` | CRUD for meetings; `createMeeting()` — only creates, does NOT batch-import members/motions | `app/Controller/MeetingsController.php` |
| `DashboardController` | `index()` — meeting list + attendance + current motion; `wizardStatus()` — hub checklist data | `app/Controller/DashboardController.php` |
| `OperatorController` | `workflowState()` — full state snapshot; `openVote()` — auto-selects next motion + creates tokens | `app/Controller/OperatorController.php` |
| `MeetingWorkflowController` | State transitions (draft→scheduled→frozen→live→closed→validated→archived), consolidate | `app/Controller/MeetingWorkflowController.php` |
| `MotionsController` | Create/update/open/close/tally motions; `motions_for_meeting` list | `app/Controller/MotionsController.php` |
| `MeetingReportsController` | `generateReport()` renders PV HTML; `generatePdf()` for download; `sendReport()` emails it | `app/Controller/MeetingReportsController.php` |
| `EventBroadcaster` | Static helpers push events to Redis `sse:events:{meeting_id}` list with file fallback | `app/WebSocket/EventBroadcaster.php` |
| `event-stream.js` | `EventStream.connect(meetingId, handlers)` — SSE client with auto-reconnect and polling fallback | `public/assets/js/core/event-stream.js` |


## API Endpoint Inventory: Exists vs. Needs Creation

### Endpoints That EXIST and Are Ready

| Endpoint | Method | Controller::method | Used By |
|----------|--------|--------------------|---------|
| `POST /api/v1/meetings` | POST | `MeetingsController::createMeeting` | `wizard.js` btnCreate |
| `GET /api/v1/dashboard` | GET | `DashboardController::index` | `dashboard.js` |
| `GET /api/v1/wizard_status` | GET | `DashboardController::wizardStatus` | `hub.js` loadData |
| `GET /api/v1/meetings` | GET | `MeetingsController::index` | meetings page |
| `GET /api/v1/meeting_summary` | GET | `MeetingsController::summary` | `postsession.js` steps 2+4 |
| `GET /api/v1/meeting_workflow_check` | GET | `MeetingWorkflowController::workflowCheck` | `postsession.js` step 2 |
| `POST /api/v1/meeting_transition` | POST | `MeetingWorkflowController::transition` | `postsession.js` doValidate |
| `GET /api/v1/motions_for_meeting` | GET | `MotionsController::listForMeeting` | `operator-motions.js`, `pv-print.js` |
| `POST /api/v1/motions` | POST | `MotionsController::createOrUpdate` | operator setup tab |
| `POST /api/v1/motions_open` | POST | `MotionsController::open` | operator exec |
| `POST /api/v1/motions_close` | POST | `MotionsController::close` | operator exec |
| `POST /api/v1/motion_tally` | POST | `MotionsController::tally` | operator exec |
| `POST /api/v1/ballots_cast` | POST | `BallotsController::cast` | `vote.htmx.html` voter |
| `GET /api/v1/ballots_result` | GET | `BallotsController::result` | voter confirmation |
| `GET /api/v1/operator_workflow_state` | GET | `OperatorController::workflowState` | `operator-tabs.js` |
| `POST /api/v1/operator_open_vote` | POST | `OperatorController::openVote` | operator exec (opens motion + generates tokens) |
| `GET /api/v1/attendances` | GET | `AttendancesController::listForMeeting` | operator attendance tab |
| `POST /api/v1/attendances_upsert` | POST | `AttendancesController::upsert` | operator attendance tab |
| `POST /api/v1/attendances_bulk` | POST | `AttendancesController::bulk` | operator attendance tab |
| `GET /api/v1/members` | GET | `MembersController::index` | operator setup + members page |
| `POST /api/v1/members` | POST | `MembersController::create` | members page |
| `POST /api/v1/members_import_csv` | POST | `ImportController::membersCsv` | members page import |
| `POST /api/v1/motions_import_csv` | POST | `ImportController::motionsCsv` | operator setup |
| `GET /api/v1/quorum_status` | GET | `QuorumController::status` | operator KPIs |
| `GET /api/v1/meeting_generate_report` | POST | `MeetingReportsController::generateReport` | `postsession.js` step 3 |
| `GET /api/v1/meeting_generate_report_pdf` | GET | `MeetingReportsController::generatePdf` | `postsession.js` PDF download link |
| `POST /api/v1/meeting_report_send` | POST | `MeetingReportsController::sendReport` | `postsession.js` step 4 |
| `POST /api/v1/meetings_archive` | POST | `MeetingsController::archive` | `postsession.js` step 4 |
| `GET /api/v1/events` | GET/SSE | `events.php` (direct file) | `event-stream.js` |
| `POST /api/v1/meeting_consolidate` | POST | `MeetingWorkflowController::consolidate` | operator post-vote |

### Endpoints That Are MISSING (Need Creation)

| Endpoint | Purpose | Blocking Which Feature |
|----------|---------|------------------------|
| `GET /api/v1/meeting_motions` | Alias/variant of `motions_for_meeting` for post-session results table | `postsession.js` step 1 calls `/api/v1/meeting_motions.php` — this file does not exist; `motions_for_meeting` is the correct existing endpoint. Fix: update `postsession.js` to call the correct URL. |

**Critical finding:** `postsession.js` line 118 calls `/api/v1/meeting_motions.php` — this endpoint does not exist in `public/api/v1/` or in `app/routes.php`. The correct endpoint is `GET /api/v1/motions_for_meeting?meeting_id=...`. This is a one-line fix in `postsession.js`.

### Payload Mismatch: Wizard vs. Backend

The wizard `buildPayload()` sends:
```js
{
  title, type, date, time, place, address, quorum, defaultMaj,
  members: [...],    // array of {nom, lot, email, voix}
  resolutions: [...] // array of {title, desc, maj, key, secret}
}
```

`MeetingsController::createMeeting()` accepts only the `ValidationSchemas::meeting()` fields:
- `title` (required)
- `meeting_type` (wizard sends `type` — mismatch)
- `scheduled_at` (wizard sends `date` + `time` separately — mismatch)
- `location` (wizard sends `place` — mismatch)
- `description`, `quorum_policy_id`, `vote_policy_id` (optional)

The backend **silently ignores** `members`, `resolutions`, `quorum`, `defaultMaj`, `date`, `time`, `place`, `type`. The wizard creates a bare meeting shell with only the title persisted correctly. The `members` and `resolutions` from the wizard payload are discarded.

**What needs to happen after `POST /api/v1/meetings` returns `meeting_id`:**
1. Members must be bulk-imported via `POST /api/v1/members` (individual) or `POST /api/v1/members_import_csv`
2. Resolutions must be bulk-imported via `POST /api/v1/motions` (one per resolution)
3. The wizard either needs multi-step posting (create → add members → add motions) OR the backend needs a new `POST /api/v1/meetings/create_with_wizard` endpoint that accepts the full payload in one transaction.

**Recommended pattern:** Keep the single API call but expand `createMeeting()` to handle optional `members[]` and `resolutions[]` arrays in a transaction, rather than requiring multiple round-trips from the frontend. This matches the existing IdempotencyGuard pattern.

## Data Flow: Complete Session Lifecycle

### Flow 1: Create (wizard → hub)

```
wizard.htmx.html
    ↓ Step 1-3: user fills form, localStorage draft saves on blur
    ↓ Step 4: recap shown, user clicks "Créer la séance"

wizard.js::buildPayload()
    → api('/api/v1/meetings', payload)          [POST]
    → MeetingsController::createMeeting()
        → ValidationSchemas::meeting()->validate($data)
        → MeetingRepository::create(id, tenant, title, ...)
        → PolicyRepository::listVotePolicies() — assigns defaults
        → audit_log('meeting_created')
        → IdempotencyGuard::store(result)
        → api_ok({ meeting_id, title }, 201)

wizard.js::then(res)
    → clearDraft() from localStorage
    → sessionStorage.setItem('ag-vote-toast', ...)
    → window.location.href = '/hub.htmx.html?id=' + meeting_id

hub.htmx.html loads
    → hub.js::checkToast() → reads sessionStorage, shows ag-toast
    → hub.js::loadData()
        → api('/api/v1/wizard_status?meeting_id=' + id)    [GET]
        → DashboardController::wizardStatus()
            → WizardRepository::getMeetingBasics()
            → WizardRepository::countAttendances()
            → WizardRepository::getMotionsCounts()
            → WizardRepository::hasPresident()
            → api_ok({ meeting_id, meeting_title, members_count,
                       motions_total, has_president, quorum_met, ... })
    → hub.js::mapApiDataToSession(data) — normalizes field names
    → applySessionToDOM(sessionData)
    → renderKpis(sessionData)
    → renderChecklist(sessionData)  — 6-item checklist driven by API data
    → renderDocuments(files)
```

### Flow 2: Members (hub checklist → operator attendance tab)

```
Hub step 2 (convocations):
    hub.js → navigation to /operator.htmx.html?meeting_id=...

operator.htmx.html (setup mode → "Participants" tab)
    → operator-attendance.js::loadAttendance()
        → api('/api/v1/attendances?meeting_id=' + id)   [GET]
        → AttendancesController::listForMeeting()
        → renders attendance rows

    user marks present:
        → api('/api/v1/attendances_upsert', { meeting_id, member_id, mode:'present' })
        → AttendancesController::upsert()
        → EventBroadcaster::attendanceUpdated()
        → SSE event 'attendance.updated' → operator-realtime.js updates KPIs
```

### Flow 3: Votes (operator console → live vote → ballot cast)

```
operator.htmx.html (exec mode, motion selected)
    → operator-exec.js click "Ouvrir vote"
        → api('/api/v1/operator_open_vote', { meeting_id, motion_id })  [POST]
        → OperatorController::openVote()
            → MeetingRepository::updateFields(status='live') if not live
            → MotionRepository::markOpenedInMeeting()
            → MeetingRepository::updateCurrentMotion()
            → AttendanceRepository::listEligibleMemberIds()
            → VoteTokenRepository::insertWithExpiry() for each eligible
            → EventBroadcaster::motionOpened(meetingId, motionId)
            → api_ok({ meeting_id, motion_id, generated })

    EventBroadcaster pushes to Redis sse:events:{meeting_id}
    event-stream.js::EventSource receives SSE message
        → operator-realtime.js::handleSSEEvent('motion.opened')
            → OpS.fn.loadResolutions() — refreshes motion list
            → OpS.fn.loadBallots(motionId) — refreshes tally
            → OpS.fn.setMode('exec') if not already

voter receives token by email / QR code
    → GET /vote.php?token=xxx  (server-rendered form)
    → voter submits ballot:
        → POST /api/v1/ballots_cast { token, value }
        → BallotsController::cast()
            → VoteTokenService::validateAndConsume(token)
            → BallotRepository::insert()
            → VoteEngine::computeDecision() — pure calculation
            → EventBroadcaster::voteCast(meetingId, motionId, tally)
            → api_ok({ recorded: true, tally })

    SSE event 'vote.cast' received by operator-realtime.js
        → OpS.fn.loadBallots(motionId) — live tally update
        → OpS.fn.refreshExecView() — KPI strip update
```

### Flow 4: Results (motions close → consolidation)

```
operator closes motion:
    → api('/api/v1/motions_close', { meeting_id, motion_id })  [POST]
    → MotionsController::close()
    → MotionRepository::markClosed()
    → EventBroadcaster::motionClosed()

all motions closed:
    → OperatorController::workflowState() shows canConsolidate=true
    → api('/api/v1/meeting_consolidate', { meeting_id })  [POST]
    → MeetingWorkflowController::consolidate()
    → records final tally decisions
```

### Flow 5: PV (postsession.htmx.html)

```
postsession.htmx.html
    → reads ?meeting_id= from URL
    → Step 1 (Vérification):
        → api('/api/v1/motions_for_meeting?meeting_id=...')   [GET]  ← CURRENT BUG: calls meeting_motions.php
        → MotionsController::listForMeeting()
        → loadResultsTable(motions) — 5-column results table

    → Step 2 (Validation):
        → api('/api/v1/meeting_workflow_check?meeting_id=...')  [GET]
        → MeetingWorkflowController::workflowCheck()
        → api('/api/v1/meeting_summary?meeting_id=...')         [GET]
        → MeetingsController::summary()
        → user clicks "Valider":
            → api('/api/v1/meeting_transition', { meeting_id, to_status:'validated' })
            → MeetingWorkflowController::transition()

    → Step 3 (Procès-verbal):
        → api('/api/v1/meeting_generate_report', { meeting_id })  [POST]
        → MeetingReportsController::generateReport()
        → MeetingReportService::renderHtml()
        → api_ok({ html, hash })
        → preview shown in iframe

    → Step 4 (Envoi & Archivage):
        → export links set directly to /api/v1/export_*.php?meeting_id=...
        → api('/api/v1/meeting_report_send', { meeting_id })  [POST]
        → MeetingReportsController::sendReport() — emails PV
        → meeting archived via POST /api/v1/meetings_archive
```

### Flow 6: Real-time SSE

```
operator-realtime.js::connectSSE()
    → EventStream.connect(meetingId, { onEvent, onConnect, onDisconnect })
    → event-stream.js creates EventSource('/api/v1/events.php?meeting_id=...')
    → events.php polls Redis list 'sse:events:{meeting_id}' (60s TTL, 100-event cap)
    → Falls back to /tmp/agvote-sse-{id}.json if Redis unavailable

Event types dispatched:
    'vote.cast'          → refresh ballot tally for motion_id
    'motion.opened'      → refresh resolutions list, switch to exec mode
    'motion.closed'      → refresh resolutions list, show result
    'attendance.updated' → refresh quorum KPI
    'quorum.updated'     → update quorum bar
    'meeting.status_changed' → update meeting status badge
    'speech.queue_updated'   → update speech queue panel
```

## Integration Points: New vs. Modified

### Must Fix (Bugs — Not New Features)

| File | Issue | Fix |
|------|-------|-----|
| `postsession.js` line 118 | Calls nonexistent `/api/v1/meeting_motions.php` | Change to `/api/v1/motions_for_meeting.php` |
| `wizard.js` payload | `type` not mapped to `meeting_type`; `date`+`time` not combined to `scheduled_at`; `place` not mapped to `location` | Either (a) fix `buildPayload()` to send correct field names, OR (b) expand backend to accept wizard field names |
| `wizard.js` / backend | `members[]` and `resolutions[]` in payload are silently discarded | Backend must process them OR wizard must do multi-step POSTs |
| `hub.js` checklist | `convocationsSent` check uses `data.convocation_status === 'sent'` but `wizard_status` does not return this field | Add `convocation_status` to `DashboardController::wizardStatus()` response |

### Must Create (New Backend)

| Endpoint | Type | What It Does | Needed By |
|----------|------|--------------|-----------|
| Extend `POST /api/v1/meetings` | Modify existing | Accept optional `members[]` and `resolutions[]`; in same transaction: create members if not exist, create motions | `wizard.js` one-shot creation |
| Or: add `wizard_create_full` endpoint | New controller method | Atomic: create meeting + bulk members + bulk motions in transaction | Alternative to modifying `createMeeting()` |

### Must Create (New Frontend Wiring)

| File | What to Wire | Backend Endpoint |
|------|--------------|-----------------|
| `hub.js` | Hub step navigation button actions (convocations send, documents upload) | `POST /api/v1/invitations_send_bulk`, `POST /api/v1/meeting_attachments` |
| `hub.js` | Remove demo data fallback; show error state when API fails | N/A — replace `DEMO_SESSION` and `DEMO_FILES` with error handling |
| `dashboard.js` | Remove demo data fallback (`showFallback()` static data) | Already calls `GET /api/v1/dashboard` — just remove static fallback |
| `operator-tabs.js` | Meeting selector must propagate `meeting_id` to all sub-modules via `MeetingContext.set()` | Existing `MeetingContext` service |
| `operator-realtime.js` | SSE must start when a meeting is selected, not just on page load | Trigger `connectSSE()` on `MeetingContext:change` event |
| `postsession.js` | Step 1 `loadVerification()` fix (wrong endpoint) | `GET /api/v1/motions_for_meeting` |
| `postsession.js` | Step 4 `exportCorrespondance` link — `/api/v1/export_correspondance.php` does not exist | Remove this link or map to existing export |

### Must Modify (Existing Files)

| File | Modification | Reason |
|------|--------------|--------|
| `app/Controller/MeetingsController.php` | Add member and motion batch creation to `createMeeting()` | Wizard sends full payload in one POST |
| `app/Controller/DashboardController.php` | Add `convocation_status` to `wizardStatus()` response | Hub checklist needs it |
| `public/assets/js/pages/wizard.js` | Fix field name mapping in `buildPayload()`: `type→meeting_type`, build `scheduled_at` from `date`+`time`, `place→location` | Backend schema uses different names |
| `public/assets/js/pages/postsession.js` | Fix line 118: `meeting_motions.php` → `motions_for_meeting.php` | Wrong endpoint |

## Suggested Build Order

### Why This Order

Dependencies run from data creation to data consumption. The wizard creates data that all other pages read. The operator console depends on both members/motions existing and the SSE pipeline being active. Post-session depends on the operator having conducted votes.

```
Phase 1: Data Foundation (unblocks everything)
    1a. Fix wizard.js payload field names (type, date, place)
    1b. Extend createMeeting() to accept + persist members[] and resolutions[]
        in a single transaction
    1c. Verify: wizard creates meeting + members in DB → hub sees real data

Phase 2: Hub Checklist (first visible result)
    2a. Fix hub.js convocationsSent field (add to wizard_status response)
    2b. Remove DEMO_SESSION / DEMO_FILES fallback from hub.js
        Replace with error state when API unavailable
    2c. Wire hub document upload button → POST /api/v1/meeting_attachments
    2d. Wire hub convocations button → POST /api/v1/invitations_send_bulk

Phase 3: Dashboard (quick win, mostly wired)
    3a. Remove showFallback() static data from dashboard.js
    3b. Verify: KPIs count real sessions; session rows link to operator correctly

Phase 4: Operator Console — Setup Mode
    4a. Verify attendance tab loads from real API
    4b. Verify motions tab loads from real API (motions_for_meeting)
    4c. Wire MeetingContext propagation: meeting selector → set(meeting_id)
        so SSE and all sub-modules use the selected meeting

Phase 5: Operator Console — Live Voting (SSE)
    5a. Trigger connectSSE() on MeetingContext:change event
    5b. Verify operator_open_vote creates tokens and opens motion
    5c. Verify vote.cast SSE events update tally in real-time
    5d. Verify motion.opened/closed events switch view mode

Phase 6: Post-Session PV Flow
    6a. Fix postsession.js line 118 (meeting_motions → motions_for_meeting)
    6b. Verify step 1 results table populates from real motions data
    6c. Verify step 2 validation transition (closed → validated) works
    6d. Verify step 3 PV generation returns HTML and hash
    6e. Verify step 4 export links all resolve to existing endpoints
        Remove exportCorrespondance link (endpoint does not exist)

Phase 7: Demo Data Removal
    7a. Remove all remaining static fallback data that masks backend errors
    7b. Add explicit error states: "Backend unavailable" / "Session not found"
    7c. Verify: every page shows an error, not fake data, when backend is down
```

### Dependency Graph

```
wizard creates meeting
    ↓
hub reads wizard_status    dashboard reads meetings list
    ↓
operator loads members, motions, attendance
    ↓
operator live vote (SSE) ← EventBroadcaster ← BallotsController
    ↓
postsession reads motions_for_meeting (results)
    ↓
PV generation (meeting_generate_report)
    ↓
archive + send (meetings_archive + meeting_report_send)
```

## Anti-Patterns to Avoid

### Anti-Pattern 1: Multi-Round-Trip Wizard Creation

**What people do:** Post wizard data in multiple sequential API calls from the frontend (create meeting → loop POST members → loop POST motions).

**Why it's wrong:** Race conditions if the user navigates away mid-sequence; partial data persists; no atomicity guarantee; 10+ HTTP calls for a 10-member/8-motion meeting.

**Do this instead:** Extend `MeetingsController::createMeeting()` to accept optional `members[]` and `resolutions[]` and persist them inside `api_transaction()`. One call, one commit, idempotent via `IdempotencyGuard`.

### Anti-Pattern 2: Silent Demo-Data Fallback Masking Real Errors

**What people do:** `showFallback()` renders static wireframe data when the API fails — the page appears functional but is showing fake numbers.

**Why it's wrong:** Real integration bugs (missing fields, wrong endpoint names, 401 auth failures) are invisible during development and QA. Users see wrong data.

**Do this instead:** Show an explicit error state with the actual error message and a retry button. The existing `ag-toast` component and the `setNotif()` helper support this pattern across all pages.

### Anti-Pattern 3: Hardcoded Meeting_ID in Frontend State

**What people do:** Store `meeting_id` only in a local JS variable, breaking cross-tab navigation and page refresh.

**Why it's wrong:** `MeetingContext` already provides `sessionStorage` + URL param propagation. Bypassing it means navigating to `/operator.htmx.html` directly loses the context.

**Do this instead:** Always use `MeetingContext.set(id)` and `MeetingContext.get()` — it syncs `?meeting_id=` to the URL, propagates to all `<a href>` links, and persists across same-tab refreshes.

### Anti-Pattern 4: SSE Connect on Page Load Without Meeting Context

**What people do:** `EventStream.connect()` called in page `init()` before a meeting is selected.

**Why it's wrong:** No `meeting_id` means the SSE endpoint has nothing to subscribe to; events are dropped or the connection fails silently.

**Do this instead:** Trigger `connectSSE()` only from the `MeetingContext:change` event handler, after a `meeting_id` is confirmed. `operator-realtime.js` already has this pattern partially; it needs to handle the case where `MeetingContext` fires before `OpS.currentMeetingId` is set.

### Anti-Pattern 5: Wrong Field Names Between Wizard and Backend

**What people do:** Use the wizard's French-leaning names (`type`, `place`, `voix`) when sending to the backend.

**Why it's wrong:** `MeetingsController::createMeeting()` validates against `ValidationSchemas::meeting()` which uses `meeting_type`, `location`, `scheduled_at`. Unknown fields are silently ignored.

**Do this instead:** Map in `buildPayload()`:
- `type → meeting_type`
- `date` + `time` → `scheduled_at` (ISO 8601: `date + 'T' + time + ':00'`)
- `place` → `location`
- `voix` per member → `voting_power` in member objects

## Architectural Patterns in Use

### Pattern 1: Exception-Based Response Flow

**What:** `api_ok()` and `api_fail()` both `throw ApiResponseException`. Controllers never `return`.

**When to use:** All controller methods.

**Trade-offs:** Makes call depth irrelevant (helpers can call api_fail deep in the stack); unconventional for developers expecting return-based flow.

```php
// Correct pattern
$meeting = $repo->findByIdForTenant($id, $tenant);
if (!$meeting) {
    api_fail('meeting_not_found', 404); // throws — execution stops here
}
api_ok(['meeting' => $meeting]); // throws — sends response
```

### Pattern 2: IIFE + var for Page Scripts, ES Modules for Components

**What:** Page JS (wizard.js, hub.js, dashboard.js, postsession.js) use `(function(){ 'use strict'; var ... })()`; Web Components (`ag-toast.js`, `ag-modal.js`) use `export default class`.

**When to use:** Always — this is the established convention. Never mix patterns for page scripts.

**Trade-offs:** IIFE scripts share the `window` scope intentionally (e.g., `window.OpS` bridge for operator sub-modules); no bundler means no tree-shaking.

### Pattern 3: OpS Bridge for Operator Sub-Modules

**What:** `operator-tabs.js` exposes `window.OpS = { fn: {}, currentMeetingId, currentMode, ... }`. Sub-modules (`operator-exec.js`, `operator-motions.js`, `operator-realtime.js`) read and write through this shared object.

**When to use:** Any time operator sub-modules need to communicate without tight coupling.

**Trade-offs:** Global mutation; requires careful load order (tabs.js must load before sub-modules); works without a framework event bus.

### Pattern 4: Wizard-to-Hub via sessionStorage Toast + URL Param

**What:** On wizard success, store toast payload in `sessionStorage('ag-vote-toast', ...)` then redirect to `/hub.htmx.html?id=meeting_id`. Hub reads and clears the toast on load.

**When to use:** Any cross-page flow where a success notification is needed after redirect.

**Trade-offs:** Survives redirect without server state; sessionStorage is cleared on browser tab close.

## Integration Points

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| `wizard.js` ↔ `MeetingsController` | `POST /api/v1/meetings` JSON | Payload field names must match backend schema |
| `hub.js` ↔ `DashboardController::wizardStatus` | `GET /api/v1/wizard_status?meeting_id=` | `mapApiDataToSession()` normalizes field names; hub can handle both old and new field names |
| `operator-tabs.js` ↔ sub-modules | `window.OpS` shared object | `OpS.fn.*` function registry; `OpS.currentMeetingId` is the truth source for sub-modules |
| `operator-realtime.js` ↔ `events.php` | SSE `EventSource` | `event-stream.js` wraps this; `EventStream.connect()` is the public API |
| `EventBroadcaster` ↔ `events.php` | Redis list `sse:events:{meeting_id}` | File fallback at `/tmp/agvote-sse-{id}.json` when Redis unavailable |
| `postsession.js` ↔ `MotionsController` | `GET /api/v1/motions_for_meeting` | Bug: postsession currently calls wrong URL; one-line fix |

## Sources

- Direct codebase inspection of 38 controllers, 32 page JS modules, routes.php, and ValidationSchemas.php
- Architecture documented at `.planning/codebase/ARCHITECTURE.md` (2026-03-16)
- Route table `app/routes.php` — complete endpoint inventory
- Phase 14 verification at `.planning/milestones/v2.0-phases/14-wizard-hub-dashboard-api/14-VERIFICATION.md`
- Milestone state at `.planning/STATE.md`

---
*Architecture research for: AG-VOTE v3.0 full-stack session lifecycle wiring*
*Researched: 2026-03-16*
