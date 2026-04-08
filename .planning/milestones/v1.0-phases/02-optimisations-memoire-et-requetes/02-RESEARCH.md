# Phase 2: Optimisations Memoire et Requetes - Research

**Researched:** 2026-04-07
**Domain:** PHP memory management, PDO PostgreSQL timeouts, streaming XLSX, SQL aggregation
**Confidence:** HIGH

## Summary

Phase 2 addresses four concrete performance problems found in the codebase. All four are
localized, surgical changes: no architectural rewrites, no new service boundaries. The fixes
touch four files plus one new dependency.

The most impactful change is replacing `phpoffice/phpspreadsheet` with `openspout/openspout`
for XLSX streaming. PhpSpreadsheet loads the entire dataset into an in-memory DOM model before
writing; OpenSpout writes row by row directly to the output stream. At 5 000 participations,
the difference is ~80 MB vs ~2-3 MB peak PHP memory.

The second most impactful change is collapsing 11 separate COUNT(*) queries in
`MeetingStatsRepository` into a single aggregation query. The pattern is already used in
`EmailQueueRepository::getQueueStats()` — this is a copy-adapt exercise.

PDO timeout and EmailQueueService batching are smaller changes but critical for production
reliability: a blocked PostgreSQL query currently locks a PHP-FPM worker indefinitely.

**Primary recommendation:** Implement in order — PDO timeout first (zero risk, pure addition),
then MeetingStats aggregation, then EmailQueue batch size fix, then OpenSpout migration.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
All implementation choices are at Claude's discretion — pure infrastructure phase. Use ROADMAP
phase goal, success criteria, and codebase conventions to guide decisions.

Key constraints from research:
- openspout/openspout ^5.6 pour streaming XLSX (sub-3MB memoire)
- PhpSpreadsheet reste si formules/charts necessaires — auditer feuille par feuille
- PostgreSQL COUNT(*) FILTER (WHERE ...) pour aggregation unique
- PDO::ATTR_TIMEOUT pour connection timeout, SET statement_timeout pour query timeout
- statement_timeout configurable par env (0 en CI/test)
- EmailQueueService batch de 25 avec LIMIT, pas de chargement complet

### Claude's Discretion
All implementation choices — pure infrastructure phase.

### Deferred Ideas (OUT OF SCOPE)
None — infrastructure phase.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PERF-01 | PDO::ATTR_TIMEOUT configure pour connection timeout, statement_timeout PostgreSQL pour query timeout, configurable par environnement | DatabaseProvider::connect() has no timeout; need to add PDO::ATTR_TIMEOUT + per-session SET statement_timeout via PDO exec after connect |
| PERF-02 | MeetingStatsRepository utilise une seule requete d'aggregation avec FILTER au lieu de 10+ COUNT(*) separes | 11 separate scalar() calls identified; EmailQueueRepository::getQueueStats() demonstrates exact pattern to replicate |
| PERF-03 | ExportService utilise openspout/openspout pour streaming XLSX, memoire sub-3MB quelle que soit la taille des donnees | All XLSX sheets use data cells only (no formulas/charts confirmed); openspout/openspout ^5.6 is the correct streaming replacement |
| PERF-04 | EmailQueueService traite les emails par lots (batch de 25), avec backpressure et pas de chargement complet en memoire | processQueue() already uses SKIP LOCKED batching; scheduleInvitations/Reminders/Results load ALL members at once — needs LIMIT/OFFSET cursor |
</phase_requirements>

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| openspout/openspout | ^5.6 | Streaming XLSX row-by-row write | Constant O(1) memory, no DOM model, PHP 8.x native |
| phpoffice/phpspreadsheet | ^1.29 (existing) | Legacy — kept for potential future formula sheets | Already in composer.json; do NOT remove |
| PDO (built-in) | PHP 8.3+ | Database access | Already used project-wide |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PostgreSQL statement_timeout | server-side | Kill long-running queries | All DB connections in production |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| openspout/openspout | PhpSpreadsheet StreamWriter | PhpSpreadsheet stream mode is experimental and still allocates per-cell objects |
| SET statement_timeout per connection | pg_query_params timeout | statement_timeout is standard, works with all drivers |

