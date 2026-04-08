# Phase 4: Tests et Decoupage Controllers - Research

**Researched:** 2026-04-07
**Domain:** PHPUnit testing (SSE edge cases, ImportService fuzzy matching), PHP controller refactoring
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None — infrastructure phase.

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure/testing phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

### Deferred Ideas (OUT OF SCOPE)
None — infrastructure phase.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| TEST-03 | SSE EventBroadcaster (post-migration Redis) a des tests pour les race conditions et la fiabilite de delivery | EventBroadcaster code fully read; Redis pipeline pattern + consumer queue identified; existing test file has 6 tests, none for race/reconnect/reorder |
| TEST-04 | ImportController a des tests pour le fuzzy matching de colonnes CSV (partial matches, case sensitivity, headers multi-langue) | ImportService.mapColumns() fully read; alias lists in getMembersColumnMap() etc. fully documented; existing ImportServiceTest has no tests for UPPERCASE headers, accented aliases, or multi-lang edge cases |
</phase_requirements>

## Summary

Phase 4 closes two test gaps (TEST-03, TEST-04) and optionally decouples oversized controllers (success criterion 3 about MeetingReportsController and MotionsController). All work is pure PHPUnit test authoring and PHP refactoring — no new libraries, no new infrastructure.

**TEST-03** targets `EventBroadcaster` (app/SSE/EventBroadcaster.php). The class is fully Redis-only post Phase 1. The current `EventBroadcasterTest.php` has 6 tests that verify structural cleanliness (no file constants, no file methods) and basic queue behavior. Missing: Redis connection failure path in `queueRedis()`/`publishToSse()`, event ordering guarantees in the pipeline, consumer cleanup on reconnect.

**TEST-04** targets `ImportService::mapColumns()` and the column alias lists in `getMembersColumnMap()`, `getAttendancesColumnMap()`, `getMotionsColumnMap()`, `getProxiesColumnMap()`. The alias lists include accented variants (`'pondération'`, `'prénom'`, `'résolution'`…) and multi-language keys (`'tantièmes'`, `'collège'`…). Existing `ImportServiceTest.php` exercises `mapColumns()` with lower-case headers only — no tests for UPPERCASE raw headers (the reader lower-cases them), partial-alias resolution, or accented header strings from Excel/CSV.

**Controller split (success criterion 3):** MeetingReportsController (727 lines) and MotionsController (720 lines) both exceed the 400-line target. Both have existing test files with assertions about which public methods exist — any split must preserve those public method names and move them to new controllers without breaking the existing tests.

**Primary recommendation:** Write tests first (TEST-03, TEST-04), then split controllers if line count does not fall below 400 through simple private-method extraction. Tests are the blocker for phase acceptance; the controller split is a best-effort target with documented justification as fallback.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | ^10.5 (configured) | Test framework | Already in place, phpunit.xml configured |
| phpredis | installed | Redis PHP extension | Used by RedisProvider, available in test environment |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| ControllerTestCase | project class | Base for controller tests | Provides injectRepos(), callController(), injectJsonBody() |
| ReflectionClass | PHP stdlib | Access private properties/constants | Used in existing tests for structural assertions |

### Alternatives Considered
None — no new libraries needed for this phase.

## Architecture Patterns

### Test Infrastructure (established in Phase 3)

**PHPUnit bootstrap:** `tests/bootstrap.php` stubs `db()`, `api_ok()`, `api_fail()`, `api_request()`, `api_transaction()`, `api_require_role()` (no-op), `audit_log()`, `config()`, `api_current_tenant_id()`, etc. All controller tests run without a real database or Redis.

**ControllerTestCase:** Abstract base in `tests/Unit/ControllerTestCase.php`. Provides:
- `injectRepos(array)` — injects PHPUnit mocks into RepositoryFactory cache via Reflection
- `callController(string, string)` — instantiates controller, calls `handle($method)`, catches `ApiResponseException`
- `injectJsonBody(array)` — sets `Request::$cachedRawBody`
- `setAuth(userId, role, tenantId)` — injects into `AuthMiddleware::setCurrentUser()`
- setUp/tearDown reset superglobals, RepositoryFactory singleton, AuthMiddleware

