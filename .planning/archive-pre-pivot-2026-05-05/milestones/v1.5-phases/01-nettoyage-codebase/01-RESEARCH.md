# Phase 1: Nettoyage Codebase - Research

**Researched:** 2026-04-10
**Domain:** JS/CSS/PHP codebase cleanup, dead code removal, superglobal migration
**Confidence:** HIGH

## Summary

Phase 1 is a pure cleanup phase with well-defined success criteria verified by grep commands. The codebase has 34 console.log/warn/error statements in JS, 1 deprecated class (PermissionChecker), 2 deprecated methods in VoteTokenService, 1 TODO in CSS, and 17 direct superglobal accesses across 6 controllers. Additionally, PageController needs a new unit test file.

All targets are concrete and mechanically verifiable. The main complexity is in the superglobal migration (CLEAN-05), which requires understanding which controllers extend AbstractController (and thus have `$this->request`) vs standalone HTML controllers that need a local `new Request()`. The console.log cleanup (CLEAN-01) requires judgment on which are "critical error handlers" to keep vs noise to remove.

**Primary recommendation:** Execute in 2 plans -- Plan 1 handles JS/CSS cleanup (CLEAN-01, CLEAN-03) and dead code removal (CLEAN-02); Plan 2 handles PHP superglobal migration (CLEAN-05) and PageController test (CLEAN-04).

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
None -- infrastructure phase, all at Claude's discretion.

### Claude's Discretion
All implementation choices are at Claude's discretion -- pure infrastructure phase. Use ROADMAP phase goal, success criteria, and codebase conventions to guide decisions.

### Deferred Ideas (OUT OF SCOPE)
None.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CLEAN-01 | Zero console.log/warn/error dans le JS de production (hors error handlers critiques) | 34 occurrences identified across 15 files -- full inventory below |
| CLEAN-02 | Zero code deprecie (PermissionChecker supprime, VoteTokenService deprecated methods supprimes) | PermissionChecker.php + test file identified; 2 deprecated methods in VoteTokenService + test methods identified |
| CLEAN-03 | Zero TODO/FIXME dans CSS et JS de production | 1 occurrence: postsession.css:31 |
| CLEAN-04 | Test unitaire PageController couvrant nonce injection et 404 | PageController analyzed (61 LOC, static methods); ControllerTestCase base class available |
| CLEAN-05 | Zero $_GET/$_POST/$_REQUEST direct dans app/ (hors bootstrap/index.php) | 17 occurrences across 6 controllers; Request class API documented |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPUnit | ^10.5 | Unit testing (PageController test) | Already in project |
| AgVote\Core\Http\Request | N/A | Replace superglobals | Already exists, wraps $_GET/$_POST/$_SERVER |

### Supporting
No new libraries needed. This is a pure cleanup phase using existing tools.

## Architecture Patterns

### Pattern 1: Superglobal Replacement in AbstractController Children
**What:** Controllers extending AbstractController already have `$this->request` (set in constructor).
**When to use:** MembersController (the only affected controller extending AbstractController).
**Example:**
```php
// BEFORE (MembersController.php:24)
$search = trim($_GET['search'] ?? '');

// AFTER
$search = trim((string) $this->request->query('search', ''));
```

### Pattern 2: Superglobal Replacement in Standalone HTML Controllers
**What:** Controllers NOT extending AbstractController (SetupController, PasswordResetController, EmailTrackingController, AccountController, DocContentController) need to instantiate Request locally or use existing api_query()/api_request() helpers.
**When to use:** All standalone controllers with superglobal access.

**Decision: Use `new Request()` at method entry.** These controllers are HTML controllers that don't extend AbstractController per project convention. Creating a local Request instance is cleanest.

```php
// BEFORE (SetupController.php:62-66)
$orgName  = trim((string) ($_POST['organisation_name'] ?? ''));
$name     = trim((string) ($_POST['admin_name'] ?? ''));

// AFTER
$request = new Request();
$orgName  = trim((string) $request->body('organisation_name', ''));
$name     = trim((string) $request->body('admin_name', ''));
```