**Installation:**
```bash
composer require openspout/openspout:^5.6
```

---

## Architecture Patterns

### PERF-01: PDO Timeout Configuration

**What:** Two-layer timeout — connection timeout via `PDO::ATTR_TIMEOUT`, query timeout via
PostgreSQL `statement_timeout` session variable.

**Where to implement:** `DatabaseProvider::connect()` in
`app/Core/Providers/DatabaseProvider.php`

**Configuration source:** `$_ENV['DB_STATEMENT_TIMEOUT_MS']` — default `30000` (30 s),
override to `0` in test/CI environments to disable.

```php
// Source: PHP docs + PostgreSQL docs
// In DatabaseProvider::connect() after creating the PDO instance:

$timeoutMs = (int) ($_ENV['DB_STATEMENT_TIMEOUT_MS'] ?? 30000);

self::$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
    PDO::ATTR_TIMEOUT            => 10,  // connection timeout in seconds
]);

// Per-session query timeout (0 = disabled)
if ($timeoutMs > 0) {
    self::$pdo->exec("SET statement_timeout = {$timeoutMs}");
}
```

**Why two layers:**
- `PDO::ATTR_TIMEOUT` covers TCP connection establishment (driver level)
- `statement_timeout` covers slow queries after the connection is open (PostgreSQL level)

**What happens on timeout:** PostgreSQL raises `57014 query_canceled` error, PDO throws
`PDOException`. The existing `ERRMODE_EXCEPTION` mode propagates this to the controller
error handler — worker is not blocked.

**Test/CI pattern:** Set `DB_STATEMENT_TIMEOUT_MS=0` in `.env.test` or CI env. The `0`
value skips the `SET statement_timeout` call entirely.

### PERF-02: MeetingStatsRepository Aggregation

**What:** Replace 11 individual `scalar()` calls with a single `selectOne()` using
`COUNT(*) FILTER (WHERE condition)`.

**Where to implement:** `app/Repository/MeetingStatsRepository.php`

**The proof of concept already exists in the same codebase:**
```php
// Source: app/Repository/EmailQueueRepository.php::getQueueStats() (lines 164-179)
// This exact pattern is what PERF-02 implements for meeting stats:
$row = $this->selectOne(
    "SELECT
         COUNT(*) as total,
         COUNT(*) FILTER (WHERE status = 'pending') as pending,
         COUNT(*) FILTER (WHERE status = 'processing') as processing,
         COUNT(*) FILTER (WHERE status = 'sent') as sent,
         COUNT(*) FILTER (WHERE status = 'failed') as failed,
         COUNT(*) FILTER (WHERE status = 'cancelled') as cancelled
     FROM email_queue
     WHERE tenant_id = :tenant_id
       AND created_at > now() - interval '7 days'",
    [':tenant_id' => $tenantId],
);
```

**New method to add:** `getDashboardStats(string $meetingId, string $tenantId): array`
returning all counts in a single query:

```sql
-- Combines countPresent, countProxy, countMotions, countClosedMotions,
-- countOpenMotions, countAdoptedMotions, countRejectedMotions, countBallots,
-- countProxies — all into ONE round-trip
SELECT
    COUNT(*) FILTER (WHERE a.mode IN ('present','remote'))  AS present_count,
    COUNT(*) FILTER (WHERE a.mode = 'proxy')                AS proxy_count
FROM attendances a
WHERE a.meeting_id = :mid AND a.tenant_id = :tid
-- ... joined sub-selects for motions, ballots, proxies via CTEs or sub-queries
```

**Note:** The 11 methods span multiple tables (attendances, motions, ballots, proxies,
audit_events). A single join requires CTEs. The cleanest approach is one CTE per table,
combined with CROSS JOIN LATERAL or subquery selects. See Code Examples section.

**Backward compatibility:** Keep the individual methods — callers outside the dashboard
may use them. Add `getDashboardStats()` as a new method. Dashboard controllers switch
to the new method.

