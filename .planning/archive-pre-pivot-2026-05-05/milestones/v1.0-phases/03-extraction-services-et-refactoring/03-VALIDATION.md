---
phase: 3
slug: extraction-services-et-refactoring
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-07
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit ^10.5 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Full suite command** | `timeout 120 php vendor/bin/phpunit tests/ --no-coverage` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **After every plan wave:** Run `timeout 120 php vendor/bin/phpunit tests/ --no-coverage`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| T1 | 03-01 | 1 | TEST-02 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/AuthMiddlewareTest.php --no-coverage` | ✅ (extended) | ⬜ pending |
| T2 | 03-01 | 1 | TEST-01 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/RgpdExportControllerTest.php --no-coverage` | ❌ W0 | ⬜ pending |
| T1 | 03-02 | 2 | REFAC-01 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ImportControllerTest.php --no-coverage` | ✅ | ⬜ pending |
| T2 | 03-02 | 2 | REFAC-01 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ImportControllerTest.php --no-coverage` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/RgpdExportControllerTest.php` — created in 03-01 Task 2

*AuthMiddlewareTest.php exists and is extended in 03-01 Task 1.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| None | — | — | — |

*All phase behaviors have automated verification.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
