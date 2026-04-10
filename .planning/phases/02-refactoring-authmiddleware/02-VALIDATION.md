---
phase: 02
slug: refactoring-authmiddleware
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-10
---

# Phase 02 — Validation Strategy

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit ^10.5 |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php --no-coverage` |
| **Full suite command** | `timeout 120 php vendor/bin/phpunit --no-coverage` |
| **Estimated runtime** | ~15 seconds |

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | Status |
|---------|------|------|-------------|-----------|-------------------|--------|
| 02-01-01 | 01 | 1 | REFAC-01 | unit | `wc -l app/Core/Security/AuthMiddleware.php` | ⬜ pending |
| 02-01-02 | 01 | 1 | REFAC-02 | unit | `wc -l app/Core/Security/SessionManager.php && wc -l app/Core/Security/RbacEngine.php` | ⬜ pending |
| 02-02-01 | 02 | 2 | REFAC-02 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/SessionManagerTest.php --no-coverage` | ⬜ pending |
| 02-02-02 | 02 | 2 | REFAC-02 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/RbacEngineTest.php --no-coverage` | ⬜ pending |

## Wave 0 Requirements

- [ ] `tests/Unit/SessionManagerTest.php` — created in Plan 02-02 Task 1
- [ ] `tests/Unit/RbacEngineTest.php` — created in Plan 02-02 Task 2

## Validation Sign-Off

**Approval:** pending