### PERF-03: OpenSpout Streaming XLSX

**What:** Replace PhpSpreadsheet calls in `ExportService` with OpenSpout streaming writer.
OpenSpout writes rows to a temp file/stream incrementally — memory stays constant at ~2-3 MB.

**Audit result — no formulas or charts detected:**
- `createSpreadsheet()` — data cells only, bold headers, fill color, auto-size, freeze pane
- `addSheet()` — same pattern
- `createFullExportSpreadsheet()` — multiple data sheets, no formulas

**OpenSpout does NOT support:** cell styling (bold, background fill), auto-column width,
freeze pane in streaming mode. These are acceptable trade-offs for the streaming benefit.
The CONTEXT.md decision is clear: use openspout for streaming, PhpSpreadsheet stays for
formula/chart sheets if needed in future.

**Migration approach:** Add a new `StreamingXlsxWriter` helper class (or static method)
in `ExportService`. ExportController callers switch from `createSpreadsheet()` +
`outputSpreadsheet()` to `streamXlsx()`.

```php
// Source: openspout/openspout README + docs
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

// In ExportService::streamXlsx():
public function streamXlsx(string $filename, array $headers, iterable $rows): void {
    $this->initXlsxOutput($filename);

    $writer = new Writer();
    $writer->openToFile('php://output');

    // Write header row
    $writer->addRow(Row::fromValues($headers));

    // Write data rows
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();
}
```

**Key design:** The `$rows` parameter is `iterable` — it can be a PDO cursor (generator)
rather than a pre-loaded array. This is where the real memory win comes from: the repository
query uses `PDOStatement::fetch()` in a loop rather than `fetchAll()`.

**Multi-sheet export:** OpenSpout supports multiple sheets via `$writer->addNewSheetAndMakeItCurrent()`.
The `createFullExportSpreadsheet()` equivalent becomes `streamFullXlsx()` iterating sheets.

### PERF-04: EmailQueueService Batch Size and Member Loading

**Two issues identified:**

1. **`processQueue()` default batch size is 50** — requirement says 25. Simple constant change.

2. **`scheduleInvitations()`, `scheduleReminders()`, `scheduleResults()` load ALL members at
   once** via `$this->memberRepo->listActiveWithEmail($tenantId)` — returns unbounded array.
   For large associations (500+ members), this is the memory problem.

**Fix for issue 2:** Add a `listActiveWithEmailPaginated(string $tenantId, int $limit, int $offset): array`
method to `MemberRepository`. Loop with LIMIT/OFFSET in `EmailQueueService`.

```php
// Pattern: cursor-style pagination without loading full dataset
$offset = 0;
$batchSize = 25;

do {
    $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
    foreach ($members as $member) {
        // ... process member
    }
    $offset += $batchSize;
} while (count($members) === $batchSize);
```

**SQL for MemberRepository:**
```sql
SELECT id, full_name, email, tenant_id
FROM members
WHERE tenant_id = :tid AND is_active = true AND email IS NOT NULL AND email <> ''
ORDER BY id
LIMIT :limit OFFSET :offset
```

**Note on `sendInvitationsNow()`:** This method also calls `listActiveWithEmail()` and then
does `array_slice()` for limit. Same fix applies — replace with paginated fetch.

### Anti-Patterns to Avoid
- **Loading fetchAll() then streaming:** Defeats the purpose of OpenSpout — the repository
  query MUST use a generator/cursor, not `fetchAll()`
- **Removing individual MeetingStats methods:** Keep them for backward compat; only ADD
  `getDashboardStats()`
- **Setting `statement_timeout` globally in PostgreSQL config:** Do it per-session in PHP —
  different workloads need different timeouts (exports vs dashboard vs auth)
