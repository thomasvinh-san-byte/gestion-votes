# Phase 3: Frontend et Validation - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Add X-Idempotency-Key header to HTMX form submissions (currently only on JS apiPost/apiPut calls) and write unit tests proving IdempotencyGuard rejects duplicate requests.

</domain>

<decisions>
## Implementation Decisions

### HTMX Key Injection (IDEM-06)
- **D-01:** Add idempotency key generation to the existing `htmx:configRequest` handler in `core/utils.js` (line 320)
- **D-02:** Only add key for POST/PATCH methods (same logic as apiPost helper at line 642)
- **D-03:** Use `crypto.randomUUID()` per request — matches existing pattern, unique per submission
- **D-04:** Don't add key if already present (same guard as line 642: `!headers['X-Idempotency-Key']`)

### Test Strategy (IDEM-07)
- **D-05:** Unit test IdempotencyGuard directly — test the check/store/reject cycle
- **D-06:** Test cases: first call succeeds, duplicate key returns 409, different key succeeds, expired key allows retry
- **D-07:** Tests mock Redis via IdempotencyGuard constructor DI pattern (nullable params)

### Claude's Discretion
- Exact test class name and location
- Whether to test PATCH in addition to POST
- Whether to add a HTMX-specific test or just unit test the guard

</decisions>

<canonical_refs>
## Canonical References

### Requirements
- `.planning/REQUIREMENTS.md` — IDEM-06, IDEM-07

### Key Files
- `public/assets/js/core/utils.js` lines 320-325 — HTMX configRequest handler (add key here)
- `public/assets/js/core/utils.js` lines 641-644 — existing apiPost idempotency pattern (reference)
- `app/Core/Security/IdempotencyGuard.php` — guard to test

</canonical_refs>

<code_context>
## Existing Code Insights

### Current State
- `htmx:configRequest` handler adds CSRF token but NOT idempotency key
- `apiPost`/`apiPut` JS helper already adds `X-Idempotency-Key: crypto.randomUUID()` for POST/PUT
- `vote.js` manually passes idempotency key for ballot cast

### What Needs to Change
- Add 3 lines to the configRequest handler: check method, generate key, set header
- Write PHPUnit test for IdempotencyGuard check/store/reject cycle

### IdempotencyGuard API
- `check()` — reads X-Idempotency-Key header, checks Redis, throws 409 if duplicate
- `store()` — stores key in Redis with 3600s TTL
- Constructor accepts nullable Redis dependency for testing

</code_context>

<specifics>
## Specific Ideas

- The HTMX configRequest change is ~3 lines of JS
- The unit test is the more substantial deliverable — must prove the guard works

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-frontend-et-validation*
*Context gathered: 2026-04-20*
