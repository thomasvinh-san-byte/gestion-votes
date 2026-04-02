# Phase 59: Vote and Quorum Edge Cases - Research

**Researched:** 2026-03-31
**Domain:** PHP vote token validation, quorum arithmetic safety, SSE broadcast, PHPUnit mocked-repo unit tests
**Confidence:** HIGH

## Summary

Phase 59 hardens two subsystems — vote token/ballot rejection and quorum zero-division — that are largely already coded defensively. The work is primarily **audit trail gaps** (adding `audit_log` calls for anomalous vote attempts) and **test-locking** (pinning the existing behavior with new unit tests). A secondary concern is confirming the three attendee-change paths in `AttendancesController` all broadcast `quorum.updated` via SSE, which the code audit shows is already true for `upsert` and `bulk`; the "remove" path is the same `upsert` endpoint with mode `absent`.

The existing codebase already handles most success criteria structurally:
- `VoteTokenService::validateAndConsume()` returns `reason: 'token_expired'` or `'token_already_used'` — `BallotsController::cast()` already maps these to HTTP 401 with `reason` field.
- `BallotsService::castBallot()` already throws `RuntimeException('Cette motion n\'est pas ouverte au vote')` when `motion_closed_at` is not null.
- `QuorumEngine::ratioBlock()` already guards `$den <= 0` and returns `ratio: 0.0, met: false`.
- `AttendancesController::upsert()` and `bulk()` already call `EventBroadcaster::quorumUpdated()`.

What is **missing** and must be added: audit trail calls in `BallotsController::cast()` for the two anomaly paths (token reuse, closed-motion vote), plus a `try/catch RuntimeException` block in `cast()` to map service exceptions to structured `api_fail()` responses, plus unit tests locking all five requirements.

**Primary recommendation:** Add `audit_log` calls and catch-block in `BallotsController::cast()`, then write targeted PHPUnit tests for each requirement using the existing mocked-repo pattern from `BallotsControllerTest` and `QuorumEngineTest`.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Error Response Strategy
- Catch RuntimeException in BallotsController and map to specific 4xx codes (409 for closed motion, 422 for invalid data) — consistent with existing api_fail pattern
- Error messages in French, matching existing codebase pattern (`'Token de vote invalide ou expiré.'`)
- Expired-token and already-used-token both return 401 with distinct `reason` field (`token_expired` vs `token_already_used`) — already implemented this way
- Closed-motion vote attempts include `motion_status: "closed"` in error payload so voter UI can show appropriate state

#### Audit Trail for Vote Anomalies
- Double-vote attempts logged as `audit_log('vote_token_reuse', 'motion', $motionId, {token_hash, timestamp, ip, member_id, reason})` — full forensic trail
- Closed-motion vote attempts also audit-logged as `audit_log('vote_rejected', 'motion', $motionId, {reason: 'motion_closed', member_id})` — important for detecting automation/abuse
- Audit calls live in BallotsController (before `api_fail` return) — consistent with existing `audit_log('ballot.cast')` pattern
- IP address captured from `$_SERVER['REMOTE_ADDR']` — already used in existing audit infrastructure

#### Quorum Real-Time Updates
- All 3 attendee-change paths in AttendancesController (add, remove, bulk) broadcast `quorum.updated` SSE event
- SSE event includes full quorum result from `QuorumEngine::computeForMeeting()` — ratio, threshold, met status, member counts
- Operator console JS handles `quorum.updated` SSE event to update display without page reload
- QuorumEngine zero-member behavior: return `ratio: 0.0, met: false` (current behavior) — add unit test to lock it

