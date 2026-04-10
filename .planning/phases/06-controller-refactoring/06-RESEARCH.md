# Phase 6: Controller Refactoring - Research

**Researched:** 2026-04-10
**Domain:** PHP controller decomposition, service extraction, DI patterns
**Confidence:** HIGH

## Summary

Phase 6 targets four controllers exceeding 500 LOC each (MeetingsController 687, MeetingWorkflowController 559, OperatorController 516, AdminController 510) for reduction to <300 LOC via extraction into dedicated service classes. The codebase already has a proven pattern for this: `ImportService` demonstrates the exact `final class` + nullable DI + `RepositoryFactory::getInstance()` fallback pattern. An existing `MeetingWorkflowService` (237 LOC) already handles pre-transition validation and must be EXPANDED rather than replaced.

A critical prerequisite is the CTRL-05 reflection audit: all four controller test files use `ReflectionClass` for structural assertions (isFinal, hasMethod, isPublic). These tests must be rewritten to assert against the new service classes BEFORE the split happens, ensuring the git log shows test commits preceding split commits.

**Primary recommendation:** Follow the ImportService pattern exactly. Extract business logic into services, keep controllers as thin HTTP-to-service adapters. Address the MeetingWorkflowService naming conflict by expanding the existing service rather than creating a new one.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CTRL-01 | MeetingsController.php reduced to <300 LOC via MeetingLifecycleService | Controller has 11 methods, 687 LOC. createMeeting (188 LOC) and stats/status methods are prime extraction targets. MeetingLifecycleService does not exist yet -- clean creation. |
| CTRL-02 | MeetingWorkflowController.php reduced to <300 LOC via MeetingWorkflowService | **CONFLICT**: MeetingWorkflowService already exists (237 LOC) handling issuesBeforeTransition/getTransitionReadiness. Controller methods (transition, launch, readyCheck, consolidate, resetDemo) must be added to existing service or a companion service. |
| CTRL-03 | OperatorController.php reduced to <300 LOC via OperatorWorkflowService | 3 methods, 516 LOC. workflowState() alone is ~200 LOC of pure business logic. Clean extraction target. |
| CTRL-04 | AdminController.php reduced to <300 LOC via AdminService | users() method is 174 LOC with 8 action branches. Extraction is straightforward. |
| CTRL-05 | Pre-split reflection audit: tests rewritten before split | All 4 test files use ReflectionClass (25+ usages total). Structural tests (isFinal, hasMethod, isPublic) must be rewritten to target service classes. Git log must show test commits before split commits. |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | ^10.5 | Unit tests for new services | Already in project, ControllerTestCase pattern established |
| PHP-CS-Fixer | ^3.0 | Code style after extraction | Project convention |
| PHPStan | ^2.1 | Type checking new services | Project convention |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| RepositoryFactory | (internal) | DI container for repos | All service constructors use nullable DI with this fallback |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Expanding existing MeetingWorkflowService | Creating MeetingWorkflowTransitionService | Adds naming complexity; expanding is cleaner since the service already owns workflow logic |
| Individual repo injection per service | RepositoryFactory injection | Individual repos give finer granularity but project convention uses RepositoryFactory pattern |

## Architecture Patterns

### Recommended Project Structure
```
app/Services/
├── MeetingLifecycleService.php    # NEW - extracted from MeetingsController
├── MeetingWorkflowService.php     # EXISTING (237 LOC) - EXPAND with controller logic
├── OperatorWorkflowService.php    # NEW - extracted from OperatorController
├── AdminService.php               # NEW - extracted from AdminController
```

### Pattern 1: ImportService Extraction Pattern (CANONICAL)
**What:** `final class` with nullable constructor DI, `RepositoryFactory::getInstance()` fallback
**When to use:** Every new service in this phase
**Example:**
```php
// Source: app/Services/ImportService.php (existing codebase pattern)
final class MeetingLifecycleService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    public function createMeeting(array $data, string $tenantId): array {
        // Business logic extracted from MeetingsController::createMeeting()
    }
}
```

