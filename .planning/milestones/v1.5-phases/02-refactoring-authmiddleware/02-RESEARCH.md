# Phase 2: Refactoring AuthMiddleware - Research

**Researched:** 2026-04-10
**Domain:** PHP class extraction / refactoring (AuthMiddleware decomposition)
**Confidence:** HIGH

## Summary

AuthMiddleware.php is currently 871 lines and contains three distinct responsibilities: session management (timeout, expiry, revalidation), RBAC evaluation (role checks, permission resolution, hierarchy, meeting roles), and orchestration (authenticate flow, deny, API key). The goal is to extract SessionManager and RbacEngine as standalone `final class` files with nullable DI constructors, each under 300 LOC, while keeping AuthMiddleware as a thin orchestrator under 300 LOC.

The file is entirely static (no instance methods), uses static state properties, and has 16 callers across the codebase. All callers use `AuthMiddleware::methodName()` statically. Two test files exist (AuthMiddlewareTest with 20 tests, AuthMiddlewareTimeoutTest with 5 tests) that must continue to pass without modification.

**Primary recommendation:** Extract two new classes into `AgVote\Core\Security` namespace (same package as AuthMiddleware). Keep the public API on AuthMiddleware as thin delegation methods so zero callers need updating. SessionManager and RbacEngine should also use static methods to match the existing architectural pattern.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REFAC-01 | AuthMiddleware <300 LOC after extraction of SessionManager and RbacEngine | Method inventory shows ~571 LOC can be extracted; remaining orchestrator + delegation stubs fit in <300 LOC |
| REFAC-02 | SessionManager and RbacEngine are final class with nullable DI, each <300 LOC | Session methods total ~120 LOC, RBAC methods total ~280 LOC; both fit under 300 LOC ceiling |
</phase_requirements>

## Current State Analysis

### AuthMiddleware Method Inventory (871 LOC total)

#### Session Management Methods (candidates for SessionManager)
| Method | Lines | LOC | Category |
|--------|-------|-----|----------|
| `getSessionTimeout()` | 114-148 | 35 | Session timeout with caching |
| `setSessionTimeoutForTest()` | 156-162 | 7 | Test helper for timeout |
| Session timeout logic in `authenticate()` | 373-391 | 19 | Timeout check + expiry |
| DB revalidation logic in `authenticate()` | 394-427 | 34 | Periodic user re-check |
| Related constants | 40-45 | 6 | DEFAULT_SESSION_TIMEOUT, SESSION_REVALIDATE_INTERVAL |
| Related state props | 64-81 | 18 | cachedSessionTimeout, testSessionTimeout, sessionExpired |
| **Subtotal** | | **~119** | |

#### RBAC Methods (candidates for RbacEngine)
| Method | Lines | LOC | Category |
|--------|-------|-----|----------|
| `requireRole()` | 276-340 | 65 | Role requirement with hierarchy |
| `can()` | 448-488 | 41 | Permission check |
| `requirePermission()` | 493-503 | 11 | Permission requirement |
| `canAccessMeeting()` | 536-559 | 24 | Meeting access check |
| `canTransition()` | 569-597 | 29 | State machine transition check |
| `requireTransition()` | 599-619 | 21 | State machine transition requirement |
| `availableTransitions()` | 621-632 | 12 | Available transitions list |
| `getEffectiveRoles()` | 227-237 | 11 | Combine system + meeting roles |
| `getMeetingRoles()` | 186-219 | 34 | Fetch meeting roles from DB |
| `setMeetingContext()` | 173-178 | 6 | Set meeting context |
| `normalizeRole()` | 246-249 | 4 | Role alias resolution |
| `isMeetingRole()` | 254-256 | 3 | Role type check |
| `isSystemRole()` | 261-263 | 3 | Role type check |
| `getAvailablePermissions()` | 672-690 | 19 | List user's permissions |
| `getRoleLevel()` | 692-694 | 3 | Hierarchy level lookup |
| `isRoleAtLeast()` | 696-698 | 3 | Hierarchy comparison |
| Role info/labels methods | 638-665 | 28 | getSystemRoles, getSystemRoleLabels, etc. |
| Related constants | 35-52 | 18 | SYSTEM_ROLES, MEETING_ROLES, ROLE_ALIASES |
| Related state props | 59-61 | 3 | currentMeetingId, currentMeetingRoles |
| **Subtotal** | | **~338** | Need to trim some into orchestrator |

