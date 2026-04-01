# Phase 62: SMTP & Template Engine - Research

**Researched:** 2026-04-01
**Domain:** PHP backend wiring (MailerService + SettingsRepository + EmailTemplateService) + frontend settings/template UI
**Confidence:** HIGH — all findings based on direct codebase inspection, no external sources needed

## Summary

This phase is a wiring phase, not a build phase. Every backend service, repository, controller, and frontend UI element already exists. The gap between the current state and the requirements is narrow but precise: (1) `MailerService` reads SMTP config only from the PHP config array (populated from `.env`), but must also fall back to DB-stored settings; (2) `SettingsController` has no `test_smtp` action — the frontend already calls `/api/v1/email_templates_preview.php` with `action: test_smtp` but `EmailController::preview()` does not handle that action; (3) the template editor in `settings.js` sends `body` as field name but `EmailTemplatesController::update()` expects `body_html`; (4) the preview in settings.js uses a hardcoded sample variable map with old variable names (`{{nom}}`, `{{date}}`) instead of the canonical `{{member_name}}`, `{{meeting_date}}` variables from `EmailTemplateService::AVAILABLE_VARIABLES`.

The plan should focus on three surgical work streams: (A) teach `MailerService` to merge DB settings over env defaults; (B) add a `test_smtp` action to `EmailController` (or `SettingsController`); (C) fix the disconnect between the settings-page template mini-editor and the real `EmailTemplatesController` API contract.

**Primary recommendation:** Wire DB settings override into `MailerService::__construct()` via a new static factory or a second config merge step; add `test_smtp` to `EmailController`; fix the settings.js field name mismatch (`body` vs `body_html`).

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None — all implementation choices are at Claude's discretion.

### Claude's Discretion
All implementation choices. Key constraints from CONTEXT.md:
- `MailerService` already handles SMTP via Symfony Mailer (app/Services/MailerService.php)
- `EmailTemplateService` already has template rendering with variables (app/Services/EmailTemplateService.php)
- `EmailTemplatesController` already has CRUD endpoints (app/Controller/EmailTemplatesController.php)
- `SettingsController` already has per-tenant key/value persistence (app/Controller/SettingsController.php)
- `.env.example` already defines MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_FROM, MAIL_FROM_NAME
- SMTP settings should be saveable from admin UI AND fall back to .env values when not set in DB
- Template editor page already exists (email-templates.htmx.html rebuilt in v4.4)
- Admin settings page already exists (settings/admin rebuilt in v4.3)

### Deferred Ideas (OUT OF SCOPE)
None.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| EMAIL-04 | Email templates are customizable from admin UI (subject, HTML body with variables) | `email-templates.htmx.html` + `email-templates-editor.js` + `EmailTemplatesController` CRUD endpoints all exist; gaps are in the settings-page mini-editor field name mismatch and preview variable map |
| EMAIL-05 | Sending uses generic SMTP (Symfony Mailer) — compatible with Mailgun, SendGrid, OVH, Gmail | `MailerService` with `symfony/mailer ^8.0` already does this; gap is reading credentials from DB settings, not only from `.env` |
</phase_requirements>

---

## Standard Stack

### Core (already installed)
| Library | Version | Purpose | Status |
|---------|---------|---------|--------|
| symfony/mailer | ^8.0 | SMTP transport, DSN building, STARTTLS/SSL | Already in composer.json |
| symfony/mime | ^8.0 | Email object, Address, header sanitization | Already in composer.json |
| PHPUnit | 10.x | Unit testing framework | Already in composer.json |

### No new dependencies required
This phase adds zero new Composer or npm packages. All transport, rendering, and persistence layers are in place.

**Installation:** None required.

---

## Architecture Patterns