### Pattern 2: MeetingWorkflowService DI Pattern (ALTERNATIVE - individual repos)
**What:** Individual nullable repo parameters instead of RepositoryFactory
**When to use:** When the service uses specific repos and tests need fine-grained mocking
**Example:**
```php
// Source: app/Services/MeetingWorkflowService.php (existing codebase pattern)
final class MeetingWorkflowService {
    public function __construct(
        ?MeetingRepository $meetingRepo = null,
        ?MotionRepository $motionRepo = null,
        // ...
    ) {
        $this->meetingRepo = $meetingRepo ?? RepositoryFactory::getInstance()->meeting();
    }
}
```

### Pattern 3: Thin Controller After Extraction
**What:** Controller becomes a thin HTTP adapter delegating to service
**When to use:** All 4 controllers after extraction
**Example:**
```php
final class MeetingsController extends AbstractController {
    public function createMeeting(): void {
        $cached = IdempotencyGuard::check();
        if ($cached !== null) { api_ok($cached, 201); }
        
        $data = api_request('POST');
        $service = new MeetingLifecycleService($this->repo());
        // Or: $service = new MeetingLifecycleService(); if using RepositoryFactory internally
        $result = $service->createMeeting($data, api_current_tenant_id());
        
        IdempotencyGuard::store($result);
        api_ok($result, 201);
    }
}
```

### Anti-Patterns to Avoid
- **God-service:** Each service MUST stay under 300 LOC. If a service grows too large, split further.
- **Service calling api_ok/api_fail directly:** Services return data/throw exceptions. Controllers handle HTTP responses.
- **Breaking URL contract:** Routes file only changes the class handler, never the URL path.
- **Moving Reflection tests to service without rewriting:** Tests must test the service's PUBLIC API, not internal structure.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Repository injection | Custom DI container | RepositoryFactory + nullable constructor | Project convention, works with existing test infrastructure |
| Test base class | New test helper | ControllerTestCase + direct service instantiation | ControllerTestCase already handles RepositoryFactory mocking |
| HTTP response formatting in services | api_ok/api_fail calls in services | Return arrays, throw exceptions | Services must be HTTP-agnostic for testability |

**Key insight:** The ControllerTestCase already provides `injectRepos()` for controller tests, but service tests should use direct constructor injection (like MeetingWorkflowServiceTest does) for simplicity.

## Common Pitfalls

### Pitfall 1: MeetingWorkflowService Naming Conflict
**What goes wrong:** Creating a new MeetingWorkflowService overwrites the existing 237-LOC service that MeetingWorkflowController already depends on.
**Why it happens:** The success criteria names "MeetingWorkflowService" as a target, but this service already exists.
**How to avoid:** EXPAND the existing MeetingWorkflowService with methods extracted from the controller (transition logic, launch path, readyCheck, consolidate, resetDemo). The existing service already has the correct DI pattern. Alternatively, name the new extraction target differently if the existing service would exceed 300 LOC.
**Warning signs:** `wc -l` on MeetingWorkflowService exceeds 300 after expansion.

### Pitfall 2: Reflection Tests Survive Unchanged
**What goes wrong:** Old tests asserting `ReflectionClass(MeetingsController::class)->hasMethod('createMeeting')` still pass after extraction because the controller still has a thin `createMeeting()` method. This gives false confidence.
**Why it happens:** The structural tests check the controller, not the service.
**How to avoid:** CTRL-05 requires rewriting tests BEFORE the split. New tests must assert service method existence and behavior. Old structural tests on controllers can remain but add service structural tests.
**Warning signs:** No service test files in `tests/Unit/` for the new services.

### Pitfall 3: Services Calling Global API Helpers
**What goes wrong:** Extracted service methods call `api_current_tenant_id()`, `api_ok()`, `api_fail()`, binding them to the HTTP context.
**Why it happens:** Copy-pasting controller code into service without refactoring.
**How to avoid:** Services receive `tenantId`, `userId` as parameters. They return arrays or throw standard exceptions (RuntimeException, InvalidArgumentException). Controllers translate between HTTP and service layer.
**Warning signs:** `grep 'api_ok\|api_fail\|api_current_' app/Services/NewService.php` returns matches.

### Pitfall 4: Breaking Existing MeetingWorkflowServiceTest
**What goes wrong:** Expanding MeetingWorkflowService changes its constructor signature, breaking the 46-line setUp in MeetingWorkflowServiceTest.
**Why it happens:** Adding new dependencies to support extracted controller methods.
**How to avoid:** If new repos are needed, add them as additional nullable parameters AFTER existing ones. This preserves backward compatibility of existing test setUp.
**Warning signs:** MeetingWorkflowServiceTest fails after service expansion.

