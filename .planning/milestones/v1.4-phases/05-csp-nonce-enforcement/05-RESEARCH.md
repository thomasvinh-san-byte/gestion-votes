# Phase 5: CSP Nonce Enforcement - Research

**Researched:** 2026-04-10
**Domain:** Content Security Policy nonce injection for PHP+nginx static HTML architecture
**Confidence:** HIGH

## Summary

Phase 5 must implement CSP nonce enforcement across an application with two distinct page-serving mechanisms: (1) PHP-rendered templates via `HtmlView::render()` (account, setup, reset-password, vote, doc pages), and (2) static `.htmx.html` files served directly by nginx (21 page shells + `login.html`). The requirements explicitly call for nonces in the `.htmx.html` files (`nonce="<?= $cspNonce ?>"`), which means these files must be routed through PHP rather than served as static files.

The current `SecurityProvider::headers()` already emits `script-src 'self'` (no `unsafe-inline`), and `style-src 'self' 'unsafe-inline'`. The nginx config also emits its own CSP header independently. The primary challenge is: (a) converting static .htmx.html serving to PHP-rendered serving so nonces can be injected, (b) removing the duplicate CSP header from nginx for PHP-served pages, and (c) handling the only inline `<script>` (in `public.htmx.html`) plus 3 inline scripts in PHP templates.

**Primary recommendation:** Route .htmx.html pages through PHP front controller via a new `PageController` that reads the static file, injects the nonce, and outputs HTML. This preserves existing file structure while enabling per-request nonce injection. The inline `<script>` in `public.htmx.html` should be externalized to a `.js` file to eliminate the need for script-src nonce on static pages entirely.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CSP-01 | `SecurityProvider::nonce()` generates nonce per request via `random_bytes(16)`, injected into CSP header | Pattern 1 below: static request-scoped accessor on SecurityProvider, generated in `headers()` before router dispatch |
| CSP-02 | `HtmlView::render()` exposes `$cspNonce`; all inline `<script>` and `<style>` in 22 .htmx.html carry nonce | Pattern 2+3 below: PageController routes .htmx.html through PHP; HtmlView auto-injects nonce |
| CSP-03 | CSP `script-src` uses `'nonce-{NONCE}' 'strict-dynamic'`; `'unsafe-inline'` removed | Pattern 1 CSP header construction; nginx CSP header deduplication |
| CSP-04 | CSP in report-only for >=1 phase; Playwright spec validates zero console CSP violations on all pages | Validation Architecture section; report-only header swap strategy |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP `random_bytes()` | 8.4 built-in | CSPRNG nonce generation | OWASP-recommended, no external dependency |
| `bin2hex()` | 8.4 built-in | Base16-encode 16 bytes to 32-char nonce | Standard encoding for CSP nonces |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Playwright `page.on('console')` | 1.59.1 (existing) | Listen for CSP violation console messages | CSP validation spec |
| Playwright `page.on('pageerror')` | 1.59.1 (existing) | Catch script errors from blocked scripts | CSP validation spec |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Nonces | Hash-based CSP (`'sha256-...'`) | Hashes work for truly static content but break if any byte changes; nonces are more maintainable and required by CSP-01 |
| PHP page serving | nginx njs/Lua module for nonce | Adds operational complexity; PHP is already in the stack |
| `paragonie/csp-builder` | Composer package | 40 lines of `random_bytes` + header is clearer; zero-dependency milestone |

**Installation:**
No new packages needed. Zero-dependency implementation.

## Architecture Patterns

### Critical Architecture Discovery: Two Serving Paths

The codebase has two fundamentally different page-serving mechanisms:

**Path A — PHP-rendered pages (5 controllers):**
- `AccountController` -> `HtmlView::render('account_form', ...)`
- `SetupController` -> `HtmlView::render('setup_form', ...)`
- `PasswordResetController` -> `HtmlView::render('reset_*', ...)`
- `VotePublicController` -> `HtmlView::render('vote_*', ...)`
- `DocController` -> `HtmlView::render('doc_page', ...)`
- These pass through PHP front controller -> router -> controller -> template
- Nonce injection via `$cspNonce` in template context is straightforward