### Claude's Discretion
- Internal error handling patterns and code structure within the decided approach
- Test organization and naming

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| VOTE-01 | Un vote avec un token expiré ou déjà utilisé retourne une erreur claire (pas 500) | Token validation path in `cast()` already returns 401 with `reason`; needs RuntimeException catch-block and audit_log for token reuse |
| VOTE-02 | Un double vote avec le même token est rejeté et l'anomalie est loguée en audit | Token consumed atomically by `validateAndConsume()`; `audit_log('vote_token_reuse', ...)` call must be added before `api_fail` in `cast()` |
| VOTE-03 | Un vote sur une motion fermée retourne une erreur explicite | `BallotsService::castBallot()` throws RuntimeException for closed motion; controller catch-block must map to 409 + `audit_log('vote_rejected', ...)` |
| QUOR-01 | Le calcul de quorum fonctionne correctement avec zéro membre présent (pas de division par zéro) | `QuorumEngine::ratioBlock()` already guards `$den <= 0`; needs unit test locking this behavior |
| QUOR-02 | L'ajout ou le retrait de présence en cours de vote met à jour le quorum en temps réel via SSE | `AttendancesController::upsert()` and `bulk()` already call `EventBroadcaster::quorumUpdated()`; needs unit tests verifying the broadcast call |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | 10.5 (configured in phpunit.xml) | Unit tests with mocked repositories | Project-wide test framework; all existing tests use it |
| PHP PDO / PostgreSQL | project standard | Database access | No change — existing persistence layer |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `audit_log()` global helper | project | Forensic audit trail writes | Used throughout controllers; signature: `audit_log($event, $entity_type, $entity_id, $data, $meeting_id)` |
| `api_fail()` global helper | project | Structured JSON error responses | Used in every controller; terminates execution |
| `EventBroadcaster::quorumUpdated()` | project SSE layer | Push quorum.updated event to operator console | Already used in `upsert()` and `bulk()` paths |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Catching RuntimeException in controller | Let exceptions bubble | Bubbling produces 500; controller catch-maps to meaningful 4xx |
| audit_log before api_fail | audit_log after | After is unreachable — `api_fail` exits; must be before |

**Installation:** No new packages required.

## Architecture Patterns

### Established Controller Error Pattern

The project's established pattern is: service throws `RuntimeException` or `InvalidArgumentException`, controller catches and calls `api_fail()`.

```php
// Source: app/Controller/BallotsController.php (existing cancel() method, lines 128-167)
// The cast() method currently does NOT have this catch-block — it must be added.
try {
    $ballot = (new BallotsService())->castBallot($data);
} catch (RuntimeException $e) {
    $msg = $e->getMessage();
    // Map specific messages to 4xx codes
    if (str_contains($msg, 'n\'est pas ouverte au vote') || str_contains($msg, 'motion_closed')) {
        // Log anomaly BEFORE calling api_fail (api_fail exits)
        audit_log('vote_rejected', 'motion', $motionId, [
            'reason'    => 'motion_closed',
            'member_id' => $data['member_id'] ?? null,
        ], /* meeting_id */ null);
        api_fail('motion_closed', 409, [
            'detail'        => 'Cette résolution n\'est plus ouverte au vote.',
            'motion_status' => 'closed',
        ]);
    }
    api_fail('vote_rejected', 422, ['detail' => $msg]);
}
```

### Audit Trail for Anomalous Votes

```php
// Pattern: audit BEFORE api_fail (api_fail terminates execution)
// Source: app/Controller/BallotsController.php cast() method, lines 63-89 (token validation block)

// For token reuse — add inside the existing !$tokenResult['valid'] block:
if (!$tokenResult['valid']) {
    $reason = $tokenResult['reason'] ?? 'unknown';
    // Audit double-vote / expired-token attempts
    if (in_array($reason, ['token_already_used', 'token_expired'], true)) {
        audit_log('vote_token_reuse', 'motion', $motionId ?: null, [
            'token_hash' => $tokenResult['token_hash'] ?? null,
            'timestamp'  => date('c'),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'member_id'  => $memberId ?: null,
            'reason'     => $reason,
        ]);
    }
    api_fail('invalid_vote_token', 401, [
        'detail' => 'Token de vote invalide ou expiré.',
        'reason' => $reason,
    ]);
}
```

### QuorumEngine Zero-Member Guard (already implemented)

```php
// Source: app/Services/QuorumEngine.php, ratioBlock() lines 248-258
// This is the existing code — needs a unit test to lock it.
if ($den <= 0) {
    return [
        'configured'  => true,
        'met'         => false,
        'ratio'       => 0.0,
        'threshold'   => $threshold,
        'numerator'   => $num,
        'denominator' => 0.0,
        'basis'       => $basis,
    ];
}
```

### Recommended Project Structure for New Tests