**ImportService test pattern:** `ImportService` methods are static helpers (no DI needed). Instance methods (`processMemberImport`, etc.) require a `RepositoryFactory`. Existing test uses `buildMockFactory()` helper that pre-populates the `cache` property via Reflection — same pattern must be followed for any new instance method tests.

**EventBroadcaster test pattern:** Current tests use real Redis (group `@group redis`). The class uses `RedisProvider::connection()` statically — there is no constructor injection. Tests must configure `RedisProvider` in `setUp()` and clean up test keys in `tearDown()`. Injecting a mock Redis for `queueRedis()`/`publishToSse()` requires either:
- Option A: Real Redis (current pattern) — integration-style, fast but requires Redis available
- Option B: Subclassing/partial mock via Reflection on static state — complex, fragile
- **Recommended:** Keep real Redis (`@group redis`). Write tests that exercise failure conditions by manipulating Redis state directly (e.g., flushDB, wrong data types), not by mocking the connection.

### Recommended Project Structure
```
tests/Unit/
├── EventBroadcasterTest.php     # existing — add race condition / reconnect tests
├── ImportServiceTest.php        # existing — add fuzzy matching / case / accent tests
└── (new split controllers)
    ├── MotionVoteControllerTest.php   # if MotionsController is split
    └── MeetingPvControllerTest.php    # if MeetingReportsController is split
```

```
app/Controller/
├── MotionsController.php              # existing — keep CRUD + list methods
├── MotionVoteController.php           # NEW if split — open/close/tally/degradedTally/overrideDecision
├── MeetingReportsController.php       # existing — keep report/exportPvHtml
└── MeetingPvController.php            # NEW if split — generatePdf/generateReport/sendReport
```

### Pattern 1: SSE Race Condition Tests (EventBroadcaster)
**What:** Test that concurrent `queue()` calls do not lose events, and that `dequeue()` returns events in insertion order.
**When to use:** TEST-03 — "race conditions et fiabilite de delivery"

```php
// Source: EventBroadcaster.php + RedisProvider pattern
public function testQueuePreservesInsertionOrder(): void {
    $redis = RedisProvider::connection();
    $redis->del('sse:event_queue');

    EventBroadcaster::toMeeting('meeting-001', 'event.first', ['seq' => 1]);
    EventBroadcaster::toMeeting('meeting-001', 'event.second', ['seq' => 2]);
    EventBroadcaster::toMeeting('meeting-001', 'event.third', ['seq' => 3]);

    $events = EventBroadcaster::dequeue();
    $this->assertCount(3, $events);
    $this->assertEquals('event.first', $events[0]['type']);
    $this->assertEquals('event.second', $events[1]['type']);
    $this->assertEquals('event.third', $events[2]['type']);
}
```

### Pattern 2: Redis Failure Isolation (EventBroadcaster)
**What:** Test that `isServerRunning()` returns false when Redis key has expired, simulating server death.
**When to use:** TEST-03 — "perte de connexion Redis"

```php
// isServerRunning() wraps redis->exists() in try/catch(Throwable) → returns false
// The only testable failure scenario without mocking is key expiry / absence
public function testIsServerRunningReturnsFalseAfterHeartbeatExpiry(): void {
    $redis = RedisProvider::connection();
    $redis->set('sse:server:active', '1', ['EX' => 1]);
    sleep(2); // Let key expire
    $this->assertFalse(EventBroadcaster::isServerRunning());
}
```

**Note:** True Redis connection loss cannot be tested without mocking or stopping Redis. Test coverage of the `catch(Throwable)` path in `isServerRunning()` can be validated via structural assertion (catch block exists in source) if live failure injection is impractical. Document this limitation.

### Pattern 3: Consumer Queue Fan-Out (EventBroadcaster)
**What:** Test that `publishToSse()` writes to per-consumer queues in Redis.
**When to use:** TEST-03 — "fiabilite de delivery"

