# STACK Research — v1.4 Tech Debt Remediation

**Mode:** Ecosystem (subsequent milestone, targeted additions only)
**Confidence:** HIGH

## Executive Summary

**Core recommendation: v1.4 is a ZERO-NEW-DEPENDENCY milestone.** Every one of the 6 chantiers can be delivered with what's already in the stack plus one version upgrade (HTMX 1.x → 2.0.6). Adding libraries would contradict the tech debt goal.

The existing Playwright + axe-core audit loop already measures contrast, PHP's `random_bytes()` + a 40-line middleware already does CSP nonces, and CSS custom properties + `oklch()` is a native browser feature requiring no build step.

## Stack Changes Summary

| Chantier | Change | Type | Risk |
|---|---|---|---|
| 1. Contrast remediation | CSS `oklch()` + `color-mix()` (native, no lib) | Native CSS | LOW |
| 2. OVERLAY-HITTEST sweep | None | — | NONE |
| 3. TRUST-DEPLOY fixtures | Reuse existing Playwright `loginAs*` helpers | — | NONE |
| 4. CSP-INLINE-THEME | New `CspNonceMiddleware.php` or extend `SecurityProvider` (~40 lines) | New file | LOW |
| 5. HTMX 2.0 upgrade | `htmx.org` 1.x → **2.0.6** (latest stable) | Version bump | **MEDIUM** |
| 6. Controller splits | None (pure refactor) | — | NONE |

## Detailed Recommendations

### 1. Contrast Remediation — Native CSS only

**Don't add:** `culori`, `chroma-js`, `@csstools/postcss-oklab-function`, PostCSS, `style-dictionary`, Tailwind. The project has no build pipeline beyond minification.

**Use what's already there:**
- **axe-core 4.10** (via `@axe-core/playwright`) — produces `v1.3-CONTRAST-AUDIT.json`
- **Native `oklch()` CSS function** — baseline since Chrome 111 / Firefox 113 / Safari 15.4 (Q1 2023)
- **Native `color-mix(in oklch, ...)`** — baseline since Chrome 111 / Firefox 113 / Safari 16.2

**Rationale:** v1.3 A11Y report identifies 6 token pairs causing ~71% of 316 failures. Fix is **3-4 token value changes** — not tooling. Report already specifies: "Relever `#988d7a` (muted-foreground) vers un ratio ≥ 4.5 sur `#f6f5f0`. Un L* autour de 45-48 en oklch rapproche du seuil."

**Verification loop (no new tools):**
```bash
bin/test-e2e.sh specs/contrast-audit.spec.js  # existing runner, CONTRAST_AUDIT=1
# Diff v1.3-CONTRAST-AUDIT.json vs prior snapshot
```

### 2. V2-OVERLAY-HITTEST Sweep — No tooling

Pure grep + manual audit. Pattern: `[hidden]` attribute overridden by `position: fixed; display: flex`. Fix is a single global CSS rule (`[hidden] { display: none !important }`) plus a codemod-style search for `display: flex` on elements that also receive `[hidden]`.

### 3. V2-TRUST-DEPLOY — Reuse existing fixtures

Existing `tests/e2e/helpers/` already provides `loginAsAdmin`, `loginAsOperator`, `loginAsVoter`. Task: add sibling helpers `loginAsAuditor` and `loginAsAssessor` following the exact pattern.

**DO NOT bump Playwright** — v1.3 cross-browser matrix was captured against 1.59.1 and bumping invalidates the baseline.

### 4. V2-CSP-INLINE-THEME — Write middleware, don't install one

**Reject Spatie `laravel-csp`** and any Composer package. Need is ~40 lines plus template helper.

**Approach:**
1. **Nonce injection:** extend `SecurityProvider::headers()` (runs before router; middleware runs after and is bypassed by HTML endpoints — confirmed by ARCHITECTURE research)
2. **Template helper:** `HtmlView::render()` exposes `$cspNonce` in template context, or global `csp_nonce()` helper
3. **Theme init scripts:** `<script nonce="<?= csp_nonce() ?>">`
4. **CSP header:** `script-src 'self' 'nonce-{NONCE}' 'strict-dynamic'`

