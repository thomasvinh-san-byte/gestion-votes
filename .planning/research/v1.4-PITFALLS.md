# Pitfalls Research — v1.4 Régler Deferred et Dette Technique

**Domain:** Brownfield tech-debt remediation on PHP 8.4 + HTMX + Web Components voting app
**Researched:** 2026-04-09
**Confidence:** HIGH (grounded in v10.0/v1.3 post-mortems from STATE.md + PROJECT.md)

Scope: six concrete chantiers — (1) contrast palette shift, (2) [hidden]+display:flex sweep,
(3) Playwright auditor/assessor fixtures, (4) CSP nonce/externalization of inline theme init,
(5) HTMX 1→2 upgrade, (6) splitting >500-line controllers.

---

## Critical Pitfalls

### Pitfall 1: Hex→oklch palette shift leaves Shadow DOM fallbacks stale

**What goes wrong:**
Web Components use `var(--color-foo, #deadbe)` fallback literals inside shadowRoot. When the
palette shifts from hex to oklch at the `:root` level, the 23 custom elements keep rendering the
*old* hex color whenever token resolution briefly fails (load order, disconnected fragments,
document.adoptNode, pre-hydration paint). The bug is invisible in light mode but screams in
dark mode where the stale fallback becomes a high-contrast gash.

**Why it happens:**
Shadow DOM inherits custom props but silently falls back to the literal if the host `:root` hasn't
been resolved at paint time. Developers search-replace `:root` hex values and forget the
`--color-foo, #hex` second operands scattered across 23 component files. v10.0 Phase 84 HARD-03
caught exactly this and had to patch 21 critical-tokens blocks *after* research claimed they were
in sync.

**How to avoid:**
- Adopt the v1.3 Phase 14-02 policy **project-wide in Phase 1 of this milestone**: remove every
  hex fallback from `var(--token, #hex)` inside Shadow DOM. Tokens are guaranteed-present via
  shell.js load order (documented in STATE.md Phase 14-02 decision).
- Grep gate in CI: `grep -rn 'var(--color[^,)]*,\s*#' app/Web/Components/ public/assets/components/`
  must return zero matches after Phase 1.
- If a fallback *must* exist (e.g. offline doc fragment), use an `oklch()` literal, not hex, so
  it matches the palette space.

**Warning signs:**
- Visual diff shows stale old-palette color in one Web Component while the rest of the page is
  on the new palette
- Bug only reproduces on first paint / cold reload
- Only dark-mode users report it

**Phase to address:** Phase 1 (Contrast remediation) — *before* touching any `:root` value.

---

### Pitfall 2: critical-tokens inline HTML block forgotten during palette sync

**What goes wrong:**
Every `public/*.htmx.html` ships a `<style>` block with 6 hex critical-tokens to prevent
flash-of-wrong-color. Palette changes that only touch `design-system.css` leave these 22 files on
the old hex values. Users see a 50–200ms flash of the old palette on every navigation.

**Why it happens:**
Two sources of truth: (a) `design-system.css @layer base`, and (b) 22 inline critical-tokens
blocks copied for FOUC prevention. v10.0 Phase 84 research *incorrectly claimed files were
already in sync* — they weren't, and the phase had to patch all 21 files retroactively.

**How to avoid:**
- Convert critical-tokens to a **generator**: a single `critical-tokens.json` + a build step (or
  a PHP partial) that emits the inline `<style>` block so there is one source of truth.
- Failing that, add a unit test that parses every `public/*.htmx.html`, extracts the
  critical-tokens block, and asserts every hex value matches the canonical palette.
- CI grep: any hex matching the *old* palette in `public/*.htmx.html` → fail.

**Warning signs:**
- Flash of old color on page load
- Diff touching `design-system.css` without touching any `public/*.htmx.html`
- Search for one new oklch value finds 1 hit; search for the hex it replaced finds 23 hits

**Phase to address:** Phase 1 (Contrast remediation) — mechanical sync as sub-phase 1.2.

---

### Pitfall 3: Token rename breaks Shadow DOM silently (never rename, only alias)

**What goes wrong:**
Renaming a token like `--color-text-primary` → `--color-text-on-surface` breaks every Shadow DOM
consumer that references the old name. Because shadow trees fall back to their hex literal (or
inherit `initial`), the breakage is *silent* — no error, just wrong colors.

**Why it happens:**
v10.0 Phase 82 codified the rule "token names must never be renamed — add new alongside old."
The v1.4 contrast work will be tempted to rename for consistency (e.g. `--color-muted-foreground`
which is the worst offender at 1.83:1). Renaming cascades across 25 per-page CSS, 23 Web
Components, 22 critical-tokens blocks, 5258-line design-system.css.

