# Phase 4: HTMX 2.0 Upgrade - Research

**Researched:** 2026-04-10
**Domain:** htmx 1.9.12 to 2.0.6 migration, frontend library upgrade
**Confidence:** HIGH

## Summary

The htmx upgrade in this project is unusually straightforward because the codebase has minimal htmx usage. Only 2 of 21 `.htmx.html` pages load htmx (vote.htmx.html and postsession.htmx.html), and there are zero `hx-*` attributes in any HTML or JS file. The only htmx integration is 4 event listeners in `utils.js` (`htmx:configRequest`, `htmx:afterSwap`, `htmx:responseError`, `htmx:sendError`). SSE is handled by a custom `event-stream.js`, not the htmx SSE extension. No htmx extensions are in use.

The main work items are: (1) replace the htmx 1.9.12 vendor file with 2.0.6, (2) add `htmx-1-compat` as a safety net, (3) verify zero `hx-on` old-syntax occurrences (already true), (4) audit DELETE endpoint parameter handling (3 controllers read body via `api_request('DELETE')`, 3 JS callers send body with DELETE), and (5) run Playwright full suite across 4 browser engines.

**Primary recommendation:** Drop-in replace htmx.min.js, load htmx-1-compat alongside for safety, audit+fix the 3 DELETE body-reading endpoints, verify Playwright suite green across all browsers.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| HTMX-01 | htmx.org updated to 2.0.6; htmx-1-compat loaded as safety net | Direct vendor file replacement + new compat script tag. Only 2 pages load htmx. |
| HTMX-02 | All hx-on attributes in kebab-case (grep returns 0 old syntax) | Already zero occurrences of `hx-on=` in entire codebase. Verification-only task. |
| HTMX-03 | DELETE handlers read params from query string, not body | 3 controllers use `api_request('DELETE')` which reads body. 3 JS callers send body with DELETE. Fix both sides. |
| HTMX-04 | HTMX extensions loaded as individual scripts, no bundle | No htmx extensions in use (SSE is custom event-stream.js). Verification-only task. |
| HTMX-05 | Playwright full suite passes chromium+firefox+webkit+mobile-chrome | Existing Playwright config already defines all 4 projects. Run via `bin/test-e2e.sh`. |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| htmx | 2.0.6 | Declarative AJAX/HTML-over-the-wire | Target version per ROADMAP |
| htmx-1-compat | 2.0.x | Backwards-compat safety net during migration | Reverts breaking defaults, catches missed patterns |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Playwright | 1.59.1 | Cross-browser E2E testing | Locked per REQUIREMENTS (no upgrade) |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| htmx-1-compat | Direct migration without compat | Compat is explicitly required by success criteria |

**Installation:**
```bash
# Download htmx 2.0.6 to vendor directory
curl -o public/assets/vendor/htmx.min.js https://cdn.jsdelivr.net/npm/htmx.org@2.0.6/dist/htmx.min.js

# Download htmx-1-compat extension
curl -o public/assets/vendor/htmx-1-compat.min.js https://cdn.jsdelivr.net/npm/htmx-ext-htmx-1-compat@2.0.2/htmx-1-compat.js
```

## Architecture Patterns

### Current htmx Usage (Minimal)

The app barely uses htmx despite the `.htmx.html` naming convention:

**Pages loading htmx (2/21):**
- `vote.htmx.html` (line 363)
- `postsession.htmx.html` (line 516)

**htmx attribute usage:** Zero. No `hx-get`, `hx-post`, `hx-delete`, `hx-on`, `hx-swap`, `hx-target`, or `hx-trigger` attributes in any HTML or JS file.

**htmx event listeners (utils.js only):**
```javascript
// These 4 listeners are the ONLY htmx integration
document.body.addEventListener('htmx:configRequest', ...);  // CSRF injection
document.body.addEventListener('htmx:afterSwap', ...);       // Re-init CSRF forms
document.body.addEventListener('htmx:responseError', ...);   // Error toast
document.body.addEventListener('htmx:sendError', ...);       // Network error toast
```

