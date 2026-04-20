# Phase 06: Refactoring EmailQueueService - Research

**Researched:** 2026-04-10
**Domain:** PHP service refactoring / email queue management
**Confidence:** HIGH

## Summary

EmailQueueService is 625 LOC with 11 methods. The bulk of the file (312 LOC) is three schedule methods (`scheduleInvitations`, `scheduleReminders`, `scheduleResults`) that share a near-identical pattern: resolve default template, paginate members, skip invalid members, render template with fallback, enqueue, log event. The remaining LOC covers queue processing (`processQueue` 68 LOC), immediate sending (`sendInvitationsNow`/`sendInvitationsNowBatch` 114 LOC), reminder processing (30 LOC), and thin repo delegations (9 LOC).

The retry/backoff logic already lives in `EmailQueueRepository.markFailed()` (exponential backoff with `5min * 2^retry_count`). The extracted `RetryPolicy` class should encapsulate the member-iteration + template-rendering + enqueue/send pattern shared across all scheduling methods, plus the queue processing logic. This follows the project pattern where extracted class names are descriptive of their domain role (ValueTranslator, ReportGenerator) rather than narrowly literal.

**Primary recommendation:** Extract a `RetryPolicy` final class containing the shared member-email-scheduling loop (template resolution, member filtering, enqueue/send, event logging) and the queue batch processor. EmailQueueService becomes a thin orchestrator that resolves config/template defaults and delegates to RetryPolicy.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REFAC-09 | EmailQueueService <300 LOC apres extraction de RetryPolicy | Method inventory shows 625 LOC; extracting shared scheduling loop (~312 LOC) + processQueue (~68 LOC) drops service well below 300 |
| REFAC-10 | RetryPolicy est une final class <300 LOC | Extracted code totals ~290 LOC after deduplication of the 3 schedule loops into 1 generic method |
</phase_requirements>

## Standard Stack

No new libraries needed. This is a pure internal refactoring using existing project patterns.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | ^10.5 | Test runner | Already in project, 34 passing tests for this service |

### Existing Patterns from Prior Phases
| Phase | Extraction | Pattern Used |
|-------|-----------|-------------|
| Phase 2 | AuthMiddleware -> SessionManager + RbacEngine | Lazy accessor `$this->sessionManager()` |
| Phase 3 | ImportService -> CsvImporter + XlsxImporter | Delegation with shared static utilities |
| Phase 4 | ExportService -> ValueTranslator | Lazy accessor `$this->translator()`, 22 delegation stubs |
| Phase 5 | MeetingReportsService -> ReportGenerator | Lazy accessor `$this->generator()`, pre-fetch data maps |

## Architecture Patterns

### Method Inventory (Current State)

| Method | Lines | LOC | Category | Extract? |
|--------|-------|-----|----------|----------|
| `__construct` | 29-48 | 20 | Setup | STAYS (adapts for RetryPolicy injection) |
| `processQueue` | 55-122 | 68 | Queue processing | EXTRACT to RetryPolicy |
| `scheduleInvitations` | 127-249 | 123 | Scheduling | EXTRACT core loop; keep thin wrapper |
| `scheduleReminders` | 254-345 | 92 | Scheduling | EXTRACT core loop; keep thin wrapper |
| `scheduleResults` | 351-447 | 97 | Scheduling | EXTRACT core loop; keep thin wrapper |
| `processReminders` | 452-481 | 30 | Orchestration | STAYS (calls scheduleInvitations) |
| `sendInvitationsNow` | 486-519 | 34 | Immediate send | EXTRACT to RetryPolicy |
| `sendInvitationsNowBatch` | 524-603 | 80 | Immediate send | EXTRACT to RetryPolicy |
| `getQueueStats` | 608-610 | 3 | Delegation | STAYS |
| `cancelMeetingEmails` | 615-617 | 3 | Delegation | STAYS |
| `cleanup` | 622-624 | 3 | Delegation | STAYS |

### Duplication Analysis

The three `schedule*` methods share this identical pattern (~70% of their code):