**How to avoid:**
- **Hard rule:** v1.4 adds `--color-X-v2` alongside `--color-X`; old token becomes a
  `var(--color-X-v2)` alias. No deletes in this milestone.
- Enforced by PR checklist + a grep gate: `git diff` must not remove any `--color-*:` definition
  without also adding a `var(--color-*-v2)` alias in the same commit.
- Old tokens flagged for removal in v1.5 (a full cycle after consumers migrate).

**Warning signs:**
- PR diff contains a `-` line deleting `--color-*:` with no corresponding alias
- Shadow DOM components suddenly using `initial` color (usually black text on dark mode)

**Phase to address:** Phase 1 — enforced by PR template and CI grep across the whole milestone.

---

### Pitfall 4: color-mix(in oklch) renders differently from color-mix(in srgb) on WebKit/older Chrome

**What goes wrong:**
Hover/active derivations use `color-mix(in srgb, base, white 10%)`. Upgrading to
`color-mix(in oklch, ...)` gives perceptually-uniform results in Chromium but WebKit and any
Chrome <111 fall back differently — hover states become *invisible* (too close to base) or
*wrong hue*.

**Why it happens:**
`color-mix(in oklch)` is Baseline 2023 but WebKit shipped it in 16.2 and has had several rendering
bugs in interpolation direction. v10.0 Phase 82-01 committed to `color-mix(in oklch)` — works in
Chromium but the Phase 15 webkit deferrals (2/25 critical-path specs) may partly stem from this.

**How to avoid:**
- Add a `@supports (color: color-mix(in oklch, red, blue))` guard **with an srgb fallback** for
  each derived token. Tedious but safe.
- Or: pre-compute hover/active as static oklch literals at build time (no runtime color-mix at
  all). This is the recommended path for v1.4 because it also speeds up paint.
- Add a cross-browser visual test specifically for hover states on Chromium + WebKit + Firefox.

**Warning signs:**
- Chromium tests green, WebKit tests flaky on anything touching `:hover`
- Hover state "feels off" on Safari but looks fine on Chrome
- Computed-style assertions differ between browsers for the same element

**Phase to address:** Phase 1 — decide static-literal vs. runtime-mix before touching any hover
token.

---

### Pitfall 5: Dark mode drift — `[data-theme="dark"]` block edited separately from `:root`

**What goes wrong:**
Contrast fix lands for light mode; dark mode still has the old (broken) ratios, or vice versa.
Worse: the *hue* drifts between modes — v10.0 Phase 82 already caught this (dark was cool hue
~260 while light was warm-neutral 78, had to warm-unify).

**Why it happens:**
`:root` and `[data-theme="dark"]` live in the same file but developers edit them in separate
passes. v10.0 decision: "Dark mode `[data-theme='dark']` block and critical-tokens inline styles
must update in the same commit as any `:root` color primitive change."

**How to avoid:**
- Carry the v10.0 rule forward: every commit touching `:root` color primitives must also touch
  `[data-theme="dark"]` in the same commit, or the commit is rejected.
- Run the contrast audit (axe + `v1.3-CONTRAST-AUDIT.json` format) in **both** modes per phase
  exit; not just light.
- Add `data-theme="dark"` variant to every parametrized axe spec (currently 22 pages × 1 theme =
  22; make it × 2 = 44).

**Warning signs:**
- Contrast audit passes on light, fails on dark (or vice versa)
- Dark-mode parity report shows hue mismatch
- Reviewer comment: "did you update the dark block?"

**Phase to address:** Phase 1 — dual-theme audit gate at phase exit.

---

### Pitfall 6: HTMX 2.0 hx-on:* case sensitivity silently drops handlers

**What goes wrong:**
HTMX 2.0 made `hx-on:` event names **case-sensitive** and switched to `hx-on:click` style with
a colon. The legacy `hx-on="click: ..."` attribute and mixed-case variants silently **stop
firing** — no console error, the handler just doesn't run. In a voting app that is a silent bug
where "Submit ballot" appears to work but nothing happens.

**Why it happens:**
HTMX 2.0 removed the legacy `hx-on` parser. Old attributes become no-ops, not errors. Event
names are normalized (`htmx:afterRequest` stays, `htmx:afterrequest` does not). Across 22
htmx.html files + per-page JS this is a big search surface.

**How to avoid:**
- **Phase 0 of the HTMX upgrade:** inventory every `hx-on` attribute and every `htmx:*` event
  name across the codebase. Produce a before/after diff table.
- Add a smoke test per page that asserts *the action actually mutated state*, not just "button
  was clicked" — i.e. test the outcome, not the event.