### Existing Project Structure (relevant to this phase)
```
app/
├── Services/
│   ├── MailerService.php          # SMTP via Symfony Mailer — reads $config['smtp']
│   └── EmailTemplateService.php   # Template rendering + variable substitution
├── Controller/
│   ├── SettingsController.php     # Per-tenant key/value CRUD (list/update only)
│   ├── EmailTemplatesController.php  # Template CRUD (list/create/update/delete)
│   └── EmailController.php        # preview() + schedule() + sendBulk()
├── Repository/
│   ├── SettingsRepository.php     # tenant_settings table (upsert/list/get)
│   └── EmailTemplateRepository.php # email_templates table CRUD
└── config.php                     # Loads SMTP from env → $config['smtp']

public/
├── settings.htmx.html             # Settings page — has SMTP card + template mini-editor
├── email-templates.htmx.html      # Dedicated template editor page
└── assets/js/pages/
    ├── settings.js                 # SMTP save, template mini-editor, SMTP test
    └── email-templates-editor.js  # Full template editor (dedicated page)

public/api/v1/
├── admin_settings.php             # → SettingsController::settings()
├── email_templates.php            # → EmailTemplatesController (GET/POST/PUT/DELETE)
└── email_templates_preview.php    # → EmailController::preview()
```

### Pattern 1: DB Settings Override Env Config
**What:** `MailerService` is constructed with `$config['smtp']` from `app/config.php` (env only). To support DB-stored SMTP settings, the SMTP array must be merged before `MailerService` is instantiated.

**Where to implement:** A new static helper `MailerService::fromConfig(array $envConfig, ?SettingsRepository $repo, string $tenantId)` or a merge step in the API entry points that instantiate `MailerService`. The simplest approach consistent with the existing codebase is a static factory method or a standalone helper function in the API layer.

**DB key convention:** The settings.js SMTP form already uses element IDs as keys (`settSmtpHost`, `settSmtpPort`, `settSmtpUser`, `settSmtpPass`, `settSenderName`, `settSenderEmail`). These keys are what get stored in `tenant_settings`. The merge must map them to the `$config['smtp']` keys:

| DB key (from settings.js) | smtp config key |
|--------------------------|-----------------|
| `settSmtpHost` | `host` |
| `settSmtpPort` | `port` |
| `settSmtpUser` | `user` |
| `settSmtpPass` | `pass` |
| `settSenderName` | `from_name` |
| `settSenderEmail` | `from_email` |

**Fallback logic:** If DB value is empty/null, use the env value. Non-empty DB value wins.

**When to use:** Any place that instantiates `MailerService` for real sends — `EmailController::sendBulk()`, `EmailQueueService`, and the new `test_smtp` action.

### Pattern 2: SMTP Test Action
**What:** The settings page (`settings.js:initSmtpTest()`) calls `POST /api/v1/email_templates_preview.php` with `{ action: 'test_smtp', dry_run: true }`. The `EmailController::preview()` currently ignores the `action` field entirely and requires `body_html` — so a `test_smtp` call currently returns a 400 `missing_body_html` error.

**Fix options:**
- Option A: Add `test_smtp` handling inside `EmailController::preview()` by checking `$input['action']` first.
- Option B: Add a separate `testSmtp()` method to `EmailController` and route it in the PHP entry point.

Option B is cleaner since preview and test_smtp are conceptually different. Route with `match (api_method())` or check action inside preview.

**What test_smtp should do:**
1. Load SMTP config (env + DB override).
2. Instantiate `MailerService` with merged config.
3. Call `$mailer->isConfigured()` — if false, return 400.
4. Attempt a real connection using a lightweight probe (e.g., send to `$smtp['from_email']` or a configurable test address). This is the only reliable way to test SMTP connectivity.
5. Return `{ ok: true }` on success, `{ ok: false, error: '...' }` on failure.

**Note on dry_run:** The frontend sends `dry_run: true` but `test_smtp` should always attempt the real connection regardless — the point is to verify credentials. The `dry_run` flag is vestigial from the `sendBulk` API; ignore it in the SMTP test handler.