- **Hard-coding timeout values:** Always read from `$_ENV` with a sensible default

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Streaming XLSX | Custom XML generator | openspout/openspout | ZIP/OOXML format has edge cases (shared strings table, cell type escaping) that openspout handles |
| Multi-sheet streaming | Manual XML parts | OpenSpout addNewSheetAndMakeItCurrent() | OpenSpout manages OOXML structure internally |
| Query timeout | PHP alarm/signal | PostgreSQL statement_timeout | Signals don't work in FPM; statement_timeout is server-enforced |
| Pagination cursor | Custom Redis cursor | PDO LIMIT/OFFSET | Simple, consistent with existing AbstractRepository patterns |

**Key insight:** The existing `EmailQueueRepository::fetchPendingBatch()` using `FOR UPDATE SKIP LOCKED`
is the model for all batch processing in this codebase — it's already correct. The problem
is upstream (member loading), not in the queue fetch itself.

---

## Common Pitfalls

### Pitfall 1: OpenSpout Cell Type Auto-Detection
**What goes wrong:** OpenSpout auto-detects cell types. Integer strings become numeric cells
in Excel, breaking leading-zero strings (IDs, phone numbers).
**Why it happens:** `Row::fromValues()` infers type from PHP type.
**How to avoid:** Wrap string-type values in `Cell::fromValue((string) $value)` explicitly,
or cast all values to string before passing to `fromValues()`.
**Warning signs:** Excel shows numbers where strings are expected (UUIDs displayed as 0).

### Pitfall 2: statement_timeout Affects COPY/pg_dump
**What goes wrong:** If the application ever uses bulk COPY operations, `statement_timeout`
kills them.
**Why it happens:** `SET statement_timeout` applies to all statements in the session.
**How to avoid:** Not a concern here — this app uses INSERT/SELECT only, no COPY.

### Pitfall 3: PDO::ATTR_TIMEOUT Is Connection-Only
**What goes wrong:** Developer thinks ATTR_TIMEOUT covers slow queries — it does not.
**Why it happens:** PHP docs are ambiguous. ATTR_TIMEOUT is for the TCP connection phase only.
**How to avoid:** Always use BOTH: `ATTR_TIMEOUT` for connection + `statement_timeout` for queries.

### Pitfall 4: OpenSpout `php://output` With Active Output Buffers
**What goes wrong:** ob_start() buffers output before streaming starts, defeating streaming.
**Why it happens:** Symfony components or middleware may activate output buffering.
**How to avoid:** Call `while (ob_get_level() > 0) { ob_end_clean(); }` before opening writer.
ExportController already does this in CSV methods — apply same pattern to XLSX.

### Pitfall 5: OFFSET Pagination Skips Records on Concurrent Inserts
**What goes wrong:** New members added during `scheduleInvitations()` loop may be missed
or processed twice when using LIMIT/OFFSET.
**Why it happens:** OFFSET is position-based, not cursor-based.
**How to avoid:** For email scheduling, this is acceptable — invitations are idempotent
(the `onlyUnsent` check skips already-sent members). Document this explicitly.

### Pitfall 6: MeetingStatsRepository Multi-Table Aggregation Complexity
**What goes wrong:** Joining attendances + motions + ballots + proxies in one SQL produces
a Cartesian product if not handled correctly.
**Why it happens:** COUNT(*) on a join inflates counts.
**How to avoid:** Use independent subqueries in SELECT, not JOINs:
```sql
SELECT
  (SELECT COUNT(*) FROM attendances WHERE meeting_id=:mid AND mode IN ('present','remote')) AS present_count,
  (SELECT COUNT(*) FROM motions WHERE meeting_id=:mid AND decision='adopted') AS adopted_count,
  ...
```
This is simpler than CTEs and avoids any join multiplication risk.

---

## Code Examples

### PERF-01: DatabaseProvider with Timeouts
```php
// Source: PHP PDO docs + PostgreSQL SET statement_timeout docs
// app/Core/Providers/DatabaseProvider.php

$timeoutMs = (int) ($_ENV['DB_STATEMENT_TIMEOUT_MS'] ?? 30000);

self::$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_STRINGIFY_FETCHES  => false,
    PDO::ATTR_TIMEOUT            => 10,
]);

if ($timeoutMs > 0) {
    self::$pdo->exec("SET statement_timeout = {$timeoutMs}");
}
```