**SSE:** Custom `event-stream.js` using native `EventSource` API (not htmx SSE extension).

**htmx extensions in use:** None.

### Script Loading Pattern (Current)
```html
<!-- vote.htmx.html and postsession.htmx.html -->
<script src="/assets/vendor/htmx.min.js"></script>
<script src="/assets/js/core/utils.js"></script>
```

### Script Loading Pattern (After Migration)
```html
<!-- htmx 2.0.6 + compat safety net -->
<script src="/assets/vendor/htmx.min.js"></script>
<script src="/assets/vendor/htmx-1-compat.min.js"></script>
<script src="/assets/js/core/utils.js"></script>
```
Note: `htmx-1-compat` must be activated with `hx-ext="htmx-1-compat"` on `<body>` or a parent element.

### DELETE Endpoint Audit

**JS callers sending body with DELETE (must fix):**

| JS File | Call | Current | Fix |
|---------|------|---------|-----|
| `wizard.js:736` | `api('/api/v1/resolution_documents', { id: doc.id }, 'DELETE')` | Body JSON | Move id to query param |
| `operator-tabs.js:3479` | `api('/api/v1/meeting_attachments', { id: id }, 'DELETE')` | Body JSON | Move id to query param |
| `members.js:734` | `api('/api/v1/members.php', { member_id: memberId }, 'DELETE')` | Body JSON | Move id to query param |

**JS callers already using query params (no fix needed):**

| JS File | Call | Status |
|---------|------|--------|
| `email-templates-editor.js:191` | `api(URL?id=X, null, 'DELETE')` | Already correct |
| `members.js:387` | `api(URL?id=X, null, 'DELETE')` | Already correct |

**PHP controllers reading from body (must audit):**

| Controller | Method | How params read | Fix needed |
|-----------|--------|-----------------|------------|
| `ResolutionDocumentController::delete()` | `api_request('DELETE')` reads body | `$input['id']` from merged array | Switch to `api_query('id')` |
| `MembersController::delete()` | `api_request('DELETE')` reads body | `$input['id']` or `$input['member_id']` | Switch to `api_query('id')` |
| `MeetingAttachmentController::delete()` | `api_request('DELETE')` reads body | `$input['id']` from merged array | Switch to `api_query('id')` |
| `EmailTemplatesController::delete()` | `api_query('id')` | Already query param | No fix |
| `MemberGroupsController::delete()` | `api_query('id')` | Already query param | No fix |
| `ReminderController::delete()` | `api_query('id')` | Already query param | No fix |
| `ExportTemplatesController::delete()` | `api_query('id')` | Already query param | No fix |
| `ProxiesController::delete()` | `api_request('POST')` (uses POST, not DELETE) | Separate concern | No fix |

**Note:** `api_request()` already does `array_merge($_GET, $data)`, so DELETE requests with query params will work even without changing the PHP side. However, HTMX-03 requires explicit migration to query params for standards compliance.

### Anti-Patterns to Avoid
- **Loading compat without `hx-ext` activation:** The `htmx-1-compat.js` script must be activated via `hx-ext="htmx-1-compat"` attribute on a parent element (e.g., `<body>`).
- **Upgrading Playwright version:** REQUIREMENTS explicitly forbid upgrading past 1.59.1 to preserve the v1.3 baseline.
- **Removing the compat extension prematurely:** Keep it for the duration of this migration; can be removed in a later phase once confirmed stable.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| htmx backwards compat | Custom shims for old behavior | `htmx-1-compat` extension | Officially maintained, covers all breaking changes |
| htmx version pinning | CDN link | Local vendor file `public/assets/vendor/htmx.min.js` | App already uses vendored files, avoids CDN dependency |

## Common Pitfalls

### Pitfall 1: htmx Event Name Changes
**What goes wrong:** htmx 2.0 renamed some events (e.g., `htmx:beforeRequest` -> different timing)
**Why it happens:** Event lifecycle was refined in 2.0
**How to avoid:** The 4 event listeners in utils.js use `htmx:configRequest`, `htmx:afterSwap`, `htmx:responseError`, `htmx:sendError` -- all of these are stable across 1.x and 2.x. No action needed.
**Warning signs:** CSRF token not being sent, error toasts not appearing.