**Path B — Static .htmx.html files (21 files + login.html):**
- Served directly by nginx: `location = /dashboard { try_files /dashboard.htmx.html =404; }`
- NEVER pass through PHP
- Cannot inject PHP variables without architectural change

**Required change:** Route page requests to PHP front controller instead of serving static files. This is the ONLY way to satisfy CSP-02 (nonce on every .htmx.html).

### Recommended Project Structure

```
app/
  Core/Providers/
    SecurityProvider.php       # MODIFIED: add nonce() + update CSP header
  View/
    HtmlView.php               # MODIFIED: inject $cspNonce in render()
  Controller/
    PageController.php         # NEW: serves .htmx.html through PHP with nonce
  Templates/
    *.php                      # MODIFIED: add nonce= to inline <script>/<style>
public/
  *.htmx.html                 # MODIFIED: add nonce="<?= $cspNonce ?>" placeholders
  assets/js/
    public-theme-force.js      # NEW: externalized from public.htmx.html inline script
deploy/
  nginx.conf                   # MODIFIED: route page URLs to PHP front controller
  nginx.conf.template          # MODIFIED: same changes
tests/
  Unit/
    SecurityProviderTest.php   # NEW: nonce generation + CSP header tests
  e2e/specs/
    csp-enforcement.spec.js    # NEW: Playwright CSP violation checker
```

### Pattern 1: SecurityProvider Nonce (request-scoped static)

**What:** Static property + lazy accessor generates nonce once per request.
**When to use:** Every request that emits a CSP header.
**Confidence:** HIGH (matches existing patterns: `Logger::getRequestId()`, `AuthMiddleware::getCurrentUserId()`)

```php
// Source: OWASP CSP Cheat Sheet + existing codebase convention
final class SecurityProvider {
    private static ?string $nonce = null;

    /**
     * Get or generate the per-request CSP nonce (32 hex chars from 16 random bytes).
     */
    public static function nonce(): string {
        return self::$nonce ??= bin2hex(random_bytes(16));
    }

    /**
     * Reset nonce between requests (for testing only).
     */
    public static function resetNonce(): void {
        self::$nonce = null;
    }

    public static function headers(): void {
        if (headers_sent()) {
            return;
        }
        // ... existing headers ...

        $nonce = self::nonce();
        // Start with report-only for CSP-04 safety
        header("Content-Security-Policy-Report-Only: default-src 'self'; "
            . "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'; "
            . "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com; "
            . "img-src 'self' data: blob:; font-src 'self' https://fonts.gstatic.com; "
            . "connect-src 'self' ws: wss:; frame-ancestors 'self'; form-action 'self'");
    }
}
```

### Pattern 2: PageController for .htmx.html files

**What:** A PHP controller that reads static .htmx.html files, injects nonce, and serves as HTML.
**When to use:** Every page URL (`/dashboard`, `/wizard`, etc.)
**Confidence:** HIGH

```php
// Source: codebase convention (controllers + HtmlView pattern)
final class PageController {
    /**
     * Serve an .htmx.html file with CSP nonce injection.
     */
    public static function serve(string $page): void {
        $file = dirname(__DIR__, 2) . '/public/' . $page . '.htmx.html';
        if (!file_exists($file)) {
            http_response_code(404);
            return;
        }

        $cspNonce = SecurityProvider::nonce();
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        // Read file and inject nonce into placeholders
        $html = file_get_contents($file);
        $html = str_replace('%%CSP_NONCE%%', $cspNonce, $html);
        echo $html;
    }
}
```

The `.htmx.html` files would use `%%CSP_NONCE%%` as a placeholder:
```html
<style id="critical-tokens" nonce="%%CSP_NONCE%%">
```