```php
// publishToSse() writes to sse:queue:{meetingId}:{consumerId}
// Test: register consumer, broadcast, verify per-consumer queue has the event
public function testPublishToSseFansOutToRegisteredConsumers(): void {
    $redis = RedisProvider::connection();
    $meetingId = 'test-meeting-001';
    $consumerId = 'consumer-abc';
    $redis->del("sse:consumers:{$meetingId}");
    $redis->del("sse:queue:{$meetingId}:{$consumerId}");

    $redis->sAdd("sse:consumers:{$meetingId}", $consumerId);
    EventBroadcaster::toMeeting($meetingId, 'vote.cast', ['motion_id' => 'xyz']);

    $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
    $raw = $redis->lRange("sse:queue:{$meetingId}:{$consumerId}", 0, -1);
    $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
    $this->assertCount(1, $raw);
    $event = json_decode($raw[0], true);
    $this->assertEquals('vote.cast', $event['type']);
}
```

### Pattern 4: mapColumns Fuzzy Matching Tests (ImportService)
**What:** Test alias resolution with uppercase, accented, whitespace-padded headers.
**When to use:** TEST-04 — "partial matches, case sensitivity, headers multi-langue"

The key insight: `readCsvFile()` and `readXlsxFile()` **both** normalize headers via `array_map(fn($h) => strtolower(trim($h)), ...)`. So `mapColumns()` always receives lower-case trimmed headers. The alias lists in column maps ARE lower-case. This means:

- Pure `mapColumns()` case sensitivity is NOT a real-world failure path (headers are pre-lowercased)
- The real TEST-04 gap is: verifying that the **alias lists** cover the expected variants for multi-language headers
- Additional gap: accented aliases like `'pondération'` vs `'ponderation'` — `strtolower()` does not strip accents, so the alias list must include both forms

```php
// Test accented header alias resolution
public function testMapColumnsAccentedHeaderAlias(): void {
    // readCsvFile normalizes: 'Pondération' → 'pondération' (strtolower keeps accents)
    $headers = ['nom', 'email', 'pondération'];  // accented, lower-case (as reader produces)
    $result = ImportService::mapColumns($headers, ImportService::getMembersColumnMap());
    $this->assertArrayHasKey('voting_power', $result, "'pondération' must map to voting_power");
}

public function testMapColumnsMultiLangHeaders(): void {
    // French tantièmes alias
    $headers = ['nom', 'email', 'tantièmes'];
    $result = ImportService::mapColumns($headers, ImportService::getMembersColumnMap());
    $this->assertArrayHasKey('voting_power', $result, "'tantièmes' must map to voting_power");
}

public function testMapColumnsAllVotingPowerAliases(): void {
    $aliases = ['voting_power', 'ponderation', 'pondération', 'weight', 'tantiemes', 'tantièmes', 'poids'];
    $columnMap = ImportService::getMembersColumnMap();
    foreach ($aliases as $alias) {
        $result = ImportService::mapColumns([$alias], $columnMap);
        $this->assertArrayHasKey('voting_power', $result, "Alias '{$alias}' must map to voting_power");
    }
}
```

### Pattern 5: Controller Split Strategy
**What:** Extract methods into new controllers while preserving existing test assertions.
**When to use:** To bring MeetingReportsController and MotionsController under 400 lines.

**MotionsController split candidate:**
- Keep in `MotionsController`: `createOrUpdate`, `createSimple`, `listForMeeting`, `deleteMotion`, `reorder`, `tally`, `current` (CRUD + read, ~400 lines)
- Move to `MotionVoteController`: `open`, `close`, `degradedTally`, `overrideDecision` (~320 lines)
- Routes change: same URL paths, new controller class
- Existing `MotionsControllerTest.php` asserts `testControllerHasAllExpectedMethods` — these assertions will fail if methods are moved; the test file must be updated to reflect the split

