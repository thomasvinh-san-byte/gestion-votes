# Phase 16: Data Foundation - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

The wizard creates a complete session record in the database (meeting + members + motions in one transaction) and the hub displays the real session state loaded from the API. No demo fallbacks.

**Requirements:** WIZ-01, WIZ-02, WIZ-03, HUB-01, HUB-02

**Success Criteria:**
1. Wizard completion → session appears in hub with correct title, type, location, scheduled date from DB
2. Members from wizard step 2 are visible as registered participants in hub attendance
3. Resolutions from wizard step 3 appear as motions on the hub checklist

</domain>

<decisions>
## Implementation Decisions

### Persistance strategy
- **Atomic single-call**: Extend `createMeeting()` to accept `members[]` and `resolutions[]` in the existing POST /api/v1/meetings payload
- Process all three (meeting + members + motions) in a single PDO transaction — rollback everything if any part fails
- Leverage existing `IdempotencyGuard` pattern for the expanded payload
- No multi-step API calls from the frontend; the wizard continues sending one payload

### Field mapping (wizard → backend)
- **Backend maps** the mismatched field names — the frontend payload stays as-is
- `type` → `meeting_type`
- `date` + `time` → `scheduled_at` (combined)
- `place` → `location`
- `quorum` → mapped to quorum policy
- `defaultMaj` → mapped to vote policy
- Frontend `buildPayload()` is NOT modified for field names

### Member handling
- **Upsert behavior**: If a member already exists (same email + tenant), reuse the existing member record — do not create duplicates
- Link existing/new members to the meeting via attendance records
- `voix` field defaults to 1 if not provided

### API response
- Return `meeting_id` + counts: `{ meeting_id, title, members_created, members_linked, motions_created }`
- The wizard uses these counts for the success toast on redirect

### Hub error handling
- **Toast + retry**: On API failure, show a red toast with error message and a retry button
- **1 automatic retry** after 2 seconds, then manual retry button if still failing
- **Remove DEMO_SESSION and DEMO_FILES** entirely — hub shows only real data
- **Invalid meeting_id**: Redirect to dashboard with toast "Séance introuvable"

### Wizard → Hub redirect contract
- Wizard **waits for 201 response** before redirecting to hub — no fire-and-forget
- On success: `clearDraft()` from localStorage, store counts in sessionStorage, redirect to `hub.htmx.html?id=X`
- Hub reads sessionStorage for toast: "Séance créée • 12 membres • 5 résolutions"
- On failure: Red toast with error detail, form stays filled, user can retry
- localStorage draft is **only cleared after confirmed 201**

### Backend validation
- **Email format validated** at creation time — reject if any member email is invalid
- **Required fields**: Member → `nom` + `email`; Resolution → `title`
- **Tout-ou-rien**: If 1 member or 1 motion is invalid, rollback entire transaction, return 422 with detailed errors listing which items failed and why
- **No quantity limits** for members or resolutions per session
- Return error structure: `{ error: true, details: [{ index: 0, field: 'email', message: 'Format invalide' }] }`

### Claude's Discretion
- Exact PDO transaction implementation details
- ValidationSchemas extension approach
- Error message wording for edge cases
- Hub loading skeleton during API call

</decisions>

<specifics>
## Specific Ideas

- Toast success format: "Séance créée • {n} membres • {n} résolutions" (uses counts from API response)
- The wizard already sends the complete payload via `buildPayload()` at `wizard.js:606` — the data is there, backend just needs to stop ignoring it
- Hub currently falls back to DEMO_SESSION at `hub.js:423-430` — this entire block gets replaced with real API data + error handling

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `IdempotencyGuard` (`app/Core/Security/IdempotencyGuard.php`): Already wraps createMeeting — extend to cover the full atomic payload
- `ValidationSchemas::meeting()` (`app/Core/Validation/ValidationSchemas.php`): Extend with member and motion sub-schemas
- `MemberRepository::create()` and `MotionRepository` — existing insert methods to reuse inside the transaction
- `wizard.js::buildPayload()` (line 606): Already builds complete payload with members[] and resolutions[]
- `wizard.js::clearDraft()` (line 122): Already exists for localStorage cleanup
- `hub.js::checkToast()`: Already reads sessionStorage for toast display

### Established Patterns
- All PHP files use `declare(strict_types=1)` — maintain this
- Controllers return via `api_ok()` / `api_error()` helpers
- Repositories use PDO with named parameters
- Frontend uses `window.api(url, data)` for all API calls
- Toast system uses `ag-toast` class and sessionStorage handoff between pages

### Integration Points
- `MeetingsController::createMeeting()` (line 367): Extend this method to handle members[] and resolutions[]
- `hub.js::loadData()`: Replace DEMO_SESSION fallback with real error handling
- `hub.js` lines 301-330: DEMO_SESSION and DEMO_FILES constants to remove
- `wizard.js` line 702-708: Payload submission and clearDraft flow — update to use counts from response
- `DashboardController::wizardStatus()`: Already returns real data — hub just needs to use it properly

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 16-data-foundation*
*Context gathered: 2026-03-16*
