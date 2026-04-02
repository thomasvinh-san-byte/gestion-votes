---
phase: 62-smtp-template-engine
verified: 2026-04-01T06:00:00Z
status: passed
score: 8/8 must-haves verified
re_verification: false
---

# Phase 62: SMTP Template Engine Verification Report

**Phase Goal:** Administrators can configure SMTP delivery and customize email templates so the application is ready to send real emails
**Verified:** 2026-04-01T06:00:00Z
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Administrator can save SMTP credentials in admin settings UI and they persist in DB | VERIFIED | `SettingsController::settings()` `update` action calls `repo->settings()->upsert()` — line 41 |
| 2 | MailerService reads SMTP config from DB settings, falling back to .env values | VERIFIED | `MailerService::buildMailerConfig()` lines 158-176: iterates 7 DB keys, skips nulls and empty strings, falls back to `$envConfig` values |
| 3 | Test SMTP button sends a real test email and reports success or failure | VERIFIED | `EmailController::preview()` dispatches to `testSmtp()` on `action=test_smtp` (line 17); `testSmtp()` lines 219-236 call `buildMailerConfig` then `$mailer->send()` and return `{tested,sent_to}` or `api_fail` |
| 4 | Password field displays masked sentinel when set, does not overwrite real password on re-save | VERIFIED | `SettingsController` line 24-26: masks on list; lines 37-40: skips write when value is `*****` |
| 5 | Template save sends `body_html` (not `body`) to the API | VERIFIED | `settings.js` line 434: `body_html: body ? body.value : ''` in PUT payload; no `body: body` pattern found |
| 6 | Template load reads `tpl.body_html` (not `tpl.body`) from API response | VERIFIED | `settings.js` line 405: `if (bodyEl) bodyEl.value = tpl.body_html \|\| '';` |
| 7 | Template preview uses server-side API call with canonical variable names | VERIFIED | `settings.js` lines 570-571: `api('/api/v1/email_templates_preview', { body_html: text, ... })` debounced 400ms; stale `{{nom}}`, `{{date}}`, `{{heure}}` patterns absent from file |
| 8 | Variable tags in the template editor use canonical names from `EmailTemplateService::AVAILABLE_VARIABLES` | VERIFIED | `settings.htmx.html` lines 235-245: 11 variable-tag buttons with `member_name`, `meeting_title`, `vote_url`, etc. |

**Score:** 8/8 truths verified

---

### Required Artifacts

| Artifact | Expected | Level 1: Exists | Level 2: Substantive | Level 3: Wired | Status |
|----------|----------|-----------------|----------------------|----------------|--------|
| `app/Services/MailerService.php` | `buildMailerConfig` static helper | Yes | `function buildMailerConfig` at lines 158-176, 7-key loop with sentinel skip | Called by `EmailController::testSmtp()` line 222 | VERIFIED |
| `app/Controller/EmailController.php` | `test_smtp` action handler | Yes | Dispatch at lines 16-20, private `testSmtp()` at lines 219-236 | Entry via `preview()` endpoint; wired to `MailerService::buildMailerConfig` | VERIFIED |
| `app/Controller/SettingsController.php` | Password sentinel masking | Yes | List mask lines 23-26, update sentinel skip lines 36-40 | Executed on every `list` and `update` action | VERIFIED |
| `public/settings.htmx.html` | `settSmtpTls` encryption select field | Yes | Lines 170-174: `<select id="settSmtpTls">` with starttls/ssl/none options | Auto-save mechanism persists via existing `SettingsController::update` | VERIFIED |
| `public/assets/js/pages/settings.js` | Fixed template editor with `body_html` and server preview | Yes | Line 405 `tpl.body_html`, line 434 `body_html:`, lines 570-571 server preview call | PUT to `/api/v1/email_templates`; preview POST to `/api/v1/email_templates_preview` | VERIFIED |
| `public/settings.htmx.html` | Variable tag buttons + preview container | Yes | 11 `.variable-tag` buttons lines 235-245; `#templatePreviewRender` div line 251 | `settings.js` targets `.variable-tag` and `#templatePreviewRender` id | VERIFIED |
| `tests/Unit/MailerServiceTest.php` | Tests for `buildMailerConfig` | Yes | 3 new tests: DB merge, sentinel skip, empty fallback (lines 255-316) | Executed by PHPUnit against the actual static method | VERIFIED |
| `tests/Unit/EmailControllerTest.php` | Tests for `test_smtp` dispatch | Yes | 2 new tests: structural source check + SMTP not-configured path (lines 334-360) | Tests exercise `preview()` dispatch path | VERIFIED |