**MeetingReportsController split candidate:**
- Keep in `MeetingReportsController`: `report`, `exportPvHtml` + private helper methods (h, decisionLabel, fmtNum, modeLabel, choiceLabel, policyLabel) (~400 lines)
- Move to `MeetingPvController`: `generatePdf`, `generateReport`, `sendReport` (~300 lines)
- `generatePdf()` contains large inline HTML template (lines 360-390) — this is the primary size driver
- Existing `MeetingReportsControllerTest.php` asserts all 5 methods exist in `MeetingReportsController` — must update test

**Route update required** for any split: `app/routes.php` must point new method names to the new controller class.

### Anti-Patterns to Avoid
- **Moving a method without updating routes.php:** Will produce 404 in production for that endpoint.
- **Moving a method without updating `testControllerHasAllExpectedMethods`:** Will fail existing tests.
- **Testing Redis failure by stopping the daemon:** Fragile, not reproducible. Use key state manipulation instead.
- **Using `array()` syntax:** Project requires short `[]` syntax per CLAUDE.md.
- **Missing `declare(strict_types=1)`:** Required in every PHP file.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Fuzzy string matching for column aliases | Custom levenshtein-based matcher | Extend existing alias arrays in column maps | The codebase uses exact-match alias lists; adding aliases is correct extension of the existing pattern |
| Redis mock for unit tests | Custom Redis mock class | Real Redis with state setup/teardown | EventBroadcaster uses static `RedisProvider::connection()` — mocking requires deep reflection |
| New test base class | New abstract test class | ControllerTestCase + TestCase already provided | Existing infrastructure covers all patterns needed |

## Common Pitfalls

### Pitfall 1: strtolower vs mb_strtolower for accented headers
**What goes wrong:** `strtolower('Pondération')` returns `'pondération'` on UTF-8 systems but `'pondÃ©ration'` on single-byte locales. The codebase uses `strtolower` in CSV header normalization (not `mb_strtolower`).
**Why it happens:** `readCsvFile()` uses `array_map(fn($h) => strtolower(trim($h)), $headers)` — not `mb_strtolower`. On the target PHP 8.4 Alpine container (UTF-8 locale), this works. On single-byte locale systems, accented aliases break.
**How to avoid:** Tests should verify that the alias lists cover both accented and unaccented forms where applicable (e.g., both `'ponderation'` and `'pondération'`). The current alias list already does this for `voting_power`.
**Warning signs:** Test passes locally but fails in CI with a different locale.

### Pitfall 2: PublishToSse is private — not directly testable
**What goes wrong:** `EventBroadcaster::publishToSse()` is `private static`. Tests cannot call it directly. It is invoked via `queue()` which is invoked via `toMeeting()`/`toTenant()`.
**Why it happens:** Method is an implementation detail.
**How to avoid:** Test via `toMeeting()` + Redis state inspection (read `sse:queue:{meetingId}:{consumerId}` directly). The Pattern 3 example above shows this correctly.
**Warning signs:** Attempting `$broadcaster->publishToSse(...)` — will throw ReflectionException.

### Pitfall 3: EventBroadcaster cleanup in tearDown is incomplete
**What goes wrong:** Tests that write to `sse:consumers:{meetingId}` or `sse:queue:{meetingId}:{consumerId}` leave stale keys, causing subsequent tests to receive unexpected events.
**Why it happens:** Current `tearDown()` only deletes `sse:event_queue` and `sse:server:active`.
**How to avoid:** Each test that creates consumer/queue keys must delete them in its own cleanup, or use a test-unique meetingId (e.g., `'test-meeting-' . uniqid()`).
**Warning signs:** Test order-dependent failures in EventBroadcasterTest.

### Pitfall 4: MeetingReportsControllerTest asserts all 5 methods exist
**What goes wrong:** If `generatePdf`, `generateReport`, or `sendReport` are moved to a new controller, the assertion `testControllerHasAllExpectedMethods` in `MeetingReportsControllerTest.php` fails.
**Why it happens:** The test hardcodes all 5 method names in an `$expectedMethods` array.
**How to avoid:** When splitting, update the test file to match the new method distribution. Also add a new test file for the new controller.
**Warning signs:** Line `$this->assertTrue($ref->hasMethod($method))` failure on the moved methods.