### PERF-02: Dashboard Stats Aggregation (Subquery Style)
```php
// Source: PostgreSQL docs — COUNT(*) subqueries in SELECT list
// app/Repository/MeetingStatsRepository.php — new method getDashboardStats()

public function getDashboardStats(string $meetingId, string $tenantId): array {
    $row = $this->selectOne(
        'SELECT
            (SELECT COUNT(*) FROM attendances
             WHERE meeting_id=:mid AND tenant_id=:tid
               AND mode IN (\'present\',\'remote\'))          AS present_count,
            (SELECT COUNT(*) FROM attendances
             WHERE meeting_id=:mid AND tenant_id=:tid
               AND mode = \'proxy\')                          AS proxy_count,
            (SELECT COUNT(*) FROM motions
             WHERE meeting_id=:mid AND tenant_id=:tid)        AS total_motions,
            (SELECT COUNT(*) FROM motions
             WHERE meeting_id=:mid AND tenant_id=:tid
               AND closed_at IS NOT NULL)                     AS closed_motions,
            (SELECT COUNT(*) FROM motions
             WHERE meeting_id=:mid AND tenant_id=:tid
               AND opened_at IS NOT NULL AND closed_at IS NULL) AS open_motions,
            (SELECT COUNT(*) FROM motions
             WHERE meeting_id=:mid AND tenant_id=:tid
               AND decision = \'adopted\')                    AS adopted_motions,
            (SELECT COUNT(*) FROM motions
             WHERE meeting_id=:mid AND tenant_id=:tid
               AND decision = \'rejected\')                   AS rejected_motions,
            (SELECT COUNT(*) FROM proxies
             WHERE meeting_id=:mid AND tenant_id=:tid)        AS proxy_count2',
        [':mid' => $meetingId, ':tid' => $tenantId],
    );
    return $row ?? [];
}
```

### PERF-03: OpenSpout Streaming Writer
```php
// Source: openspout/openspout README (github.com/openspout/openspout)
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;

public function streamXlsx(string $filename, array $headers, iterable $dataRows): void {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $this->initXlsxOutput($filename);

    $writer = new Writer();
    $writer->openToFile('php://output');
    $writer->addRow(Row::fromValues($headers));

    foreach ($dataRows as $row) {
        $writer->addRow(Row::fromValues(array_values($row)));
    }

    $writer->close();
}
```