**Pitfall — HTMX `hx-on:*`:** These execute inline event code and are governed by `script-src-attr` / `unsafe-hashes`, not nonces. Recommendation: `strict-dynamic` for `script-src` + `unsafe-hashes` for `script-src-attr`. Refactoring every `hx-on` defeats HTMX's locality-of-behavior principle.

### 5. HTMX 2.0 Upgrade — The only real version bump

**Target version:** `htmx.org@2.0.6` (verify at phase start).

**Breaking changes (verified against official migration guide):**

| Change | Impact | Action |
|---|---|---|
| **`hx-on` syntax** — kebab-case `hx-on:event-name` | Every `hx-on="htmx:afterRequest: ..."` → `hx-on:htmx:after-request="..."` | Grep + rewrite sweep |
| **DELETE uses URL params** (not form-encoded body) | `hx-delete` endpoints reading `$_POST` will break silently | Audit all handlers; transition via `methodsThatUseUrlParams` |
| **Cross-domain restricted by default** | Low (single-origin) | Smoke test |
| **Extensions unbundled** | Check SSE / preload refs | Load extensions separately |
| **`htmx:beforeRequest` → `htmx:configRequest`** | Any listener renamed | Grep + rewrite |

**Safety net:** `htmx-1-compat` extension for gradual migration.

**Risk: MEDIUM.** `hx-on` rewrite is mechanical but high-volume. DELETE change is a **silent behavioral break**. Dedicated phase needed:
1. Full grep inventory (`hx-on`, `hx-delete`, `htmx:beforeRequest`, extensions)
2. Sweep rewrite
3. Full Playwright run (chromium + firefox + webkit + mobile) against v1.3 baseline
4. Manual smoke of every `hx-delete` endpoint

**Sanity:** v1.3 A11Y and keyboard baselines captured with HTMX 1.x — re-run post-upgrade.

### 6. Controller Refactoring — Pure PHP refactor

Targets:
- `MeetingsController` 687
- `MeetingWorkflowController` 559
- `OperatorController` 516
- `AdminController` 510

**Reuse v1.0 playbook:** ImportController 687 → 149 + ImportService with constructor DI nullable.

**Pitfall (v1.0 Key Decision):** "Existing tests assert private method existence, making splits disruptive." Audit test files for reflection usage and rewrite through public entry points first.

## Explicit "Do NOT Add" List

| Package | Why rejected |
|---|---|
| `spatie/laravel-csp` | Laravel-only |
| `paragonie/csp-builder` | 40 lines of `random_bytes` + header is clearer |
| `culori` / `chroma-js` | Native CSS suffices |
| `style-dictionary` / PostCSS | No build pipeline |
| `htmx.org@4.x` alpha | Pre-release; double migration pain |
| Playwright 1.60+ | Invalidates v1.3 cross-browser baseline |

## Integration Points

- **CSP nonce** plugs into `SecurityProvider::headers()`
- **HTMX 2.0** coordinated changes across `public/*.htmx.html`, `public/assets/js/`, PHP `hx-delete` handlers, `htmx.config`, CSP nonce on htmx script tag
- **Contrast tokens** — values change only in existing design-token CSS
- **Controller splits** use existing `RepositoryFactory` DI + `AbstractController` base
- **Playwright fixtures** extend `tests/e2e/helpers/`

## Confidence Assessment

| Area | Confidence |
|---|---|
| HTMX 2.0 breaking changes | HIGH (official migration guide) |
| Native OKLCH/color-mix support | HIGH (baseline since 2023) |
| CSP nonce middleware pattern | HIGH (OWASP + MDN idiom) |
| HTMX + strict CSP empirical behavior | MEDIUM (not tested in-repo) |
| "No new packages needed" | HIGH |

## Open Questions for Phase Research

1. Complete `hx-on` grep inventory
2. `hx-delete` body-reading audit (silent-break risk)
3. HTMX extension inventory (SSE, preload, response-targets?)
4. `strict-dynamic` + `unsafe-hashes` empirical test across browser matrix
5. Dark mode contrast re-audit post token shifts

## Sources

- [HTMX 1.x → 2.x Migration Guide](https://htmx.org/migration-guide-htmx-1/)
- [htmx-1-compat extension](https://htmx.org/extensions/htmx-1-compat/)
- [OWASP CSP Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [MDN CSP Reference](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CSP)
- [WebAIM Contrast and Color Accessibility](https://webaim.org/articles/contrast/)