### Pattern 3: Template Editor Field Name Fix
**What:** `settings.js:initEmailTemplates()` saves templates using `body` as the field name but `EmailTemplatesController::update()` reads `$input['body_html']`. The frontend also reads `tpl.body` when loading but the API returns `body_html`.

**All mismatches in settings.js:**

| settings.js sends/reads | API expects/returns |
|------------------------|---------------------|
| `body` | `body_html` |
| GET: `tpl.body` | GET: `tpl.body_html` |

**Fix:** Update `settings.js` to use `body_html` in both the load (`tpl.body_html`) and save (`body_html: body.value`) paths.

### Pattern 4: Template Preview Variable Map Fix
**What:** `settings.js:updateTemplatePreview()` uses a hardcoded sample map with `{{nom}}`, `{{date}}`, `{{heure}}`, `{{lieu}}`, `{{organisation}}`, `{{lien_vote}}` — none of which match the canonical `EmailTemplateService::AVAILABLE_VARIABLES` keys like `{{member_name}}`, `{{meeting_date}}`, `{{vote_url}}`.

**Fix:** Replace the hardcoded map with the same sample data used by `EmailTemplateService::preview()` (the `$sampleData` array in that method). Either call the preview API or replicate the sample values client-side.

**Recommended approach:** Call `POST /api/v1/email_templates_preview.php` with `{ body_html: ... , subject: ... }` and render the returned `preview_html` into the preview div. This reuses the server-side `preview()` method and keeps the frontend in sync with the service.

### Pattern 5: Loading Saved SMTP Into the UI
**What:** `settings.js:loadSettings()` already calls `action: list` and populates all form elements by ID. The SMTP fields use IDs `settSmtpHost`, `settSmtpPort`, etc. Since these are the same keys stored in `tenant_settings`, the existing `loadSettings()` function will auto-populate SMTP fields on page load — no changes needed here.

**Caveat:** The password field (`settSmtpPass`) should not be returned in plaintext from the API. The current `SettingsRepository` returns values as raw strings. The planner should decide whether to: (a) return a placeholder mask for the password field, or (b) store the password in DB as-is and accept the risk for a self-hosted app. The simplest approach for MVP is to return a sentinel value like `"*****"` when the password is set, and only update the DB value if the submitted value is not that sentinel.

### Anti-Patterns to Avoid
- **Refactoring MailerService constructor:** Do not change the existing constructor signature. It is used by many callers. Add a factory method or merge externally.
- **Adding a new API endpoint for test_smtp:** The frontend already calls `/email_templates_preview.php`. Reuse that endpoint.
- **Rebuilding the template editor:** `email-templates.htmx.html` and `email-templates-editor.js` are already complete and working. The settings-page mini-editor (`settings.js`) only needs the field name fix, not a full rebuild.
- **Storing SMTP password in plaintext without any consideration:** Accept it for self-hosted MVP but document the behavior clearly.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| SMTP transport | Custom socket code | `symfony/mailer` (already installed) | Handles STARTTLS negotiation, AUTH, connection pooling, DSN encoding |
| Variable substitution | Custom regex engine | `EmailTemplateService::render()` (already exists) | Battle-tested `str_replace` approach with known variable set |
| Template persistence | Custom table logic | `EmailTemplateRepository` (already exists) | Full CRUD, default-flag management, duplication, name uniqueness check |
| Settings persistence | Custom key-value store | `SettingsRepository` (already exists) | PostgreSQL UPSERT, per-tenant scoping |
| Template preview rendering | Client-side JS variable replacement | `EmailController::preview()` API call | Server-side rendering keeps client in sync with `AVAILABLE_VARIABLES` |

---

## Common Pitfalls

