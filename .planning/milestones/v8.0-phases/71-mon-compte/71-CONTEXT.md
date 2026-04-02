# Phase 71: Mon Compte - Context

**Gathered:** 2026-04-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Deliver a /account page where any logged-in user can view their profile (name, email, role) and change their own password. This is a self-service feature that removes the dependency on admin for password changes.

</domain>

<decisions>
## Implementation Decisions

### Page & Navigation
- Separate page at /account using HtmlView::render() (same HTML controller pattern as /reset-password, /setup)
- Accessible via a link in the user menu/header (profile icon or username), visible on all pages
- Profile is read-only (name, email, role displayed) — admin manages user details via /admin
- Add nginx location = /account in both nginx.conf and nginx.conf.template (same pattern as /reset-password fix from v7.0 audit)

### Password Change UX
- Inline section on the /account page with 3 fields: current password, new password, confirmation
- Min 8 characters validation (same rule as /reset-password)
- Success shows inline message, session maintained (no forced logout)
- Audit event "password_changed" logged with user_id and IP

### Claude's Discretion
- CSS styling approach (reuse login.css card pattern or create account.css)
- AccountController structure (single method or multiple)
- Form field labels and error message wording (in French)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- HtmlView::render() for HTML template rendering
- PasswordResetController pattern (HTML controller, no AbstractController)
- UserRepository::setPasswordHash($tenantId, $userId, $hash) for password update
- AuthMiddleware for session-authenticated access
- login.css / setup_form.php styling pattern (login-card, login-orb)
- RateLimiter for abuse prevention
- InputValidator for form validation

### Established Patterns
- HTML controllers: don't extend AbstractController, use HtmlView::render()
- Templates in app/Templates/ with PHP variables
- Routes: $router->mapAny('/path', Controller::class, 'method')
- Redirect exception pattern for testing (PasswordResetRedirectException, SetupRedirectException)
- audit_log() global function for event tracking

### Integration Points
- app/routes.php — add /account route
- deploy/nginx.conf + deploy/nginx.conf.template — add location = /account
- Navigation header — add "Mon Compte" link (existing shell.js or layout)
- UserRepository — read user profile data
- password_verify() + password_hash() for password change verification

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches following existing patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
