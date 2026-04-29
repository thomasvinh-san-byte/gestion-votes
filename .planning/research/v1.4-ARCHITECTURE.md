# Architecture Patterns — v1.4 Tech Debt Remediation

**Domain:** Integrate 6 chantiers into existing PHP 8.4 custom-MVC + Shadow DOM design system
**Researched:** 2026-04-09
**Confidence:** HIGH (existing codebase inspection) / MEDIUM (HTMX 2.0 interactions)

## Recommended Architecture

The 6 chantiers touch three architectural planes. Keeping them separated during build avoids cross-chantier blast radius:

```
Plane A — Design tokens (CSS / Shadow DOM)
    Chantier 1 (Contrast)
    Chantier 4 (CSP inline theme)   <-- touches <style id="critical-tokens"> in 22 HTML files
    Chantier 5 (HTMX 2.0)           <-- no token coupling, but shares HTML files with #4

Plane B — PHP HTTP layer
    Chantier 4 (CSP nonce injection)  <-- SecurityProvider::headers() + HtmlView::render()
    Chantier 6 (Controller splits)    <-- AbstractController + RepositoryFactory + routes.php

Plane C — Test harness (Playwright + JS runtime)
    Chantier 2 (V2-OVERLAY-HITTEST)  <-- CSS audit + spec per overlay
    Chantier 3 (V2-TRUST-DEPLOY)     <-- tests/e2e/fixtures/ auditor + assessor
    Chantier 5 (HTMX 2.0)            <-- hx-on rewrites touched by specs
```

### Component Boundaries (files touched per chantier)

| Chantier | Plane | Files modified (primary) | Indirect / read-only |
|----------|-------|--------------------------|----------------------|
| 1. Contrast | A | `design-system.css` @layer base tokens (5258 LOC), `public/*.htmx.html` critical-tokens (22 files), dark-mode block | 23 Web Components (token consumers), per-page CSS (~15 hex refs) |
| 2. Overlay-hittest | C | per-page CSS (`operator.css`, `meetings.css`, …), possibly `ag-modal` | axe specs, keyboard-nav.spec.js |
| 3. Trust-deploy | C | `tests/e2e/fixtures/*.js` (new `loginAsAuditor`, `loginAsAssessor`), `trust.htmx.html` specs | AuthMiddleware seed users (DB) |
| 4. CSP nonce | B+A | `SecurityProvider.php::headers()`, `HtmlView.php`, 22 `.htmx.html` `<style id=critical-tokens>`, theme-init `<script>` blocks | CSP decision log |
| 5. HTMX 2.0 | A(HTML) + C | `public/assets/vendor/htmx.min.js`, `hx-on:*` attributes (case-sensitive rewrite), `waitForHtmxSettled()` helper | every page HTML + page-interactions.spec.js |
| 6. Controller splits | B | `MeetingsController.php` (687), `MeetingWorkflowController.php` (559), `OperatorController.php` (516), `AdminController.php` (510), new `AgVote\Service\*`, `app/routes.php` | `AbstractController`, `RepositoryFactory`, Unit tests |

### Data Flow — CSP nonce (new path)

```
index.php (front controller)
    -> SecurityProvider::headers()          # generates nonce once, sets in CSP header
    -> SecurityProvider::nonce() accessor   # static, request-scoped
    -> Router dispatch
        -> Controller
            -> HtmlView::render($tpl, $ctx) # reads SecurityProvider::nonce()
                -> template emits <style nonce="..."> and <script nonce="...">
```

The nonce must be generated ONCE per request, BEFORE the CSP header is sent, and must remain accessible to `HtmlView` at template-render time. A request-scoped static on `SecurityProvider` (`SecurityProvider::nonce()`) matches existing conventions (`api_current_user_id()`, `Logger::getRequestId()`).

## Patterns to Follow

### Pattern 1: Controller Split — mirror ImportService

Already validated in v1.0 Phase 3 (ImportController 149 LOC + ImportService). Apply identically to the four fat controllers.

