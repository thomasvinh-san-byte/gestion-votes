---
phase: 1
slug: infrastructure-redis
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-07
---

# Phase 1 — Validation Strategy

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
| TBD | TBD | TBD | REDIS-01 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/SSE/EventBroadcasterTest.php --no-coverage` | ✅ | ⬜ pending |
| TBD | TBD | TBD | REDIS-02 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/Security/RateLimiterTest.php --no-coverage` | ✅ | ⬜ pending |
| TBD | TBD | TBD | REDIS-03 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/SSE/EventBroadcasterTest.php --no-coverage` | ✅ | ⬜ pending |
| TBD | TBD | TBD | REDIS-04 | integration | `timeout 60 php vendor/bin/phpunit tests/Unit/Core/ApplicationTest.php --no-coverage` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Existing test infrastructure covers REDIS-01, REDIS-02, REDIS-03
- [ ] `tests/Unit/Core/ApplicationTest.php` — stub for REDIS-04 health check

*Existing PHPUnit infrastructure covers most phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| No /tmp/sse* files created after SSE events | REDIS-01 | Filesystem side-effect | Run SSE test, check `ls /tmp/agvote-sse*` returns empty |
| No /tmp/agvote-ratelimit* files created | REDIS-02 | Filesystem side-effect | Run rate-limit test, check `ls /tmp/agvote-ratelimit*` returns empty |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
