# Phase 62: SMTP & Template Engine - Context

**Gathered:** 2026-04-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire SMTP configuration into the admin settings UI (currently env-only) with a test email button, and make email templates editable with live preview. All backend services already exist — this phase connects them to the frontend.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — infrastructure phase. Key constraints:
- `MailerService` already handles SMTP via Symfony Mailer (app/Services/MailerService.php)
- `EmailTemplateService` already has template rendering with variables (app/Services/EmailTemplateService.php)
- `EmailTemplatesController` already has CRUD endpoints (app/Controller/EmailTemplatesController.php)
- `SettingsController` already has per-tenant key/value persistence (app/Controller/SettingsController.php)
- `.env.example` already defines MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_FROM, MAIL_FROM_NAME
- SMTP settings should be saveable from admin UI AND fall back to .env values when not set in DB
- Template editor page already exists (email-templates.htmx.html rebuilt in v4.4)
- Admin settings page already exists (settings/admin rebuilt in v4.3)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `MailerService::send()` — sends email via Symfony Mailer, returns `{ok, error}`
- `MailerService::isConfigured()` — checks if SMTP host+port are set
- `EmailTemplateService::render()` — renders template with variable substitution
- `EmailTemplateService::AVAILABLE_VARIABLES` — list of all template variables with descriptions
- `EmailTemplatesController` — full CRUD for templates (list, create, update, delete, preview)
- `SettingsController::settings()` — per-tenant key/value upsert/list via SettingsRepository
- `email-templates-editor.js` — frontend template editor with preview

### Integration Points
- `app/config.php` — loads SMTP config from env
- `app/Services/MailerService.php` — needs to also read from DB settings
- `public/assets/js/pages/settings.js` — admin settings page
- `public/settings.htmx.html` — settings page HTML
- `public/email-templates.htmx.html` — template editor page

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