### Pitfall 5: Route Changes
**What goes wrong:** Modifying route URLs or middleware when only the handler class should change.
**Why it happens:** Refactoring enthusiasm leads to "cleaning up" routes.
**How to avoid:** `app/routes.php` changes are ONLY allowed to change the class reference (e.g., `MeetingsController::class` stays -- controller is still the entry point, it just delegates to the service). In fact, routes should NOT change at all since controllers remain as thin adapters.
**Warning signs:** `git diff app/routes.php` shows URL or middleware changes.

### Pitfall 6: 300 LOC Ceiling Creates God-Service
**What goes wrong:** Cramming all extracted logic into one service to meet the "4 new services" requirement, resulting in a service barely under 300 LOC.
**Why it happens:** MeetingsController has 687 LOC across 11 methods -- extracting all to one service could produce ~450 LOC.
**How to avoid:** Extract only the heavy methods (createMeeting, stats, status, summary) to MeetingLifecycleService. Leave thin methods (index, archive, archivesList) in the controller if they're already under ~30 LOC each.
**Warning signs:** New service exceeds 250 LOC.

## Code Examples

### Service with RepositoryFactory DI (recommended for new services)
```php
// Source: Derived from ImportService.php pattern
<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;

final class MeetingLifecycleService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /**
     * Create a meeting with members and resolutions from wizard data.
     *
     * @return array{meeting_id: string, title: string, members_created: int, members_linked: int, motions_created: int}
     */
    public function createFromWizard(array $data, string $tenantId): array {
        // Extracted from MeetingsController::createMeeting()
        // Returns data array, does NOT call api_ok/api_fail
    }
}
```

### Service test with direct DI
```php
// Source: Derived from MeetingWorkflowServiceTest.php pattern
<?php
declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\MeetingRepository;
use AgVote\Service\MeetingLifecycleService;
use PHPUnit\Framework\TestCase;

class MeetingLifecycleServiceTest extends TestCase {
    private MeetingRepository&MockObject $meetingRepo;
    private MeetingLifecycleService $service;

    protected function setUp(): void {
        // Create mock RepositoryFactory
        $factory = new RepositoryFactory(null);
        $ref = new \ReflectionClass(RepositoryFactory::class);
        $cacheProp = $ref->getProperty('cache');
        $cacheProp->setAccessible(true);
        
        $this->meetingRepo = $this->createMock(MeetingRepository::class);
        $cacheProp->setValue($factory, [
            MeetingRepository::class => $this->meetingRepo,
        ]);
        
        $this->service = new MeetingLifecycleService($factory);
    }
}
```

### Thin controller after extraction
```php
// Source: Pattern derived from existing codebase conventions
public function createMeeting(): void {
    $cached = IdempotencyGuard::check();
    if ($cached !== null) { api_ok($cached, 201); }

    $data = api_request('POST');
    $tenantId = api_current_tenant_id();

    $service = new MeetingLifecycleService();
    $result = $service->createFromWizard($data, $tenantId);

    audit_log('meeting_created', 'meeting', (string) $result['meeting_id'], [
        'title' => $result['title'],
    ]);

    IdempotencyGuard::store($result);

    // Auto-assign president meeting role
    if (api_current_role() === 'president') {
        $this->repo()->user()->assignMeetingRole(
            $tenantId, (string) $result['meeting_id'],
            api_current_user_id(), 'president', api_current_user_id(),
        );
    }

    api_ok($result, 201);
}
```

## Critical Analysis: Per-Controller Extraction Plan

### MeetingsController (687 LOC -> target <300)
| Method | LOC | Extract? | Target |
|--------|-----|----------|--------|
| index() | 18 | No | Thin enough |
| update() | 75 | Yes | MeetingLifecycleService::updateMeeting() |
| archive() | 9 | No | Thin enough |
| archivesList() | 7 | No | Thin enough |
| status() | 50 | Yes | MeetingLifecycleService::getStatus() |
| statusForMeeting() | 45 | Yes | MeetingLifecycleService::getStatusForMeeting() |
| summary() | 55 | Yes | MeetingLifecycleService::getSummary() |
| stats() | 75 | Yes | MeetingLifecycleService::getStats() |
| createMeeting() | 188 | Yes | MeetingLifecycleService::createFromWizard() |
| deleteMeeting() | 45 | Yes | MeetingLifecycleService::deleteDraft() |
| voteSettings() | 50 | Yes | MeetingLifecycleService::handleVoteSettings() |
| validate() | 35 | Yes | MeetingLifecycleService::validateMeeting() |

