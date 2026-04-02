---
phase: 63
slug: email-sending-workflows
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-01
---

# Phase 63 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php vendor/bin/phpunit --filter "EmailQueue\|EmailController\|MeetingWorkflow"` |
| **Full suite command** | `php vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick filter
- **After every plan wave:** Run full suite
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 63-01-01 | 01 | 1 | EMAIL-02, EMAIL-03 | grep | grep for scheduleReminders, DEFAULT_RESULTS_TEMPLATE, results_url | N/A | ⬜ pending |
| 63-01-02 | 01 | 1 | EMAIL-02, EMAIL-03 | grep | grep for sendReminder route, transition close hook | N/A | ⬜ pending |
| 63-01-03 | 01 | 1 | EMAIL-01, EMAIL-02, EMAIL-03 | unit | `php vendor/bin/phpunit --filter "EmailQueueServiceTest\|EmailControllerTest\|MeetingWorkflowControllerTest"` | ✅ | ⬜ pending |
| 63-02-01 | 02 | 2 | EMAIL-01, EMAIL-02 | grep | grep for btnSendReminder, inv-status-badge | N/A | ⬜ pending |
| 63-02-02 | 02 | 2 | EMAIL-01, EMAIL-02 | manual | Visual checkpoint — buttons and badge in operator console | N/A | ⬜ pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Invitation emails arrive with vote link | EMAIL-01 | Requires real SMTP | Send invitations, check inbox for vote link |
| Reminder emails arrive with hub link | EMAIL-02 | Requires real SMTP | Send reminder, check inbox for hub link |
| Results emails sent automatically on close | EMAIL-03 | Requires live session close + SMTP | Close session, verify results email received |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 15s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved
