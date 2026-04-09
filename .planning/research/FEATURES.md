# Feature Research — v1.4 Tech Debt Remediation

**Domain:** Brownfield tech debt milestone for PHP 8.4 / HTMX voting application
**Researched:** 2026-04-09
**Confidence:** HIGH (grounded in v1.3 audit artifacts + verified upstream sources)

## Framing

This is a **debt milestone, not a feature milestone**. "Features" here are interpreted as **capabilities delivered to the codebase** across 6 chantiers. Each chantier is scoped as its own mini-feature-set with table stakes / differentiators / anti-features. MVP for v1.4 = ship contrast AA + HTMX 2.0 upgrade + CSP nonce (highest user + security impact). Everything else is secondary.

Existing features (meetings, ballots, quorum, procurations, import/export, email queue, SSE, RBAC) are treated as **preserved invariants** — nothing in v1.4 should regress them.

---

## Chantier 1 — Contrast Remediation (WCAG 2.1 AA)

**Starting state:** 316 failing nodes across 22/22 pages, 42 unique (fg, bg) pairs, 6 dominant pairs = 71% of cases. Worst ratio 1.83 on wizard. Root cause = design tokens (`#988d7a` muted-foreground on warm-neutral surfaces), not per-page HTML.

### Table Stakes

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Raise `#988d7a` muted-foreground ≥ 4.5:1 on `#f6f5f0` / `#e0dbcf` / `#ffffff` | Fixes 177/316 nodes in one token edit | LOW | oklch L* ~45-48 targets per v1.3-A11Y-REPORT §3 |
| Raise `#bdb7a9` wizard step numbers | 1.83 ratio blocks assistant navigation | LOW | Single token, 3+ nodes, worst severity |
| Raise `#9d9381` KPI labels | Same family as muted-foreground, easy sweep | LOW | 27 nodes across 4 pages |
| Fix `#4d72d8` on `#ebf0f9` settings chip active state | 3.89 fails normal text threshold | LOW | Single component, darken primary-600 one step |
| Update 21 htmx.html `critical-tokens` inline blocks (hex → oklch) | Prevent flash-of-wrong-color post-shift | MEDIUM | Same pattern as Phase 84 HARD-03 |
| Refresh 23 Web Components Shadow DOM fallback hex literals | Shadow roots fall back to hardcoded hex if tokens absent | MEDIUM | Phase 14-02 pattern; can remove fallbacks per design-system invariant |
| Re-run `CONTRAST_AUDIT=1 contrast-audit.spec.js` → delta report | Proves zero regression, writes v1.4-CONTRAST-AUDIT.json | LOW | Existing runner, gated env var |
| Dark mode parity check (`[data-theme="dark"]` block) | Phase 82 pitfall — light/dark must ship together | MEDIUM | Non-negotiable per PROJECT.md Key Decisions |
| Update v1.3-A11Y-REPORT.md → "CONFORME" on contrast dimension | The declared conformance gap from v1.3 | LOW | §6 currently "NON CONFORME (déféré)" |

### Differentiators

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| @property registration for new/changed tokens | Interpolation safety; precedent in Phase 84 | LOW | Only 8 core tokens, not derived |
| Automated contrast linting in CI (not just audit) | Catches regressions before merge | MEDIUM | Promote `contrast-audit.spec.js` into chromium CI matrix |
| Contrast budget documentation ("what ratio each token class targets") | Future designers understand the math | LOW | Short markdown next to design-system.css |
| oklch color-mix hover/active derivations for newly-darkened tokens | Keep Phase 82-01 interactive cue language consistent | LOW | Already the house pattern |

### Anti-Features

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Per-page CSS overrides to fix contrast | "Fix the violating node without touching tokens" | Disperses fix across 316 sites, guarantees drift | Fix at token layer only |
| Abandon warm-neutral identity for high-contrast cool palette | "Guaranteed AA" | Destroys brand identity validated in v1.1/v1.3 | Nudge L* only, preserve hue |
| Full redesign of muted states | "Clean slate" | Scope explosion, breaks Phase 82 palette shift | Targeted L* adjustment on 4 tokens |
| Target WCAG AAA (7:1) | "Even better a11y" | Out of scope per v1.3 report §6, massive hue rework | Stay on AA per declared target |

### MVP vs Stretch

- **MVP:** 4 token adjustments + critical-tokens sync + Shadow DOM fallback refresh + contrast runner re-pass at 0 violations + A11Y-REPORT status flipped to CONFORME
- **Stretch:** CI gating, budget docs, @property expansion

**Complexity:** MEDIUM overall. Low technical risk, high visual-review gatekeeping risk (needs design ack per Key Decisions).

---

## Chantier 2 — V2-OVERLAY-HITTEST Sweep

**Starting state:** Phase 16-02 fixed 2 reactive instances (`.op-quorum-overlay`, `.op-transition-card`) where `[hidden]` + `display: flex` leaves the element visually hidden but still intercepting pointer events. Codebase-wide sweep pending.

### Table Stakes

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Grep audit of `[hidden]` + `display:` rules across `public/**/*.css` | Find every occurrence | LOW | `rg '\[hidden\]'` + cross-ref `display:` |
| Grep audit of inline JS setting `style.display` on elements also `[hidden]` | Inline `display:flex` bypasses `[hidden]` too | LOW | Scan JS for that pattern |
| Global CSS rule `[hidden] { display: none !important }` in design-system base layer | One-line defensive baseline | LOW | `!important` is normally disliked, but `[hidden]` is a browser invariant |
| Playwright smoke test: iterate all `[hidden]` elements, assert computed `display: none` or `pointer-events: none` | Prevents regression | MEDIUM | New small spec |
| Document pattern in PITFALLS | Prevent re-introduction | LOW | Short note |

### Differentiators

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Custom stylelint rule flagging `[hidden]` + `display:` same selector | Compile-time prevention | MEDIUM | No built-in rule exists |
| Migrate overlays to `<dialog>` element + `showModal()` | Browser-native hit-testing + focus mgmt | HIGH | Big refactor, defer to v1.5+ |

### Anti-Features

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Replace `[hidden]` with `.hidden` class globally | "Avoid the trap entirely" | Loses semantic HTML attribute, hurts a11y tree | Keep `[hidden]`, enforce via CSS base |
| `visibility: hidden` per-component workaround | "Skip the conflict" | Still intercepts pointer events unless also `pointer-events: none` | Use `[hidden] { display: none !important }` |

### MVP vs Stretch

- **MVP:** Grep audit + global CSS defensive rule + Playwright smoke test + PITFALL doc
- **Stretch:** Stylelint rule, `<dialog>` migration

**Complexity:** LOW. Risk: discovering `!important` on `[hidden]` breaks some intentional override — mitigate by running full Playwright suite end-to-end.

---

## Chantier 3 — V2-TRUST-DEPLOY (Auditor + Assessor Playwright Fixtures)

**Starting state:** `trust.htmx.html` tested via `loginAsAdmin` fallback because auditor/assessor role fixtures don't exist. System roles: admin, operator, auditor, viewer, president. Meeting roles: president/assessor/voter. `/trust` is admin+auditor (Phase 14 — assessor is meeting-scoped, not system-wide).

### Table Stakes

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| `loginAsAuditor` Playwright helper | Symmetry with existing `loginAsAdmin/Operator/Voter` | LOW | Seeded `auditor@test`, wire into `tests/e2e/helpers/auth.js` |
| `loginAsAssessor` helper (meeting-scoped) | Assessor is meeting role, helper must assign meeting role | MEDIUM | Requires seeded meeting + assessor assignment |
| Seed: auditor user + assessor-on-meeting assignment | Helpers need backing data | LOW | Extend fixtures SQL |
| Replace `loginAsAdmin` fallback in trust.htmx.html axe spec | The actual gap | LOW | One-line swap once helper exists |
| Positive test: auditor can view `/trust`, cannot mutate | Verifies RBAC correctness | MEDIUM | DOM check + API 403 on mutation endpoint |
| Negative test: viewer/voter blocked from `/trust` | Ensures middleware not bypassable | LOW | 2 cases |

