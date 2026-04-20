# Phase 2: Gardes Backend - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Add IdempotencyGuard to the 13 Critique-risk routes identified in the Phase 1 audit, and make workflow transitions (launch/close meeting) explicitly idempotent. No frontend changes — that's Phase 3.

</domain>

<decisions>
## Implementation Decisions

### Target Routes (from 01-IDEMPOTENCY-AUDIT.md)
- **D-01:** Protect all 13 Critique routes that lack IdempotencyGuard AND lack sufficient UNIQUE constraints
- **D-02:** Group by controller: EmailController (4 routes), ImportController (6 routes), MotionsController (2 routes), MembersController::bulk (1 route)

### Guard Pattern
- **D-03:** Reuse existing IdempotencyGuard::check() / ::store() pattern from MeetingsController/AgendaController/MembersController
- **D-04:** Add `$this->idempotencyGuard->check()` at entry, `$this->idempotencyGuard->store()` before return on success
- **D-05:** IdempotencyGuard already injected via constructor DI — add it to controllers that don't have it yet

### Workflow Idempotence (IDEM-05)
- **D-06:** Meeting workflow transitions (launch, close) should return success without side effects if meeting is already in the target state
- **D-07:** Pattern: check current state, if already target state → return success response (not error)

### Claude's Discretion
- Whether to add IdempotencyGuard to the controller constructor or use a middleware approach
- Exact error response format for duplicate requests (409 Conflict recommended)
- Whether import routes need a different TTL (file uploads may take longer)

</decisions>

<canonical_refs>
## Canonical References

### Requirements
- `.planning/REQUIREMENTS.md` — IDEM-03, IDEM-04, IDEM-05

### Audit Document
- `.planning/phases/01-audit-et-classification/01-IDEMPOTENCY-AUDIT.md` — Cibles Phase 2 section lists all 13 routes

### Key Files
- `app/Core/Security/IdempotencyGuard.php` — existing guard implementation
- `app/Controller/MeetingsController.php` — reference implementation (check at line 142, store at 167)
- `app/Controller/EmailController.php` — 4 routes to protect
- `app/Controller/ImportController.php` — 6 routes to protect
- `app/Controller/MotionsController.php` — 2 routes to protect
- `app/Controller/MembersController.php` — 1 route (bulk) to protect
- `app/Controller/MeetingWorkflowController.php` — workflow transitions to make idempotent

</canonical_refs>

<code_context>
## Existing Code Insights

### IdempotencyGuard Pattern (from MeetingsController)
```php
$this->idempotencyGuard->check();  // throws 409 if duplicate key
// ... do work ...
$this->idempotencyGuard->store();  // stores key in Redis with TTL
```

### Controllers Needing Guard Addition
- EmailController — no IdempotencyGuard in constructor currently
- ImportController — no IdempotencyGuard in constructor currently
- MotionsController — no IdempotencyGuard in constructor currently
- MembersController — already has IdempotencyGuard (add to bulk method)
- MeetingWorkflowController — needs state-check pattern, not IdempotencyGuard

</code_context>

<specifics>
## Specific Ideas

- Email routes are highest priority — duplicate emails are the most visible user impact
- Import routes handle file uploads — consider whether the same file uploaded twice should be blocked or if UNIQUE constraints handle it
- Motions createOrUpdate is a hybrid — creation needs guard, update is idempotent

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 02-gardes-backend*
*Context gathered: 2026-04-20*