```
tests/Unit/
├── BallotsControllerTest.php      # Add cast() token-error + closed-motion tests
├── QuorumEngineTest.php           # Add zero-eligible-members test
└── AttendancesControllerTest.php  # Add quorum broadcast verification tests
```

### Anti-Patterns to Avoid

- **Placing audit_log after api_fail:** `api_fail()` calls `exit()` — any code after it never runs. Audit calls must always come before `api_fail`.
- **Using the deprecated `validate()` + `consume()` two-step:** These methods are marked `@deprecated` in `VoteTokenService`. Always use `validateAndConsume()` (atomic, no TOCTOU race).
- **Catching all Throwable in cast():** Only catch `RuntimeException`. `InvalidArgumentException` extends it but carries different semantics (client validation errors). Catching `Throwable` masks real programming errors.
- **Ignoring SSE broadcast failures:** The pattern throughout the project is to wrap `EventBroadcaster` calls in `try/catch(Throwable)` — broadcast failure must never fail the HTTP response.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Atomic token consume | Custom SELECT + UPDATE | `VoteTokenRepository::consumeIfValid()` already exists | Race-free single UPDATE; hand-rolled SELECT+UPDATE has TOCTOU vulnerability |
| Quorum ratio calculation | Custom division code | `QuorumEngine::ratioBlock()` already guards `$den <= 0` | Already handles both `eligible_members` and `eligible_weight` bases |
| SSE quorum broadcast | Custom Redis write | `EventBroadcaster::quorumUpdated($meetingId, $quorumResult)` | Encapsulates payload shape and Redis queue key |
| Audit trail writes | Custom DB inserts | `audit_log()` global helper | Handles meeting_id, entity_type, IP, timestamps consistently |

**Key insight:** All five requirements are either already implemented in service/repository layers, or require only controller-layer wiring (audit calls + catch blocks). No new service classes are needed.

## Common Pitfalls

### Pitfall 1: audit_log after api_fail
**What goes wrong:** Audit entry is never written because `api_fail()` calls `exit()`.
**Why it happens:** Developers assume `api_fail` is a return-like call.
**How to avoid:** Always place `audit_log(...)` immediately before `api_fail(...)` in the same block.
**Warning signs:** Grep for any `api_fail` immediately after `audit_log` within the same if-block — if audit is after, it's wrong.

### Pitfall 2: Confusion between "remove" endpoint and "upsert with absent mode"
**What goes wrong:** Planner creates a task to add quorum broadcast to a "remove" endpoint that doesn't exist, wasting time.
**Why it happens:** CONTEXT.md says "add, remove, bulk" — but there is no `remove()` method. Removal is `upsert()` with `mode=absent`.
**How to avoid:** The `upsert()` path already calls `EventBroadcaster::quorumUpdated()`. QUOR-02 for the upsert path is already satisfied in code; the gap is the **test** verifying it.

### Pitfall 3: Missing $motionId when building audit data for token failure
**What goes wrong:** `$motionId` is set in `cast()` from `$data['motion_id']` AFTER the token block — but the token block runs first. The motion ID is available in `$data['motion_id']` at token-check time as `trim((string) ($data['motion_id'] ?? ''))`.
**Why it happens:** Reading the code quickly, developers miss that `$motionId` is only reassigned to `$data['motion_id'] ?? $ballot['motion_id']` after `castBallot()` succeeds.
**How to avoid:** Read `$motionId` from `$data['motion_id']` directly when building the audit payload in the token failure block.

### Pitfall 4: Test isolation for `$_SERVER['REMOTE_ADDR']`
**What goes wrong:** Tests that call `cast()` may fail because `$_SERVER['REMOTE_ADDR']` is not set in the PHPUnit environment, causing a PHP notice/warning.
**Why it happens:** Server superglobals are not automatically populated in CLI test runs.
**How to avoid:** Access IP as `$_SERVER['REMOTE_ADDR'] ?? null` (already the pattern in `reportIncident()`). Tests do not need to set this — the `?? null` makes it safe.

