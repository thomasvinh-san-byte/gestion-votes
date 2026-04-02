---
phase: 59
slug: vote-and-quorum-edge-cases
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-31
---

# Phase 59 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php vendor/bin/phpunit --filter "Ballot\|Quorum\|Attendance"` |
| **Full suite command** | `php vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php vendor/bin/phpunit --filter "Ballot\|Quorum\|Attendance"`
- **After every plan wave:** Run `php vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 59-01-01 | 01 | 1 | VOTE-01 | unit | `php vendor/bin/phpunit --filter BallotsControllerTest` | ✅ | ⬜ pending |
| 59-01-02 | 01 | 1 | VOTE-02 | unit | `php vendor/bin/phpunit --filter BallotsControllerTest` | ✅ | ⬜ pending |
| 59-01-03 | 01 | 1 | VOTE-03 | unit | `php vendor/bin/phpunit --filter BallotsControllerTest` | ✅ | ⬜ pending |
| 59-02-01 | 02 | 1 | QUOR-01 | unit | `php vendor/bin/phpunit --filter QuorumEngineTest` | ✅ | ⬜ pending |
| 59-02-02 | 02 | 1 | QUOR-02 | unit | `php vendor/bin/phpunit --filter AttendancesController` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/AttendancesControllerTest.php` — stubs for QUOR-02 SSE broadcast verification (if not exists)

*If none: "Existing infrastructure covers all phase requirements."*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| SSE quorum update in operator console | QUOR-02 | Requires browser + SSE connection | Open operator console, add attendee during open vote, verify quorum display updates without reload |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