**What:** HTTP controller becomes a thin orchestrator (parse request -> call service -> `api_ok()`). Domain logic moves to a `final class XxxService` with constructor DI and `?Repo = null` defaults.

**When:** controller >500 LOC mixing repo access, validation, workflow transitions.

**Example:**
```php
final class MeetingWorkflowService {
    public function __construct(
        private ?MeetingRepository $meetingRepo = null,
        private ?BallotRepository $ballotRepo = null,
        private ?QuorumEngine $quorum = null,
    ) {
        $this->meetingRepo ??= RepositoryFactory::getInstance()->meeting();
        // ...
    }

    public function transition(string $meetingId, string $to): array { /* ... */ }
}
```

The existing `RepositoryFactory` singleton remains the injection seam; do not introduce a DI container. Tests pass mocks via the nullable constructor, matching `ImportServiceTest`.

### Pattern 2: Route Re-registration without URL change

When splitting `MeetingsController` into multiple classes, preserve public URLs by changing only the handler class in `app/routes.php`:

```php
// Before
$router->map('POST', '/api/v1/meetings/update', [MeetingsController::class, 'update']);
// After
$router->map('POST', '/api/v1/meetings/update', [MeetingsCrudController::class, 'update']);
```

No API compatibility break, no client change, no middleware config change.

### Pattern 3: CSP nonce as request-scoped static

```php
final class SecurityProvider {
    private static ?string $nonce = null;

    public static function nonce(): string {
        return self::$nonce ??= bin2hex(random_bytes(16));
    }

    public static function headers(): void {
        $nonce = self::nonce();
        header("Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'nonce-{$nonce}'; "
            . "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com; "
            . "...");
    }
}
```

`HtmlView::render()` reads `SecurityProvider::nonce()` and injects into templates. This keeps the nonce lifecycle inside the Security plane and avoids threading it through every controller.

### Pattern 4: Token shift with dual-name safety

Contrast remediation MUST follow v1.0 Phase 84 pitfall: **never rename a token, only add new tokens alongside**. Shadow DOM components inherit by name; a rename silently returns the `@property` `initial-value` (design-system.css lines 18–57). Add `--color-text-strong` / `--color-muted-foreground-aa` rather than mutating existing keys, then migrate consumers.

Critical-tokens inline blocks in the 22 `.htmx.html` files MUST be updated in the SAME commit as the `:root`/`[data-theme=dark]` block — otherwise the flash-of-wrong-color returns on navigation.

### Pattern 5: Overlay hit-test — `[hidden]` wins

Standardize on `[hidden] { display: none !important; }` in the design-system base layer (already applied in v1.3 to 2 overlays). Chantier 2 is a grep/codemod sweep for per-page CSS that overrides `display:` on elements that ALSO carry `[hidden]`, producing a hittable flex container.

## Anti-Patterns to Avoid

### Anti-Pattern 1: Splitting controllers before CSP landing

**Why bad:** controller splits churn `app/routes.php` and the PHP class graph; merging with CSS/CSP changes multiplies rebase risk and makes E2E regressions ambiguous (was it the split or the CSP?).
**Instead:** land Plane A + B (CSP) first, then splits.

### Anti-Pattern 2: HTMX 2.0 before contrast remediation

**Why bad:** HTMX 2.0 `hx-on:*` rewrite touches nearly every `.htmx.html` file. Contrast remediation ALSO touches the `<style id="critical-tokens">` block in the same 22 files. Running HTMX 2.0 first forces the contrast phase to rebase across already-modified HTML.

**Instead:** run contrast FIRST — it only touches `<style>` blocks; HTMX 2.0 touches attributes on elements and leaves style blocks alone. Merge-conflict surface is near-zero in that order.

Note on the "Shadow DOM token propagation" concern in the question: HTMX does not affect Shadow DOM token inheritance. Web Components inherit CSS custom properties via the CSS cascade, independently of HTMX's swap mechanism. The real risk is file-level merge conflict in the `.htmx.html` inline style blocks, not runtime propagation. Confidence: HIGH.

### Anti-Pattern 3: CSP nonce in middleware