#### Orchestration (stays in AuthMiddleware)
| Method | Lines | LOC | Category |
|--------|-------|-----|----------|
| `init()` | 87-89 | 3 | Config init |
| `isEnabled()` | 91-99 | 9 | Auth toggle check |
| `authenticate()` (core flow) | 346-438 | ~35 (after extraction) | Auth orchestration |
| `getCurrentUser()` | 509-513 | 5 | Getter |
| `getCurrentUserId()` | 516-519 | 4 | Getter |
| `getCurrentRole()` | 522-525 | 4 | Getter |
| `getCurrentTenantId()` | 527-530 | 4 | Getter |
| `extractApiKey()` | 704-716 | 13 | API key extraction |
| `findUserByApiKey()` | 718-746 | 29 | API key auth |
| `generateApiKey()` | 748-752 | 5 | Key generation |
| `hashApiKey()` | 754-756 | 3 | Key hashing |
| `deny()` | 758-777 | 20 | Error response |
| `logAuthFailure()` | 779-786 | 8 | Auth failure logging |
| `logAccessAttempt()` | 788-807 | 20 | Access logging |
| `getAccessLog()` | 809-811 | 3 | Log getter |
| `isOwner()` | 813-831 | 19 | Ownership check |
| `getAppSecret()` | 833-843 | 11 | Secret retrieval |
| `getDefaultTenantId()` | 846-849 | 4 | Default tenant |
| `setCurrentUser()` | 856-858 | 3 | Test helper |
| `reset()` | 860-870 | 11 | Test reset |
| Delegation stubs to SessionManager | | ~15 | Forward calls |
| Delegation stubs to RbacEngine | | ~50 | Forward calls |
| **Subtotal** | | **~278** | Under 300 ceiling |

### Existing Callers (16 files)

All callers use `AuthMiddleware::` static access. Key methods called externally:
- `authenticate()`, `requireRole()`, `getCurrentUser/Id/Role/TenantId()` -- most common
- `requireTransition()` -- MeetingWorkflowController
- `getSystemRoleLabels()`, `getMeetingRoleLabels()`, `getMeetingStatusLabels()` -- AdminController, AdminService
- `hashApiKey()` -- AdminService
- `getSessionTimeout()` -- CsrfMiddleware
- `isEnabled()`, `reset()` -- AuthController
- `getCurrentRole()` -- OfficialResultsService
- `init()` -- SecurityProvider

### Existing Tests

**AuthMiddlewareTest.php** (480 LOC, 20 test methods):
- Tests `isEnabled()`, `generateApiKey()`, `hashApiKey()`, `getCurrentUser/Id/Role/TenantId()`
- Tests `requireRole()` with various configs (auth disabled, no key, public, array)
- Tests `authenticate()` with session expiry, deactivated user, role change, DB failure
- Tests `reset()` clears all 10 static properties
- Uses Reflection to set static props, injects mock UserRepository

**AuthMiddlewareTimeoutTest.php** (103 LOC, 5 test methods):
- Tests `getSessionTimeout()` default, custom, min/max clamp, DB failure fallback
- Uses `setSessionTimeoutForTest()` helper

**Critical constraint:** Tests must pass WITHOUT modification. This means all `AuthMiddleware::` static calls in tests must still work. Delegation pattern is mandatory.

## Architecture Patterns

### Recommended Extraction Strategy

```
app/Core/Security/
  AuthMiddleware.php      (<300 LOC) - Orchestrator + delegation
  SessionManager.php      (<300 LOC) - Session timeout, expiry, revalidation
  RbacEngine.php          (<300 LOC) - Role checks, permissions, transitions
  Permissions.php          (existing) - Constants only, unchanged
  SessionHelper.php        (existing) - Cookie params, unchanged
```

### Pattern: Static Facade with Extracted Static Classes

Since AuthMiddleware is entirely static and callers depend on `AuthMiddleware::methodName()`, the extracted classes should also be static. AuthMiddleware becomes a thin delegation layer.