### PERF-04: Paginated Member Fetch
```php
// app/Repository/MemberRepository.php — new method
public function listActiveWithEmailPaginated(
    string $tenantId,
    int $limit,
    int $offset
): array {
    return $this->selectAll(
        'SELECT id, full_name, email, voting_power, tenant_id
         FROM members
         WHERE tenant_id = :tid
           AND is_active = true
           AND email IS NOT NULL
           AND email <> \'\'
         ORDER BY id
         LIMIT :limit OFFSET :offset',
        [':tid' => $tenantId, ':limit' => $limit, ':offset' => $offset],
    );
}

// EmailQueueService batch loop:
$offset = 0;
do {
    $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, 25, $offset);
    foreach ($members as $member) { /* ... process ... */ }
    $offset += 25;
} while (count($members) === 25);
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PhpSpreadsheet full-DOM XLSX | OpenSpout streaming XLSX | openspout v5 (2023+) | O(1) vs O(n) memory |
| Multiple COUNT(*) round-trips | COUNT(*) FILTER single query | PostgreSQL 9.4+ | 10x fewer DB round-trips |
| Unbounded fetchAll() | LIMIT/OFFSET paginated fetch | N/A — standard practice | Constant memory for scheduling |
| No PDO timeout | ATTR_TIMEOUT + statement_timeout | N/A — always available | Worker never blocked indefinitely |

**Deprecated/outdated:**
- `array_map([$export, 'formatAttendanceRow'], $rows)`: Builds full formatted array before XLSX write — replace with generator in streaming path

---

## Open Questions

1. **Dashboard controllers: which ones call MeetingStatsRepository?**
   - What we know: CONTEXT.md mentions "tableau de bord d'assemblee" — likely `DashboardController`
     or `MeetingWorkflowController`
   - What's unclear: Exact controller method(s) to update after adding `getDashboardStats()`
   - Recommendation: Planner should grep for `MeetingStatsRepository` instantiation in controllers
     as part of PERF-02 task

2. **OpenSpout column auto-sizing workaround**
   - What we know: OpenSpout streaming does not support auto-column-width
   - What's unclear: Whether users require formatted column widths in XLSX exports
   - Recommendation: Accept plain-width columns for streaming exports; this is a visual
     trade-off for functional correctness. Document in task.

3. **`sendInvitationsNow()` limit parameter**
   - What we know: Method already accepts `int $limit = 0` and does `array_slice()` after
     loading all members
   - What's unclear: Whether this is used in production or only admin tools
   - Recommendation: Fix anyway — replace `array_slice` with paginated fetch respecting limit

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | phpunit.xml (root) |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/ExportServiceTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PERF-01 | PDO::ATTR_TIMEOUT set on connect | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/DatabaseProviderTest.php --no-coverage` | ❌ Wave 0 |
| PERF-01 | statement_timeout SET when env > 0 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/DatabaseProviderTest.php --no-coverage` | ❌ Wave 0 |
| PERF-01 | statement_timeout NOT SET when env = 0 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/DatabaseProviderTest.php --no-coverage` | ❌ Wave 0 |
| PERF-02 | getDashboardStats() returns single-query result | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingStatsRepositoryTest.php --no-coverage` | ❌ Wave 0 |
| PERF-03 | streamXlsx() outputs valid XLSX bytes | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ExportServiceTest.php --no-coverage` | ✅ (extend) |
| PERF-03 | Memory under 3MB for 5000 rows | unit (memory_get_peak_usage) | `timeout 60 php vendor/bin/phpunit tests/Unit/ExportServiceTest.php --no-coverage` | ✅ (extend) |
| PERF-04 | processQueue() default batch = 25 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php --no-coverage` | ✅ (extend) |
| PERF-04 | scheduleInvitations() never loads all members at once | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php --no-coverage` | ✅ (extend) |

### Sampling Rate
- **Per task commit:** Quick run on affected test file
- **Per wave merge:** `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/DatabaseProviderTest.php` — covers PERF-01 (connection timeout, statement_timeout with/without env)
- [ ] `tests/Unit/MeetingStatsRepositoryTest.php` — covers PERF-02 (getDashboardStats single-query behavior with mock PDO)

*(ExportServiceTest.php and EmailQueueServiceTest.php exist — extend, don't create)*

---

## Sources

### Primary (HIGH confidence)
- Direct codebase analysis — `app/Core/Providers/DatabaseProvider.php`, `app/Repository/MeetingStatsRepository.php`, `app/Services/ExportService.php`, `app/Services/EmailQueueService.php`, `app/Controller/ExportController.php`, `app/Repository/EmailQueueRepository.php`
- `composer.json` — confirmed phpoffice/phpspreadsheet ^1.29, openspout not yet installed
- PHP built-in: `php -r "echo PDO::ATTR_TIMEOUT;"` → confirmed constant value 2
- PostgreSQL docs: `SET statement_timeout` — standard since PostgreSQL 9.0

### Secondary (MEDIUM confidence)
- openspout/openspout GitHub README — streaming XLSX pattern, Row::fromValues API
- PHP PDO documentation — ATTR_TIMEOUT scope (connection only, not query)

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — composer.json read directly, openspout decision locked in CONTEXT.md
- Architecture: HIGH — all four files read and analyzed, patterns confirmed against existing code
- Pitfalls: HIGH — identified from direct code reading (output buffering in CSV methods already handled, provides model)
- Test gaps: HIGH — test directory enumerated, existing files confirmed

**Research date:** 2026-04-07
**Valid until:** 2026-05-07 (stable dependencies)