### Pitfall 1: Double Configuration Source (env vs DB)
**What goes wrong:** `MailerService` is instantiated with only env config. A user saves SMTP credentials in the UI. The test_smtp call builds `MailerService` from env config only, not from DB — test succeeds or fails for wrong reasons.
**Why it happens:** `EmailController` accesses `global $config` which is populated from env at boot time. DB settings are not in `$config`.
**How to avoid:** Create a config merge utility that reads DB settings via `SettingsRepository::get()` and merges over the env `$config['smtp']` array before instantiating `MailerService`. Call this utility in every place that needs to send email.
**Warning signs:** Test SMTP button behavior doesn't match what was entered in the form UI.

### Pitfall 2: The `test_smtp` Endpoint Conflict
**What goes wrong:** `settings.js:initSmtpTest()` posts to `/api/v1/email_templates_preview.php` with `action: test_smtp`. `EmailController::preview()` ignores the `action` field, checks for `body_html`, and returns 400. The UI shows "Connexion SMTP echouee" even when SMTP is correctly configured.
**Why it happens:** The JS was written anticipating a future handler that was never implemented.
**How to avoid:** Add the `test_smtp` action check at the top of `EmailController::preview()` (or a dedicated method), before the `body_html` check.

### Pitfall 3: Field Name Mismatch in Settings Mini-Editor
**What goes wrong:** User edits a template in the settings page Communication tab. Save button fires. `EmailTemplatesController::update()` receives `body` instead of `body_html` → treats it as null → replaces `body_html` with empty string → corrupts the template.
**Why it happens:** `settings.js` was written with a different field name convention than the controller expects.
**How to avoid:** Fix `settings.js` to use `body_html` in both PUT payload and GET result reading.

### Pitfall 4: Password Sentinel Logic
**What goes wrong:** On each settings load, `settSmtpPass` is populated with the stored value (or `"*****"` sentinel). If the user clicks "Enregistrer" without changing the password, the sentinel `"*****"` gets written to the DB, overwriting the real password.
**Why it happens:** The auto-save/section-save logic in `settings.js` saves all fields unconditionally.
**How to avoid:** Either: (a) in the section-save handler for SMTP, skip the password field if its value matches the sentinel; or (b) never display the password value in the UI (always blank), and only save if the field is non-empty.

### Pitfall 5: MailerService is Lazy-Cached
**What goes wrong:** If SMTP config is changed during a request lifecycle, the cached `$this->mailer` will use the old config.
**Why it happens:** `getMailer()` is lazy-initialized and cached on the instance.
**How to avoid:** Not a concern in PHP's shared-nothing request model — each request creates a new `MailerService` instance. But if a test creates one instance and modifies config, it won't affect the existing instance. Be explicit about creating a fresh instance for the test_smtp handler.

---

## Code Examples

Verified patterns from direct codebase inspection:

### DB Settings Merge for MailerService
```php
// Merge pattern — to be implemented in a helper or inside controllers
// Source: app/Repository/SettingsRepository.php + app/Services/MailerService.php
function buildMailerConfig(array $envConfig, SettingsRepository $repo, string $tenantId): array {
    $smtpKeyMap = [
        'settSmtpHost'    => 'host',
        'settSmtpPort'    => 'port',
        'settSmtpUser'    => 'user',
        'settSmtpPass'    => 'pass',
        'settSenderName'  => 'from_name',
        'settSenderEmail' => 'from_email',
    ];
    $smtp = $envConfig['smtp'] ?? [];
    foreach ($smtpKeyMap as $dbKey => $smtpKey) {
        $dbVal = $repo->get($tenantId, $dbKey);
        if ($dbVal !== null && $dbVal !== '' && $dbVal !== '*****') {
            $smtp[$smtpKey] = ($smtpKey === 'port') ? (int) $dbVal : $dbVal;
        }
    }
    return array_merge($envConfig, ['smtp' => $smtp]);
}
```

