---
phase: 60
slug: session-import-and-auth-edge-cases
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-31
---

# Phase 60 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php vendor/bin/phpunit --filter "MeetingWorkflow\|Meetings\|Import\|Auth"` |
| **Full suite command** | `php vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php vendor/bin/phpunit --filter "MeetingWorkflow\|Meetings\|Import\|Auth"`
- **After every plan wave:** Run `php vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 60-01-01 | 01 | 1 | SESS-01 | unit | `php vendor/bin/phpunit --filter MeetingWorkflowControllerTest` | ✅ | ⬜ pending |
| 60-01-02 | 01 | 1 | SESS-02 | unit | `php vendor/bin/phpunit --filter MeetingsControllerTest` | ✅ | ⬜ pending |
| 60-02-01 | 02 | 1 | IMP-01 | unit | `php vendor/bin/phpunit --filter ImportServiceTest` | ✅ | ⬜ pending |
| 60-02-02 | 02 | 1 | IMP-02 | unit | `php vendor/bin/phpunit --filter ImportControllerTest` | ✅ | ⬜ pending |
| 60-03-01 | 03 | 1 | AUTH-01 | unit | `php vendor/bin/phpunit --filter AuthMiddlewareTest` | ✅ | ⬜ pending |
| 60-03-02 | 03 | 1 | AUTH-02 | unit | `php vendor/bin/phpunit --filter AuthControllerTest` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Expired session redirect shows message in browser | AUTH-01 | Requires browser session + expiry | Log in, wait for session timeout, navigate — verify redirect to /login?expired=1 with visible message |
| CSV with Windows-1252 accented characters imports correctly | IMP-01 | Requires actual file upload through UI | Upload test CSV with accented names, verify they display correctly |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