### Pattern 3: Console Statement Removal with Critical Handler Preservation
**What:** Remove all console.log/warn/error except those in critical error catch blocks that are the last line of defense.
**Judgment criteria:** Keep console.error in catch blocks of core infrastructure (API fetch, upload, SSE); remove console.warn for non-critical warnings and all console.log.

### Anti-Patterns to Avoid
- **Removing console in error boundaries:** The utils.js API timeout/error handlers (lines 663, 666, 710, 713) are critical error handlers -- these are the ONLY place these network failures surface to developers. Preserve them.
- **Breaking CsrfMiddleware/Router:** These core infrastructure files use $_POST/$_REQUEST by design -- they ARE the abstraction layer. The success criteria excludes "hors bootstrap/index.php" and these are infrastructure, not app controllers.

## Detailed Inventory: CLEAN-01 (Console Statements)

### To REMOVE (25 statements -- non-critical)
| File | Line | Statement | Reason |
|------|------|-----------|--------|
| components/ag-searchable-select.js | 21 | `console.log(e.detail.value)` | In JSDoc example comment -- keep as documentation? Actually in code comment, harmless but grep matches it |
| components/index.js | 86 | `console.log('[AG-VOTE] Web Components registered:', [...])` | Debug/startup noise |
| core/page-components.js | 29 | `console.log('Tab changed:', tabId)` | JSDoc example in comment |
| core/shared.js | 558 | `console.warn('ag-toast not loaded...')` | Graceful degradation noise |
| core/shell.js | 912 | `console.warn('[shell] auth-ui.js injection failed:', e)` | Non-critical UI warning |
| pages/analytics-dashboard.js | 678 | `console.error('CSV export failed:', err)` | User already sees export failure in UI |
| pages/audit.js | 562 | `console.warn('[audit.js] API unavailable:', ...)` | Graceful degradation noise |
| pages/email-templates-editor.js | 31 | `console.error('Load templates error:', err)` | UI shows error state |
| pages/email-templates-editor.js | 259 | `console.error('Preview error:', err)` | UI shows error state |
| pages/hub.js | 648 | `console.warn('Hub loadData error:', e)` | Non-critical |
| pages/operator-realtime.js | 288 | `console.warn('autoPoll error:', err)` | Polling retry handles it |
| pages/operator-tabs.js | 182 | `console.error('onDismiss error:', e)` | Non-critical UI callback |
| pages/operator-tabs.js | 517 | `console.warn('loadAllData: Task ${idx} failed:', ...)` | Non-critical |
| pages/operator-tabs.js | 1411 | `console.warn('loadQuorumStatus error:', e)` | Non-critical |
| pages/public.js | 18 | `console.warn('sessionStorage unavailable:', e.message)` | Non-critical fallback |
| pages/public.js | 233 | `console.error('loadResults error:', e)` | UI handles error |
| pages/public.js | 450 | `console.error('refresh error:', e)` | UI handles error |
| pages/public.js | 485 | `console.warn('[projection] heartbeat:...')` | Non-critical monitoring noise |
| pages/settings.js | 229 | `console.warn('Settings load failed...:', e)` | Graceful degradation |
| pages/settings.js | 471 | `console.warn('Template load failed')` | Non-critical |
| pages/vote.js | 351 | `console.warn('[vote] Policy fetch partial failure:...')` | Non-critical |
| pages/vote.js | 560 | `console.warn('[vote] Attendance fetch failed...:', ...)` | Non-critical fallback |
| pages/vote.js | 1252 | `console.error` (in `.catch(console.error)`) | Lazy error swallowing |
| pages/vote.js | 1327 | `console.error('SSE refresh error:', e)` | Non-critical |
| pages/vote.js | 1368 | `console.error('vote refresh error:', e)` | Non-critical |
| pages/vote.js | 1376 | `console.error('heartbeat error:', e)` | Non-critical |
| services/meeting-context.js | 206 | `console.error('MeetingContext listener error:', e)` | Non-critical |