### Pitfall 2: DELETE Body vs Query Params
**What goes wrong:** In htmx 2.0, DELETE requests use URL query params instead of form-encoded body (aligning with HTTP spec). Server-side handlers reading `php://input` or `$_POST` for DELETE would get empty data.
**Why it happens:** htmx 2.0 moved `DELETE` into `methodsThatUseUrlParams` (same as GET).
**How to avoid:** Fix both JS callers (move params to URL query string) and PHP handlers (use `api_query()` or `$_GET`).
**Warning signs:** 404 or "missing_id" errors on delete operations.

### Pitfall 3: compat Extension Not Activated
**What goes wrong:** Loading `htmx-1-compat.js` without `hx-ext="htmx-1-compat"` on a parent element means it has no effect.
**Why it happens:** htmx extensions must be declared via the `hx-ext` attribute.
**How to avoid:** Add `hx-ext="htmx-1-compat"` to `<body>` on both pages that load htmx.
**Warning signs:** Old behaviors still broken despite compat being loaded.

### Pitfall 4: Cross-Browser Playwright Failures Unrelated to htmx
**What goes wrong:** WebKit or Firefox may have pre-existing failures from the v1.3 baseline that are unrelated to the htmx upgrade.
**Why it happens:** CI only runs chromium; cross-browser was "local-only" (noted in STATE.md tech debt).
**How to avoid:** Run full cross-browser suite BEFORE the htmx upgrade as a baseline. Document pre-existing failures. Only new failures after upgrade count as regressions.
**Warning signs:** Test failures on selectors, timing, or features that are unrelated to htmx.

### Pitfall 5: selfRequestsOnly Default Changed
**What goes wrong:** htmx 2.0 defaults `selfRequestsOnly` to `true`, blocking cross-origin requests.
**Why it happens:** Security hardening in 2.0.
**How to avoid:** Not relevant for this app (all requests are same-origin). The compat extension reverts this if needed.
**Warning signs:** Any external API calls via htmx attributes would fail (but none exist in this codebase).

## Code Examples

### Replacing Vendor File
```bash
# Source: https://htmx.org/migration-guide-htmx-1/
# Download htmx 2.0.6
curl -sL https://cdn.jsdelivr.net/npm/htmx.org@2.0.6/dist/htmx.min.js \
  -o public/assets/vendor/htmx.min.js

# Download htmx-1-compat
curl -sL https://cdn.jsdelivr.net/npm/htmx-ext-htmx-1-compat@2.0.2/htmx-1-compat.js \
  -o public/assets/vendor/htmx-1-compat.js
```

### Updating HTML Script Tags (vote.htmx.html, postsession.htmx.html)
```html
<!-- Before -->
<script src="/assets/vendor/htmx.min.js"></script>

<!-- After -->
<script src="/assets/vendor/htmx.min.js"></script>
<script src="/assets/vendor/htmx-1-compat.js"></script>
```

And on the `<body>` tag:
```html
<body hx-ext="htmx-1-compat">
```

### Fixing DELETE JS Callers
```javascript
// Before (wizard.js)
window.api('/api/v1/resolution_documents', { id: doc.id }, 'DELETE')

// After — id moved to query string, no body
window.api('/api/v1/resolution_documents?id=' + encodeURIComponent(doc.id), null, 'DELETE')
```