**Alternative considered:** Using `<?= $cspNonce ?>` and `include` -- but this requires renaming files to `.php` and breaks the current naming convention. The placeholder approach preserves `.htmx.html` extension and is simpler.

### Pattern 3: HtmlView nonce auto-injection

**What:** `HtmlView::render()` automatically includes `$cspNonce` in template context.
**When to use:** All PHP-rendered templates (vote, setup, reset, account, doc).

```php
public static function render(string $template, array $data = [], int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: text/html; charset=utf-8');
    $data['cspNonce'] = SecurityProvider::nonce();
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/Templates/' . $template . '.php';
}
```

Templates use: `<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES) ?>">`.

### Pattern 4: Nginx CSP header deduplication

**What:** Remove CSP header from nginx for PHP-served responses to avoid double headers.
**When to use:** All locations that route to PHP.
**Confidence:** HIGH

Currently nginx sets its own CSP header AND PHP sets one via `SecurityProvider::headers()`. With nonces, the nginx CSP (which has no nonce) would conflict. Two approaches:

**Approach A (recommended):** Remove CSP from nginx entirely for PHP-served routes. PHP owns the CSP header. Nginx retains CSP only for truly static assets (CSS, JS, images) where no inline content exists.

**Approach B:** Use `fastcgi_hide_header Content-Security-Policy` in nginx PHP locations and let PHP's header through. But this is fragile.

The simplest: change nginx's `add_header Content-Security-Policy` in the server block to NOT include script-src restrictions for page locations that go through PHP. Or better: remove CSP from nginx server-level and only add it in the static asset location.

### Pattern 5: Report-Only to Enforcement transition

**What:** Start with `Content-Security-Policy-Report-Only` header, flip to `Content-Security-Policy` after validation.
**When to use:** CSP-04 requires report-only for >=1 phase.

```php
// In SecurityProvider::headers():
// Phase 5 Wave 1: report-only
$headerName = 'Content-Security-Policy-Report-Only';
// Phase 6 (or later): flip to enforcement
// $headerName = 'Content-Security-Policy';

header("{$headerName}: default-src 'self'; "
    . "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'; "
    . "...");
```

Keep the EXISTING `Content-Security-Policy: ... script-src 'self' ...` header alongside the report-only header during Phase 5. This provides defense-in-depth: the existing CSP enforces basic protections while the nonce-based CSP is validated in report-only mode.

### Anti-Patterns to Avoid

- **Putting nonce in middleware:** Middleware runs after routing and is API-only. SecurityProvider runs before routing in Application::boot(). Nonce MUST be in SecurityProvider.
- **Hash-based CSP for .htmx.html inline styles:** Hashes break on any byte change. Token value changes (common in this project) would silently break pages. Nonces are maintainable.
- **Removing the existing CSP header immediately:** Keep `script-src 'self'` enforcing while nonce-based CSP runs in report-only. Only remove the old header when flipping to enforcement.
- **Using `'unsafe-inline'` as fallback:** With nonces present, browsers that support nonces ignore `'unsafe-inline'`. But adding it gives false confidence and defeats the purpose for older browsers.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Nonce generation | Custom PRNG | `random_bytes(16)` + `bin2hex()` | CSPRNG, OWASP-blessed, 1 line |
| CSP header construction | String concatenation from config | Inline string in SecurityProvider | Only 1 CSP policy to manage; no need for a builder |
| Nonce propagation to templates | Global variable / $_SERVER | Static accessor `SecurityProvider::nonce()` | Matches codebase convention, testable |

## Common Pitfalls

### Pitfall 1: Duplicate CSP headers (nginx + PHP)
**What goes wrong:** Browser receives TWO CSP headers with different policies. Browser enforces the MOST RESTRICTIVE combination -- if nginx says `script-src 'self'` (no nonce) and PHP says `script-src 'nonce-xxx'`, the browser blocks scripts that don't match BOTH.
**Why it happens:** nginx `add_header` runs independently of PHP `header()`.
**How to avoid:** Remove CSP from nginx for PHP-served locations. Only keep nginx CSP for truly static asset locations.
**Warning signs:** Scripts blocked despite correct nonce in PHP CSP header.

