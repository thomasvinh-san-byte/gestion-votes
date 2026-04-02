# Phase 72: Security Config - Context

**Gathered:** 2026-04-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Add 2-step confirmation for critical admin operations (user deletion, admin password reset) and make session timeout configurable from the admin settings UI. Both features harden the application's security posture.

</domain>

<decisions>
## Implementation Decisions

### 2-Step Confirmation
- Critical operations: `delete` (user suppression) and `set_password` (admin password reset) in AdminController
- Frontend: use existing ag-confirm Web Component for confirmation dialog
- Backend: require `confirm_password` field on critical POST requests — controller verifies admin's own password via password_verify() before executing the operation
- POST without valid `confirm_password` is rejected with 400 error
- Audit event `admin.confirm.failed` logged when wrong password on critical action

### Session Timeout Configurable
- Store in `tenant_settings` table with key `session_timeout` (same pattern as SMTP config)
- Allowed range: 5 to 480 minutes, step 5 min, default 30 min
- Configure from existing /settings page (add a Security or General section)
- AuthMiddleware reads timeout from tenant_settings instead of hardcoded SESSION_TIMEOUT constant
- CsrfMiddleware TOKEN_LIFETIME aligned to the same dynamic value
- SettingsRepository::get() already supports key-value reads

### Claude's Discretion
- UI layout of the timeout setting in /settings page
- Error message wording for confirmation failures (in French)
- Whether to cache the tenant_settings timeout value in session to avoid DB reads on every request

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- ag-confirm Web Component (public/assets/js/components/ag-confirm.js) — confirmation dialogs
- SettingsRepository::get()/set() — tenant_settings key-value store
- AuthMiddleware::SESSION_TIMEOUT (line 41) — currently hardcoded 1800
- CsrfMiddleware::TOKEN_LIFETIME (line 16) — currently hardcoded 1800
- AdminController (lines 43, 67, 79) — delete/set_password/toggle actions
- password_verify() already used in AuthController for login

### Established Patterns
- Admin operations use api_ok()/api_fail() responses
- Settings saved via SettingsController with POST to /api/v1/admin_settings
- audit_log() for all admin actions
- ag-confirm usage: `<ag-confirm message="..." on-confirm="callback()">`

### Integration Points
- app/Controller/AdminController.php — add confirm_password check on delete/set_password
- app/Core/Security/AuthMiddleware.php — read timeout from tenant_settings
- app/Core/Security/CsrfMiddleware.php — align token lifetime
- public/assets/js/pages/admin.js — add ag-confirm dialogs on critical buttons
- settings page — add timeout configuration field

</code_context>

<specifics>
## Specific Ideas

No specific requirements beyond the decisions above.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
