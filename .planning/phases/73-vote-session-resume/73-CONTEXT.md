# Phase 73: Vote Session Resume - Context

**Gathered:** 2026-04-02
**Status:** Ready for planning

<domain>
## Phase Boundary

When a voter's session expires during an active vote, they should be redirected to login with a return_to parameter that brings them back to /vote after re-authentication. sessionStorage already preserves meeting_id and member_id across navigation, so the vote context survives the re-auth round-trip. If the vote closed while the voter was timed out, the existing SSE/status detection shows a clear message.

</domain>

<decisions>
## Implementation Decisions

### Session Expiry Redirect
- auth-ui.js already detects `session_expired` (line 520) and redirects to login — modify to include `?return_to=/vote` when on the vote page
- Login page reads `return_to` query param and passes it through the auth flow
- AuthController redirects to `return_to` after successful login (validated to same-origin only, prevent open redirect)

### Context Preservation
- sessionStorage already persists `public.meeting_id` and `public.member_id` — no additional work needed
- Votes already cast are persisted in DB via `consumeIfValid()` — they survive session expiry

### Vote Closed During Timeout
- vote.js already handles session close via SSE events and status checks — no new code needed
- If voter returns to a closed vote, existing "vote ended" UI will display

### Claude's Discretion
- Exact implementation of return_to validation (allowlist vs regex vs URL parsing)
- Whether to show a toast/notification "Session restored" after re-auth return
- Error message wording if return_to is invalid

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- auth-ui.js line 520: `session_expired` detection and redirect to login
- auth-ui.js line 457: session expiry warning banner
- vote.js: sessionStorage for meeting_id (line 482) and member_id (line 595)
- AuthController login flow — POST /api/v1/auth_login
- login.js: form submission handler

### Established Patterns
- Voter confinement: allowedExact array in auth-ui.js (line 355)
- API error handling: `data.error === 'session_expired'` pattern
- Redirect after login: currently always goes to /dashboard

### Integration Points
- public/assets/js/pages/auth-ui.js — modify session_expired redirect to include return_to
- public/assets/js/pages/login.js — read return_to and include in login POST or redirect
- app/Controller/AuthController.php — redirect to return_to after successful login
- public/login.html — pass return_to through the form

</code_context>

<specifics>
## Specific Ideas

No specific requirements — the mechanism is mostly about wiring existing pieces together.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