```
1. Resolve default template (findDefault by type)
2. Paginated member loop (batchSize=25, offset += batchSize)
3. Skip: wrong tenant_id
4. Skip: empty email
5. [scheduleInvitations only: skip if already sent]
6. Render template (try templateId -> fallback to default)
7. Enqueue to queue
8. Log event
```

Differences between the three:
- `scheduleInvitations`: has `onlyUnsent` check, creates invitation via `upsertBulk`, uses invitation token, different default subject
- `scheduleReminders`: no invitation check, empty token, different default subject/template constant
- `scheduleResults`: `isConfigured()` guard, no invitation check, empty token, different default subject/template constant

### Recommended Extraction: RetryPolicy

**What RetryPolicy encapsulates:**
1. **`processBatch()`** - Queue batch processing (current `processQueue` body)
2. **`scheduleForMembers()`** - Generic member-iteration + template-render + enqueue loop, parameterized by email type config
3. **`sendImmediateForMembers()`** - Immediate send loop (current `sendInvitationsNowBatch`)
4. **`sendImmediate()`** - Pagination wrapper (current `sendInvitationsNow` body)

**What EmailQueueService keeps:**
1. Constructor (simplified - injects RetryPolicy)
2. `scheduleInvitations()` - resolves template default, builds type config, delegates to `retryPolicy->scheduleForMembers()`
3. `scheduleReminders()` - same pattern
4. `scheduleResults()` - same pattern with `isConfigured()` guard
5. `processQueue()` - delegates to `retryPolicy->processBatch()`
6. `processReminders()` - orchestration (stays as-is, calls scheduleInvitations)
7. `sendInvitationsNow()` - delegates to `retryPolicy->sendImmediate()`
8. Three thin repo delegations (getQueueStats, cancelMeetingEmails, cleanup)
9. Lazy accessor `retryPolicy()`

### LOC Budget Estimate

**RetryPolicy (~280 LOC):**
- Class boilerplate + constructor: ~25 LOC
- `processBatch()`: ~55 LOC (from processQueue, cleaned up)
- `scheduleForMembers()`: ~85 LOC (unified loop from 3 schedule methods, parameterized)
- `sendImmediate()`: ~25 LOC (from sendInvitationsNow)
- `sendImmediateForMembers()`: ~70 LOC (from sendInvitationsNowBatch)
- Private helpers (member filtering, template rendering): ~20 LOC
- Total: ~280 LOC

**EmailQueueService (~200 LOC):**
- Class boilerplate + imports: ~20 LOC
- Constructor: ~25 LOC (adds RetryPolicy injection)
- Lazy accessor: ~4 LOC
- `processQueue()`: ~5 LOC (delegate)
- `scheduleInvitations()`: ~25 LOC (resolve template + type config + delegate)
- `scheduleReminders()`: ~20 LOC (resolve template + type config + delegate)
- `scheduleResults()`: ~22 LOC (isConfigured guard + resolve template + delegate)
- `processReminders()`: ~25 LOC (stays as-is)
- `sendInvitationsNow()`: ~8 LOC (delegate)
- Three delegations: ~9 LOC
- Total: ~200 LOC

### RetryPolicy Constructor Dependencies

RetryPolicy needs these dependencies (all via nullable DI):
- `EmailQueueRepository` - enqueue, markSent, markFailed, resetStuckProcessing, fetchPendingBatch
- `EmailEventRepository` - logEvent
- `InvitationRepository` - findStatusByMeetingAndMember, upsertBulk, findByMeetingAndMember, markSent, markBounced
- `MemberRepository` - listActiveWithEmailPaginated
- `MailerService` - send, isConfigured
- `EmailTemplateService` - renderTemplate, getVariables, renderHtml

EmailQueueService keeps:
- `EmailTemplateRepository` - findDefault (for template resolution before delegation)
- `ReminderScheduleRepository` - findDueReminders, markExecuted (for processReminders)
- All of the above (passes to RetryPolicy constructor)

### Type Config Pattern

To unify the three schedule methods, pass a config array to `scheduleForMembers()`:

