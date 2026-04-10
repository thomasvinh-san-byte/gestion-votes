---
phase: 02-refactoring-authmiddleware
plan: 01
subsystem: auth
tags: [rbac, session-management, refactoring, static-facade]

requires:
  - phase: 01-nettoyage-codebase
    provides: clean codebase baseline with superglobals migrated
provides:
  - SessionManager final class for session timeout/expiry/revalidation
  - RbacEngine final class for role checks/permissions/transitions
  - AuthMiddleware thin orchestrator under 300 LOC
affects: [02-02 (unit tests for extracted classes), 07-validation-gate]

tech-stack:
  added: []
  patterns: [static-facade-delegation, extracted-class-with-user-parameter-injection]

key-files:
  created:
    - app/Core/Security/SessionManager.php
    - app/Core/Security/RbacEngine.php
  modified:
    - app/Core/Security/AuthMiddleware.php

key-decisions:
  - "Keep all 10 static properties on AuthMiddleware as mirrors for Reflection-based test compatibility"
  - "RbacEngine methods receive $user as parameter (not calling back to AuthMiddleware) for isolation"
  - "SessionManager reads tenant_id from $_SESSION directly to avoid circular dependency"
  - "Label getters delegated through RbacEngine (not kept on AuthMiddleware) to reduce AuthMiddleware LOC"

patterns-established:
  - "Static facade delegation: AuthMiddleware forwards to SessionManager/RbacEngine with zero caller changes"
  - "User-as-parameter: extracted RBAC methods receive user array, not calling getCurrentUser()"

requirements-completed: [REFAC-01, REFAC-02]

duration: 8min
completed: 2026-04-10
---

# Phase 2 Plan 1: AuthMiddleware Extraction Summary

**AuthMiddleware (871 LOC) decomposed into thin orchestrator (277 LOC) + SessionManager (227 LOC) + RbacEngine (259 LOC), all 33 tests green**

## Performance

- **Duration:** 8 min
- **Started:** 2026-04-10T11:23:00Z
- **Completed:** 2026-04-10T11:31:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Extracted SessionManager with session timeout, expiry checking, and DB revalidation (227 LOC)
- Extracted RbacEngine with role checks, permissions, transitions, meeting roles (259 LOC)
- Slimmed AuthMiddleware from 871 to 277 LOC as thin orchestrator with 33 delegation stubs
- All 33 existing tests pass without modification (zero caller changes needed)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create SessionManager and RbacEngine** - `e741409a` (feat)
2. **Task 2: Refactor AuthMiddleware to thin orchestrator** - `8f49b583` (refactor)

## Files Created/Modified
- `app/Core/Security/SessionManager.php` - Session timeout, expiry, DB revalidation (227 LOC, final class)
- `app/Core/Security/RbacEngine.php` - Role checks, permissions, transitions, labels (259 LOC, final class)
- `app/Core/Security/AuthMiddleware.php` - Thin orchestrator with delegation stubs (277 LOC, down from 871)

## Decisions Made
- Kept all 10 static properties on AuthMiddleware as mirrors for Reflection-based test backward compatibility
- RbacEngine methods receive `$user` as parameter rather than calling back to AuthMiddleware, enabling isolated testing
- SessionManager reads tenant_id from `$_SESSION` directly (not `AuthMiddleware::getCurrentTenantId()`) to avoid circular dependency during authentication
- Label getters (getSystemRoleLabels etc.) live in RbacEngine with one-liner stubs on AuthMiddleware

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- SessionManager and RbacEngine are ready for dedicated unit tests (02-02-PLAN.md)
- All 16 caller files continue to work via AuthMiddleware delegation stubs

---
*Phase: 02-refactoring-authmiddleware*
*Completed: 2026-04-10*