### To KEEP as critical error handlers (4 statements)
| File | Line | Statement | Reason |
|------|------|-----------|--------|
| core/utils.js | 663 | `console.error('API Timeout:', url)` | Core API infrastructure -- only visibility into network timeouts |
| core/utils.js | 666 | `console.error('API Error:', err)` | Core API infrastructure -- only visibility into fetch failures |
| core/utils.js | 710 | `console.error('Upload Timeout:', url)` | Core upload infrastructure |
| core/utils.js | 713 | `console.error('Upload Error:', err)` | Core upload infrastructure |

### To KEEP as documentation (2 statements in JSDoc comments)
| File | Line | Statement | Reason |
|------|------|-----------|--------|
| components/ag-searchable-select.js | 21 | In `*` comment block | JSDoc usage example |
| core/page-components.js | 29 | In `*` comment block | JSDoc usage example |

**Note:** The 2 JSDoc examples contain `console.log` inside code comments (`*` lines). grep will match them. Either rewrite the JSDoc examples to not use console.log, or document them as exceptions. Recommendation: rewrite to use a different example.

### To KEEP but document (1 statement)
| File | Line | Statement | Reason |
|------|------|-----------|--------|
| core/event-stream.js | 43 | `console.warn('[EventStream] EventSource not supported...')` | Browser capability detection -- but should convert to silent degradation |
| core/event-stream.js | 144 | `console.warn('[EventStream] Max reconnect attempts...')` | Connection state -- but should be silent |

**Revised:** Remove these too. EventStream failures are handled by polling fallback. Total to remove: ~30, keep: 4 in utils.js.

## Detailed Inventory: CLEAN-02 (Dead Code)

### PermissionChecker (DELETE entirely)
- **File:** `app/Core/Security/PermissionChecker.php` (227 LOC)
- **Status:** Marked `@deprecated`, not imported or used anywhere in `app/`
- **Test file:** `tests/Unit/PermissionCheckerTest.php` -- DELETE
- **Integration test:** `tests/Integration/AdminCriticalPathTest.php` -- uses PermissionChecker, needs update or deletion

### VoteTokenService Deprecated Methods (DELETE 2 methods + update tests)
- **File:** `app/Services/VoteTokenService.php`
- **Method 1:** `validate()` at line 95-125 (31 LOC) -- `@deprecated`
- **Method 2:** `consume()` at line 132-150 (19 LOC) -- `@deprecated`
- **No production callers** -- only test callers in `tests/Unit/VoteTokenServiceTest.php` (lines 242, 268, 291, 298, 309, 440, 446, 461, 473, 517)
- **Action:** Delete the 2 methods from VoteTokenService.php, delete corresponding test methods from VoteTokenServiceTest.php

## Detailed Inventory: CLEAN-03 (TODO/FIXME)

### Single occurrence
- **File:** `public/assets/css/postsession.css` line 31
- **Content:** `/* TODO: HTML partial needs .form-grid on form containers */`
- **Action:** Remove the comment. The CSS below it works regardless of whether .form-grid is added.

## Detailed Inventory: CLEAN-04 (PageController Test)

### Target: `app/Controller/PageController.php` (61 LOC)
- Static class with 2 public methods: `serveFromUri()` and `serve(string $page)`
- Uses `$_SERVER['REQUEST_URI']` and `SecurityProvider::nonce()`
- Outputs via `echo` and `header()` -- requires output buffering in tests
- Reads files from `public/*.htmx.html`

