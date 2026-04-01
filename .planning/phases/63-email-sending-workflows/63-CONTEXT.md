# Phase 63: Email Sending Workflows - Context

**Gathered:** 2026-04-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire email sending buttons into the operator console UI, add auto-results email on session close, and display send status. Backend services (EmailQueueService, EmailController::schedule/sendBulk, MailerService) already exist — this phase connects them to the frontend and adds the results workflow.

</domain>

<decisions>
## Implementation Decisions

### Email Trigger UI
- "Envoyer les invitations" button in the operator console hub tab — near session details, per-session context
- Results emails triggered automatically when operator closes the session (hook into MeetingWorkflowController::transition() on close)
- Send status shown as badge/counter on the invitation button ("12/15 envoyés") + toast notification on completion

### Email Content & Links
- Invitation link: `{app_url}/vote.htmx.html?token={token}` — already implemented in sendBulk(), uses existing vote token system
- Reminder link: `{app_url}/hub.htmx.html?meeting_id={meeting_id}` — brings member to session hub
- Results link: `{app_url}/postsession.htmx.html?meeting_id={meeting_id}` — shows final results
- Separate templates for invitation (DEFAULT_INVITATION_TEMPLATE) and reminder (DEFAULT_REMINDER_TEMPLATE) — different content, different purpose
- Results email needs a new DEFAULT_RESULTS_TEMPLATE

### Claude's Discretion
- Internal implementation details for the auto-results hook
- Queue processing strategy (immediate vs background)
- Error handling and retry logic

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `EmailController::schedule()` — schedules invitations via EmailQueueService
- `EmailController::sendBulk()` — sends invitations immediately with vote token links
- `EmailQueueService::scheduleInvitations()` — queues invitation emails
- `EmailQueueService::processQueue()` — processes email queue batch
- `MailerService::send()` — sends via Symfony Mailer SMTP
- `MailerService::buildMailerConfig()` — merges DB+env SMTP config (Phase 62)
- `EmailTemplateService::render()` — renders template with variables
- `DEFAULT_INVITATION_TEMPLATE` and `DEFAULT_REMINDER_TEMPLATE` already exist
- `InvitationRepository::upsertBulk()` — tracks invitation status per member

### Integration Points
- `MeetingWorkflowController::transition()` — hook results email on close transition
- `operator-tabs.js` — operator console JS (add invitation/reminder buttons)
- `public/partials/operator-live-tabs.html` — operator console HTML
- `app/routes.php` — email_schedule, email_send routes already defined

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches within the decided strategy.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
