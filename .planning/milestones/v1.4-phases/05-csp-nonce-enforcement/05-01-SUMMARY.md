---
phase: 05-csp-nonce-enforcement
plan: 01
subsystem: security
tags: [csp, nonce, security-headers, nginx, htmx, php-templates]

# Dependency graph
requires:
  - phase: 04-htmx-upgrade
    provides: "hx-on:* migrated from inline hx-on — safe for strict CSP"
provides:
  - "SecurityProvider::nonce() — per-request 32-char hex nonce accessor"
  - "PageController — serves .htmx.html through PHP with %%CSP_NONCE%% replacement"
  - "HtmlView auto-injects $cspNonce into all PHP template scope"
  - "Nginx routes all page URLs to PHP front controller"
  - "All inline content carries nonce placeholders"
  - "public-theme-force.js — externalized dark theme script"
affects: [05-02-csp-header-enforcement, security-headers]

# Tech tracking
tech-stack:
  added: []
  patterns: ["%%CSP_NONCE%% placeholder in .htmx.html for server-side nonce injection", "PageController static file serving with string replacement", "HtmlView auto-injects security context into template scope"]

key-files:
  created:
    - app/Controller/PageController.php
    - public/assets/js/public-theme-force.js
    - tests/Unit/SecurityProviderTest.php
  modified:
    - app/Core/Providers/SecurityProvider.php
    - app/View/HtmlView.php
    - app/routes.php
    - deploy/nginx.conf
    - deploy/nginx.conf.template
    - "21 public/*.htmx.html files"
    - "12 app/Templates/*.php files"

key-decisions:
  - "PageController uses serveFromUri() pattern extracting page from REQUEST_URI — Router map() does not support extra params"
  - "/vote route split: POST for VotePublicController (form submission), GET for PageController (page shell)"
  - "%%CSP_NONCE%% placeholder chosen over PHP <?= ?> to preserve .htmx.html extension and structure"
  - "All <script src> tags also carry nonce for strict-dynamic compatibility"

patterns-established:
  - "%%CSP_NONCE%% placeholder: all .htmx.html inline content uses this placeholder, replaced at serve time by PageController"
  - "PHP template nonce: <?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?> pattern for all inline script/style"
  - "Page shell route pattern: nginx try_files $uri /index.php$is_args$args for all page URLs"

requirements-completed: [CSP-01, CSP-02]

# Metrics
duration: 6min
completed: 2026-04-10
---

# Phase 5 Plan 01: CSP Nonce Infrastructure Summary

**SecurityProvider::nonce() accessor + PageController PHP serving for 21 .htmx.html files + nonce placeholders on all 168 script/style tags across 33 files**

## Performance

- **Duration:** 6 min
- **Started:** 2026-04-10T08:58:56Z
- **Completed:** 2026-04-10T09:05:19Z
- **Tasks:** 2
- **Files modified:** 41

## Accomplishments
- SecurityProvider::nonce() generates request-scoped 32-char hex nonce via random_bytes(16) with resetNonce() for test isolation
- PageController serves all 21 .htmx.html page shells through PHP, replacing %%CSP_NONCE%% placeholders with real nonce
- HtmlView::render() auto-injects $cspNonce into template scope for all PHP-rendered pages
- Nginx routing changed from static file serving to PHP front controller for all 21 page URLs + UUID-path patterns
- Zero inline `<style>` or `<script>` tags without nonce attribute across entire codebase (htmx + PHP templates)
- public.htmx.html inline dark-theme script externalized to public-theme-force.js
- 4 unit tests for nonce generation, idempotency, and reset

## Task Commits

Each task was committed atomically:

1. **Task 1: SecurityProvider nonce + PageController + HtmlView + nginx routing + unit tests** - `19ff4291` (feat)
2. **Task 2: Nonce placeholders on all inline content + externalize public script** - `7f3bcd63` (feat)

## Files Created/Modified
- `app/Core/Providers/SecurityProvider.php` - Added nonce() and resetNonce() static methods
- `app/Controller/PageController.php` - New controller serving .htmx.html through PHP with nonce injection
- `app/View/HtmlView.php` - Auto-injects $cspNonce into template data before extract()
- `app/routes.php` - PageController routes for 21 pages, /vote split to POST-only for VotePublicController
- `deploy/nginx.conf` - All page locations route to PHP front controller
- `deploy/nginx.conf.template` - Same nginx changes for template
- `tests/Unit/SecurityProviderTest.php` - 4 tests for nonce generation
- `public/assets/js/public-theme-force.js` - Externalized dark theme force script
- `public/*.htmx.html` (21 files) - nonce="%%CSP_NONCE%%" on all style and script tags
- `app/Templates/*.php` (12 files) - nonce on all inline script, style, and script src tags

## Decisions Made
- PageController uses `serveFromUri()` extracting page name from URI because Router::map() does not support passing extra parameters
- `/vote` route split into POST (VotePublicController) and GET (PageController) since vote form submission is POST-only
- `%%CSP_NONCE%%` placeholder pattern chosen over PHP `<?=?>` to preserve .htmx.html file extension and authoring workflow
- All `<script src=...>` tags carry nonce too (not just inline) for strict-dynamic CSP compatibility where browsers ignore 'self'

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added nonce to all <script src> tags in PHP templates**
- **Found during:** Task 2 (nonce placeholders)
- **Issue:** Plan mentioned adding nonces to inline scripts in 3 PHP templates but 12 template files have <script src> tags needing nonces for strict-dynamic
- **Fix:** Applied nonce to all <script src> and <script type="module" src> tags across all 12 PHP template files
- **Files modified:** All app/Templates/*.php files with script tags
- **Verification:** `grep -rn '<script src=' app/Templates/*.php | grep -v 'nonce=' | wc -l` returns 0

---

**Total deviations:** 1 auto-fixed (1 missing critical)
**Impact on plan:** Essential for strict-dynamic CSP — without nonces on script src tags, browsers would block all external scripts when strict-dynamic is active.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All nonce infrastructure is in place; Plan 02 can upgrade the CSP header to use nonce-based policy
- SecurityProvider::headers() still emits the original CSP header; Plan 02 will add the nonce-based Content-Security-Policy-Report-Only header
- Nginx still emits its own CSP header at server level; Plan 02 should address the duplicate header issue for PHP-served pages

---
*Phase: 05-csp-nonce-enforcement*
*Completed: 2026-04-10*
