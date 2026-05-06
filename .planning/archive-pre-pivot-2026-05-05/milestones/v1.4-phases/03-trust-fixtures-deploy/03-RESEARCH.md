# Phase 3: Trust Fixtures Deploy - Research

**Researched:** 2026-04-10
**Domain:** Playwright E2E fixture infrastructure, PHP test-gated API endpoints, RBAC role coverage
**Confidence:** HIGH

## Summary

This phase creates end-to-end test fixtures for two roles that currently lack Playwright helpers: **auditor** (system role) and **assessor** (meeting role). The auditor user already exists in the seed database (`02_test_users.sql`) with credentials `auditor@ag-vote.local / Auditor2026!` but has no Playwright auth helper. The assessor role is a **meeting role** (not a system role) assigned via the `meeting_roles` table, which means a user must have both a system-level account AND a meeting_roles assignment to act as assessor.

The phase also requires a `POST /api/v1/test/seed-user` endpoint that creates users programmatically in dev/test but returns 404 in production. The existing `DevSeedController` provides a pattern (runtime `guardProduction()` check), but the success criteria explicitly require **route-level gating** (`APP_ENV !== 'production'` in `app/routes.php`) rather than controller-level gating. This is a stricter security posture: the route itself must not be registered in production, not just guarded.

Finally, `critical-path-trust.spec.js` currently uses `loginAsOperator` (not `loginAsAdmin`), and `accessibility.spec.js` line 83 uses `loginAsAdmin` as a fallback for trust.htmx.html. Both must be migrated to use the new auditor/assessor fixtures.

**Primary recommendation:** Add `loginAsAuditor` + `loginAsAssessor` to `tests/e2e/helpers.js`, create a conditionally-registered seed-user route in `app/routes.php`, and migrate all trust-related specs to use the new fixtures.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None -- all implementation choices at Claude's discretion.

### Claude's Discretion
All implementation choices are at Claude's discretion -- pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

### Deferred Ideas (OUT OF SCOPE)
None -- discuss phase skipped.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| TRUST-01 | Les helpers Playwright `loginAsAuditor` et `loginAsAssessor` existent et sont fonctionnels dans `tests/e2e/helpers/` | Auth infrastructure fully mapped: helpers.js pattern, auth.setup.js global login, storageState injection. Auditor has DB seed; assessor needs meeting_roles assignment. |
| TRUST-02 | Un endpoint de seed test-gated `POST /api/v1/test/seed-user` existe, retourne 404 en production | Route conditional registration pattern identified. DevSeedController provides controller pattern but route-level gating needed per success criteria. |
| TRUST-03 | trust.htmx.html specs utilisent les nouvelles fixtures : grep `loginAsAdmin` dans trust specs retourne 0 occurrences | Identified all files using loginAsAdmin for trust: accessibility.spec.js line 83, contrast-audit.spec.js line 45. critical-path-trust.spec.js uses loginAsOperator (not loginAsAdmin). |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| @playwright/test | 1.59.1 | E2E test framework | Already pinned in project |
| PHPUnit | ^10.5 | PHP unit tests | Already in project |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @axe-core/playwright | 4.10.2 | Accessibility auditing | Already in project, used by accessibility specs |

No new dependencies required. This phase uses only existing libraries.

## Architecture Patterns

### Existing Auth Fixture Pattern
```
tests/e2e/
  setup/auth.setup.js     # Global setup: API login -> save PHPSESSID to .auth/{role}.json
  helpers.js              # loginAs{Role}(page) -> inject saved cookies
  .auth/{role}.json       # Playwright storageState (PHPSESSID cookie)
```

**Current roles with fixtures:** admin, operator, voter, president
**Missing roles:** auditor, assessor