### Differentiators

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Matrix spec: every role × every route → expected status | Catches RBAC drift globally | HIGH | Large data-driven test, v1.5 |
| Visual snapshot diff per role on `/trust` | Catches role-dependent UI leaks | MEDIUM | Depends on visual regression milestone |

### Anti-Features

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Expand assessor to system-wide RBAC | "Unify the roles" | Contradicts Phase 14 decision, breaks meeting model | Keep assessor meeting-scoped |
| Mock RBAC in tests | "Faster than seeding" | Doesn't exercise AuthMiddleware where bugs live | Real fixtures, real session |

### MVP vs Stretch

- **MVP:** auditor helper + assessor helper + seed + trust axe spec fallback replaced + one positive role test
- **Stretch:** Role × route matrix, visual snapshots

**Complexity:** MEDIUM. Bulk is seed-data plumbing + understanding existing fixture harness.

---

## Chantier 4 — V2-CSP-INLINE-THEME (Externalize or Nonce Inline Theme Scripts)

**Starting state:** Inline `<script>document.documentElement.dataset.theme = ...</script>` run pre-paint to avoid flash-of-wrong-theme. These violate strict CSP `script-src` without `unsafe-inline`. Phase 12 documented Docker CSP blocking inline scripts (DISP-01 adaptation). Inline `critical-tokens` **styles** are a separate directive (`style-src`) but face the same concern.

### Table Stakes

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Inventory all inline `<script>` blocks across 22 htmx.html files + login.html | Know the surface area | LOW | Grep `<script>` without `src=` |
| Per-request nonce generator in PHP boot | Nonce must be fresh per response | LOW | `bin2hex(random_bytes(16))`, inject via `HtmlView::render()` |
| CSP header emission with `'nonce-XXX'` in script-src | The actual enforcement | MEDIUM | Extend `SecurityProvider`; verify interaction with existing header middleware |
| All inline scripts carry `nonce="<?= $nonce ?>"` | Browser match | MEDIUM | Template edits across 22 files |
| Remove `'unsafe-inline'` from script-src | Proves nonce works | LOW | Single config edit |
| Playwright assertion: no console CSP violations on 22 pages | Regression gate | MEDIUM | `page.on('console')` listener in axe spec |
| Nonce inline `critical-tokens` styles (or externalize) | Same problem for style-src | MEDIUM | Inline styles prevent FOUC — prefer nonce |

### Differentiators

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Hash-based CSP for static inline blocks | No per-request state | MEDIUM | Only works if content is truly static; theme init reads `localStorage`, so nonce is correct |
| `report-uri` / `report-to` endpoint for CSP violations | Observability in prod | MEDIUM | Useful but not blocking v1.4 |
| Externalize theme-init into cacheable `theme-init.js` | No inline at all | LOW-MEDIUM | Tradeoff: extra HTTP request pre-paint, FOUC risk if deferred |
| `strict-dynamic` for script resilience | Modern CSP pattern | MEDIUM | Likely overkill here |

### Anti-Features

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Keep `'unsafe-inline'` "for compatibility" | "It works today" | Defeats entire purpose of CSP | Nonce is the answer |
| Globally hash-allow every inline block | "Set-and-forget" | Breaks on any byte change | Nonce for dynamic, hash only for truly static |
| Move theme detection server-side via cookie | "No inline script needed" | Round-trip, breaks system preference detection | Keep client-side, nonce it |

### MVP vs Stretch

- **MVP:** nonce generator + header emission + template attribute sweep + `unsafe-inline` removed + Playwright CSP-clean assertion
- **Stretch:** report-uri, hash strategy, externalization, strict-dynamic

**Complexity:** MEDIUM-HIGH. Mostly mechanical but security-critical — missing one inline block = production white screen. Needs careful rollout.

