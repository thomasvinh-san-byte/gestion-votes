# Research Summary: AgVote v1.4 — Régler Deferred et Dette Technique

**Synthesized:** 2026-04-09
**Sources:** STACK.md, FEATURES.md, ARCHITECTURE.md, PITFALLS.md
**Confidence:** HIGH

## Executive Summary

v1.4 is a **zero-new-dependency debt milestone** covering six surgical chantiers: contrast AA remediation, `[hidden]`+`display:flex` sweep, Playwright auditor/assessor fixtures, CSP nonce for inline theme scripts, HTMX 1.x → 2.0.6 upgrade, and splitting four >500-line controllers. Every chantier is deliverable with the existing stack plus one upstream version bump.

**Reconciled build order** (resolving conflicts between STACK, PITFALLS, ARCHITECTURE):
Contrast → Overlay → Trust (parallelizable, independent, low risk) → **HTMX 2.0** (unblocks CSP strategy) → **CSP nonce** (safe once `hx-on` migrated) → Controllers (parallel Plane B).

**Key risks:** HTMX 2.0 silent breaks (`hx-on` case, DELETE body→query, extensions unbundled) mitigated by `htmx-1-compat` safety net; controller splits breaking reflection-based tests mitigated by mandatory pre-split inventory.

## Stack Decisions

**Zero new packages.** Explicit rejects: `spatie/laravel-csp`, `paragonie/csp-builder`, `culori`/`chroma-js`/`style-dictionary`/PostCSS, Playwright >1.59.1, HTMX 4.x alpha.

**Existing technologies reused:**
- Native CSS `oklch()` + `color-mix(in oklch, ...)` — Baseline 2023, no build step
- axe-core 4.10 via `@axe-core/playwright` — re-runs contrast audit for delta
- PHP `random_bytes(16)` + `SecurityProvider::headers()` — ~40-line CSP nonce
- htmx.org 2.0.6 — only real version bump
- `htmx-1-compat` extension — safety net during migration
- Existing `loginAs*` Playwright helpers + `RepositoryFactory` nullable DI

## Feature Scope

**MVP (must ship):**

| Chantier | Deliverables |
|---|---|
| 1. Contrast AA | 4 token adjustments (`#988d7a`, `#bdb7a9`, `#9d9381`, `#4d72d8`) to oklch L* 45-48, critical-tokens sync 22 htmx.html, Shadow DOM fallback refresh, axe zero-violation, A11Y-REPORT → CONFORME |
| 5. HTMX 2.0 | hx-on kebab-case migration, extensions split, DELETE handler audit, htmx-1-compat loaded, Playwright regression clean |
| 4. CSP nonce | `SecurityProvider::nonce()` + header, template sweep, `'unsafe-inline'` removed, Playwright CSP-clean |

**Stretch:**

| Chantier | Deliverables |
|---|---|
| 2. Overlay sweep | `:where([hidden]) { display: none !important }` base rule + codebase audit |
| 3. Trust fixtures | `loginAsAuditor` + `loginAsAssessor` + test-gated seed endpoint |
| 6. Controller splits | 4 controllers (Meetings 687, MeetingWorkflow 559, Operator 516, Admin 510) → thin HTTP orchestrators + final services |

**Deferred to v1.5+:** MeetingReports + Motions splits, HTMX 4.x, role×route matrix, `<dialog>` migration, CSP `report-uri`, visual regression milestone.

## Architecture

Three planes with minimal overlap:
- **Plane A — Design tokens (CSS / Shadow DOM):** Chantiers 1, 4 (critical-tokens), 5 (shared files)
- **Plane B — PHP HTTP layer:** Chantiers 4 (CSP), 6 (controller splits)
- **Plane C — Test harness:** Chantiers 2, 3, 5

**Major components:**

1. **`SecurityProvider::nonce()`** — static request-scoped accessor generated in `SecurityProvider::headers()` BEFORE router dispatch. NOT middleware (middleware is API-only, bypassed by HTML endpoints).
2. **New services from controller splits** — `MeetingLifecycleService`, `MeetingWorkflowService`, `OperatorWorkflowService`, `AdminService`. Each `final class` with `?Repo = null` constructor DI resolved via `RepositoryFactory::getInstance()`. Controllers become thin HTTP orchestrators <300 LOC. Routes preserved by handler-class swap.
3. **Playwright fixtures** — `loginAsAuditor`, `loginAsAssessor` helpers backed by test-gated seed endpoint (`POST /api/v1/test/seed-user`, 404 in production via route-registration gate) that builds full graph (user → tenant → meeting → meeting-role).
4. **Token dual-name safety** — NEVER rename tokens; add `--color-X-v2` aliases. Critical-tokens inline blocks in 22 `.htmx.html` updated in SAME commit as `:root`/`[data-theme="dark"]`.
5. **`:where([hidden]) { display: none !important }`** — single base rule; zero specificity via `:where()`, beats components via `!important`.

## Critical Pitfalls (15 total; top 10)