### Test Strategy
- **File to create:** `tests/Unit/PageControllerTest.php`
- **Base class:** PHPUnit TestCase (NOT ControllerTestCase -- PageController doesn't extend AbstractController)
- **Key tests needed:**
  1. `testServeFromUri_validPage_injectsNonce` -- set `$_SERVER['REQUEST_URI']`, mock SecurityProvider::nonce(), capture output, assert nonce replacement
  2. `testServeFromUri_invalidPage_returns404` -- set invalid URI, assert 404 response code
  3. `testServe_validPage_replacesNoncePlaceholder` -- test nonce injection with known placeholder
  4. `testServe_nonexistentFile_returns404` -- test missing .htmx.html file

### SecurityProvider::nonce() mocking
```php
// SecurityProvider::nonce() is static -- check if it can be controlled in test
```
Need to check if SecurityProvider allows test override.

## Detailed Inventory: CLEAN-05 (Superglobal Migration)

### Files requiring changes (6 controllers, 17 occurrences)

**1. MembersController.php** (extends AbstractController -- has `$this->request`)
| Line | Before | After |
|------|--------|-------|
| 24 | `$search = trim($_GET['search'] ?? '')` | `$search = trim((string) $this->request->query('search', ''))` |

**2. DocContentController.php** (standalone)
| Line | Before | After |
|------|--------|-------|
| 16 | `$page = trim((string) ($_GET['page'] ?? ''))` | `$request = new Request(); $page = trim((string) $request->query('page', ''))` |

**3. PasswordResetController.php** (standalone)
| Line | Before | After |
|------|--------|-------|
| 37 | `$_GET['token'] ?? $_POST['token'] ?? ''` | `$request->query('token', '') ?: $request->body('token', '')` |
| 80 | `$_POST['password'] ?? ''` | `$request->body('password', '')` |
| 81 | `$_POST['password_confirm'] ?? ''` | `$request->body('password_confirm', '')` |
| 138 | `$_POST['email'] ?? ''` | `$request->body('email', '')` |

**4. SetupController.php** (standalone)
| Line | Before | After |
|------|--------|-------|
| 62 | `$_POST['organisation_name'] ?? ''` | `$request->body('organisation_name', '')` |
| 63 | `$_POST['admin_name'] ?? ''` | `$request->body('admin_name', '')` |
| 64 | `$_POST['admin_email'] ?? ''` | `$request->body('admin_email', '')` |
| 65 | `$_POST['admin_password'] ?? ''` | `$request->body('admin_password', '')` |
| 66 | `$_POST['admin_password_confirm'] ?? ''` | `$request->body('admin_password_confirm', '')` |

**5. EmailTrackingController.php** (standalone)
| Line | Before | After |
|------|--------|-------|
| 17 | `$_GET['id'] ?? ''` | `$request->query('id', '')` |
| 55 | `$_GET['id'] ?? ''` | `$request->query('id', '')` |
| 56 | `$_GET['url'] ?? ''` | `$request->query('url', '')` |

**6. AccountController.php** (standalone)
| Line | Before | After |
|------|--------|-------|
| 63 | `$_POST['current_password'] ?? ''` | `$request->body('current_password', '')` |
| 64 | `$_POST['new_password'] ?? ''` | `$request->body('new_password', '')` |
| 65 | `$_POST['new_password_confirm'] ?? ''` | `$request->body('new_password_confirm', '')` |

### Exclusions (infrastructure -- not in scope)
- `app/Core/Router.php` (lines 165-169) -- IS the routing infrastructure, sets $_REQUEST for route params
- `app/Core/Security/CsrfMiddleware.php` (lines 124-125) -- IS the security middleware
- `app/Core/Http/Request.php` (lines 28, 34) -- IS the Request class constructor
- `app/api.php` (lines 129, 133, 140, 147) -- IS the legacy helper layer
- `app/Core/Validation/InputValidator.php` (line 31) -- In a comment/docblock

**Success criteria says "hors bootstrap/index.php"** -- but Router, CsrfMiddleware, Request.php, and api.php are infrastructure that wraps superglobals by design. They should be excluded from the cleanup target just like bootstrap/index.php.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP request abstraction | Custom wrapper | `AgVote\Core\Http\Request` | Already exists, tested, handles JSON body parsing |
| Output capture for testing | Manual ob_start | PHPUnit output testing or ob_start/ob_get_clean | Standard approach |

## Common Pitfalls

### Pitfall 1: Breaking CsrfMiddleware
**What goes wrong:** Migrating $_POST in CsrfMiddleware breaks CSRF validation
**Why it happens:** CsrfMiddleware reads $_POST BEFORE Request object is constructed
**How to avoid:** Exclude CsrfMiddleware from CLEAN-05 scope -- it IS infrastructure

### Pitfall 2: Request Constructor Side Effects in Standalone Controllers
**What goes wrong:** Creating `new Request()` in methods called multiple times wastes php://input reads
**Why it happens:** Request reads php://input in constructor, which can only be read once
**How to avoid:** Request already caches raw body in static property. Safe to create multiple instances.

### Pitfall 3: JSDoc Console Examples Matching Grep
**What goes wrong:** grep still matches console.log in JSDoc comments
**Why it happens:** Comments contain example code with console.log
**How to avoid:** Rewrite JSDoc examples to not use console.log (use event handler or callback example instead)

### Pitfall 4: PermissionChecker Deletion Breaking Integration Test
**What goes wrong:** AdminCriticalPathTest.php imports PermissionChecker
**Why it happens:** Integration test depends on deprecated class
**How to avoid:** Update AdminCriticalPathTest to use AuthMiddleware directly, or delete if tests are redundant

### Pitfall 5: PageController Uses Static Methods
**What goes wrong:** Can't easily inject dependencies for testing
**Why it happens:** PageController::serve() and serveFromUri() are static
**How to avoid:** Use $_SERVER manipulation + output buffering in tests. SecurityProvider::nonce() is also static -- check if it uses a predictable value in test env.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/PageControllerTest.php --no-coverage` |
| Full suite command | `timeout 120 php vendor/bin/phpunit --testsuite Unit --no-coverage` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CLEAN-01 | Zero console.log in JS (hors critical) | grep check | `grep -rn 'console\.\(log\|warn\|error\)' public/assets/js/` | N/A (grep) |
| CLEAN-02 | Zero PermissionChecker + deprecated methods | grep check | `grep -rn 'PermissionChecker' app/` | N/A (grep) |
| CLEAN-03 | Zero TODO/FIXME in JS/CSS | grep check | `grep -rn 'TODO\|FIXME' public/assets/js/ public/assets/css/` | N/A (grep) |
| CLEAN-04 | PageController test passes | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/PageControllerTest.php --no-coverage` | Wave 0 |
| CLEAN-05 | Zero direct superglobals in app/ | grep check | `grep -rn '\$_GET\|\$_POST\|\$_REQUEST' app/` then filter | N/A (grep) |

### Sampling Rate
- **Per task commit:** Run relevant grep verification
- **Per wave merge:** `timeout 120 php vendor/bin/phpunit --testsuite Unit --no-coverage`
- **Phase gate:** All 5 grep success criteria pass + PHPUnit green

### Wave 0 Gaps
- [ ] `tests/Unit/PageControllerTest.php` -- covers CLEAN-04 (to be created)

## Sources

### Primary (HIGH confidence)
- Direct codebase grep and file analysis -- all findings verified against actual files
- `app/Core/Http/Request.php` -- Request class API confirmed via source code
- `app/Controller/AbstractController.php` -- confirms `$this->request` available to child controllers
- `tests/Unit/ControllerTestCase.php` -- confirms test infrastructure pattern

### Secondary (MEDIUM confidence)
- None needed -- all findings from direct codebase analysis

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- no new libraries needed, all existing
- Architecture: HIGH -- patterns directly observed in codebase
- Pitfalls: HIGH -- verified through code analysis
- Inventory completeness: HIGH -- grep results are exhaustive

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable -- codebase cleanup, no external dependencies)