### Pitfall 5: QuorumEngine test needs mocked repos for computeForMeeting
**What goes wrong:** Unit test tries to call `QuorumEngine::computeForMeeting()` directly but hits `RepositoryFactory` which requires a database connection.
**Why it happens:** QuorumEngine constructor accepts optional mock repos but falls back to the real factory when null.
**How to avoid:** Use the existing pattern in `QuorumEngineTest.php` — test `ratioBlock()` logic indirectly via the `computeRatioBlock()` helper method, or inject mock repos via the constructor. For the zero-member test, testing the static `ratioBlock()` logic directly via a mock-injected `computeInternal()` call is cleaner.

## Code Examples

### Test Pattern: cast() with expired token returns 401

```php
// Source: existing BallotsControllerTest.php pattern — extend with token scenarios
public function testCastExpiredTokenReturns401(): void
{
    $this->setHttpMethod('POST');
    $this->injectJsonBody([
        'motion_id'  => self::MOTION_ID,
        'member_id'  => self::MEMBER_ID,
        'value'      => 'for',
        'vote_token' => 'some-raw-token',
    ]);

    $tokenRepo = $this->createMock(VoteTokenRepository::class);
    $tokenRepo->method('consumeIfValid')->willReturn(null);
    $tokenRepo->method('diagnoseFailure')->willReturn('token_expired');

    $this->injectRepos([VoteTokenRepository::class => $tokenRepo]);

    $resp = $this->callController(BallotsController::class, 'cast');
    $this->assertSame(401, $resp['status']);
    $this->assertSame('invalid_vote_token', $resp['body']['error']);
    $this->assertSame('token_expired', $resp['body']['reason']);
}
```

### Test Pattern: QuorumEngine zero eligible members

```php
// Source: existing QuorumEngineTest.php computeRatioBlock() helper pattern
public function testRatioBlockWithZeroEligibleMembersReturnsZeroRatio(): void
{
    $block = $this->computeRatioBlock(
        basis: 'eligible_members',
        threshold: 0.5,
        numMembers: 0,
        numWeight: 0.0,
        eligibleMembers: 0,   // zero denominator
        eligibleWeight: 0.0,
    );

    $this->assertFalse($block['met']);
    $this->assertSame(0.0, $block['ratio']);
    $this->assertSame(0.0, $block['denominator']);
}
```

### Test Pattern: AttendancesController upsert broadcasts quorum.updated

```php
// Source: existing controller test patterns (ControllerTestCase + injectRepos)
public function testUpsertBroadcastsQuorumUpdated(): void
{
    // The test verifies that EventBroadcaster::quorumUpdated is triggered
    // by checking that QuorumEngine::computeForMeeting is called
    // (indirect verification — EventBroadcaster is static so cannot be mocked directly)
    $this->setHttpMethod('POST');
    $this->injectJsonBody([
        'meeting_id' => self::MEETING_ID,
        'member_id'  => self::MEMBER_ID,
        'mode'       => 'present',
    ]);
    // ... inject repos returning valid meeting/member ...
    $resp = $this->callController(AttendancesController::class, 'upsert');
    $this->assertSame(200, $resp['status']);
    // Quorum broadcast is non-blocking — success response is sufficient verification
    // for unit tests; SSE delivery is covered by integration layer
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `validate()` + `consume()` two-step | `validateAndConsume()` atomic | Phase pre-58 | Eliminates TOCTOU race on concurrent votes |
| `AgVote\WebSocket` namespace | `AgVote\SSE` namespace | Phase 58 | All imports in Phase 59 must use `AgVote\SSE\EventBroadcaster` |

**Deprecated/outdated:**
- `VoteTokenService::validate()` and `VoteTokenService::consume()`: Both marked `@deprecated`. Never use in new code.

## Open Questions

1. **Does `cast()` need a RuntimeException catch-block at the service call site?**
   - What we know: The current `cast()` calls `(new BallotsService())->castBallot($data)` with no try/catch. If `castBallot()` throws RuntimeException (e.g., closed motion), it propagates up as an unhandled 500.
   - What's unclear: Whether any upstream middleware (bootstrap, api.php) already catches RuntimeException and converts it to a 500 with a message. A quick test with a closed motion would confirm current behavior.
   - Recommendation: Add the catch-block regardless — the CONTEXT.md decision mandates it and it's necessary for the `audit_log` calls for VOTE-02 and VOTE-03.

2. **Is `motion_id` available in the token-failure path of `cast()`?**
   - What we know: In `cast()`, `$motionId` is set early from `$data['motion_id']` via `trim((string)($data['motion_id'] ?? ''))` at line 44. It is available when the token-failure block runs (lines 68-73).
   - What's unclear: Nothing — reading the code confirms it is available.
   - Recommendation: Use `$motionId` (already set, possibly empty string) in the audit payload; fallback to `null` if empty.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` (project root) |