- Keep HTMX 1.x pinned until the full sweep is done. Don't upgrade incrementally on a subset of
  pages; HTMX is global.

**Warning signs:**
- Console shows no errors but a click does nothing
- Integration tests that mock HTMX pass, but real clicks don't mutate
- Mixed-case `hx-on:Click` or `htmx:afterRequest` in grep output

**Phase to address:** Phase 5 (HTMX 2.0 upgrade). **Must include a full-page outcome test suite
in the same phase.**

---

### Pitfall 7: HTMX 2.0 swap fragment parser breaks SSE partial updates

**What goes wrong:**
HTMX 2.0 uses a stricter fragment parser; illegal HTML constructs that 1.x tolerated are now
silently dropped. In AgVote this hits the real-time results table and SSE-driven scrutin updates
(`<tr>` fragments streamed without a `<table>` wrapper).

**Why it happens:**
Browsers' fragment parsing rejects bare `<tr>` outside `<table>`, `<li>` outside `<ul>`, etc.
HTMX 1.x silently worked around this; 2.0 is stricter.

**How to avoid:**
- Wrap every `<tr>`/`<td>`/`<li>` OOB swap target in a `<template>` with
  `hx-swap-oob="outerHTML:#target"`.
- Add a test for the SSE results stream specifically (it was not in Phase 15's critical-path).
- Enable `htmx:oobErrorNoTarget` and `htmx:swapError` listeners in dev mode to surface drops.

**Warning signs:**
- SSE events fire but UI does not update
- Table rows disappear on partial update
- `htmx:swapError` events in the console

**Phase to address:** Phase 5 (HTMX 2.0 upgrade).

---

### Pitfall 8: CSP nonce added to `<script>` but inline theme init runs *before* nonce can be trusted

**What goes wrong:**
The inline theme init (reads localStorage, sets `data-theme="dark"` before first paint to prevent
flash) is in `<head>`, intentionally blocking. Adding CSP `script-src 'nonce-XXX'` means the
nonce must be generated per-request server-side and injected into *every* inline script. If the
nonce is generated after the script is flushed, or if any script is left without the nonce, CSP
blocks it and the page is unstyled / untreated.

**Why it happens:**
- Nonce must be fresh per-request (cannot be static)
- Nonce must exist at the moment the template renders — in PHP this means generating it in a
  very early middleware before `HtmlView::render()` runs
- Any third-party or legacy inline script forgotten in the sweep = CSP violation = feature broken

**How to avoid:**
- **Prefer externalization over nonce.** For the theme init specifically, externalize to
  `public/assets/js/theme-init.js` with `<script src>` (not defer/async — must be blocking).
  External scripts with `script-src 'self'` don't need a nonce.
- If you must nonce: generate in a `SecurityProvider` bootstrap step, store on `Request`
  attributes (or a static), expose via `HtmlView::nonce()`. Test with CSP **report-only** first
  for at least one full phase before enforcing.
- Audit every inline `<script>` and `<style>` in `public/*.htmx.html` and `app/Templates/*` —
  produce a before/after table.

**Warning signs:**
- CSP violations in report-only mode mentioning `'unsafe-inline'`
- Theme flashes to light then back to dark on load
- One page works, another does not — nonce generation ordering issue

**Phase to address:** Phase 4 (CSP inline theme). **Report-only mode first**, enforce only after
a full audit cycle passes with zero violations.

---

### Pitfall 9: CSP enforcement breaks HTMX `hx-on:*` handlers

**What goes wrong:**
Strict CSP (`script-src 'self' 'nonce-XXX'`) **blocks HTMX's `hx-on:*` handlers** because HTMX
evaluates them via `Function()` / eval-like mechanisms. You either need `'unsafe-eval'` (bad) or
`'unsafe-hashes'` with per-handler hashes (tedious), or you migrate every `hx-on` to a JS file.

**Why it happens:**
HTMX's `hx-on` attribute is essentially an inline event handler — CSP treats it as inline script.
HTMX 2.0 added `htmx.config.allowEval`, but CSP still sees the inline attribute.

**How to avoid:**
- **Sequence matters:** do the HTMX 2.0 upgrade (Phase 5) and the `hx-on` migration *before*
  CSP enforcement (Phase 4) — OR keep CSP in report-only mode until both are done.
- Migrate every `hx-on:click="..."` to a real JS event listener attached in the per-page module.
  This is easier than it sounds because v1.1 Phase 5 already inventoried ID contracts.

**Warning signs:**
- CSP violations mentioning inline attribute values
- HTMX buttons do nothing after CSP enforcement
- `hx-on` grep returns matches after Phase 5

**Phase to address:** Phase 4 depends on Phase 5 — **must be sequenced Phase 5 → Phase 4**.

---

### Pitfall 10: Controller split breaks tests that assert private method existence

**What goes wrong:**
Splitting `MeetingsController` (687 lines) into `MeetingsController` + `MeetingLifecycleService`
moves methods out. Existing PHPUnit tests use `ReflectionClass::hasMethod('privateMethodName')`
or call via reflection — they pass with the old API, fail with the new one. Or tests that
instantiate the controller with a specific constructor signature break when the signature
changes.

**Why it happens:**
PROJECT.md Key Decisions explicitly flags: "Controller splits deferred to v2: Existing tests
assert private method existence, making splits disruptive." This is a known trap, now due.

**How to avoid:**
- **Phase 0 of each split:** grep the test file for every `->`, `::`, `hasMethod`, `getMethod`,
  `ReflectionClass`, `ReflectionMethod` reference to the controller being split. Categorize:
    - Tests that exercise *public HTTP behavior* → keep, retarget to the new HTTP entry point
    - Tests that exercise *private helpers* → extract the helper to the new service and rewrite
      the test to target the service directly
    - Tests that assert *method existence* → delete (they were implementation tests anyway)
- Keep the controller as a thin HTTP wrapper delegating to a new service. The service is the new
  test target. Use nullable DI (CLAUDE.md rule) so the service is testable without HTTP.
- Run the targeted test file after *each* extraction (not at the end). CLAUDE.md: max 3 test
  executions per task — budget them.

**Warning signs:**
- Test failure mentions `ReflectionException: Method X does not exist`
- Constructor mismatch errors after refactor
- Coverage drops on the new service because tests still target the old controller

**Phase to address:** Phase 6 (Controller refactoring). One controller at a time, not in parallel.

---

### Pitfall 11: Controller split creates a god-service (moving the problem, not solving it)

**What goes wrong:**
You extract `MeetingLifecycleService` from `MeetingsController` but the service ends up at 650
lines because you moved everything. You've renamed the file, not reduced complexity.

**Why it happens:**
Mechanical extraction without domain analysis. v1.0 Phase 3 succeeded (`ImportService` 149
lines) because import has a clear domain boundary. Meetings mix lifecycle + reports + workflow +
motions — extracting them all into one service = god-service.

**How to avoid:**
- Before extracting, **do a responsibility inventory**: list every public method and classify by
  domain (lifecycle, reporting, workflow, state transitions, member roster, ...).
- Extract to **multiple** services along domain lines, e.g. `MeetingsController` (687) →
  `MeetingLifecycleService` + `MeetingReportsService` + `MeetingMembershipService`.
- Set a **size ceiling** per extracted service: "no service > 300 lines in this milestone." If a
  service exceeds it, re-split before declaring the phase done.

**Warning signs:**
- Extracted service is > 400 lines
- Service constructor takes > 5 dependencies
- Test file for the service mirrors the old controller test file 1:1

**Phase to address:** Phase 6 — pre-split inventory is a phase-entry gate.

---

### Pitfall 12: Playwright auditor/assessor fixtures leak into production seed data

**What goes wrong:**
Adding `auditor@test.local` and `assessor@test.local` fixtures for Playwright. Developer uses
`database/migrations/` to insert them — they end up in staging, then prod, as real user accounts
with known passwords. Or worse, the fixture seeder is tenant-unaware and creates cross-tenant
accounts.

**Why it happens:**
Multi-tenant RBAC makes test fixtures tricky: each test-tenant needs its own auditor/assessor.
The easy path is a global SQL seed; the correct path is a per-test factory tied to a fresh
tenant.

**How to avoid:**
- Fixtures live in `tests/e2e/fixtures/` **never** in `database/migrations/`. Use Playwright
  `globalSetup` or per-test `beforeEach` that calls a `POST /api/v1/test/seed-user` endpoint
  **gated by `APP_ENV=development|test`** (returns 404 in prod).
- Each fixture is scoped to a test-only tenant (`tenant_id = 'test-e2e-<uuid>'`) deleted in
  `globalTeardown`.
- Passwords generated per-run, never hardcoded.
- CI must fail if `APP_ENV=production` and any `/api/v1/test/*` route is registered.

**Warning signs:**
- Seed file in `database/migrations/` with hardcoded emails
- Hardcoded test password in `playwright.config.js`
- Grep for `auditor@test.local` finds matches in `database/` or `src/`

**Phase to address:** Phase 3 (Playwright fixtures). **Build the test-only endpoint first**,
then the fixtures.

---

### Pitfall 13: Auditor/assessor fixture logs in but lacks meeting-scoped role

**What goes wrong:**
Per CLAUDE.md architecture: "system roles: admin, operator, auditor, viewer, president. Meeting
roles: president, assessor, voter." Assessor is a *meeting* role, not a system role. A fixture
that seeds a user but does not attach them to a meeting with the assessor role → `RoleMiddleware`
rejects with "not assessor for this meeting" on every test.

**Why it happens:**
Two-level RBAC is subtle. Phase 14 already caught the dual of this: "assessor removed from
`/trust` data-requires-role — assessor is a meeting role, /trust is system-wide." The inverse
trap is building assessor fixtures without building an associated test-meeting.

**How to avoid:**
- The seed endpoint must create the *full graph*: user → tenant → meeting → meeting-role
  assignment → optional ballot. Not just the user.
- Use a factory pattern: `createTestAssessor($meeting)` returns a session cookie for a user who
  is assessor *on that specific meeting*.
- For `/trust` (system-wide auditor view), use `createTestAuditor($tenant)` — no meeting needed.
- Document the two fixture types in `tests/e2e/fixtures/README.md` so future devs do not reuse
  the wrong one.

**Warning signs:**
- Playwright auditor test works, assessor test fails at `RoleMiddleware`
- Fixture uses `assessor` as `users.role` column value (wrong — that's a meeting role)
- 403 Forbidden mid-test despite successful login

**Phase to address:** Phase 3 (Playwright fixtures).

---

### Pitfall 14: `[hidden]` + `display:flex` sweep introduces specificity wars

**What goes wrong:**
The HTML `[hidden]` attribute sets `display:none` via the UA stylesheet. Any CSS rule with
`display:flex` at equal-or-greater specificity *wins* because of cascade order, so the element
is still visible or click-targetable. v1.3 fixed two sites reactively; v1.4's codebase-wide
sweep risks naive fixes that introduce new specificity bugs, especially inside Shadow DOM.

**Why it happens:**
The correct fix is `[hidden] { display: none !important }` in `design-system.css @layer base` —
but `!important` can break component authors who explicitly set display. OR developers write
`.overlay[hidden] { display: none }` which requires editing every overlay rule individually.

**How to avoid:**
- Land a **single base rule** in `design-system.css @layer base`:
  `:where([hidden]) { display: none !important; }`. `:where()` keeps specificity at 0,
  `!important` beats component rules. This is the WAI-ARIA APG recommended pattern.
- Then grep for every `display:flex`, `display:grid`, `display:block` rule and verify it does
  not target a `[hidden]`-capable element as a bare selector.
- Add a Playwright assertion: for every element in the DOM with `[hidden]` attribute,
  `getComputedStyle(el).display === 'none'`.
- Do **not** use `hx-swap-oob` to toggle `[hidden]` — HTMX attribute handling can desync.

**Warning signs:**
- Element is clickable but `[hidden]` is set (pointer-events trap)
- Operator overlay reappears after route change
- Playwright `expect(locator).toBeHidden()` passes but `toBeVisible()` also passes

**Phase to address:** Phase 2 (V2-OVERLAY-HITTEST). Land the base rule **first**, audit **second**.

---

### Pitfall 15: "Fixed" contrast reintroduces at runtime via inline style or JS

**What goes wrong:**
Token-level contrast fix lands, axe passes. Then a runtime JS sets `el.style.color = '#988d7a'`
based on data state (e.g. "muted row"). The contrast audit is clean because it runs after page
load but before the JS runs; real users see the broken contrast.

**Why it happens:**
Inline `style=` from PHP templates or JS bypasses the token layer entirely. The v1.3 contrast
audit ran at a specific moment — it does not catch dynamic state changes.

**How to avoid:**
- Grep for `style="color:` and `style="background` in `public/**/*.html`, `app/Templates/`,
  `public/assets/**/*.js` — these are the offenders. Convert to class-based styling referencing
  tokens.
- Grep for `.style.color`, `.style.backgroundColor` in JS — same treatment.
- Contrast audit must run in **multiple states** per page: default, loading, error, selected,
  disabled, hover. Parametrize the existing `contrast-audit.spec.js` over these states.
- CI grep gate: zero `style="color|background"` in templates after Phase 1.

**Warning signs:**
- Axe passes in CI, user reports bad contrast on a specific row type
- Grep finds `style="color:` in templates
- Disabled states look identical to enabled states

**Phase to address:** Phase 1 (Contrast remediation). Stateful audit is a phase-exit gate.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Keep `hx-on:*` inline and add `'unsafe-inline'` to CSP | CSP ships in a day | CSP provides zero XSS protection; must redo sweep later | Never — defeats the purpose of Phase 4 |
| Rename tokens during contrast fix for "consistency" | Cleaner names | Silent Shadow DOM breakage (Pitfall 3) | Never in v1.4 — defer renames to v1.5 |
| Mechanical controller split (move all methods to one service) | Quick line-count win | God-service with same complexity (Pitfall 11) | Never — always split by domain |
| Hardcode test fixture users in `database/migrations/` | Fixtures work locally immediately | Prod pollution + cross-tenant leaks (Pitfall 12) | Never — use test-gated seed endpoint |
| Keep HTMX 1.x and cherry-pick 2.0 features | No big-bang upgrade | HTMX is global; mixed versions = undefined behavior | Never — HTMX upgrade is all-or-nothing |
| Add `!important` to every overlay rule to beat `[hidden]` | Overlay hides | Specificity chaos, next dev can't override anything | Never — use `:where([hidden]) !important` base rule once |
| Waive axe contrast violations per-page | Unblocks CI | Debt accumulates, v1.5 inherits partial AA again | Only for known non-token offenders with an explicit v1.5 ticket |
| Skip dark-mode contrast audit this phase | Half the work | Dark mode ships broken, reported in prod | Never — dark mode is in scope |
| Generate CSP nonce in `HtmlView::render()` without early middleware | Simpler | Some scripts flushed before nonce exists → CSP violations | Never — nonce must be in bootstrap middleware |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| HTMX 2.0 ↔ Web Components | Assume events bubble through shadowRoot | Explicit `composed: true` on custom events; test event propagation per-component |
| HTMX 2.0 ↔ SSE (`hx-ext="sse"`) | Assume 1.x extension config carries over | Re-verify the `sse` extension is loaded and `sse-swap` attribute syntax (may have changed) |
| CSP nonce ↔ HtmlView | Pass nonce as template var but forget HTMX partial templates | Every `HtmlView::render()` call site must get the nonce; audit via grep |
| CSP nonce ↔ inline `<style>` | Only nonce `<script>`, forget `style-src` | `style-src 'self' 'nonce-XXX'` too; or externalize all inline styles first |
| Playwright ↔ Redis sessions | Each test login floods Redis with session keys | `globalTeardown` must FLUSH test-prefix session keys; use `test:` prefix convention |
| Playwright ↔ Multi-tenant | Tests race on shared tenant fixtures | Per-test tenant UUID, teardown isolated |
| axe-core ↔ Shadow DOM | Audit stops at shadow boundary by default | Pass `include: ['ag-modal::shadow']` etc. — already done for ag-modal, verify for all 23 components |
| PHPUnit ↔ refactored controller | Test uses `new MeetingsController(...)` with old constructor | Use nullable DI — CLAUDE.md rule — and update test constructors in the same commit as refactor |
| oklch ↔ older Safari | `color-mix(in oklch)` falls back unpredictably | `@supports` guard OR pre-compute static literals (see Pitfall 4) |
| `[hidden]` ↔ `hx-swap-oob` | HTMX swap reapplies element without `[hidden]` | OOB template must include the `hidden` attribute explicitly on every swap |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| `@property` with `var()` in initial-value | CSS parse errors, tokens do not register | Only register primitives with literal initial-values (v10.0 Phase 84 decision) | Immediate on any browser |
| Contrast audit across 22 pages × 2 themes × 6 states | CI job 20+ min | Cache login state; shard; skip states where class combinations don't vary | When parametrization lands without sharding |
| Every HTMX request reloads full theme init | TTFB +100ms | Externalize theme init so it's cached; add `Cache-Control: immutable` | Once theme-init.js is a separate file |
| Controller split creates N+1 service instantiation | Slower page loads | Lazy-instantiate via `RepositoryFactory`; cache per request | When multiple services share a repo (most of them) |
| Playwright full suite runs serially | 30+ min feedback loop | `fullyParallel: true` + sharding; per-tenant isolation enables this | Now — Phase 15 already has this pain |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| CSP report-only forever, never enforce | Zero real protection despite the work | Phase 4 must include an enforcement cutover date + rollback plan |
| Test-seed endpoint in prod | Anyone can create admin users | Guard with `APP_ENV !== 'production'` check at *route registration time*, not runtime |
| Playwright session cookies logged in CI artifacts | Session theft if CI artifacts leak | Strip cookies from traces; use test-only sessions with 5 min TTL |
| HTMX `hx-headers` with hardcoded CSRF | CSRF token stale on retry | Use `htmx:configRequest` listener to inject fresh token from `meta[name=csrf-token]` each request |
| Contrast fix lowers opacity instead of changing color | WCAG measures final rendered contrast; opacity still fails | Never use opacity to "fix" contrast — change the color token |
| Auditor fixture given admin role "just for tests" | Tests pass but they test the wrong access | Fixture must match production role exactly; if admin is needed, use `createTestAdmin` |
| CSP nonce reused across requests | Predictable nonce defeats the purpose | Generate per-request via `random_bytes(16)`; never cache |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Contrast fix makes muted text *too* prominent (over-correction) | "Muted" no longer reads as secondary | Target exactly 4.5:1, not 7:1; use weight/size to convey secondariness alongside color |
| Palette shift changes brand identity accidentally | Users feel "something is off" without knowing why | Keep hue stable; shift only lightness/chroma for contrast |
| HTMX 2.0 upgrade drops a loading indicator pattern | Users think clicks did nothing | Smoke test every `hx-indicator` across 22 pages post-upgrade |
| CSP nonce rotation causes `<form>` POST to be refused on retry | Vote submission appears to fail | CSRF token ≠ CSP nonce — don't conflate them |
| `[hidden]` sweep hides elements that were intentionally keyboard-reachable | Keyboard users lose access | Verify `keyboard-nav.spec.js` still green after the sweep — mandatory gate |
| Dark mode contrast fix makes one state invisible (disabled vs. default) | Can't tell what's clickable | Audit in both themes + all 6 states (see Pitfall 15) |

---

## "Looks Done But Isn't" Checklist

- [ ] **Contrast remediation:** Axe passes in light mode — verify dark mode **and** all 6
      interaction states (default, hover, focus, active, disabled, loading)
- [ ] **Contrast remediation:** `:root` updated — verify all 22 critical-tokens inline blocks
      + all 23 Shadow DOM fallback literals + all 25 per-page CSS files
- [ ] **Contrast remediation:** No `style="color|background"` inline attributes left in
      templates or JS
- [ ] **[hidden] sweep:** Base `:where([hidden])` rule landed — verify no per-component
      override re-applies `display:flex`
- [ ] **[hidden] sweep:** `keyboard-nav.spec.js` still 6/6 green (no element became
      keyboard-unreachable)
- [ ] **Playwright fixtures:** Auditor works against `/trust` — verify assessor works against a
      *meeting-scoped* route (not system-wide)
- [ ] **Playwright fixtures:** Test-seed endpoint returns 404 when `APP_ENV=production` — add a
      test for this
- [ ] **Playwright fixtures:** `globalTeardown` deletes test tenants — verify no `test-e2e-*`
      tenant rows remain after suite
- [ ] **CSP:** Nonce present on every `<script>` and `<style>` — grep for inline ones missing it
- [ ] **CSP:** Report-only mode shipped for ≥1 phase before enforcement
- [ ] **CSP:** `hx-on:*` migration complete *before* CSP enforcement
- [ ] **HTMX 2.0:** Every `hx-on` grep returns zero — migrated to JS listeners
- [ ] **HTMX 2.0:** SSE real-time results stream smoke test passes
- [ ] **HTMX 2.0:** Every `hx-indicator` still shows during real network activity
- [ ] **Controller split:** Each extracted service < 300 lines
- [ ] **Controller split:** Tests target the service directly, not via the controller
- [ ] **Controller split:** No test uses `ReflectionClass::hasMethod` to assert structure
- [ ] **All phases:** `php -l` on every touched PHP file before commit (CLAUDE.md rule)
- [ ] **All phases:** Targeted PHPUnit only, max 3 runs per task (CLAUDE.md rule)

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| #1 Stale Shadow DOM fallback | LOW | Grep `var(--color[^,)]*,\s*#`, delete fallbacks, re-run contrast audit |
| #2 critical-tokens drift | LOW | Script that syncs from `design-system.css :root` into every htmx.html `<style>` block |
| #3 Token renamed by accident | MEDIUM | Revert the rename, add new token as alias, migrate consumers async |
| #4 color-mix oklch WebKit drift | MEDIUM | Switch to pre-computed static literals for affected derived tokens |
| #5 Dark-mode drift | LOW | Re-run dual-theme audit, patch missing `[data-theme="dark"]` block |
| #6 HTMX 2.0 hx-on silent drop | HIGH | Full grep + outcome test per page; revert to 1.x if deadline pressure |
| #7 HTMX 2.0 swap fragment parser | MEDIUM | Wrap fragments in `<template>`, re-test SSE stream |
| #8 CSP nonce ordering | MEDIUM | Move nonce generation to earliest middleware; report-only until clean |
| #9 CSP breaks `hx-on` | HIGH | Revert to report-only, complete Phase 5 `hx-on` migration, retry |
| #10 Private-method test failures | MEDIUM | Rewrite tests against extracted service; delete pure-structure tests |
| #11 God-service extraction | HIGH | Re-split; painful because consumers already migrated — do an inventory first next time |
| #12 Test fixtures in prod | HIGH | Emergency migration to delete test users from prod; rotate passwords; post-mortem |
| #13 Assessor fixture rejects at RoleMiddleware | LOW | Add meeting + meeting-role assignment to seed factory |
| #14 `[hidden]` specificity war | MEDIUM | Add `:where([hidden]) !important` base rule, remove per-rule overrides |
| #15 Runtime inline style contrast | LOW | Grep + migrate to classes; add stateful audit to CI |

---

## Pitfall-to-Phase Mapping

Recommended phase ordering derived from dependency analysis (**CSP must come after HTMX upgrade**):

| # | Pitfall | Prevention Phase | Verification Gate |
|---|---------|------------------|-------------------|
| 1 | Stale Shadow DOM fallback | Phase 1 (Contrast) sub-phase 1.1 | Grep gate: zero `var(--color*,\s*#*)` in components |
| 2 | critical-tokens drift | Phase 1 sub-phase 1.2 | Grep gate: old hex values absent from every `public/*.htmx.html` |
| 3 | Token rename | Phase 1 (milestone-wide rule) | PR checklist + `git diff` grep on `--color-*:` deletions |
| 4 | oklch WebKit rendering | Phase 1 sub-phase 1.3 | WebKit contrast audit passes; decision logged |
| 5 | Dark mode drift | Phase 1 exit gate | Contrast audit runs in both themes per phase exit |
| 6 | HTMX `hx-on` case sensitivity | Phase 5 (HTMX upgrade) | Zero `hx-on` in grep; outcome smoke tests green |
| 7 | HTMX fragment parser | Phase 5 | SSE stream smoke test green |
| 8 | CSP nonce ordering | Phase 4 (CSP) | Report-only produces zero violations for one full phase |
| 9 | CSP breaks `hx-on` | Phase 4 (after Phase 5) | `hx-on` grep empty *before* Phase 4 starts |
| 10 | Private-method test failures | Phase 6 sub-phase per controller | Pre-split test inventory; no `ReflectionClass` in tests |
| 11 | God-service extraction | Phase 6 entry gate | Each extracted service < 300 lines; ≤5 constructor deps |
| 12 | Test fixtures leak to prod | Phase 3 (Playwright fixtures) | Prod env rejects `/api/v1/test/*` with 404 |
| 13 | Assessor fixture role-scope | Phase 3 | Fixture factory requires meeting context for assessor |
| 14 | `[hidden]` specificity wars | Phase 2 (V2-OVERLAY-HITTEST) | `:where([hidden])` rule landed first; keyboard-nav green |
| 15 | Runtime inline style bypass | Phase 1 sub-phase 1.4 | Stateful contrast audit (6 states); grep gate on inline styles |

**Phase ordering rationale:**
1. **Phase 1 — Contrast remediation** (largest surface, biggest user value, blocks no other phase)
2. **Phase 2 — `[hidden]` overlay sweep** (CSS-only, can parallelize with Phase 3)
3. **Phase 3 — Playwright fixtures** (unblocks auditor/assessor coverage used by later phases)
4. **Phase 5 — HTMX 2.0 upgrade** (must precede Phase 4; introduces `hx-on` migration)
5. **Phase 4 — CSP nonce/externalization** (depends on Phase 5 `hx-on` migration complete)
6. **Phase 6 — Controller refactoring** (last; highest test-breakage risk, benefits from stable
   Playwright fixtures from Phase 3)

---

## Sources

- `.planning/PROJECT.md` Key Decisions (2026-04-09) — controller split deferral, axeAudit
  methodology split, docker-compose.override.yml
- `.planning/STATE.md` Decisions — v10.0 Phase 82/84 palette shift, oklch decision, token rename
  prohibition (Pitfall 3), Phase 14-02 Shadow DOM fallback removal policy, Phase 17 loose ends
  (V2-OVERLAY-HITTEST, V2-TRUST-DEPLOY, V2-CSP-INLINE-THEME)
- v10.0 Phase 84 HARD-03 retrospective — critical-tokens sync claim was wrong, 21 files patched
- v1.3 Phase 16-02 — ag-modal focus trap shadow DOM traversal fix
- v1.3 Phase 16-04 — contrast audit methodology, 316 nodes deferred
- CLAUDE.md — PHPUnit targeted/3-run rule, nullable DI pattern, namespaces, no copropriété
- HTMX 2.0 release notes (general knowledge — MEDIUM confidence; verify against
  htmx.org/migration-guide-htmx-1/ in Phase 5 entry research)
- W3C CSP Level 3 spec + MDN `script-src` guidance (HIGH confidence for nonce semantics)

---
*Pitfalls research for: v1.4 tech-debt remediation on AgVote PHP/HTMX/Web Components stack*
*Researched: 2026-04-09*