**Estimated controller after:** ~180 LOC (thin delegators for 11 methods + imports)
**Estimated service:** ~450 LOC -- EXCEEDS 300 LOC ceiling. Split needed: MeetingLifecycleService (create, update, delete, validate ~250 LOC) + keep status/stats/summary in controller or create MeetingStatusService.

### MeetingWorkflowController (559 LOC -> target <300)
| Method | LOC | Extract? | Target |
|--------|-----|----------|--------|
| transition() | 206 | Yes | MeetingWorkflowService (expand) |
| launch() | 112 | Yes | MeetingWorkflowService (expand) |
| workflowCheck() | 20 | No | Already delegates to service |
| readyCheck() | 158 | Yes | MeetingWorkflowService (expand) |
| consolidate() | 30 | Partial | Thin enough, delegates to OfficialResultsService |
| resetDemo() | 47 | Yes | MeetingWorkflowService (expand) |

**Existing MeetingWorkflowService:** 237 LOC. Adding transition (~100), launch (~80), readyCheck (~120), resetDemo (~30) = ~567 LOC total. EXCEEDS 300 LOC ceiling.
**Solution:** Split into: MeetingWorkflowService (existing + readyCheck ~300 LOC) + MeetingTransitionService (transition + launch ~250 LOC). Or keep existing service and create a new companion.

### OperatorController (516 LOC -> target <300)
| Method | LOC | Extract? | Target |
|--------|-----|----------|--------|
| workflowState() | 200 | Yes | OperatorWorkflowService::getWorkflowState() |
| openVote() | 150 | Yes | OperatorWorkflowService::openVote() |
| anomalies() | 165 | Yes | OperatorWorkflowService::getAnomalies() |

**Estimated controller after:** ~90 LOC (3 thin delegators)
**Estimated service:** ~450 LOC -- EXCEEDS 300 LOC. Consider splitting: OperatorWorkflowService (workflowState ~200) + OperatorVoteService (openVote + anomalies ~250).

### AdminController (510 LOC -> target <300)
| Method | LOC | Extract? | Target |
|--------|-----|----------|--------|
| users() | 174 | Yes | AdminService::handleUserAction() |
| roles() | 30 | No | Thin enough |
| meetingRoles() | 100 | Yes | AdminService::handleMeetingRole() |
| systemStatus() | 100 | Yes | AdminService::getSystemStatus() |
| auditLog() | 85 | Yes | AdminService::getAuditLog() |
| parsePayload() | 10 | Yes | Move with auditLog |

**Estimated controller after:** ~150 LOC (5 thin delegators + roles inline)
**Estimated service:** ~370 LOC -- EXCEEDS 300 LOC. Split: AdminService (users + meetingRoles ~250) + AdminMonitoringService (systemStatus + auditLog ~170).

## Reflection Audit Summary (CTRL-05)

### MeetingsControllerTest.php (2407 LOC)
| Line | Usage | Purpose | Action |
|------|-------|---------|--------|
| 48-51 | ReflectionClass(Request) | Set cachedRawBody | KEEP -- test infrastructure, not structural |
| 60 | ReflectionClass(MeetingsController) | isFinal check | KEEP + add service isFinal test |
| 72-93 | ReflectionClass + hasMethod | Method existence | REWRITE to check service methods |
| 97-117 | ReflectionClass + isPublic | Visibility check | REWRITE to check service methods |

### MeetingWorkflowControllerTest.php (2380 LOC)
| Line | Usage | Purpose | Action |
|------|-------|---------|--------|
| 56 | ReflectionClass | extends check | KEEP + add for service |
| 68-86 | hasMethod + isPublic | Structural checks | REWRITE to check service |
| 2043-2052 | getMethods | Completeness check | REWRITE to check service |

### OperatorControllerTest.php (621 LOC)
| Line | Usage | Purpose | Action |
|------|-------|---------|--------|
| 46-60 | ReflectionClass + getMethods | Structure + method list | REWRITE to check service |