---

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `public/assets/js/pages/settings.js` | `/api/v1/email_templates_preview` | `action: 'test_smtp'` POST | WIRED | Line 478: `api('/api/v1/email_templates_preview', { action: 'test_smtp', dry_run: true })` — pattern confirmed |
| `app/Controller/EmailController.php` | `app/Services/MailerService.php` | `buildMailerConfig` inside `testSmtp()` | WIRED | Line 222: `$mergedConfig = MailerService::buildMailerConfig($config ?? [], $this->repo()->settings(), $tenantId)` |
| `public/assets/js/pages/settings.js` | `/api/v1/email_templates` | PUT with `body_html` field | WIRED | Line 434: `body_html: body ? body.value : ''` in PUT payload |
| `public/assets/js/pages/settings.js` | `/api/v1/email_templates_preview` | POST for server-side preview | WIRED | Lines 570-571: `api('/api/v1/email_templates_preview', { body_html: text, ... })` |

---

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| EMAIL-04 | 62-02-PLAN.md | Email templates are customizable from the admin UI (subject, HTML body with variables) | SATISFIED | Template editor reads/saves `body_html`, 11 canonical variable tag buttons, server-side preview via `EmailController::preview()` |
| EMAIL-05 | 62-01-PLAN.md | Sending uses generic SMTP (Symfony Mailer) — compatible with Mailgun, SendGrid, OVH, Gmail | SATISFIED | `MailerService::buildMailerConfig()` merges DB SMTP credentials over env config; `testSmtp()` sends via `MailerService::send()` which uses `Symfony\Component\Mailer`; TLS selector supports starttls/ssl/none |

Both requirement IDs declared in PLAN frontmatter are accounted for. No orphaned requirements detected for Phase 62 in REQUIREMENTS.md.

---

### Anti-Patterns Found

No blockers or warnings found.

| File | Pattern | Severity | Result |
|------|---------|----------|--------|
| `app/Services/MailerService.php` | TODO/placeholder scan | - | None found |
| `app/Controller/EmailController.php` | Stub return / empty handler scan | - | None found; `testSmtp()` is substantive |
| `app/Controller/SettingsController.php` | Sentinel logic completeness | - | Both list-mask and update-skip implemented |
| `public/assets/js/pages/settings.js` | Stale `{{nom}}`, `{{date}}`, `{{heure}}` variables | - | Absent from file |
| `public/settings.htmx.html` | Old variable names in template section | - | None found |

---

### Human Verification Required

#### 1. End-to-end SMTP test button flow

**Test:** In the admin settings page, fill in valid SMTP credentials (host, port, user, password, from_email), click "Test SMTP".
**Expected:** A success notification appears; a test email arrives at the configured `from_email` address.
**Why human:** Requires a live SMTP server; cannot be verified by static code analysis.

#### 2. Password sentinel round-trip

**Test:** Save an SMTP password, reload the page, verify the password field shows `*****`. Edit other SMTP fields and save. Reload again; verify the password was not overwritten with `*****`.
**Expected:** Password persists correctly across reloads and partial saves.
**Why human:** Requires browser interaction with live DB; static analysis confirms the logic exists but not the end-to-end UX.

#### 3. Template preview with variable substitution

**Test:** In the template editor, select an email template, type `Hello {{member_name}}` in the body. Click the `{{meeting_title}}` variable tag button.
**Expected:** The preview area updates (debounced 400ms) showing resolved values like `Hello Jean Dupont` from sample data; the variable tag is inserted at cursor position.
**Why human:** Requires browser rendering; server-side preview response depends on `EmailTemplateService` sample data which is not exercised by static analysis.

---

## Gaps Summary

No gaps. All automated checks passed:
- PHP syntax clean on all 3 modified PHP files
- All 5 must-have artifact patterns confirmed in source code
- All 4 key links confirmed wired with exact line references
- EMAIL-04 and EMAIL-05 both satisfied with substantive implementation
- Commits d3877840, dce8fa8f, bbf5f6c3, d9a07509, 71f26f36 all verified in git history
- No stale variable names remain; no placeholder implementations found

---

_Verified: 2026-04-01T06:00:00Z_
_Verifier: Claude (gsd-verifier)_