**Why bad:** middleware runs per-route (after routing). CSP headers must ship with the response before any body — `SecurityProvider::headers()` runs in `index.php` before the router. Middleware is also API-only; HTML endpoints (`.htmx.html`, email tracking pixels) bypass it entirely.
**Instead:** nonce lives on `SecurityProvider`, generated in `headers()`, exposed via static accessor.

### Anti-Pattern 4: New DI container for controller splits

**Why bad:** PROJECT.md explicitly rejects frameworks ("refactoring incremental uniquement"). `RepositoryFactory` singleton + nullable constructor params is the sanctioned pattern.
**Instead:** copy ImportService exactly.

### Anti-Pattern 5: Fixture invention instead of DB seeding

**Why bad:** `loginAsAuditor` needs a real session; Playwright cannot forge `AuthMiddleware` state without a user row. Inventing a session cookie bypasses RBAC and produces false-green specs.
**Instead:** seed `users` with auditor + assessor roles in the test bootstrap (same mechanism as existing admin fixture).

## Blast-Radius / Rollback

| Concern | Cross-page blast | Rollback strategy |
|---------|------------------|-------------------|
| Contrast tokens | 316 nodes, 22 pages | Revert @layer base block + 22 critical-tokens blocks (single commit) |
| Overlay sweep | ~5–10 overlays estimated | Per-file revert |
| Trust fixtures | 0 (additive only) | Delete fixture files |
| CSP nonce | All pages rendering inline `<style>`/`<script>` | Revert `SecurityProvider::headers()` CSP string |
| HTMX 2.0 | 22 .htmx.html + shell.js | Pin `vendor/htmx.min.js` back to 1.x |
| Controller split | 4 controllers, tests touching privates | Per-controller feature branch |

## Recommended Build Order

Ordered to minimize cross-chantier merge conflict and rebase cost:

1. **Chantier 1 — Contrast remediation** (Plane A, token layer)
   - Touches only `<style>` blocks in 22 HTML files + design-system.css
   - Foundational for a11y report closure
   - Leaves HTMX attributes untouched; HTMX 2.0 later has zero overlap

2. **Chantier 2 — V2-OVERLAY-HITTEST** (Plane C, independent CSS)
   - Per-page CSS only; no HTML attribute changes
   - Can run in parallel with #3 if capacity allows

3. **Chantier 3 — V2-TRUST-DEPLOY** (Plane C, additive fixtures)
   - New files only; zero modification of existing test code
   - Unblocks trust.htmx.html role-specific specs that currently use loginAsAdmin fallback
   - Fully independent — can land any time

4. **Chantier 4 — V2-CSP-INLINE-THEME** (Plane B + A)
   - Must land AFTER chantier 1: contrast has already modified the critical-tokens `<style>` blocks; adding a `nonce=` attribute is one line per file
   - Must land BEFORE chantier 5: HTMX 2.0 rewrites share the same files as theme-init `<script>` extraction
   - New: `SecurityProvider::nonce()`, `HtmlView` nonce injection
   - Modified: `SecurityProvider::headers()` CSP string, 22 `.htmx.html` inline tags, possibly extracted `/assets/js/theme-init.js`

5. **Chantier 5 — HTMX 2.0 upgrade** (Plane A HTML + C tests)
   - Breaking: audit for camelCase `hx-on:*` survivors (2.0 is case-sensitive)
   - Lands AFTER #4 so nonce `<script>` extraction is already in place
   - Re-run page-interactions.spec.js + `waitForHtmxSettled()` helper

6. **Chantier 6 — Controller splits** (Plane B, pure PHP)
   - Fully independent of Planes A and C
   - Last because it's the highest-LOC churn and the most likely to reveal hidden tests inspecting private method presence (known debt from v1.0 Phase 3 Key Decision)
   - Pattern: MeetingsController -> MeetingsCrudController + MeetingsTransitionController + MeetingsReportsController, etc., via ImportService pattern
   - Optionally parallelize with #1 (different planes, zero file overlap) if team capacity allows

