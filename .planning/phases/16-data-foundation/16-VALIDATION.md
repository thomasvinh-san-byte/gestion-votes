---
phase: 16
slug: data-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-16
---

# Phase 16 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` |
| **Full suite command** | `./vendor/bin/phpunit --no-coverage` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage`
- **After every plan wave:** Run `./vendor/bin/phpunit --no-coverage`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 16-01-01 | 01 | 1 | WIZ-01 | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (extend) | ⬜ pending |
| 16-01-02 | 01 | 1 | WIZ-02 | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (extend) | ⬜ pending |
| 16-01-03 | 01 | 1 | WIZ-03 | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (extend) | ⬜ pending |
| 16-01-04 | 01 | 1 | WIZ-01/02/03 | unit | `./vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ (extend) | ⬜ pending |
| 16-02-01 | 02 | 2 | HUB-01 | manual | n/a — frontend JS | ❌ manual | ⬜ pending |
| 16-02-02 | 02 | 2 | HUB-02 | manual | n/a — frontend JS | ❌ manual | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Extend `tests/Unit/MeetingsControllerTest.php` — add test methods for atomic creation with members[], resolutions[], validation rollback, upsert behavior
- [ ] No new test files needed — existing MeetingsControllerTest.php is the correct location

*Existing infrastructure covers the framework. Only new test methods needed.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Hub loads real data from wizard_status API | HUB-01 | Frontend JS, no automated test harness | Open hub.htmx.html?id=X after wizard creation; verify real data displayed |
| Hub shows error toast when API unreachable | HUB-02 | Frontend JS, needs browser | Block /api/v1/wizard_status; verify red toast + retry button appears |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