### Pattern 1: Adding a New System Role Fixture (auditor)
**What:** Auditor is a system role (`users.role = 'auditor'`). The user already exists in `02_test_users.sql` with credentials `auditor@ag-vote.local / Auditor2026!`.
**Steps:**
1. Add `auditor` to `ACCOUNTS` array in `setup/auth.setup.js`
2. Add `loginAsAuditor` function in `helpers.js`
3. Export from `helpers.js`

**Example (helpers.js addition):**
```javascript
async function loginAsAuditor(page) {
  await injectAuth(page, 'auditor', 'auditor@ag-vote.local', 'Auditor2026!');
}
```

**Example (auth.setup.js addition):**
```javascript
// Add to ACCOUNTS array:
{ role: 'auditor', email: 'auditor@ag-vote.local', password: 'Auditor2026!' },
```

### Pattern 2: Adding a Meeting Role Fixture (assessor)
**What:** Assessor is a **meeting role**, not a system role. A user needs: (a) a system-level account (any system role), AND (b) an entry in `meeting_roles` table with `role = 'assessor'` for a specific meeting.
**Complexity:** The trust page (`data-page-role="auditor,assessor"`) checks BOTH system roles and meeting roles via `hasAccess()` in `auth-ui.js`. An assessor user needs `meetingRoles` to contain `{role: 'assessor', meeting_id: '...'}` for access.
**Approach:** The seed-user endpoint (TRUST-02) must create the full graph: user -> tenant -> meeting -> meeting_roles(assessor). Alternatively, use an existing DB seed user with assessor role already assigned.

**Key insight from DB analysis:** The E2E seed (`04_e2e.sql`) already assigns operator user as assessor on the E2E meeting (line 117-124). But for a clean fixture, a dedicated assessor user is better.

**Assessor fixture options:**
1. **Create via seed-user API endpoint** at test setup time (aligns with TRUST-02)
2. **Add to SQL seed** (`02_test_users.sql` or `04_e2e.sql`) with a dedicated user

**Recommended:** Option 1 -- use the seed-user endpoint to create the assessor user + meeting_roles assignment. This validates the endpoint AND provides the fixture. The endpoint must create the full graph: user (system role: viewer) + meeting_roles (role: assessor, on the E2E meeting).

### Pattern 3: Route-Level Production Gate
**What:** Success criteria require `APP_ENV !== 'production'` check at route registration, not just controller guard.
**Current pattern:** DevSeedController uses `guardProduction()` inside controller methods (runtime check).
**Required pattern:** Wrap route registration in `if` block in `app/routes.php`.

**Example:**
```php
// In app/routes.php
$appEnv = config('env', 'dev');
if (!in_array($appEnv, ['production', 'prod'], true)) {
    $router->map('POST', "{$prefix}/test/seed-user", DevSeedController::class, 'seedUser');
}
```

**Why route-level:** In production, the route is never registered. Any request to it falls through to 404 (router default). No controller code executes. This is defense-in-depth beyond the existing `guardProduction()` pattern.

### Pattern 4: Seed-User Endpoint Design
**What:** `POST /api/v1/test/seed-user` creates a user with optional meeting role assignment.
**Request body:**
```json
{
  "email": "assessor-e2e@ag-vote.local",
  "password": "Assessor2026!",
  "name": "Assessor E2E",
  "system_role": "viewer",
  "meeting_id": "eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001",
  "meeting_role": "assessor"
}
```
**The endpoint must:**
1. Create user in `users` table (or update if exists via ON CONFLICT)
2. Hash password with `password_hash()` bcrypt
3. Optionally assign meeting role in `meeting_roles` table
4. Return 200 with user ID