### Direct answers to the question

- **Contrast BEFORE HTMX 2.0 — YES.** Both touch the 22 `.htmx.html` files but in disjoint regions (style block vs. hx-on attributes). Doing contrast first is surgical; HTMX-first forces contrast to rebase across large diffs. No Shadow DOM runtime risk — the risk is merge, not propagation.
- **Controller splits follow ImportService pattern — YES.** `final class`, constructor DI with `?Type = null` defaults resolved to `RepositoryFactory::getInstance()`, thin HTTP controller invoking service, `routes.php` handler-class swap. No DI container. No URL break.
- **CSP nonce location — SecurityProvider, NOT middleware.** `SecurityProvider::headers()` already runs before the router in `index.php`, which is where the CSP header must be emitted. Expose via `SecurityProvider::nonce()` static accessor; `HtmlView::render()` reads it. Middleware is wrong because (a) it runs after routing, (b) it is API-only and bypassed by HTML endpoints, (c) the nonce must appear in the very first response header.

## Integration Points Summary

### New files
- `app/Service/MeetingWorkflowService.php` and siblings (from 4 controller splits)
- `app/Service/OperatorWorkflowService.php`
- `app/Service/AdminService.php`
- `tests/e2e/fixtures/auditor.js`, `tests/e2e/fixtures/assessor.js`
- Possibly `public/assets/js/theme-init.js` (extracted from inline theme-init)
- Possibly new tokens `--color-text-strong`, `--color-muted-foreground-aa` in design-system.css

### Modified files (high churn)
- `app/Core/Providers/SecurityProvider.php` — add `nonce()`, update CSP string, drop `'unsafe-inline'` from style-src
- `app/View/HtmlView.php` — read nonce and inject into templates
- `public/assets/css/design-system.css` — token value shifts (no renames)
- 22 `public/*.htmx.html` — critical-tokens values + `nonce=` attributes + HTMX 2.0 hx-on rewrites
- `public/assets/vendor/htmx.min.js` — 1.x -> 2.x
- `app/routes.php` — handler-class swaps for split controllers
- 4 fat controllers slimmed to HTTP-only orchestrators

### Read-only / unchanged seams
- `RepositoryFactory` — stable injection seam
- `AbstractController` — stable base
- `AuthMiddleware`, RBAC — stable (only seed data changes for trust fixtures)

## Confidence Assessment

| Area | Confidence | Reason |
|------|------------|--------|
| Controller split pattern | HIGH | ImportService validated in v1.0 Phase 3 |
| CSP nonce in SecurityProvider | HIGH | `SecurityProvider::headers()` is the documented entry point, called before routing in index.php |
| Contrast-before-HTMX ordering | HIGH | File-level overlap analysis — disjoint regions in same files |
| HTMX 2.0 Shadow DOM risk | MEDIUM | Training-data based; recommend Context7 `/bigskysoftware/htmx` query before starting chantier 5 |
| Overlay sweep scope | MEDIUM | v1.3 found 2 reactively; unknown how many more — needs grep pass during phase |
| Controller split test disruption | MEDIUM | v1.0 Key Decisions flagged "existing tests assert private method existence" — unquantified blast radius |

## Sources

- `/home/user/gestion_votes_php/.planning/PROJECT.md` (v1.4 tech debt list, ImportService decision, no-framework constraint)
- `/home/user/gestion_votes_php/.planning/STATE.md` (Phase 82–84 token pitfalls, Phase 14-02 hex-fallback removal, Phase 17 V2 deferrals)
- `/home/user/gestion_votes_php/app/Core/Providers/SecurityProvider.php` (current CSP string, header lifecycle, init signature)
- `/home/user/gestion_votes_php/app/Controller/MeetingsController.php` (split candidate, 687 LOC, AbstractController usage confirmed)
- `/home/user/gestion_votes_php/public/assets/css/design-system.css` (`@property` declarations confirm rename loses initial-value)
- `CLAUDE.md` architecture rules (AbstractController vs HtmlView split, DI nullable pattern)