### Pitfall 5: MotionsController `open()` method has 90 lines
**What goes wrong:** Even after moving `open`, `close`, `degradedTally`, `overrideDecision` to a new controller, the remaining methods in MotionsController may still exceed 400 lines because `open()` alone is ~90 lines.
**Why it happens:** `open()` does policy resolution, quorum setup, and event broadcasting inline.
**How to avoid:** Count lines carefully after the split. If MotionsController stays > 400 after split, the documentation requirement from success criterion 3 applies — write a brief inline comment justifying why.

## Code Examples

### Verified: EventBroadcaster queue flow
```php
// Source: app/SSE/EventBroadcaster.php lines 143-149
private static function queue(array $event): void {
    $event['queued_at'] = microtime(true);
    self::queueRedis($event);           // pushes to sse:event_queue

    if (self::isPushEnabled()) {
        self::publishToSse($event);     // fans out to sse:queue:{meetingId}:{consumerId}
    }
}
```

### Verified: Consumer key pattern for SSE fan-out
```php
// Source: app/SSE/EventBroadcaster.php lines 157-182
$consumers = $redis->sMembers("sse:consumers:{$meetingId}");
foreach ($consumers as $consumerId) {
    $queueKey = "sse:queue:{$meetingId}:{$consumerId}";
    $pipe->rPush($queueKey, $encoded);
    $pipe->expire($queueKey, 60);
    $pipe->lTrim($queueKey, -100, -1);  // keeps last 100 events per consumer
}
```

### Verified: Column alias lists (all accented variants)
```php
// Source: app/Services/ImportService.php lines 271-298
'voting_power' => ['voting_power', 'ponderation', 'pondération', 'weight', 'tantiemes', 'tantièmes', 'poids'],
'first_name'   => ['first_name', 'prenom', 'prénom'],
'groups'       => ['groups', 'groupes', 'group', 'groupe', 'college', 'collège', 'categorie', 'catégorie'],
'title'        => ['title', 'titre', 'intitule', 'intitulé', 'resolution', 'résolution'],
```

### Verified: Header normalization in readCsvFile
```php
// Source: app/Services/ImportService.php line 236
$headers = array_map(fn ($h) => strtolower(trim($h)), $headers);
// Same pattern in readXlsxFile line 130
$headers = array_map(fn ($h) => strtolower(trim($h)), $rowData);
```

### Verified: isServerRunning() catch pattern
```php
// Source: app/SSE/EventBroadcaster.php lines 215-222
public static function isServerRunning(): bool {
    try {
        $redis = RedisProvider::connection();
        return (bool) $redis->exists(self::HEARTBEAT_KEY);
    } catch (Throwable) {
        return false;
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| File fallback for SSE | Redis-only EventBroadcaster | Phase 1 | TEST-03 tests the Redis-only path; no file fallback paths to test |
| ImportController 921 lines | ImportController 149 lines + ImportService 791 lines | Phase 3 | TEST-04 tests ImportService methods directly |
| No consumer fan-out | Per-consumer Redis queues via `sse:queue:{meetingId}:{consumerId}` | Phase 1 | TEST-03 must verify fan-out behavior |

**Deprecated/outdated:**
- `QUEUE_FILE`, `LOCK_FILE` constants: removed in Phase 1, verified by existing `testNoFileConstantsExist()`
- File-based SSE methods (`queueFile`, `dequeueFile`): removed in Phase 1, verified by existing `testNoFileMethodsExist()`

## Open Questions

1. **Can Redis connection failure be tested without stopping Redis?**
   - What we know: `queueRedis()` has no `try/catch` — it will throw if Redis is down. `isServerRunning()` has `catch(Throwable)`. `publishToSse()` has no catch.
   - What's unclear: Whether the success criterion "tests pour les race conditions et la fiabilite de delivery" requires testing actual connection loss, or just delivery guarantees.
   - Recommendation: Focus tests on delivery guarantees (ordering, fan-out, queue cleanup) which are fully testable. For the connection failure path: add a structural assertion test that verifies `isServerRunning()` has a `catch(Throwable)` block via source inspection, or document that failure injection requires test infrastructure beyond PHPUnit scope.

2. **Should controller split happen in same phase as test writing, or separate plan?**
   - What we know: Success criterion 3 is "best effort with documented justification." Tests are the blocker for TEST-03 and TEST-04 acceptance.
   - What's unclear: Whether splitting MotionsController/MeetingReportsController can be done safely without introducing regressions given existing tests assert exact method lists.
   - Recommendation: Plan as separate wave: Wave 1 = TEST-03/TEST-04 tests. Wave 2 = controller split (if proceeding). This allows TEST-03/TEST-04 to close even if the split is deferred with documentation.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | phpunit.xml |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/EventBroadcasterTest.php tests/Unit/ImportServiceTest.php --no-coverage` |
