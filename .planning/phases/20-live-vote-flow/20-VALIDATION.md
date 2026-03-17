---
phase: 20
slug: live-vote-flow
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-17
---

# Phase 20 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 (unit) + Playwright (e2e) |
| **Config file** | `phpunit.xml` (unit), `tests/e2e/` (e2e) |
| **Quick run command** | `./vendor/bin/phpunit tests/Unit/BallotsServiceTest.php tests/Unit/MeetingWorkflowServiceTest.php -x` |
| **Full suite command** | `./vendor/bin/phpunit --testsuite Unit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/phpunit tests/Unit/BallotsServiceTest.php tests/Unit/MeetingWorkflowServiceTest.php -x`
- **After every plan wave:** Run `./vendor/bin/phpunit --testsuite Unit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 20-01-01 | 01 | 1 | VOT-01 | unit | `./vendor/bin/phpunit tests/Unit/MotionsControllerTest.php` | ❌ W0 | ⬜ pending |
| 20-01-02 | 01 | 1 | VOT-02 | unit | `./vendor/bin/phpunit tests/Unit/BallotsServiceTest.php` | ✅ | ⬜ pending |
| 20-01-03 | 01 | 1 | VOT-03 | unit | `./vendor/bin/phpunit tests/Unit/OfficialResultsServiceTest.php` | ✅ | ⬜ pending |
| 20-01-04 | 01 | 1 | VOT-04 | unit | `./vendor/bin/phpunit tests/Unit/MeetingWorkflowControllerTest.php` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/MotionsControllerTest.php` — stubs for VOT-01 (motion open broadcasts SSE event)
- [ ] `tests/Unit/OperatorControllerTest.php` — extend for VOT-04 (implicit frozen→live broadcasts meeting.status_changed)

*All other test files already exist.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Voter view auto-switches to ballot on motion.opened SSE | VOT-01 | SSE + DOM interaction requires browser | Open voter view, trigger motion open from operator, verify ballot card appears without reload |
| Projection screen shows live progress bar | VOT-01 | Full-screen DOM + SSE interaction | Open public.htmx.html, trigger vote cycle, verify progress bar and results display |
| Confirmation modal before ballot submit | VOT-02 | Modal interaction requires browser | Click ballot option, verify confirmation modal appears, confirm, verify success state |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