### Fixing DELETE PHP Handlers
```php
// Before (ResolutionDocumentController::delete)
$input = api_request('DELETE');
$id = trim((string) ($input['id'] ?? ''));

// After — read from query string explicitly
$id = api_query('id');
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| htmx 1.x `hx-on="event: code"` | htmx 2.0 `hx-on:event-name="code"` | June 2024 (2.0.0) | Not applicable (zero usage) |
| DELETE body params | DELETE query params | June 2024 (2.0.0) | 3 JS callers + 3 PHP handlers to fix |
| Bundled extensions | Separate extension scripts | June 2024 (2.0.0) | Not applicable (zero extensions) |
| `htmx.makeFragment()` returns Element | Always returns DocumentFragment | June 2024 (2.0.0) | Not applicable (no JS API usage) |

**Deprecated/outdated:**
- `hx-on="event: code"` syntax: Removed in 2.0, replaced by `hx-on:event-name="code"`
- `hx-sse` / `hx-ws` attributes: Removed in 2.0, replaced by extension-based approach
- IE support: Dropped in 2.0

## Open Questions

1. **Pre-existing cross-browser failures**
   - What we know: CI only runs chromium; cross-browser is "local-only"
   - What's unclear: Whether firefox/webkit/mobile-chrome pass the current v1.3 baseline
   - Recommendation: Run cross-browser suite BEFORE htmx upgrade to establish baseline. Document any pre-existing failures with rationale per success criteria #5.

2. **Whether htmx is actually needed on these 2 pages**
   - What we know: Zero `hx-*` attributes, only event listeners in utils.js which fire on htmx lifecycle events
   - What's unclear: Whether removing htmx entirely would break anything (the event listeners would simply never fire)
   - Recommendation: Out of scope for this phase. Keep htmx loaded; the upgrade is the goal, not removal.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright 1.59.1 |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `PROJECT=chromium ./bin/test-e2e.sh` |
| Full suite command | `PROJECT=chromium ./bin/test-e2e.sh && PROJECT=firefox ./bin/test-e2e.sh && PROJECT=webkit ./bin/test-e2e.sh && PROJECT=mobile-chrome ./bin/test-e2e.sh` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| HTMX-01 | htmx 2.0.6 loaded, compat present | smoke | `grep -c 'version.*2.0.6\|2\.0\.6' public/assets/vendor/htmx.min.js` + manual check of compat script tag | N/A (file grep) |
| HTMX-02 | Zero old hx-on syntax | grep audit | `grep -rE 'hx-on="[^:]' public/*.html public/*.htmx.html` returns 0 | N/A (grep) |
| HTMX-03 | DELETE uses query params | unit/integration | Existing Playwright specs for delete operations | Existing specs cover delete flows |
| HTMX-04 | Extensions loaded individually | grep audit | `grep -r 'hx-ext=' public/*.html` audit | N/A (grep) |
| HTMX-05 | Full Playwright suite cross-browser | e2e | `./bin/test-e2e.sh` per browser project | 47 spec files exist |

### Sampling Rate
- **Per task commit:** `PROJECT=chromium ./bin/test-e2e.sh` (chromium smoke)
- **Per wave merge:** All 4 browser projects
- **Phase gate:** Full suite green across chromium+firefox+webkit+mobile-chrome

### Wave 0 Gaps
None -- existing Playwright infrastructure covers all phase requirements. The 47 existing spec files provide comprehensive coverage. No new test files needed.

## Sources

### Primary (HIGH confidence)
- [htmx migration guide](https://htmx.org/migration-guide-htmx-1/) - All breaking changes between 1.x and 2.x
- [htmx-1-compat extension](https://htmx.org/extensions/htmx-1-compat/) - What compat restores, installation, activation
- Codebase grep analysis - htmx usage patterns, DELETE handler audit

### Secondary (MEDIUM confidence)
- [htmx 2.0.0 release announcement](https://htmx.org/posts/2024-06-17-htmx-2-0-0-is-released/) - Release context and highlights
- [htmx-1-compat GitHub README](https://github.com/bigskysoftware/htmx-extensions/blob/main/src/htmx-1-compat/README.md) - Installation details

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - htmx 2.0.6 is stable (released June 2024), well-documented migration path
- Architecture: HIGH - Codebase grep reveals minimal htmx surface area (2 pages, 4 event listeners, 0 attributes)
- Pitfalls: HIGH - DELETE body/query param change is the only real breaking change affecting this codebase
- Cross-browser: MEDIUM - Pre-existing failures unknown until baseline run

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable library, migration path well-documented)