Sources: [MDN — CSP script-src](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/script-src), [content-security-policy.com — Nonce](https://content-security-policy.com/nonce/), [Stay safe, no more unsafe-inline](https://centralcsp.com/articles/unsafe-inline)

---

## Chantier 5 — HTMX 2.0 Upgrade

**Starting state:** HTMX 1.x in production. 2.0 shipped June 2024; upstream provides `htmx-1-compat` extension for incremental migration.

### Breaking Changes (Verified Upstream)

1. **Legacy `hx-on` syntax removed** → migrate to wildcard `hx-on:eventname` syntax
2. **Extensions no longer bundled** → each extension needs its own `<script>` tag
3. **DELETE requests use query params**, not form-encoded body
4. **`htmx.makeFragment()` always returns `DocumentFragment`**
5. IE support dropped (non-issue)

### Table Stakes

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Grep inventory of every `hx-on` occurrence in `public/**/*.html` | Know migration surface | LOW | Likely dozens |
| Migrate `hx-on="click:..."` → `hx-on:click="..."` | Breaking change | MEDIUM | Mechanical, must be visually audited |
| Inventory of currently-loaded extensions | Find what needs explicit `<script>` | LOW | Check `shell.js`, base layouts |
| Load extensions as individual scripts | New 2.x requirement | LOW | Per-extension `<script>` tag |
| Audit all `DELETE` calls (PHP endpoints + client) | Body → query param change | MEDIUM | PHP `$_POST` won't see DELETE bodies |
| Update server-side DELETE handlers to read query params | The actual breakage | MEDIUM | Maybe none if already RESTful |
| Audit `htmx.makeFragment()` callers | Return type narrowed | LOW | Likely 0 internal callers |
| Install `htmx-1-compat` extension as safety net during rollout | Upstream-blessed fallback | LOW | Remove after validation |
| Version bump in `package.json` / CDN URL / vendored copy | The ship | LOW | Depends on current distribution |
| Full Playwright regression run | Guards happy paths | LOW | Existing specs |
| Targeted HTMX 2.0 smoke spec (hx-on:click etc. fire) | Proves new syntax works | LOW | Small dedicated spec |

### Differentiators

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Remove `htmx-1-compat` after stable run | Lean bundle | LOW | Post-validation |
| Adopt new 2.x features (view-transition, etc.) | Modern UX | MEDIUM | Out of debt-milestone scope |
| SRI hashes on HTMX script tag | Supply chain hardening | LOW | Good hygiene |

### Anti-Features

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Skip 2.0, jump to 4.x | "Avoid double migration" | 4.x is alpha/unreleased | 1.x → 2.x now, evaluate 4.x later |
| Keep 1-compat extension forever | "Why fix what works" | Defeats upgrade, extra bundle weight | Remove after 1 milestone of stability |
| Refactor all HTMX patterns during upgrade | "While we're in there" | Scope explosion | Minimum changes for 2.x compatibility only |

### MVP vs Stretch

- **MVP:** hx-on migration + extensions split + DELETE audit + 1-compat safety net + version bump + regression clean
- **Stretch:** Remove 1-compat, SRI, new 2.x features

**Complexity:** MEDIUM. Mechanical but "mechanical across 22 pages" = nonzero risk of missed occurrences. `hx-on` is the dominant category per PROJECT.md.

Sources: [HTMX 1.x → 2.x Migration Guide](https://htmx.org/migration-guide-htmx-1/), [htmx 2.0.0 release](https://htmx.org/posts/2024-06-17-htmx-2-0-0-is-released/), [htmx-1-compat](https://htmx.org/extensions/htmx-1-compat/)

---

## Chantier 6 — Controller Split (>500 Lines)

**Starting state:** MeetingsController 687, MeetingWorkflowController 559, OperatorController 516, AdminController 510. Previously deferred in v1.0 because "existing tests assert private method existence, making splits disruptive" (PROJECT.md Key Decisions). MeetingReportsController and MotionsController also >700 lines (noted for Revisit).

### Table Stakes

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Per-controller responsibility breakdown | Know what belongs where before cutting | LOW | One-shot analysis per file |
| Extract business logic into Services (constructor DI nullable) | Established AgVote pattern (ImportService precedent, 149-line controller result) | HIGH | One service per concern |
| Keep controller as pure HTTP orchestrator | Target < 300 lines per controller | MEDIUM | Mirrors ImportController pattern |
| Rewrite tests to assert behavior via public service methods, not private controller methods | Unblocks the original deferral | HIGH | Bulk of the work per Key Decisions |
| Preserve existing API contracts (URLs, payloads, error codes) | Non-negotiable per CLAUDE.md compatibility | LOW | Just don't change routes |
| Per-split targeted phpunit runs | CLAUDE.md test discipline | LOW | `vendor/bin/phpunit tests/Unit/XxxTest.php` per split |
| Preserve AbstractController for API, HtmlView for HTML | Architecture invariant from CLAUDE.md | LOW | Don't cross-pollinate |

### Differentiators

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Also split MeetingReports (>700) + Motions (>700) | Address the other listed deferrals | HIGH | Explicit Revisit from PROJECT.md |
| Extract shared repository traits (like Motion* pattern) | Scale the Traits approach | MEDIUM | Already an established pattern |
| Introduce interfaces for injected services | Test double clarity | MEDIUM | Nice-to-have; concrete classes work fine |

### Anti-Features

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Migrate to Symfony/Laravel framework | "Solve it once" | Explicitly out of scope in PROJECT.md | Incremental refactor only |
| Split by HTTP verb | "Clean REST" | Loses cohesion, multiplies files | Split by domain sub-concern (MeetingsSetup/MeetingsRun) |
| Rewrite tests from scratch | "While we're in there" | Loses coverage during migration | Move tests alongside code, keep assertions |
| Change API shapes during split | "Clean up the surface" | Breaks clients, violates CLAUDE.md compatibility | Behavior-preserving refactor only |

### MVP vs Stretch

- **MVP:** Split 4 named controllers (Meetings 687, MeetingWorkflow 559, Operator 516, Admin 510) into controller + service pairs, update tests, zero API regressions
- **Stretch:** Also tackle MeetingReportsController + MotionsController (>700)

**Complexity:** HIGH. Biggest chantier by far. Test-rewriting friction is the real cost. Risk: hitting CLAUDE.md "3 test executions max per task" limit if tests are flaky.

---

## Cross-Chantier Feature Dependencies

```
Chantier 5 (HTMX 2.0)
    └──depends-on──> Chantier 2 (Overlay hittest)
         └── hx-on migration touches overlay event handlers;
             fix hittest pattern first so new handlers don't inherit broken state.

Chantier 4 (CSP nonce)
    └──depends-on──> Chantier 1 (Contrast tokens)
         └── critical-tokens inline STYLES may need re-nonced after
             token edits; doing tokens first avoids re-nonce churn.

Chantier 3 (Trust fixtures)
    └──enhances──> Chantiers 1, 2, 5
         └── Better fixtures = better regression signal for
             every other chantier's Playwright validation.

Chantier 6 (Controller split)
    └──independent──> all others
         └── PHP layer only, zero frontend interaction.
             Can run as parallel track.

Chantier 1 (Contrast)
    └──conflicts──> visual regression milestone (separate per PROJECT.md)
         └── Token changes invalidate any visual baselines —
             coordinate or explicitly rebaseline post-v1.4.
```

### Dependency Notes

- **HTMX 2.0 after Overlay sweep:** Fixing hittest first removes a category of flaky behaviors that would corrupt HTMX migration validation.
- **CSP after Contrast:** Avoid recomputing style nonces while token churn is ongoing.
- **Fixtures (Chantier 3) early:** Cheap accelerator — schedule it first to benefit every downstream Playwright check.
- **Controllers parallelize:** No coupling with the other 5 chantiers — can be a parallel track.

---

## MVP Definition — v1.4 Milestone

### Launch With (v1.4 MVP)

Direct user / security impact — MUST ship.

- [ ] **Chantier 1 Contrast MVP** — 4 token adjustments + critical-tokens sync + Shadow DOM refresh + zero-violation re-run + A11Y-REPORT flipped to CONFORME
- [ ] **Chantier 5 HTMX 2.0 MVP** — hx-on migration + extensions split + DELETE audit + 1-compat safety net + regression clean
- [ ] **Chantier 4 CSP nonce MVP** — nonce generator + header + template sweep + `unsafe-inline` removed + Playwright CSP-clean

### Add After Core (v1.4 stretch, fit if time)

- [ ] **Chantier 2 Overlay sweep** — grep audit + global CSS rule + Playwright smoke (small)
- [ ] **Chantier 3 Trust fixtures** — auditor + assessor helpers + seed + fallback removed (small)
- [ ] **Chantier 6 Controller split (4 of 6)** — Meetings, MeetingWorkflow, Operator, Admin

### Future Consideration (v1.5+)

- [ ] Chantier 6 extension to MeetingReports + Motions (>700 lines)
- [ ] HTMX 4.x evaluation once upstream stable
- [ ] Role × route RBAC matrix spec
- [ ] `<dialog>` element migration for all overlays
- [ ] CSP report-uri telemetry

---

## Feature Prioritization Matrix

| Chantier | User Value | Implementation Cost | Priority |
|----------|------------|---------------------|----------|
| 1. Contrast AA | HIGH (a11y compliance, declared gap) | MEDIUM | **P1** |
| 5. HTMX 2.0 | MEDIUM (supply chain + long-term) | MEDIUM | **P1** |
| 4. CSP nonce | HIGH (security hardening) | MEDIUM-HIGH | **P1** |
| 2. Overlay hittest | MEDIUM (prevents real bugs) | LOW | **P2** |
| 3. Trust fixtures | LOW (test infra only) | MEDIUM | **P2** |
| 6. Controller split | LOW (code quality only) | HIGH | **P2** |

**Priority key:**
- P1: Must ship in v1.4 (MVP)
- P2: Should ship in v1.4 if time permits (stretch)
- P3: v1.5+

---

## Milestone-Level Anti-Features

| Anti-Feature | Why It Tempts | Why Bad for v1.4 | Alternative |
|--------------|---------------|------------------|-------------|
| Bundle v1.4 with visual regression testing milestone | "Fix it all at once" | Contrast changes invalidate baselines — circular dependency | Ship v1.4 first, rebaseline visual regression after |
| Add new features "since we're touching the code" | Opportunism | Debt milestone discipline; violates PROJECT.md scope | Explicit debt-only scope, log ideas for v1.5 |
| Fix contrast node-by-node | Tempting because axe violations are per-node | Guarantees drift, dilutes design intent | Token-layer fixes only |
| Skip HTMX 1-compat safety net | "Clean cut migration" | Increases rollback cost if post-deploy bug found | Ship with compat layer, remove next milestone |
| Treat CSP as "just add header" | Underestimate | Missing one inline block = white screen in prod | Full template audit + CSP-clean Playwright assertion |
| Change API shapes during controller split | Refactor fever | Violates CLAUDE.md compatibility, breaks clients | Behavior-preserving only |

---

## Sources

- **Internal artifacts (HIGH):**
  - `/home/user/gestion_votes_php/.planning/PROJECT.md` — scope, constraints, key decisions, tech debt carried forward
  - `/home/user/gestion_votes_php/.planning/STATE.md` — accumulated decisions, Phase 82/84 token history
  - `/home/user/gestion_votes_php/.planning/v1.3-A11Y-REPORT.md` — contrast audit §3, 316 nodes / 42 pairs / 6 dominant pairs
  - `/home/user/gestion_votes_php/.planning/v1.3-CONTRAST-AUDIT.json` — per-page per-node baseline
  - `/home/user/gestion_votes_php/CLAUDE.md` — test discipline, PHP conventions, DI patterns, namespaces
- **HTMX 2.0 upgrade (HIGH, verified):**
  - [HTMX 1.x → 2.x Migration Guide](https://htmx.org/migration-guide-htmx-1/)
  - [htmx 2.0.0 release notes](https://htmx.org/posts/2024-06-17-htmx-2-0-0-is-released/)
  - [htmx-1-compat extension](https://htmx.org/extensions/htmx-1-compat/)
  - [htmx CHANGELOG](https://github.com/bigskysoftware/htmx/blob/master/CHANGELOG.md)
- **CSP nonce strategy (HIGH, verified):**
  - [MDN — CSP script-src](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/script-src)
  - [content-security-policy.com — Nonce](https://content-security-policy.com/nonce/)
  - [Stay safe, no more unsafe-inline](https://centralcsp.com/articles/unsafe-inline)

---

*Feature research for: AgVote v1.4 tech debt remediation milestone*
*Researched: 2026-04-09*