### test_smtp Handler Pattern
```php
// Inside EmailController or a new SmtpTestController
// Source: app/Services/MailerService.php isConfigured() + send()
public function testSmtp(): void {
    $input = api_request('POST');
    global $config;
    $tenantId = api_current_tenant_id();
    // Merge DB settings over env
    $mergedConfig = buildMailerConfig($config, $this->repo()->settings(), $tenantId);
    $mailer = new MailerService($mergedConfig);
    if (!$mailer->isConfigured()) {
        api_fail('smtp_not_configured', 400);
    }
    $fromEmail = $mergedConfig['smtp']['from_email'] ?? '';
    $result = $mailer->send($fromEmail, 'Test SMTP AG-VOTE', '<p>Test de configuration SMTP.</p>');
    if (!$result['ok']) {
        api_fail('smtp_connection_failed', 400, ['detail' => $result['error']]);
    }
    api_ok(['tested' => true]);
}
```

### Existing EmailController::preview() Entry Point
```php
// Source: public/api/v1/email_templates_preview.php
(new \AgVote\Controller\EmailController())->handle('preview');
// preview() calls api_request('POST') — add action check at top:
$action = trim((string) ($input['action'] ?? ''));
if ($action === 'test_smtp') {
    $this->testSmtp();  // or inline handling
    return;
}
```

### Settings.js Field Fix (body vs body_html)
```javascript
// Current (WRONG) — settings.js line ~430
api('/api/v1/email_templates', {
    id: _currentTemplateId,
    type: _currentTemplate,
    subject: subject ? subject.value : '',
    body: body ? body.value : ''    // <-- wrong field name
}, 'PUT')

// Fixed:
api('/api/v1/email_templates', {
    id: _currentTemplateId,
    template_type: _currentTemplate,
    subject: subject ? subject.value : '',
    body_html: body ? body.value : ''    // <-- correct
}, 'PUT')

// Also fix load (line ~403-405):
// WRONG:  bodyEl.value = tpl.body || '';
// FIXED:  bodyEl.value = tpl.body_html || '';
```

### Canonical Variable Names (from EmailTemplateService)
```php
// Source: app/Services/EmailTemplateService.php AVAILABLE_VARIABLES constant
// The settings.js preview must use these keys, not the old {{nom}}/{{date}} ones:
'{{member_name}}', '{{member_first_name}}', '{{member_email}}',
'{{meeting_title}}', '{{meeting_date}}', '{{meeting_time}}',
'{{vote_url}}', '{{app_url}}', '{{tenant_name}}',
'{{current_date}}', '{{current_time}}', etc.
```

---

## State of the Art

| Old Approach | Current Approach | Status |
|--------------|------------------|--------|
| Hand-rolled SMTP socket | symfony/mailer with DSN building | Already done (MailerService) |
| PHP `mail()` function | `Mailer::send()` with proper transport | Already done |
| Hardcoded templates in PHP files | DB-backed `email_templates` table | Already done |
| env-only SMTP config | env + DB override (to be wired) | Gap to close in this phase |

**Deprecated/outdated:**
- `app/Templates/email_invitation.php`: `EmailController::sendBulk()` uses this PHP file template via `include` + output buffer. This pre-dates `EmailTemplateService`. Phase 63 (sending email workflows) should migrate to `EmailTemplateService`, but Phase 62 does not need to touch this.

---

## Open Questions

1. **Password storage and display**
   - What we know: `tenant_settings` stores values as plain strings. The password field is `settSmtpPass`.
   - What's unclear: Should we mask the password in the list response, or accept plaintext for self-hosted MVP?
   - Recommendation: Display a sentinel `"*****"` when password is set, blank when not set. Skip DB write if value matches sentinel. This is standard for admin UIs.

2. **test_smtp recipient address**
   - What we know: The test needs to actually connect and send to verify credentials.
   - What's unclear: Who receives the test email? `from_email` is likely the safest choice (self-send). Or prompt the user for a test address.
   - Recommendation: Send to `from_email` (self-send). No need for a separate input field — simplest path.