```php
// In RetryPolicy
public function scheduleForMembers(
    string $tenantId,
    string $meetingId,
    ?string $templateId,
    ?string $scheduledAt,
    string $defaultSubjectTemplate,
    string $defaultBodyTemplate,
    bool $checkUnsent,
    bool $createInvitation,
    string $token,
): array
```

Or a simpler approach - a small value object or just named parameters. Given PHP 8.4 named arguments, the parameter list is readable.

### Recommended Project Structure

```
app/Services/
├── EmailQueueService.php    # Thin orchestrator (~200 LOC)
├── RetryPolicy.php          # Queue processing + member scheduling loops (~280 LOC)
└── ... (other services unchanged)
```

### Anti-Patterns to Avoid
- **Over-abstracting the schedule types**: Don't create separate strategy classes per email type (invitation/reminder/results). A single parameterized method is sufficient given the small differences.
- **Moving processReminders to RetryPolicy**: processReminders is orchestration (calls scheduleInvitations), not retry logic. It stays on EmailQueueService.
- **Breaking the MailerService injection**: MailerService is `final` and cannot be mocked. The existing test pattern (empty config = not configured, bad SMTP = configured but fails) must be preserved.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Template rendering | Custom render in RetryPolicy | Pass `EmailTemplateService` as dependency | Already handles fallback logic |
| Retry backoff | Custom backoff in RetryPolicy | `EmailQueueRepository.markFailed()` already has exponential backoff | SQL-level retry with `power(2, retry_count)` |
| Member pagination | Custom cursor logic | `MemberRepository.listActiveWithEmailPaginated()` with offset | Existing pattern works |

## Common Pitfalls

### Pitfall 1: Breaking the Test Builder Pattern
**What goes wrong:** Tests use `buildServiceNotConfigured()` and `buildServiceConfiguredBadSmtp()` which construct EmailQueueService with specific params. If RetryPolicy changes the constructor signature, all 34 tests break.
**How to avoid:** RetryPolicy is injected as nullable param with default `null`. The lazy accessor `retryPolicy()` handles instantiation. Existing test builders pass `null` for retryPolicy and the service auto-creates it internally.

### Pitfall 2: Template Rendering Requires DB
**What goes wrong:** `EmailTemplateService->getVariables()` and `renderTemplate()` call DB repos internally. Unit tests cannot reach the "member queued" code path.
**How to avoid:** Don't try to add new tests for the template rendering path. The existing test suite intentionally tests only skip/guard paths. The 1 skipped test documents this limitation.

### Pitfall 3: MailerService is Final
**What goes wrong:** Cannot mock MailerService in tests.
**How to avoid:** Continue using the real-instance pattern: empty config for "not configured", bad SMTP for "configured but fails."

### Pitfall 4: Breaking processReminders Chain
**What goes wrong:** `processReminders()` calls `$this->scheduleInvitations()` internally. If scheduleInvitations moves entirely to RetryPolicy, processReminders breaks.
**How to avoid:** Keep scheduleInvitations as a public method on EmailQueueService (thin wrapper). processReminders calls the wrapper, which delegates to RetryPolicy.

## Code Examples

### Lazy Accessor Pattern (from Phase 4/5)
```php
// Source: app/Services/ExportService.php (Phase 4 pattern)
private ?RetryPolicy $retryPolicy = null;
private function retryPolicy(): RetryPolicy {
    return $this->retryPolicy ??= new RetryPolicy(
        $this->queueRepo,
        $this->eventRepo,
        $this->invitationRepo,
        $this->memberRepo,
        $this->mailer,
        $this->templateService,
    );
}
```

### Unified Schedule Method Signature
```php
// In RetryPolicy - unifies scheduleInvitations/Reminders/Results member loop
public function scheduleForMembers(
    string $tenantId,
    string $meetingId,
    ?string $templateId,
    ?string $scheduledAt,
    string $defaultSubject,
    string $defaultBodyConstant,
    bool $checkUnsent = false,
    bool $createInvitation = false,
): array {
    $result = ['scheduled' => 0, 'skipped' => 0, 'errors' => []];
    $offset = 0;
    $batchSize = 25;
    do {
        $members = $this->memberRepo->listActiveWithEmailPaginated($tenantId, $batchSize, $offset);
        foreach ($members as $member) {
            // Unified member filtering + template rendering + enqueue
        }
        $offset += $batchSize;
    } while (count($members) === $batchSize);
    return $result;
}
```