| Full suite command | `timeout 120 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TEST-03 | SSE queue preserves event insertion order | unit (redis) | `timeout 60 php vendor/bin/phpunit tests/Unit/EventBroadcasterTest.php --no-coverage` | ✅ (extend) |
| TEST-03 | publishToSse fans out to registered consumers | unit (redis) | same | ✅ (extend) |
| TEST-03 | dequeue empties the queue atomically (no double-delivery) | unit (redis) | same | ✅ (extend) |
| TEST-03 | isServerRunning returns false when heartbeat expires | unit (redis) | same | ✅ (extend) |
| TEST-04 | mapColumns resolves all accented aliases (pondération, tantièmes, etc.) | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ImportServiceTest.php --no-coverage` | ✅ (extend) |
| TEST-04 | mapColumns resolves all multi-language aliases (proxies, motions maps) | unit | same | ✅ (extend) |
| TEST-04 | mapColumns handles whitespace-padded alias strings | unit | same | ✅ (extend) |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/EventBroadcasterTest.php tests/Unit/ImportServiceTest.php --no-coverage`
- **Per wave merge:** `timeout 120 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full unit suite green before `/gsd:verify-work`

### Wave 0 Gaps
None — existing test infrastructure covers all phase requirements. Both test files exist and the ControllerTestCase base class is fully established.

## Sources

### Primary (HIGH confidence)
- Direct code read: `app/SSE/EventBroadcaster.php` — full file, all methods, Redis key patterns
- Direct code read: `app/Services/ImportService.php` — all 791 lines, all column maps, all alias lists
- Direct code read: `tests/Unit/EventBroadcasterTest.php` — all 6 existing tests, setUp/tearDown pattern
- Direct code read: `tests/Unit/ImportServiceTest.php` — all existing tests, coverage gaps identified
- Direct code read: `tests/Unit/ControllerTestCase.php` — full infrastructure available to tests
- Direct code read: `tests/bootstrap.php` — stubs, no-op behaviors
- Direct code read: `app/Controller/MeetingReportsController.php` — 727 lines, 5 public methods, private helpers
- Direct code read: `app/Controller/MotionsController.php` — 720 lines, 11 public methods
- Direct code read: `app/routes.php` — confirmed controller→route mapping for both controllers
- Direct code read: `phpunit.xml` — PHPUnit 10.5, bootstrap path, test suite config

### Secondary (MEDIUM confidence)
- `.planning/REQUIREMENTS.md` — TEST-03 and TEST-04 definitions
- `.planning/STATE.md` — accumulated context from Phases 1-3

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new libraries; all infrastructure confirmed from direct code reads
- Architecture: HIGH — test patterns verified from existing passing tests
- Pitfalls: HIGH — derived from direct reading of actual code, not hypothesis

**Research date:** 2026-04-07
**Valid until:** 2026-05-07 (stable codebase, no external dependencies changing)