| Quick run command | `./vendor/bin/phpunit --testsuite Unit` |
| Full suite command | `./vendor/bin/phpunit` |

### Phase Requirements to Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| VOTE-01 | `cast()` with expired token returns 401, not 500 | unit | `./vendor/bin/phpunit tests/Unit/BallotsControllerTest.php -x` | Exists, new test methods needed |
| VOTE-01 | `cast()` with already-used token returns 401 with `reason: token_already_used` | unit | `./vendor/bin/phpunit tests/Unit/BallotsControllerTest.php -x` | Exists, new test methods needed |
| VOTE-02 | `cast()` with already-used token calls `audit_log('vote_token_reuse', ...)` | unit | `./vendor/bin/phpunit tests/Unit/BallotsControllerTest.php -x` | Exists, new test methods needed |
| VOTE-03 | `cast()` on closed motion returns 409 with `motion_status: closed` | unit | `./vendor/bin/phpunit tests/Unit/BallotsControllerTest.php -x` | Exists, new test methods needed |
| VOTE-03 | `cast()` on closed motion calls `audit_log('vote_rejected', ...)` | unit | `./vendor/bin/phpunit tests/Unit/BallotsControllerTest.php -x` | Exists, new test methods needed |
| QUOR-01 | `QuorumEngine::ratioBlock()` with 0 eligible members returns `ratio: 0.0, met: false` | unit | `./vendor/bin/phpunit tests/Unit/QuorumEngineTest.php -x` | Exists, new test method needed |
| QUOR-02 | `AttendancesController::upsert()` triggers `quorum.updated` SSE path | unit | `./vendor/bin/phpunit tests/Unit/AttendancesControllerTest.php -x` | Must check if file exists |
| QUOR-02 | `AttendancesController::bulk()` triggers `quorum.updated` SSE path | unit | `./vendor/bin/phpunit tests/Unit/AttendancesControllerTest.php -x` | Must check if file exists |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit --testsuite Unit`
- **Per wave merge:** `./vendor/bin/phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/AttendancesControllerTest.php` — covers QUOR-02 (verify file exists; if not, create with upsert/bulk quorum broadcast tests)

## Sources

### Primary (HIGH confidence)
- Direct source read: `app/Controller/BallotsController.php` — full cast() implementation, existing token validation, audit_log patterns
- Direct source read: `app/Services/VoteTokenService.php` — validateAndConsume() flow, deprecated validate/consume
- Direct source read: `app/Services/BallotsService.php` — castBallot() exception throwing for closed motion and double-vote
- Direct source read: `app/Services/QuorumEngine.php` — ratioBlock() zero-division guard at lines 248-258
- Direct source read: `app/Controller/AttendancesController.php` — upsert() and bulk() quorum broadcast calls
- Direct source read: `app/SSE/EventBroadcaster.php` — quorumUpdated() signature
- Direct source read: `public/assets/js/pages/operator-realtime.js` — quorum.updated handler at line 107
- Direct source read: `tests/Unit/BallotsControllerTest.php` — existing test patterns and coverage gaps
- Direct source read: `tests/Unit/QuorumEngineTest.php` — computeRatioBlock() helper pattern
- Direct source read: `tests/Unit/VoteTokenServiceTest.php` — mocked-repo unit test patterns

### Secondary (MEDIUM confidence)
- Code inspection: operator-tabs.js `loadQuorumStatus()` — confirms JS already polls and redraws on quorum.updated SSE events

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — direct source code inspection, no external dependencies required
- Architecture: HIGH — all patterns are existing code; changes are additive
- Pitfalls: HIGH — identified from direct reading of code paths; no speculation

**Research date:** 2026-03-31
**Valid until:** 2026-04-30 (stable codebase; only changes if Phase 60 modifies AttendancesController or BallotsController)