### AdminControllerTest.php (1133 LOC)
| Line | Usage | Purpose | Action |
|------|-------|---------|--------|
| 47-60 | ReflectionClass + getMethods | Structure + method list | REWRITE to check service |

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/TargetTest.php --no-coverage` |
| Full suite command | `timeout 120 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CTRL-01 | MeetingLifecycleService methods work | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingLifecycleServiceTest.php --no-coverage` | Wave 0 |
| CTRL-02 | MeetingWorkflowService expanded methods work | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingWorkflowServiceTest.php --no-coverage` | Exists (expand) |
| CTRL-03 | OperatorWorkflowService methods work | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/OperatorWorkflowServiceTest.php --no-coverage` | Wave 0 |
| CTRL-04 | AdminService methods work | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/AdminServiceTest.php --no-coverage` | Wave 0 |
| CTRL-05 | Reflection tests rewritten pre-split | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php tests/Unit/MeetingWorkflowControllerTest.php tests/Unit/OperatorControllerTest.php tests/Unit/AdminControllerTest.php --no-coverage` | Exists (rewrite) |
| ALL | Existing controller tests still pass | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php tests/Unit/MeetingWorkflowControllerTest.php tests/Unit/OperatorControllerTest.php tests/Unit/AdminControllerTest.php --no-coverage` | Exists |
| ALL | No URL changes | manual | `git diff app/routes.php` shows no URL changes | N/A |
| ALL | LOC check | manual | `wc -l app/Controller/Meetings*.php app/Controller/Operator*.php app/Controller/Admin*.php` | N/A |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/TargetTest.php --no-coverage`
- **Per wave merge:** `timeout 120 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full suite green + `wc -l` check on all 4 controllers

### Wave 0 Gaps
- [ ] `tests/Unit/MeetingLifecycleServiceTest.php` -- covers CTRL-01
- [ ] `tests/Unit/OperatorWorkflowServiceTest.php` -- covers CTRL-03
- [ ] `tests/Unit/AdminServiceTest.php` -- covers CTRL-04
- [ ] Reflection tests in 4 existing test files rewritten -- covers CTRL-05

## Open Questions

1. **MeetingWorkflowService expansion vs companion service**
   - What we know: Existing service is 237 LOC. Adding controller transition/launch/readyCheck would push to ~500 LOC.
   - What's unclear: Should we split into two services or keep one over 300 LOC (contradicting success criteria)?
   - Recommendation: The success criteria says "each service <=300 LOC (no god-service)". If expanding MeetingWorkflowService exceeds 300, create a MeetingTransitionService for the new transition/launch methods and leave the existing service for pre-transition validation. The success criteria names "MeetingWorkflowService" but the spirit is to avoid god-services.

2. **Where to put audit_log calls**
   - What we know: Currently in controllers alongside api_ok calls.
   - What's unclear: Should audit_log move to services or stay in controllers?
   - Recommendation: Keep audit_log in controllers. It's a cross-cutting concern tied to the HTTP request context (ip, user, etc.). Services should return data; controllers handle side effects.

3. **EventBroadcaster calls**
   - What we know: MeetingWorkflowController calls EventBroadcaster::meetingStatusChanged after transitions.
   - What's unclear: Should SSE broadcasting move to the service?
   - Recommendation: Keep in controllers. Broadcasting is a side effect that should happen after the HTTP response is composed. Services should focus on business logic and data.

## Sources

### Primary (HIGH confidence)
- app/Services/ImportService.php -- canonical extraction pattern (nullable DI, final class, RepositoryFactory)
- app/Services/MeetingWorkflowService.php -- existing service with individual repo DI pattern
- tests/Unit/MeetingWorkflowServiceTest.php -- canonical service test pattern
- tests/Unit/ControllerTestCase.php -- test infrastructure for controller/repo mocking
- app/Controller/AbstractController.php -- base controller pattern

### Secondary (HIGH confidence)
- All 4 target controllers read in full (exact LOC counts, method inventory, dependency analysis)
- All 4 target test files grepped for Reflection usage (25+ hits catalogued)
- app/routes.php read in full (route-to-controller mapping documented)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - project conventions are clear and consistent
- Architecture: HIGH - ImportService and MeetingWorkflowService provide proven extraction patterns
- Pitfalls: HIGH - based on actual codebase analysis (naming conflict, LOC ceiling math, reflection usage counts)

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable codebase, no external dependency changes)
