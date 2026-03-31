# Phase 60: Session, Import, and Auth Edge Cases - Context

**Gathered:** 2026-03-31
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase hardens three subsystems: (1) session state machine — explicit rejection of invalid transitions and live-session deletion, (2) CSV import — encoding detection/conversion for Windows-1252/ISO-8859-1 and duplicate email detection, (3) authentication — expired session redirect with user-facing message and rate-limited login with configurable thresholds and audit trail.

</domain>

<decisions>
## Implementation Decisions

### Session State Machine Enforcement
- Invalid transitions return 422 with specific `from_status` and `to_status` in the error response: `"Transition '{from}' → '{to}' non autorisée"`
- Delete-live-session rejection includes hint: `"Fermez d'abord la séance avant de la supprimer"` — existing code already returns 409 for non-draft
- Invalid transition attempts are NOT audit-logged — these are normal user errors, not anomalies

### CSV Import Hardening
- Detect non-UTF-8 encodings with `mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true)` then `mb_convert_encoding()` — applied at CSV read step before parsing
- Duplicate email detection happens in ImportService/ImportController during validation before insert — collect all emails, detect duplicates, return error listing them
- Duplicate error lists ALL duplicate emails in the response — user fixes the CSV once instead of iterating
- Empty/missing email rows are skipped during duplicate check — only flag actual duplicated email values

### Auth & Session Edge Cases
- Expired session redirect shows French flash message: `"Votre session a expiré. Veuillez vous reconnecter."` — set as query param before redirect to `/login`
- Rate limiting uses existing `RateLimiter` with configurable `maxAttempts` (default: 5) and `windowSeconds` (default: 300) from `APP_LOGIN_MAX_ATTEMPTS`/`APP_LOGIN_WINDOW` env vars
- Rate limit blocking produces audit entry: `audit_log('auth_rate_limited', 'security', null, {ip, attempt_count, window})` — essential for security monitoring
- Rate limit response includes `Retry-After` header (already in RateLimiter) and French message: `"Trop de tentatives. Réessayez dans X minutes."`

### Claude's Discretion
- Internal code organization and helper structure
- Test structure and naming conventions
- Specific encoding detection order or fallback strategies

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `MeetingWorkflowService::issuesBeforeTransition()` — validates pre-conditions for transitions with issues/warnings/can_proceed pattern
- `AuthMiddleware::requireTransition()` — checks allowed transition map
- `MeetingsController::deleteMeeting()` — already rejects non-draft at line 557 with `api_fail('meeting_not_draft', 409)`
- `ImportService::readCsvFile()` — CSV reader (no encoding detection yet)
- `ImportService::validateUploadedFile()` — file validation (size, MIME, extension)
- `RateLimiter::check()` — Redis/file-based rate limiting with `denyWithRetryAfter()`
- `AuthController::login()` — already calls `userRepo->logAuthFailure()` on bad credentials
- `AuthMiddleware` — session expiry detection at line 299-301

### Established Patterns
- Controllers use `api_fail()` for structured JSON errors
- `MeetingWorkflowService` returns `{issues, warnings, can_proceed}` — controller blocks on issues
- `audit_log($event, $entity_type, $entity_id, $data, $meeting_id)` for forensic logging
- Rate limiting via `RateLimiter::check()` with configurable thresholds

### Integration Points
- `MeetingWorkflowController::transition()` (app/Controller/MeetingWorkflowController.php:19) — state transition endpoint
- `MeetingsController::deleteMeeting()` (app/Controller/MeetingsController.php:543) — delete endpoint
- `ImportService::readCsvFile()` (app/Services/ImportService.php:176) — CSV reading
- `ImportController` (app/Controller/ImportController.php) — import endpoint
- `AuthController::login()` (app/Controller/AuthController.php:19) — login endpoint
- `AuthMiddleware` (app/Core/Security/AuthMiddleware.php) — session check middleware
- `RateLimiter` (app/Core/Security/RateLimiter.php) — rate limiting infrastructure

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches within the decided strategy.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