### Thin Wrapper Pattern
```php
// In EmailQueueService - scheduleReminders becomes ~15 LOC
public function scheduleReminders(
    string $tenantId,
    string $meetingId,
    ?string $templateId = null,
): array {
    if (!$templateId) {
        $defaultTemplate = $this->emailTemplateRepo->findDefault($tenantId, 'reminder');
        $templateId = $defaultTemplate['id'] ?? null;
    }
    return $this->retryPolicy()->scheduleForMembers(
        $tenantId, $meetingId, $templateId, null,
        'Rappel : {{meeting_title}}',
        EmailTemplateService::DEFAULT_REMINDER_TEMPLATE,
    );
}
```

## Existing Test Coverage

### EmailQueueServiceTest.php (783 LOC, 34 tests, 1 skipped)

| Test Group | Count | What's Tested |
|-----------|-------|---------------|
| processQueue (not configured) | 2 | Early return, structure |
| processQueue (configured) | 4 | Reset stuck, empty batch, SMTP failure, default batch size |
| scheduleInvitations | 7 | Skip no-email, skip other tenant, skip already sent, queue failure, structure, pagination, onlyUnsent=false |
| processReminders | 3 | Empty, processes due, structure |
| sendInvitationsNow | 8 | Not configured, structure, skip no-email, skip other tenant, skip sent, limit, template fallback, no-template path |
| scheduleReminders | 4 | Queue all, skip no-email, template type lookup, structure |
| scheduleResults | 3 | Not configured early return, configured reaches members, template type lookup |
| Delegations | 3 | getQueueStats, cancelMeetingEmails, cleanup |

### EmailQueueRepositoryRetryTest.php (108 LOC, 7 tests)
Source-code-as-spec tests verifying SQL patterns exist in repository file. Not affected by this refactoring.

### Key Constraint
All 34 EmailQueueServiceTest tests must pass unchanged after refactoring. The tests mock repositories and call EmailQueueService public methods directly. Since public method signatures stay identical, tests should work as-is.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | phpunit.xml |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php tests/Unit/EmailQueueRepositoryRetryTest.php --no-coverage` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REFAC-09 | EmailQueueService <300 LOC | metric | `wc -l app/Services/EmailQueueService.php` | N/A |
| REFAC-09 | Public API unchanged | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php --no-coverage` | Yes (34 tests) |
| REFAC-10 | RetryPolicy <300 LOC, final class | metric + lint | `wc -l app/Services/RetryPolicy.php && php -l app/Services/RetryPolicy.php` | Wave 0 |
| REFAC-10 | RetryPolicy syntax valid | lint | `php -l app/Services/RetryPolicy.php` | Wave 0 |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php --no-coverage`
- **Per wave merge:** Full suite including retry tests
- **Phase gate:** Full suite green + LOC checks

### Wave 0 Gaps
- [ ] `app/Services/RetryPolicy.php` -- new file to create (REFAC-10)
- No new test files needed -- existing 34 tests cover the public API

## Sources

### Primary (HIGH confidence)
- Direct source analysis of `app/Services/EmailQueueService.php` (625 LOC)
- Direct source analysis of `app/Repository/EmailQueueRepository.php` (205 LOC)
- Direct analysis of `tests/Unit/EmailQueueServiceTest.php` (783 LOC, 34 tests)
- Direct analysis of `tests/Unit/EmailQueueRepositoryRetryTest.php` (108 LOC, 7 tests)
- Prior phase plans: Phase 4 (ExportService/ValueTranslator) and Phase 5 (MeetingReportsService/ReportGenerator)

### Secondary (MEDIUM confidence)
- LOC budget estimates based on manual line counting and deduplication analysis

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new dependencies, pure refactoring
- Architecture: HIGH - follows established Phase 2-5 extraction patterns exactly
- Pitfalls: HIGH - derived from direct code and test analysis, final class constraints verified

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable internal refactoring, no external dependencies)