3. **SMTP TLS field in settings UI**
   - What we know: `MailerService` supports `tls` field (`starttls`, `ssl`, `none`). The settings.htmx.html SMTP card has no TLS selector field.
   - What's unclear: Is the TLS field intentionally omitted from the UI, or an oversight?
   - Recommendation: Add a TLS select field (`settSmtpTls`) to the settings SMTP card with options `starttls` (default), `ssl`, `none`. Store as `settSmtpTls` in DB, map to `tls` key in config merge.

---

## Validation Architecture

> `workflow.nyquist_validation` is absent from config.json — treated as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.x |
| Config file | `/home/user/gestion_votes_php/phpunit.xml` |
| Quick run command | `./vendor/bin/phpunit tests/Unit --no-coverage` |
| Full suite command | `./vendor/bin/phpunit --no-coverage` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| EMAIL-05 | MailerService reads DB SMTP settings over env | unit | `./vendor/bin/phpunit tests/Unit/MailerServiceTest.php -x` | exists — needs new test |
| EMAIL-05 | test_smtp action returns ok on valid config | unit | `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php -x` | exists — needs new test |
| EMAIL-05 | test_smtp action returns error on unconfigured SMTP | unit | `./vendor/bin/phpunit tests/Unit/EmailControllerTest.php -x` | exists — needs new test |
| EMAIL-04 | EmailTemplatesController update accepts body_html | unit | `./vendor/bin/phpunit tests/Unit/EmailTemplatesControllerTest.php -x` | exists — already tests update |
| EMAIL-04 | EmailTemplateService preview returns rendered HTML | unit | `./vendor/bin/phpunit tests/Unit/EmailTemplateServiceTest.php -x` | exists |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Unit --no-coverage`
- **Per wave merge:** `./vendor/bin/phpunit --no-coverage`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/MailerServiceTest.php` — add test for DB config merge (`buildMailerConfig` helper or factory method)
- [ ] `tests/Unit/EmailControllerTest.php` — add `test_smtp` action dispatch tests (configured/unconfigured/connection failure)

*(Existing test infrastructure covers everything else — no new files needed, only new test methods in existing files.)*

---

## Sources

### Primary (HIGH confidence)
- Direct inspection of `app/Services/MailerService.php` — SMTP config structure, `isConfigured()`, `send()`, `getMailer()` DSN building
- Direct inspection of `app/Services/EmailTemplateService.php` — `AVAILABLE_VARIABLES`, `preview()`, `render()`, `validate()`
- Direct inspection of `app/Controller/EmailTemplatesController.php` — update() field names (`body_html`), create() field names
- Direct inspection of `app/Controller/SettingsController.php` — current actions (list/update only, no test_smtp)
- Direct inspection of `app/Controller/EmailController.php` — preview() entry point, lack of action dispatch
- Direct inspection of `app/Repository/SettingsRepository.php` — `upsert()`, `get()`, `listByTenant()`
- Direct inspection of `public/assets/js/pages/settings.js` — SMTP field IDs, `initSmtpTest()` calling `email_templates_preview`, `initEmailTemplates()` field name mismatch, `updateTemplatePreview()` wrong variable names
- Direct inspection of `public/settings.htmx.html` — SMTP card field IDs, Communication tab structure
- Direct inspection of `app/config.php` — smtp config keys loaded from env
- Direct inspection of `phpunit.xml` — PHPUnit 10.x, `tests/Unit` directory, bootstrap path
- Direct inspection of `tests/Unit/MailerServiceTest.php` and `tests/Unit/SettingsControllerTest.php` — test patterns, ControllerTestCase usage

### Secondary (MEDIUM confidence)
- None needed — all research from codebase inspection

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — everything already installed and working
- Architecture: HIGH — all services, repos, controllers, and frontend files inspected directly
- Pitfalls: HIGH — all pitfalls identified from actual code contradictions, not speculation
- Test gaps: HIGH — existing test files verified, new tests needed identified precisely

**Research date:** 2026-04-01
**Valid until:** 2026-06-01 (stable codebase, no fast-moving dependencies)