### Pitfall 2: `strict-dynamic` propagation breaks external CDN scripts
**What goes wrong:** `strict-dynamic` means only nonce-bearing scripts and scripts they load are trusted. If a nonce-bearing script dynamically creates a `<script>` tag, that's allowed. But static `<script src="cdn.example.com">` WITHOUT a nonce is blocked.
**Why it happens:** `strict-dynamic` overrides host allowlists in `script-src`.
**How to avoid:** All external scripts (`theme-init.js`, `utils.js`, `shell.js`, etc.) are loaded via `<script src="...">` tags in the HTML. With `strict-dynamic`, these MUST also carry the nonce attribute, OR be loaded by a nonce-bearing script. Since all scripts are `'self'` origin and the nonce policy includes `'self'`, this works. BUT: Google Fonts CSS loads via `<link>` not `<script>`, so `strict-dynamic` does not affect it.
**Warning signs:** External scripts from CDN suddenly blocked.

### Pitfall 3: Inline `<style>` and `style-src` nonce
**What goes wrong:** `style-src` with nonce means ALL inline `<style>` elements need nonces. Missing one = that style block silently ignored = broken layout.
**Why it happens:** 22 files have `<style id="critical-tokens">` blocks. Miss one = FOUC on that page.
**How to avoid:** Grep verification: `grep -rn '<style' public/*.htmx.html | grep -v 'nonce='` must return 0 results after migration.
**Warning signs:** Flash of unstyled content on specific pages.

### Pitfall 4: The `public.htmx.html` inline script
**What goes wrong:** `public.htmx.html` has 1 inline `<script>` that forces dark theme. Under `script-src 'nonce-...'`, this script needs a nonce or must be externalized.
**Why it happens:** This was a quick DISP-01 fix. The test already documents it may be CSP-blocked in Docker.
**How to avoid:** Externalize to `/assets/js/public-theme-force.js`. Three lines of code. Eliminates the only inline script in .htmx.html files.
**Warning signs:** public.htmx.html projection screen shows light theme instead of forced dark.

### Pitfall 5: PHP templates with inline scripts
**What goes wrong:** Three PHP templates have inline `<script>` blocks (reset_newpassword_form.php, doc_page.php, setup_form.php). These need `nonce="<?= htmlspecialchars($cspNonce) ?>"`.
**Why it happens:** HtmlView::render() already passes through PHP, so nonce injection is straightforward. But forgetting one template = broken page.
**How to avoid:** Grep all `<script>` and `<style>` tags in `app/Templates/*.php` that lack nonce attribute.

### Pitfall 6: Nonce not available for login.html
**What goes wrong:** `login.html` is served as a static file by nginx (`location = /login { try_files /login.html =404; }`). It has no inline scripts (all external), but has no inline styles either. It does NOT have a `<style id="critical-tokens">` block.
**Why it happens:** login.html is not an .htmx.html file.
**How to avoid:** login.html has zero inline content -- no nonce needed. Verify with grep.

### Pitfall 7: `resetNonce()` for test isolation
**What goes wrong:** Unit tests that call `SecurityProvider::headers()` twice get the same nonce (static property persists across test methods in the same process).
**Why it happens:** Static property not reset between tests.
**How to avoid:** Add `SecurityProvider::resetNonce()` method. Call in test `tearDown()`.

## Code Examples

### Nonce generation and CSP header
```php
// SecurityProvider.php - nonce accessor
public static function nonce(): string {
    return self::$nonce ??= bin2hex(random_bytes(16));
}
```

### .htmx.html nonce placeholder
```html
<!-- Before -->
<style id="critical-tokens">
  :root { --color-bg: oklch(0.922 0.013 95); ... }
</style>

<!-- After -->
<style id="critical-tokens" nonce="%%CSP_NONCE%%">
  :root { --color-bg: oklch(0.922 0.013 95); ... }
</style>
```