**No auth middleware needed** on this endpoint (it's for bootstrapping test users before login). But route-level env gate ensures it's dev/test only.

### Anti-Patterns to Avoid
- **Controller-only production guard:** The success criteria explicitly require route-level gating. Don't rely solely on `guardProduction()`.
- **Hardcoding assessor as a system role:** Assessor is a meeting role. The user's system role should be `viewer` (read-only platform access), with `assessor` assigned via `meeting_roles`.
- **Skipping the full graph:** An assessor fixture that only creates the user without `meeting_roles` assignment will fail the `hasAccess()` check on trust.htmx.html.
- **Modifying ACCOUNTS in auth.setup.js without the user existing in DB:** The global setup calls the login API. If the user doesn't exist in the DB, login fails silently and tests fall back to slow form login (which also fails).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password hashing | Custom hash | `password_hash($pw, PASSWORD_BCRYPT)` | Consistent with existing user creation |
| UUID generation | Custom UUID | `api_uuid4()` | Existing global helper |
| Auth state injection | Custom cookie logic | `injectAuth()` from helpers.js | Existing pattern handles storageState fallback |
| Rate limit clearing | Manual Redis cleanup | Existing `clearRateLimit()` in auth.setup.js | Already handles Docker + host environments |

## Common Pitfalls

### Pitfall 1: Assessor Needs Active Meeting Context
**What goes wrong:** Creating an assessor user without linking to a meeting via `meeting_roles` table. The whoami endpoint returns `meeting_roles: []` and trust.htmx.html's `hasAccess('auditor,assessor', 'viewer', [])` returns false.
**Why it happens:** Assessor is a meeting role, not a system role. The `SYSTEM_ROLE_LEVEL` object in auth-ui.js doesn't have an entry for `assessor`.
**How to avoid:** The seed-user endpoint must INSERT into both `users` AND `meeting_roles`. The E2E meeting ID `eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001` is the standard target.
**Warning signs:** Trust page shows "Acces refuse" after login.

### Pitfall 2: Rate Limit Exhaustion from Too Many Auth Roles
**What goes wrong:** Adding auditor + assessor to auth.setup.js global login brings total logins to 6 (admin, operator, voter, president, auditor, assessor). Current budget is 10 requests / 300s, and the comment says "8 < 10 → safe margin of 2."
**Why it happens:** Each role in ACCOUNTS triggers one API login in globalSetup.
**How to avoid:** 6 setup logins + ~4 explicit login tests = 10 exactly. This is tight. The existing `clearRateLimit()` resets counters before setup, so it should be fine, but the margin drops to zero.
**Warning signs:** `[auth-setup] Rate limited!` in console output.
**Mitigation:** The assessor user could be created and logged in via the seed-user endpoint + direct session creation, bypassing the rate-limited auth_login endpoint. Or simply accept the tight margin since clearRateLimit runs first.

### Pitfall 3: trust.htmx.html Data-Page-Role Is "auditor,assessor" Not "admin"
**What goes wrong:** Assuming admin can access trust page because admin "has access to everything."
**Why:** Admin CAN access (line 44 of auth-ui.js: `if (currentSystemRole === 'admin') return true`). But the success criteria require testing with actual auditor/assessor roles, not admin fallback.
**How to avoid:** The specs must explicitly use `loginAsAuditor` or `loginAsAssessor`, not `loginAsAdmin`.

### Pitfall 4: Trust API Routes Allow operator/admin/auditor But Not assessor
**What goes wrong:** The trust API routes (`trust_anomalies`, `trust_checks`) require `['role' => ['auditor', 'admin', 'operator']]`. An assessor-only user (system role: viewer) cannot call these APIs.
**Why:** The trust page HTML is gated by `data-page-role="auditor,assessor"` but the API endpoints have a different role list.
**How to avoid:** The assessor smoke test should verify page-level elements (UI renders, role-gated elements visible) but not necessarily API data. Or the specs can assert that the page loads and shows UI elements without meeting-dependent data. The auditor fixture should be the one testing full API interaction.

### Pitfall 5: Stale Auth State Files
**What goes wrong:** After adding new accounts to auth.setup.js, old `.auth/` files from previous runs may interfere.
**How to avoid:** The global setup overwrites `.auth/{role}.json` on every run. New roles start with empty files until globalSetup runs. This is already handled correctly.

## Code Examples

### helpers.js: Adding loginAsAuditor and loginAsAssessor
```javascript
// In CREDENTIALS object:
auditor:   { email: 'auditor@ag-vote.local',   password: 'Auditor2026!'   },
assessor:  { email: 'assessor-e2e@ag-vote.local', password: 'Assessor2026!' },

// New functions:
async function loginAsAuditor(page) {
  await injectAuth(page, 'auditor', CREDENTIALS.auditor.email, CREDENTIALS.auditor.password);
}

async function loginAsAssessor(page) {
  await injectAuth(page, 'assessor', CREDENTIALS.assessor.email, CREDENTIALS.assessor.password);
}
```

### auth.setup.js: Adding accounts
```javascript
const ACCOUNTS = [
  { role: 'operator',  email: 'operator@ag-vote.local',  password: 'Operator2026!'  },
  { role: 'admin',     email: 'admin@ag-vote.local',      password: 'Admin2026!'     },
  { role: 'voter',     email: 'votant@ag-vote.local',     password: 'Votant2026!'    },
  { role: 'president', email: 'president@ag-vote.local',  password: 'President2026!' },
  { role: 'auditor',   email: 'auditor@ag-vote.local',    password: 'Auditor2026!'   },
  // assessor created via seed-user endpoint, logged in via API
];
```

### Route-level production gate in routes.php
```php
// ── Test seed (dev/test ONLY — not registered in production) ──
$appEnv = config('env', 'dev');
if (!in_array($appEnv, ['production', 'prod'], true)) {
    $router->map('POST', "{$prefix}/test/seed-user", DevSeedController::class, 'seedUser');
}
```

### DevSeedController::seedUser method
```php
public function seedUser(): void {
    // Double guard: route-level + controller-level
    $this->guardProduction();

    $in = api_request('POST');
    $email = trim((string) ($in['email'] ?? ''));
    $password = trim((string) ($in['password'] ?? ''));
    $name = trim((string) ($in['name'] ?? 'Test User'));
    $systemRole = trim((string) ($in['system_role'] ?? 'viewer'));
    $meetingId = trim((string) ($in['meeting_id'] ?? ''));
    $meetingRole = trim((string) ($in['meeting_role'] ?? ''));

    if ($email === '' || $password === '') {
        throw new InvalidArgumentException('email and password required');
    }

    $tenantId = api_current_tenant_id();
    $userId = api_uuid4();
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Insert or update user
    // ... (use insertReturning or ON CONFLICT pattern)

    // Optionally assign meeting role
    if ($meetingId !== '' && $meetingRole !== '') {
        // INSERT INTO meeting_roles ...
    }

    api_ok(['user_id' => $userId, 'email' => $email]);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `loginAsAdmin` for trust page | `loginAsAuditor` / `loginAsAssessor` | This phase | Tests validate actual role-gated access |
| Runtime-only production guard | Route-level + runtime guard | This phase | Defense-in-depth for test endpoints |
| 4 auth fixtures (admin/operator/voter/president) | 6 fixtures (+auditor/assessor) | This phase | Full RBAC coverage in E2E |

## Open Questions

1. **Assessor fixture: SQL seed vs API seed?**
   - What we know: The seed-user endpoint (TRUST-02) exists to create users. Using it for the assessor fixture validates the endpoint itself.
   - What's unclear: Should auth.setup.js call the seed-user endpoint before logging in? Or should assessor be added to SQL seeds for reliability?
   - Recommendation: Use a hybrid approach. Add assessor to SQL seed (`04_e2e.sql`) for database-level reliability, AND test the seed-user endpoint separately. The auth.setup.js can then include assessor in ACCOUNTS.

2. **Seed-user endpoint authentication**
   - What we know: The existing dev_seed_members/dev_seed_attendances routes use `$op` middleware (requires operator role). The seed-user endpoint creates users, which is a bootstrapping operation.
   - What's unclear: Should it require auth (operator/admin) or be unauthenticated (since it's for bootstrapping)?
   - Recommendation: No auth middleware -- route-level env gate is sufficient. The endpoint only exists in dev/test. Adding auth would create a chicken-and-egg problem for creating the first test user. However, for consistency with existing patterns, using `$opAdm` middleware is also valid since test users already exist.

3. **Rate limit budget with 6 roles**
   - What we know: Budget is 10/300s, clearRateLimit runs first. 6 setup + ~4 test = 10 exactly.
   - Recommendation: This is acceptable. clearRateLimit ensures a clean slate. If flaky, increase the limit in test env or add assessor to SQL seed instead of API login.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright 1.59.1 (E2E) + PHPUnit 10.5 (unit) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `cd tests/e2e && npx playwright test --project=chromium --grep="trust"` |
| Full suite command | `cd tests/e2e && npx playwright test --project=chromium` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| TRUST-01 | loginAsAuditor/loginAsAssessor helpers work | e2e | `cd tests/e2e && npx playwright test --project=chromium specs/critical-path-trust.spec.js` | Exists (needs migration) |
| TRUST-02 | seed-user endpoint returns 200 dev, 404 prod | unit + e2e | `timeout 60 php vendor/bin/phpunit tests/Unit/DevSeedControllerTest.php --no-coverage` | Needs creation |
| TRUST-03 | trust specs use auditor/assessor, zero loginAsAdmin | e2e + grep | `grep -c loginAsAdmin tests/e2e/specs/critical-path-trust.spec.js` (must be 0) | Exists (needs migration) |

### Sampling Rate
- **Per task commit:** `cd tests/e2e && npx playwright test --project=chromium specs/critical-path-trust.spec.js`
- **Per wave merge:** `cd tests/e2e && npx playwright test --project=chromium`
- **Phase gate:** Full suite green + `grep loginAsAdmin tests/e2e/specs/trust*.spec.js` returns 0

### Wave 0 Gaps
- [ ] `tests/Unit/DevSeedControllerTest.php` -- test seedUser method + production 404 behavior (may need creation or extension of existing test)
- [ ] `tests/e2e/helpers/auditor.js` -- loginAsAuditor helper (TRUST-01 requires files in `helpers/` directory)
- [ ] `tests/e2e/helpers/assessor.js` -- loginAsAssessor helper (TRUST-01 requires files in `helpers/` directory)

## Sources

### Primary (HIGH confidence)
- Codebase analysis: `tests/e2e/helpers.js` -- auth injection pattern, CREDENTIALS, ACCOUNTS
- Codebase analysis: `tests/e2e/setup/auth.setup.js` -- global login setup, rate limit handling
- Codebase analysis: `app/routes.php` -- route registration, middleware config, DevSeedController routes
- Codebase analysis: `app/Controller/DevSeedController.php` -- guardProduction pattern
- Codebase analysis: `database/seeds/02_test_users.sql` -- existing test users (auditor exists)
- Codebase analysis: `database/seeds/04_e2e.sql` -- E2E meeting, meeting_roles assignments
- Codebase analysis: `public/assets/js/pages/auth-ui.js` -- hasAccess(), SYSTEM_ROLE_LEVEL, MEETING_ROLES
- Codebase analysis: `app/Core/Security/AuthMiddleware.php` -- role constants, ROLE_ALIASES
- Codebase analysis: `app/Core/Security/Permissions.php` -- role hierarchy, permission matrix

### Secondary (MEDIUM confidence)
- Codebase analysis: `tests/e2e/specs/critical-path-trust.spec.js` -- current trust test (uses loginAsOperator)
- Codebase analysis: `tests/e2e/specs/accessibility.spec.js` -- loginAsAdmin fallback for trust (line 83)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - no new dependencies, fully existing tooling
- Architecture: HIGH - all patterns derived from existing codebase conventions
- Pitfalls: HIGH - identified from direct code analysis of role system, rate limiting, auth flow

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable infrastructure, no external dependency changes expected)
