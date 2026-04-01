---
gsd_state_version: 1.0
milestone: v6.0
milestone_name: Production & Email
status: planning
stopped_at: Completed 63-email-sending-workflows-02-PLAN.md
last_updated: "2026-04-01T06:16:05.014Z"
last_activity: 2026-03-31 — Roadmap created for v6.0
progress:
  total_phases: 3
  completed_phases: 2
  total_plans: 4
  completed_plans: 4
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-31)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v6.0 Production & Email — SMTP emails (invitations, reminders, results), customizable templates, in-app notifications (bell + SSE toasts)

## Current Position

Phase: 62 of 64 (SMTP & Template Engine) — ready to plan
Plan: --
Status: Ready to plan Phase 62
Last activity: 2026-03-31 — Roadmap created for v6.0

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

- [v6.0 roadmap]: 3 phases derived from 8 requirements — SMTP/templates foundation, email workflows, in-app notifications
- [v6.0 roadmap]: Phase 62 groups EMAIL-04 + EMAIL-05 (template editing + SMTP config) as foundation before any sends
- [v6.0 roadmap]: Phase 63 groups EMAIL-01 + EMAIL-02 + EMAIL-03 (invitation, reminder, results) as the three email workflows
- [v6.0 roadmap]: Phase 64 groups NOTIF-01 + NOTIF-02 + NOTIF-03 (bell, list, toast) as the notification system
- [Phase 62-smtp-template-engine]: Template editor uses body_html field (not body) for correct API alignment with EmailTemplatesController
- [Phase 62-smtp-template-engine]: Server-side preview via debounced POST to email_templates_preview replaces stale client-side substitution
- [Phase 62-smtp-template-engine]: MailerService::buildMailerConfig static helper merges DB SMTP settings over env config with password sentinel skip
- [Phase 62-smtp-template-engine]: test_smtp action dispatched in EmailController::preview() before body_html check; sends real test email to from_email
- [Phase 63]: DEFAULT_REMINDER_TEMPLATE CTA updated to use {{hub_url}} per user locked decision
- [Phase 63]: scheduleResults() added as dedicated method; results hook uses fire-and-forget try/catch pattern identical to SSE broadcast
- [Phase 63-email-sending-workflows]: results_emails added to transition api_ok() response so JS can show count in close toast without second API call
- [Phase 63-email-sending-workflows]: Reminder button uses btn-secondary to visually distinguish from primary invitation button in invitationsCard
- [Phase 63-email-sending-workflows]: results_emails captured from scheduleResults() return value and added to transition api_ok() payload — JS reads count directly without second API call

### Existing Infrastructure

- Symfony Mailer already installed (composer.json: symfony/mailer ^8.0)
- EmailTemplateService exists with DEFAULT_INVITATION_TEMPLATE and DEFAULT_REMINDER_TEMPLATE
- EmailQueueService exists for queuing emails
- SSE infrastructure exists (EventBroadcaster, SseListener, Redis fan-out)
- Shell has notification bell UI (shell.js notifPanel code)
- ag-toast Web Component already exists

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% (3 exit()-based controllers are structural ceiling)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

None.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260331-7s9 | Remove voting weight/ponderation from UI and sample CSV | 2026-03-31 | 7cb5378 | [260331-7s9-remove-voting-weight-ponderation-from-ui](./quick/260331-7s9-remove-voting-weight-ponderation-from-ui/) |
| 260331-854 | Wizard field layout and time input modernization | 2026-03-31 | e655a46 | [260331-854-wizard-field-layout-and-time-input-moder](./quick/260331-854-wizard-field-layout-and-time-input-moder/) |
| 260331-8wf | Modernize project README.md | 2026-03-31 | 868c43a | [260331-8wf-modernize-project-readme-md](./quick/260331-8wf-modernize-project-readme-md/) |
| 260331-901 | Modernize all documentation files | 2026-03-31 | c4e68b1 | [260331-901-modernize-all-docs-rich-french-no-em-das](./quick/260331-901-modernize-all-docs-rich-french-no-em-das/) |
| 260331-ez9 | Fix admin login — double rate limit on auth_login | 2026-03-31 | c3b1add2 | [260331-ez9-fix-admin-login-failure](./quick/260331-ez9-fix-admin-login-failure/) |
| 260331-ffw | Full project audit — gitignore, env, CSS tokens, git hygiene | 2026-03-31 | 4625f6ca | [260331-ffw-full-project-audit-bugs-cleanup-config-i](./quick/260331-ffw-full-project-audit-bugs-cleanup-config-i/) |
| 260331-fya | Second pass audit — remaining CSS tokens, route cleanup | 2026-03-31 | 00fe92f5 | [260331-fya-second-pass-audit-remaining-issues](./quick/260331-fya-second-pass-audit-remaining-issues/) |
| 260331-g8a | Critical path audit — API functional, operator null guards | 2026-03-31 | 3d504fe2 | [260331-g8a-critical-path-audit-functional-visual-on](./quick/260331-g8a-critical-path-audit-functional-visual-on/) |
| 260331-gj6 | Low-priority fixes — null guards, login CSS classes, wizard responsive | 2026-03-31 | 11f18eb4 | [260331-gj6-fix-remaining-low-priority-items-null-gu](./quick/260331-gj6-fix-remaining-low-priority-items-null-gu/) |

## Session Continuity

Last session: 2026-04-01T06:15:58.911Z
Stopped at: Completed 63-email-sending-workflows-02-PLAN.md
Resume file: None