### PHP template nonce injection
```php
<!-- In app/Templates/setup_form.php -->
<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
  // Password eye toggles
  function initEyeToggle(inputId, btnId) { ... }
</script>
```

### Nginx routing change
```nginx
# BEFORE: static file serving
location = /dashboard { try_files /dashboard.htmx.html =404; }

# AFTER: route to PHP front controller
location = /dashboard { try_files $uri /index.php$is_args$args; }
```

### Router page route registration
```php
// In app/routes.php
$router->map('GET', '/dashboard', [PageController::class, 'dashboard']);
$router->map('GET', '/wizard', [PageController::class, 'wizard']);
// ... one route per page
```

### Playwright CSP violation checker
```javascript
// Source: Playwright docs + CSP spec
test('zero CSP violations across all pages', async ({ page }) => {
  const violations = [];

  page.on('console', msg => {
    if (msg.text().includes('[Report Only]') || msg.text().includes('Content Security Policy')) {
      violations.push({ url: page.url(), message: msg.text() });
    }
  });

  page.on('pageerror', error => {
    violations.push({ url: page.url(), error: error.message });
  });

  // Navigate to each page...
  for (const pageUrl of PAGES) {
    await page.goto(pageUrl);
    await page.waitForLoadState('networkidle');
  }

  expect(violations).toEqual([]);
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `'unsafe-inline'` in script-src | Nonce-based CSP with `'strict-dynamic'` | CSP Level 3 (2018+) | Eliminates XSS via inline script injection |
| Hash-based CSP for static content | Nonce + `strict-dynamic` (preferred) | CSP Level 3 | More maintainable; hashes break on content changes |
| Single CSP header | Report-only alongside enforcing | CSP Level 2 | Safe rollout of policy changes |

## Open Questions

1. **Performance impact of PHP-serving .htmx.html**
   - What we know: Each page request now passes through PHP-FPM instead of nginx static serving. Adds ~5-10ms per page load.
   - What's unclear: Whether opcache file cache (`file_get_contents` result) mitigates this sufficiently.
   - Recommendation: Accept the overhead. Security > microsecond optimization. PHP is already handling all API requests; 21 additional routes is negligible.

2. **nginx CSP for static assets**
   - What we know: Static assets (JS, CSS, images) are served by nginx with their own CSP. These don't need nonces (no inline content).
   - What's unclear: Whether the static asset CSP should match the PHP CSP exactly minus nonces.
   - Recommendation: Keep a simple static asset CSP: `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'`.

3. **`strict-dynamic` with `'self'`**
   - What we know: `strict-dynamic` causes browsers to ignore `'self'` and host-based allowlists in `script-src` (CSP Level 3 spec).
   - What's unclear: Whether this affects external `<script src="/assets/js/...">` tags that carry the nonce.
   - Recommendation: Every `<script>` tag that loads a file must also carry `nonce="%%CSP_NONCE%%"`. The nonce allows the script; `strict-dynamic` allows scripts that IT loads dynamically. This is the correct pattern.

## Inventory of Inline Content Requiring Nonces

### .htmx.html files (21 files)
| Type | Count | Files | Content |
|------|-------|-------|---------|
| `<style id="critical-tokens">` | 21 | All .htmx.html files | CSS custom property declarations (identical content) |
| `<script>` (inline, no src) | 1 | public.htmx.html only | Dark theme force (3 lines) -- EXTERNALIZE |

### PHP templates (14 files)
| Type | Count | Files | Content |
|------|-------|-------|---------|
| `<script>` (inline) | 3 | reset_newpassword_form.php, doc_page.php, setup_form.php | Eye toggles, smooth scroll, setup validation |
| `<style>` (inline) | 2 | account_form.php, vote_form.php | Page-specific styles |

