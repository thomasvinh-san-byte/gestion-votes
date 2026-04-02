---
phase: 63-email-sending-workflows
plan: "01"
subsystem: email
tags: [email, queue, templates, backend, php]
dependency_graph:
  requires:
    - 62-smtp-template-engine
  provides:
    - scheduleReminders() in EmailQueueService
    - scheduleResults() in EmailQueueService
    - sendReminder() in EmailController
    - invitations_send_reminder route
    - results email hook in MeetingWorkflowController::transition()
    - DEFAULT_RESULTS_TEMPLATE constant
    - "{{results_url}} and {{hub_url}} template variables"
  affects:
    - app/Services/EmailTemplateService.php
    - app/Services/EmailQueueService.php
    - app/Controller/EmailController.php
    - app/Controller/MeetingWorkflowController.php
    - app/routes.php
tech_stack:
  added: []
  patterns:
    - fire-and-forget try/catch hook pattern (same as SSE broadcast)
    - reminder queue sends to all members (no onlyUnsent check)
    - results queue guarded by isConfigured() silent return
key_files:
  created:
    - .planning/phases/63-email-sending-workflows/63-01-SUMMARY.md
  modified:
    - app/Services/EmailTemplateService.php
    - app/Services/EmailQueueService.php
    - app/Controller/EmailController.php
    - app/Controller/MeetingWorkflowController.php
    - app/routes.php
    - tests/Unit/EmailQueueServiceTest.php
    - tests/Unit/EmailControllerTest.php
    - tests/Unit/MeetingWorkflowControllerTest.php
decisions:
  - DEFAULT_REMINDER_TEMPLATE CTA link updated from {{vote_url}} to {{hub_url}} per user locked decision (hub.htmx.html not vote.htmx.html)
  - scheduleResults() added as dedicated method rather than reusing scheduleInvitations() to avoid type parameter coupling
  - Results email hook uses same fire-and-forget try/catch pattern as SSE broadcast in transition()
  - scheduleReminders() passes empty string token to getVariables() since reminders use hub_url not vote_url
metrics:
  duration_minutes: 17
  completed_date: "2026-04-01"
  tasks_completed: 3
  files_modified: 8
  commits: 3
---

# Phase 63 Plan 01: Email Sending Workflows Backend Summary

**One-liner:** Reminder and results email backend infrastructure — scheduleReminders()/scheduleResults() queue methods, sendReminder() controller, non-blocking results hook on session close, and DEFAULT_RESULTS_TEMPLATE with {{results_url}} CTA.

## What Was Built

Three email workflow components added to the PHP backend:

**1. EmailTemplateService — 3 additions:**
- `{{results_url}}` added to AVAILABLE_VARIABLES → resolves to `postsession.htmx.html?meeting_id=...`
- `{{hub_url}}` added to AVAILABLE_VARIABLES → resolves to `hub.htmx.html?meeting_id=...`
- `DEFAULT_RESULTS_TEMPLATE` constant added — green CTA button linking to `{{results_url}}`, member greeting, meeting title/date, tenant footer
- `DEFAULT_REMINDER_TEMPLATE` CTA updated from `{{vote_url}}` to `{{hub_url}}` (per locked user decision)
- Both variables added to `getVariables()` return array

**2. EmailQueueService — 2 new methods:**
- `scheduleReminders(string $tenantId, string $meetingId, ?string $templateId)` — looks up `type='reminder'` default template, sends to all active members with email (no onlyUnsent check), queues without invitation_id
- `scheduleResults(string $tenantId, string $meetingId, ?string $templateId)` — guarded by `isConfigured()` silent return, looks up `type='results'` default template, queues results emails without invitation_id or token

**3. EmailController — 1 new method:**
- `sendReminder(): void` — validates meeting_id UUID, calls `scheduleReminders()`, logs `email.reminder` audit event, returns `['scheduled', 'errors']`

**4. MeetingWorkflowController — results email hook:**
- After SSE broadcast block in `transition()`: if `$toStatus === 'closed'`, fires `scheduleResults()` inside try/catch
- Non-blocking: failures only logged via `error_log`, transition always succeeds
- EmailQueueService imported at top of file

**5. routes.php:**
- `invitations_send_reminder` route registered mapping to `EmailController::sendReminder` with operator role

## Tests Added

**EmailQueueServiceTest.php — 7 new tests:**
- `testScheduleRemindersLooksUpReminderTemplateType` — verifies findDefault called with 'reminder'
- `testScheduleRemindersSkipsMembersWithoutEmail` — 2 skipped, 0 scheduled
- `testScheduleRemindersReturnsExpectedStructure` — structure check
- `testScheduleRemindersQueuesForAllMembersWithEmail` — verifies email check logic
- `testScheduleResultsReturnsEarlyWhenSmtpNotConfigured` — memberRepo never called
- `testScheduleResultsLooksUpResultsTemplateType` — verifies findDefault called with 'results'
- `testScheduleResultsQueuesForAllMembersWhenSmtpConfigured` — passes isConfigured() guard

**EmailControllerTest.php — 6 new tests:**
- `testSendReminderRequiresMeetingId` — 400 with missing_meeting_id
- `testSendReminderRejectsInvalidMeetingId` — 400 for non-UUID
- `testSendReminderMethodExistsInController` — reflection check
- `testSendReminderAuditsEmailReminderEvent` — source verify audit_log
- `testSendReminderCallsScheduleReminders` — source verify call
- `testSendReminderGuardsMeetingNotValidated` — source verify guard

**MeetingWorkflowControllerTest.php — 3 new tests:**
- `testTransitionToClosedSchedulesResultsEmails` — hook exists, closed check present, non-blocking log
- `testTransitionResultsEmailHookIsNonBlocking` — catch does not re-throw
- `testTransitionResultsEmailHookGuardedByClosedStatus` — scheduleResults after toStatus check

**Full test run: 219 tests, 511 assertions, 1 skipped (pre-existing) — all green.**

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] DEFAULT_REMINDER_TEMPLATE used {{vote_url}} instead of {{hub_url}}**
- **Found during:** Task 1 — code review of existing template before adding new variables
- **Issue:** The template CTA linked to `{{vote_url}}` (vote token link) but per the locked user decision, reminder link should be `hub.htmx.html?meeting_id=...` via `{{hub_url}}`
- **Fix:** Updated reminder template `<a href="{{hub_url}}">` and changed CTA text to "Acceder au hub de la seance"
- **Files modified:** app/Services/EmailTemplateService.php
- **Commit:** b60fa7e9

## Commits

| Hash | Description |
|------|-------------|
| b60fa7e9 | feat(63-01): add DEFAULT_RESULTS_TEMPLATE, {{results_url}}/{{hub_url}} variables, scheduleReminders() and scheduleResults() methods |
| 208a0fc0 | feat(63-01): add sendReminder() controller, invitations_send_reminder route, and results email hook in transition() |
| bc55f917 | test(63-01): add unit tests for scheduleReminders, scheduleResults, sendReminder, and transition results hook |

## Self-Check: PASSED