```php
// SessionManager.php - new file
final class SessionManager {
    // All session timeout constants + state props move here
    // getSessionTimeout(), setSessionTimeoutForTest()
    // checkSessionExpiry(array $sessionUser, int $lastActivity): bool
    // revalidateFromDb(array $sessionUser, int $lastDbCheck): ?array
    // reset()
}

// RbacEngine.php - new file  
final class RbacEngine {
    // SYSTEM_ROLES, MEETING_ROLES, ROLE_ALIASES constants move here
    // Meeting context state props move here
    // All role/permission/transition methods move here
    // reset()
}

// AuthMiddleware.php - slimmed down
final class AuthMiddleware {
    // State: $currentUser, $debug, $accessLog only
    // authenticate() - orchestrates SessionManager + API key auth
    // Delegation stubs: requireRole() -> RbacEngine, can() -> RbacEngine, etc.
    // API key methods stay here (authentication concern)
    // deny(), logging methods stay here
    // reset() calls SessionManager::reset() + RbacEngine::reset()
}
```

### Pattern: Delegation Stubs (zero-caller-change guarantee)

```php
// In AuthMiddleware.php - example delegation stub
public static function requireRole(string|array $roles, bool $strict = true): bool {
    if (!self::isEnabled()) {
        self::authenticate();
        return true;
    }
    // 'public' shortcut stays here (it's an auth concern)
    $roles = is_array($roles) ? $roles : [$roles];
    if (in_array('public', array_map([RbacEngine::class, 'normalizeRole'], $roles), true)) {
        return true;
    }
    $user = self::authenticate();
    if ($user === null) {
        if ($strict) { self::deny('authentication_required', 401); }
        return false;
    }
    return RbacEngine::checkRole($user, $roles, $strict)
        ?: ($strict ? self::deny('forbidden', 403) : false);
}
```

### Namespace Decision

Use `AgVote\Core\Security` (same as AuthMiddleware). Reasons:
- SessionManager here is a security-scoped session manager (timeout, revalidation), NOT a general-purpose session service
- RbacEngine is inherently a security component
- Keeps imports minimal in AuthMiddleware
- The `app/Services/` namespace is for business-domain services, not framework-level security

### DI Pattern (nullable constructor)

Per project conventions, extracted classes need nullable DI constructors for testability:

```php
final class SessionManager {
    private static ?RepositoryFactory $repoFactory = null;
    
    public function __construct(?RepositoryFactory $repoFactory = null) {
        // Instance constructor for test injection
    }
    
    // Static methods use RepositoryFactory::getInstance() by default
    // Tests can use setSessionTimeoutForTest() (already exists)
}
```

However, since the current pattern is entirely static, and the requirement says "DI nullable constructor", the cleanest approach is:

```php
final class SessionManager {
    private ?SettingsRepository $settingsRepo;
    
    public function __construct(?SettingsRepository $settingsRepo = null) {
        $this->settingsRepo = $settingsRepo;
    }
    
    // Instance methods for testability
    // Static facade methods delegate to a singleton or direct instance
}
```

**Pragmatic recommendation:** Keep the static pattern with test helpers (like existing `setSessionTimeoutForTest`). Add a constructor that accepts dependencies but the primary usage remains static through AuthMiddleware delegation. This minimizes change surface while satisfying the DI requirement.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Role hierarchy comparison | Custom if/else chains | Permissions::HIERARCHY lookup | Already exists, single source of truth |
| Session cookie management | Manual cookie params | SessionHelper::start() | Already centralized, secure defaults |
| Permission constants | Inline role arrays | Permissions::PERMISSIONS | Already exists, used by both can() and requireRole() |

## Common Pitfalls

### Pitfall 1: Breaking the Static Call Chain
**What goes wrong:** Moving a method to a new class but forgetting that AuthMiddleware tests call `AuthMiddleware::methodName()` -- tests fail.
**Why it happens:** Eager method removal without delegation stub.
**How to avoid:** ALWAYS keep a delegation stub on AuthMiddleware for every public method that existed before. The stub forwards to SessionManager or RbacEngine.
**Warning signs:** Any `AuthMiddleware::` call in tests or production code that resolves to a missing method.

### Pitfall 2: Circular Dependencies in authenticate()
**What goes wrong:** `authenticate()` calls `getSessionTimeout()` which reads from `$_SESSION['auth_user']['tenant_id']`, but `authenticate()` is what populates `$currentUser`. Extracting without preserving this ordering breaks the flow.
**How to avoid:** `SessionManager::getSessionTimeout()` must read tenant_id directly from `$_SESSION` (as it does today), not from `AuthMiddleware::getCurrentTenantId()`.
**Warning signs:** Infinite recursion or null tenant during authentication.

### Pitfall 3: State Split Across Classes
**What goes wrong:** `$currentMeetingId` and `$currentMeetingRoles` move to RbacEngine, but `$currentUser` stays in AuthMiddleware. Methods in RbacEngine need the current user.
**How to avoid:** RbacEngine methods accept `$user` as a parameter (injected by AuthMiddleware delegation stubs) rather than calling back to AuthMiddleware. Alternatively, RbacEngine can call `AuthMiddleware::getCurrentUser()` -- but this creates coupling.
**Recommended approach:** Pass user as parameter to RbacEngine methods. This makes RbacEngine testable in isolation.

### Pitfall 4: reset() Must Chain
**What goes wrong:** `AuthMiddleware::reset()` must also call `SessionManager::reset()` and `RbacEngine::reset()` to clear their static state. Missing this causes test pollution.
**How to avoid:** Update `AuthMiddleware::reset()` to chain: `SessionManager::reset(); RbacEngine::reset();`

### Pitfall 5: The 300 LOC Budget
**What goes wrong:** RbacEngine has ~338 LOC of method bodies plus constants -- may exceed 300 LOC with boilerplate.
**How to avoid:** Move label/info methods (getSystemRoleLabels, getMeetingRoleLabels, etc.) to either stay on AuthMiddleware as pure Permissions lookups, or create them as Permissions convenience methods. These are pure data accessors, not RBAC logic.

## Extraction Plan (Method Assignment)

### SessionManager (target: ~150 LOC)
- Constants: `DEFAULT_SESSION_TIMEOUT`, `SESSION_REVALIDATE_INTERVAL`
- State: `$sessionExpired`, `$cachedSessionTimeout`, `$cachedTimeoutTenantId`, `$testSessionTimeout`, `$testTimeoutTenantId`
- Methods:
  - `getSessionTimeout(?string $tenantId = null): int`
  - `setSessionTimeoutForTest(string $tenantId, ?int $seconds): void`
  - `checkExpiry(int $lastActivity, ?string $tenantId = null): bool` (extracted from authenticate)
  - `revalidateUser(string $userId): ?array` (extracted from authenticate)
  - `isSessionExpired(): bool` (getter)
  - `consumeSessionExpired(): bool` (get + clear flag)
  - `reset(): void`

### RbacEngine (target: ~250 LOC)
- Constants: `SYSTEM_ROLES`, `MEETING_ROLES`, `ROLE_ALIASES`
- State: `$currentMeetingId`, `$currentMeetingRoles`
- Methods:
  - `normalizeRole(string $role): string` (becomes public)
  - `isMeetingRole(string $role): bool`
  - `isSystemRole(string $role): bool`
  - `setMeetingContext(?string $meetingId): void`
  - `getMeetingRoles(?array $user, ?string $meetingId = null): array`
  - `getEffectiveRoles(?array $user, ?string $meetingId = null): array`
  - `checkRole(?array $user, array $roles): bool` (core of requireRole)
  - `can(?array $user, string $permission, ?string $meetingId = null): bool`
  - `canTransition(?array $user, string $from, string $to, ?string $meetingId = null): bool`
  - `availableTransitions(?array $user, string $currentStatus, ?string $meetingId = null): array`
  - `getAvailablePermissions(?array $user, ?string $meetingId = null): array`
  - `getRoleLevel(string $role): int`
  - `isRoleAtLeast(string $role, string $minimumRole): bool`
  - `reset(): void`

