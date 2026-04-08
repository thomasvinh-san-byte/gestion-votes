---
phase: 04
slug: tests-et-decoupage-controllers
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-07
---

# Phase 04 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit ^10.5 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/{File}Test.php --no-coverage` |
| **Full suite command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `timeout 60 php vendor/bin/phpunit tests/Unit/{File}Test.php --no-coverage`
- **After every plan wave:** Run `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 04-01-01 | 01 | 1 | TEST-03 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/EventBroadcasterTest.php --no-coverage` | ✅ | ⬜ pending |
| 04-01-02 | 01 | 1 | TEST-04 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ImportServiceTest.php --no-coverage` | ✅ | ⬜ pending |
| 04-02-01 | 02 | 2 | REFAC | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingReportsControllerTest.php --no-coverage` | ✅ | ⬜ pending |
| 04-02-02 | 02 | 2 | REFAC | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MotionsControllerTest.php --no-coverage` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Redis connection failure SSE behavior | TEST-03 | Requires real Redis stop/start | Stop Redis, verify SSE client reconnects gracefully |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