1. **Shadow DOM hex fallbacks go stale after oklch shift** — remove `var(--token, #hex)` second operand in 23 Web Components; CI grep gate
2. **critical-tokens inline blocks drift from `:root`** — enforce same-commit rule
3. **Token renames silently break Shadow DOM** — hard rule: only add aliases, never rename
4. **HTMX 2.0 `hx-on:*` case sensitivity silently drops handlers** — outcome-based smoke tests
5. **CSP strict mode blocks HTMX `hx-on:*`** — CSP enforcement must follow HTMX migration; report-only first
6. **Controller splits break reflection-based tests** — mandatory pre-split `ReflectionClass`/`hasMethod` grep
7. **Mechanical controller split → god-service** — 300 LOC ceiling per service
8. **Test fixtures leak to prod** — gate at route-registration time, not runtime
9. **Assessor fixture lacks meeting-scoped role** — fixture must build full graph (meeting role)
10. **`[hidden]` specificity wars** — `:where([hidden]) !important` base rule FIRST, audit SECOND

## Phase-by-Phase Implications

### Phase 1 — Contrast Remediation
Zero dependencies, largest user-visible surface, disjoint file regions from later phases. Delivers 4 token adjustments, critical-tokens sync, Shadow DOM fallback removal, axe clean, A11Y-REPORT → CONFORME. Dark mode in same commit as light. Avoids pitfalls #1, #2, #3.

### Phase 2 — V2-OVERLAY-HITTEST Sweep
Pure CSS, parallelizable with Phase 3. Delivers `:where([hidden]) !important` base rule + grep audit + Playwright smoke (computed `display: none`). Avoids pitfall #10.

### Phase 3 — V2-TRUST-DEPLOY Fixtures
Additive only. Delivers `loginAsAuditor` + `loginAsAssessor` + `POST /api/v1/test/seed-user` (404 in prod) + per-test tenant isolation. Replaces trust.htmx.html fallback. Avoids pitfalls #8, #9.

### Phase 4 — HTMX 2.0 Upgrade
Must precede CSP enforcement. Contrast already done → minimal merge conflict. Delivers htmx.org 1.x → 2.0.6, full `hx-on` inventory + kebab-case rewrite, extensions loaded individually, DELETE handler audit, `htmx-1-compat` safety net, outcome smoke tests, SSE stream smoke test. Avoids pitfalls #4, #5.

### Phase 5 — V2-CSP-INLINE-THEME Nonce
Follows Phase 4 so `hx-on` already migrated. **Report-only first** for one full phase before enforcement flip. Delivers `SecurityProvider::nonce()` + header, `HtmlView::render()` injection, 22 htmx.html nonced, `'unsafe-inline'` removed, Playwright console listener asserting zero violations.

### Phase 6 — Controller Refactoring (parallelizable with any earlier phase)
Pure Plane B, fully independent. Last because highest LOC churn and reflection-test risk. Delivers 4 controllers split via ImportService pattern → thin HTTP orchestrator (<300 LOC) + final class service with nullable DI. Pre-split reflection inventory is entry gate. Avoids pitfalls #6, #7.

## Research Flags

**Need deeper research (`/gsd:research-phase`):**
- **Phase 4 (HTMX 2.0):** re-verify `htmx.org@2.0.6` latest; full grep inventory; empirical `strict-dynamic` + `unsafe-hashes` test across browser matrix
- **Phase 5 (CSP nonce):** full inline `<script>`/`<style>` inventory; nonce vs externalization per block
- **Phase 6 (Controller splits):** per-controller responsibility inventory; reflection grep per target test file

**Skip research-phase:**
- Phase 1 (Contrast): v1.3-A11Y-REPORT.md §3 + v10.0 Phase 82/84 history
- Phase 2 (Overlay): single-rule fix, understood since v1.3 Phase 16-02
- Phase 3 (Trust fixtures): existing `loginAs*` pattern

## Confidence Assessment

| Area | Confidence |
|------|------------|
| Stack | HIGH — zero new deps, migration guide verified, OKLCH baseline 2023 |
| Features | HIGH — grounded in v1.3-A11Y-REPORT + CONTRAST-AUDIT + PROJECT debt list |
| Architecture | HIGH — ImportService precedent + SecurityProvider documented |
| Pitfalls | HIGH — 15 pitfalls from v10.0 Phase 82/84 + v1.3 Phase 16 post-mortems |

**Overall:** HIGH

### Gaps (MEDIUM confidence)
- HTMX 2.0 Shadow DOM interaction — Context7 query during Phase 4 research
- Overlay sweep scope — unknown until grep pass
- Controller split test disruption — unquantified reflection count
- `color-mix(in oklch)` WebKit rendering — may explain Phase 15 deferrals
- HTMX + strict CSP empirical behavior — report-only phase before enforcement

## Sources

- `.planning/PROJECT.md`, `.planning/STATE.md`, `.planning/v1.3-A11Y-REPORT.md`, `.planning/v1.3-CONTRAST-AUDIT.json`, `CLAUDE.md`
- https://htmx.org/migration-guide-htmx-1/
- https://htmx.org/posts/2024-06-17-htmx-2-0-0-is-released/
- https://htmx.org/extensions/htmx-1-compat/
- https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/script-src
- https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html
- https://webaim.org/articles/contrast/