### Other static HTML
| Type | Count | Files | Content |
|------|-------|-------|---------|
| login.html | 0 inline | login.html | All scripts are external src= |

**Total nonce injection points:** 21 style + 0 script (after externalization) in .htmx.html, 5 inline blocks in PHP templates = **26 injection points**.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework (unit) | PHPUnit ^10.5 |
| Config file | phpunit.xml |
| Quick run command | `timeout 60 php vendor/bin/phpunit tests/Unit/SecurityProviderTest.php --no-coverage` |
| Full suite command | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| Framework (e2e) | Playwright 1.59.1 |
| E2E run command | `npx playwright test tests/e2e/specs/csp-enforcement.spec.js` |

### Phase Requirements to Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CSP-01 | SecurityProvider::nonce() generates 32-char hex, is request-scoped, appears in CSP header | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/SecurityProviderTest.php --no-coverage` | No -- Wave 0 |
| CSP-02 | All inline script/style in .htmx.html carry nonce placeholder; HtmlView injects nonce | unit + grep | `grep -rn '<style\|<script' public/*.htmx.html \| grep -v 'nonce=\|src='` returns 0 | No -- manual verification |
| CSP-03 | CSP header contains nonce + strict-dynamic, no unsafe-inline in script-src | unit + e2e | `timeout 60 php vendor/bin/phpunit tests/Unit/SecurityProviderTest.php --no-coverage` | No -- Wave 0 |
| CSP-04 | Report-only header; Playwright zero CSP violations on all 22 pages | e2e | `npx playwright test tests/e2e/specs/csp-enforcement.spec.js` | No -- Wave 0 |

### Sampling Rate
- **Per task commit:** `timeout 60 php vendor/bin/phpunit tests/Unit/SecurityProviderTest.php --no-coverage`
- **Per wave merge:** Full PHPUnit suite + csp-enforcement.spec.js
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Unit/SecurityProviderTest.php` -- covers CSP-01, CSP-03
- [ ] `tests/e2e/specs/csp-enforcement.spec.js` -- covers CSP-04
- [ ] `app/Controller/PageController.php` -- new controller for serving .htmx.html through PHP

## Sources

### Primary (HIGH confidence)
- `/home/user/gestion_votes_php/app/Core/Providers/SecurityProvider.php` -- current CSP implementation, header lifecycle
- `/home/user/gestion_votes_php/app/View/HtmlView.php` -- current render method signature
- `/home/user/gestion_votes_php/deploy/nginx.conf` -- static file serving config, duplicate CSP headers
- `/home/user/gestion_votes_php/public/index.php` -- front controller dispatch flow
- `/home/user/gestion_votes_php/app/Core/Application.php` -- boot sequence, SecurityProvider::headers() call location
- Codebase grep: 168 `<script>` tags across 21 .htmx.html files (all `src=` external except 1 in public.htmx.html)
- Codebase grep: 22 `<style>` tags across 21 .htmx.html files (all `id="critical-tokens"`, identical content)

### Secondary (MEDIUM confidence)
- `.planning/research/ARCHITECTURE.md` -- prior v1.4 research confirming SecurityProvider placement
- `.planning/research/STACK.md` -- prior research confirming zero-dependency approach
- [OWASP CSP Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [MDN CSP script-src](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/script-src)

### Tertiary (LOW confidence)
- `strict-dynamic` interaction with `'self'` -- MDN documents this but browser implementation nuances may vary. Needs empirical testing in Phase 5 e2e spec.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH -- `random_bytes()` and CSP nonce pattern are OWASP-standard, no dependencies
- Architecture: HIGH -- codebase inspection reveals exact serving paths, all files inventoried
- Pitfalls: HIGH -- duplicate CSP header and static-vs-PHP serving are empirically verified in codebase
- Nonce+strict-dynamic interaction: MEDIUM -- well-documented spec but needs empirical validation

**Research date:** 2026-04-10
**Valid until:** 2026-05-10 (stable domain, no fast-moving dependencies)