### AuthMiddleware (target: ~250 LOC)
- State: `$currentUser`, `$debug`, `$accessLog`
- Own methods: `init()`, `isEnabled()`, `authenticate()`, all current-user getters, API key methods, deny/logging, isOwner, reset
- Delegation stubs: `requireRole()`, `can()`, `requirePermission()`, `canAccessMeeting()`, `canTransition()`, `requireTransition()`, `availableTransitions()`, `getSessionTimeout()`, etc.
- Label getters (thin forwards to Permissions): `getSystemRoles()`, `getSystemRoleLabels()`, etc.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit ^10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php tests/Unit/AuthMiddlewareTimeoutTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REFAC-01 | AuthMiddleware <300 LOC | smoke | `wc -l app/Core/Security/AuthMiddleware.php` | N/A |
| REFAC-01 | Existing AuthMiddleware tests pass unchanged | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php --no-coverage` | Yes |
| REFAC-01 | Existing AuthMiddleware timeout tests pass unchanged | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTimeoutTest.php --no-coverage` | Yes |
| REFAC-02 | SessionManager is final class <300 LOC with DI | smoke | `grep 'final class' app/Core/Security/SessionManager.php && wc -l app/Core/Security/SessionManager.php` | No - Wave 0 |
| REFAC-02 | RbacEngine is final class <300 LOC with DI | smoke | `grep 'final class' app/Core/Security/RbacEngine.php && wc -l app/Core/Security/RbacEngine.php` | No - Wave 0 |
| REFAC-02 | SessionManager unit tests | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/SessionManagerTest.php --no-coverage` | No - Wave 0 |
| REFAC-02 | RbacEngine unit tests | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/RbacEngineTest.php --no-coverage` | No - Wave 0 |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php tests/Unit/AuthMiddlewareTimeoutTest.php --no-coverage`
- **Per wave merge:** `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Phase gate:** Full unit suite green + `wc -l` checks on all 3 files

### Wave 0 Gaps
- [ ] `app/Core/Security/SessionManager.php` -- new file to create
- [ ] `app/Core/Security/RbacEngine.php` -- new file to create
- [ ] `tests/Unit/SessionManagerTest.php` -- covers SessionManager extraction
- [ ] `tests/Unit/RbacEngineTest.php` -- covers RbacEngine extraction

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| God-class AuthMiddleware (871 LOC) | Extract to 3 focused classes | This phase | Maintainability, testability |
| All RBAC inline in middleware | Dedicated RbacEngine | This phase | Reusable permission checks |
| Session timeout buried in authenticate() | Dedicated SessionManager | This phase | Clearer session lifecycle |

## Open Questions

1. **Label getter methods -- where should they live?**
   - What we know: `getSystemRoleLabels()`, `getMeetingRoleLabels()`, etc. are pure lookups into `Permissions::LABELS`
   - What's unclear: Should they stay on AuthMiddleware (thin delegation to Permissions), move to RbacEngine, or become direct Permissions methods?
   - Recommendation: Keep delegation stubs on AuthMiddleware (callers don't change), actual implementation trivial (1-3 lines each), fits in any class without LOC pressure

2. **Static vs instance pattern for extracted classes**
   - What we know: Everything is currently static. Tests use Reflection to poke at static props.
   - What's unclear: How far to push toward instance-based DI
   - Recommendation: Keep static pattern with static reset/test helpers. Add a `public function __construct(?Repo $repo = null)` that stores to a static field -- satisfies "DI nullable constructor" requirement while maintaining zero-change for callers.

## Sources

### Primary (HIGH confidence)
- Direct source code analysis of `app/Core/Security/AuthMiddleware.php` (871 LOC)
- Direct source code analysis of `app/Core/Security/Permissions.php` (199 LOC)
- Direct source code analysis of `app/Core/Security/SessionHelper.php` (90 LOC)
- Direct source code analysis of `tests/Unit/AuthMiddlewareTest.php` (480 LOC, 20 tests)
- Direct source code analysis of `tests/Unit/AuthMiddlewareTimeoutTest.php` (103 LOC, 5 tests)
- Grep of 16 caller files across `app/`

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new libraries needed, pure refactoring
- Architecture: HIGH - clear method boundaries identified, LOC budgets verified
- Pitfalls: HIGH - circular dependency and state management risks identified from code analysis

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable -- internal refactoring, no external dependencies)
