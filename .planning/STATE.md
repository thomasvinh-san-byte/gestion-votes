---
gsd_state_version: 1.0
milestone: v1.4
milestone_name: Régler Deferred et Dette Technique
status: executing
stopped_at: Completed 02-01-PLAN.md
last_updated: "2026-04-10T06:08:40.293Z"
last_activity: 2026-04-10 -- Completed 02-01-PLAN.md
progress:
  total_phases: 6
  completed_phases: 1
  total_plans: 5
  completed_plans: 4
  percent: 80
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-09)

**Core value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.
**Current focus:** Phase 02 — overlay-hittest-sweep

## Current Position

Phase: 02 (overlay-hittest-sweep) — EXECUTING
Plan: 2 of 2
Status: Executing Phase 02 (Plan 1 complete)
Last activity: 2026-04-10 -- Completed 02-01-PLAN.md

**Progress:** [████████░░] 80%

## v1.4 Phase Summary

| Phase | Goal | Requirements |
|-------|------|--------------|
| 1 — Contrast AA Remediation | WCAG 2.1 AA conforme sur 316 nœuds via 4 shifts de tokens oklch dual-theme | CONTRAST-01..04 |
| 2 — Overlay Hittest Sweep | Règle base `:where([hidden])!important` + audit codebase-wide | OVERLAY-01..03 |
| 3 — Trust Fixtures Deploy | `loginAsAuditor`/`loginAsAssessor` + endpoint seed test-gated | TRUST-01..03 |
| 4 — HTMX 2.0 Upgrade | Migration 1.x→2.0.6 + `htmx-1-compat` safety net | HTMX-01..05 |
| 5 — CSP Nonce Enforcement | `SecurityProvider::nonce()` + `strict-dynamic`, report-only first | CSP-01..04 |
| 6 — Controller Refactoring | 4 controllers >500 LOC → <300 LOC via ImportService pattern | CTRL-01..05 |

## Accumulated Context

### v1.4 Decisions

- [02-01]: Single :where([hidden]) rule with !important in @layer base replaces all 16 per-selector overrides
- [02-01]: :not([hidden]) selectors in design-system.css (transition reveal animations) intentionally preserved -- positive selectors, not overrides
- [01-01]: Audit hex values (#988d7a, #bdb7a9, #9d9381, #4d72d8) are COMPUTED RGB, not source literals — zero grep matches across public/. Source tokens identified via CSS cascade analysis.
- [01-01]: One --color-text-muted shift (L* 0.648 → 0.47 light, cool 0.45 → warm 0.78 dark) covers 3 of 4 audit families (muted text + wizard step opacity blend + kpi tooltip variant)
- [01-01]: --color-primary left untouched (brand identity); new --color-primary-on-subtle companion token added for chip-on-primary-subtle. Wiring deferred to plan 01-02.
- [01-01]: Plan 01-01 same-commit propagation to 21 critical-tokens inline blocks is a structural no-op — those blocks only declare --color-bg/surface/text. Pitfall #2 trivially neutralised.
- [01-01]: Dark-mode --color-text-muted was itself broken (oklch 0.450 cool too dark on dark bg) — fixed to 0.780 warm hue 82 aligning Phase 82-01 convention.
- [01-02]: Stripped 110 `var(--color-*, #hex)` fallbacks from 16 Web Components via single sed pass — 7/23 components were already clean, zero oklch fallbacks to preserve. Pitfall #1 (stale hex after oklch shift) eliminated.
- [01-02]: Shadow DOM Web Components must never carry hex fallbacks on --color-* tokens; enforced by CI grep gate `grep -rnE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/` → 0.
- [01-02]: --color-primary-on-subtle chip wiring (settings.css) still deferred — moved from 01-02 scope to 01-03 (axe re-run + chip wiring combined).
- [01-02]: --shadow-*, --toast-*, --size-*, --radius-* fallbacks explicitly out of scope; only --color-* tokens were touched.
- [01-03]: --color-text-muted iteratively darkened L* 0.470 -> 0.340 over 5 axe runs to pass AA on all warm surfaces including mid-animation states
- [01-03]: --color-primary darkened L* 0.520 -> 0.480 for reliable white-on-primary button text AA compliance
- [01-03]: Companion on-subtle token pattern adopted for success/accent/purple (mirrors primary-on-subtle from 01-01) — prevents brand token darkening
- [01-03]: opacity CSS rules removed from wizard steps and onboarding labels — opacity multiplicatively degrades contrast, tokens are now dark enough standalone
- [01-03]: Playwright contrast-audit.spec.js must disable browser cache via CDP and wait 500ms for CSS animations to settle before axe analysis
- [01-03]: v1.3-A11Y-REPORT.md updated: "partiellement conforme" -> "CONFORME" for WCAG 2.1 AA
- [v1.4 roadmap]: 6 phases derived from 24 requirements, 1 phase per category — clean boundaries, minimal cross-phase coupling
- [v1.4 roadmap]: Phase numbering reset to 1 (v1.3 phases archived to `.planning/milestones/v1.3-phases/`); `--reset-phase-numbers` mode active
- [v1.4 roadmap]: Build order Contrast → Overlay → Trust → HTMX → CSP → Controllers reconciles STACK/PITFALLS/ARCHITECTURE conflicts. Disjoint file regions minimize merge conflicts.
- [v1.4 roadmap]: Phase 1 first — contrast `<style>` blocks disjoint from HTMX `hx-on` attributes in same `.htmx.html` files; reverse order forces rebase
- [v1.4 roadmap]: Phase 4 MUST precede Phase 5 — `hx-on:*` is inline script, would break under strict CSP `strict-dynamic` if un-migrated
- [v1.4 roadmap]: Phase 5 report-only gate — CSP runs in `Content-Security-Policy-Report-Only` for ≥1 full phase before enforcement flip
- [v1.4 roadmap]: Phase 6 entry gate — pre-split reflection audit (`ReflectionClass`/`hasMethod` grep) on tests is MANDATORY before each controller split (pitfall #6)
- [v1.4 roadmap]: 300 LOC ceiling per extracted service to prevent god-service anti-pattern (pitfall #7)
- [v1.4 roadmap]: CSP nonce lives on `SecurityProvider`, NOT middleware — middleware runs post-routing and is API-only; CSP header must ship from `index.php` before dispatch
- [v1.4 roadmap]: Controller splits mirror ImportService pattern — `final class`, nullable constructor DI, `RepositoryFactory::getInstance()` fallback, no DI container
- [v1.4 roadmap]: Token dual-name safety — never rename tokens in `design-system.css`; only add aliases. Shadow DOM `@property` `initial-value` silently swallows renames (pitfall #3).
- [v1.4 roadmap]: Critical-tokens same-commit rule — `<style id="critical-tokens">` in 22 `.htmx.html` must update atomically with `:root`/`[data-theme="dark"]` (pitfall #2)
- [v1.4 roadmap]: 4 token value edits (`#988d7a`, `#bdb7a9`, `#9d9381`, `#4d72d8` to oklch L* 45-48) fix ~71% of 316 nodes per research

### Decisions (carry-over from v1.3)

- [v10.0 roadmap]: 3 phases derived from 14 requirements — token layer → component geometry → hardening
- [v10.0 roadmap]: Phase 82 changes design-system.css @layer base only; no per-page CSS files touched in this phase
- [v10.0 roadmap]: Dark mode [data-theme="dark"] block and critical-tokens inline styles must update in the same commit as any :root color primitive change (Pitfall 2 from research)
- [v10.0 roadmap]: Token names must never be renamed — add new alongside old to avoid Shadow DOM fallback staleness (Pitfall 3 from research)
- [v10.0 roadmap]: Phase 83 deferred skeleton shimmer scope requires pre-phase audit of which pages use spinners vs. HTMX-managed states
- [v9.0 roadmap]: AgConfirm.ask() is the universal confirmation pattern across all 7 page modules
- [Phase 81-fix-ux]: Shared.openModal() preserved for form-containing modals only
- [Phase 82-01]: color-mix(in oklch) used for all hover/active derivations — perceptually uniform darkening vs srgb
- [Phase 82-01]: --color-accent aliased to var(--purple-600) confirming COLOR-03 accent sparsity at token level
- [Phase 82-token-foundation-palette-shift]: Dark mode surface hue set to 78 (warm-neutral) replacing cool hue ~260 — warm identity now consistent across both modes
- [Phase 82-token-foundation-palette-shift]: Dark mode hover direction uses color-mix(in oklch, base 88%, white) — lightening in dark context is correct interactive cue
- [Phase 83-01]: All component corners unified to --radius-base (8px) — no per-component radius overrides remain
- [Phase 83-01]: Shadow scale reduced 9→3 levels: sm=0.06 opacity, md unchanged, lg=old xl 0.14 opacity
- [Phase 83-01]: --color-border-alpha uses oklch alpha for adaptive depth (black/white based for light/dark modes)
- [Phase 83]: Skeleton KPI height 88px matches kpi-card approx height; .kpi-card-wrapper wrapper enables CSS-only show/hide toggle
- [Phase 84]: @property registered for 8 core color tokens — derived tokens excluded (no var() in initial-value per CSS spec)
- [Phase 84]: HARD-03: all 21 htmx.html critical-tokens blocks updated from hex to oklch — research incorrectly claimed files were already in sync
- [Phase 84]: Token name --color-primary-text is canonical for text on primary backgrounds (not --color-text-on-primary or --color-primary-contrast)
- [Phase 84]: oklch() literals used for rgba(white/black, N) where no semantic token exists
- [Phase 12-page-by-page-mvp-sweep]: Docker CSP blocks inline scripts (script-src without unsafe-inline) — DISP-01 dark theme assertion must be CSP-aware: accept null or dark
- [Phase 14-visual-polish]: Used login page for POLISH-04 hover assertion — no auth required, idempotent
- [Phase 14-visual-polish]: assessor removed from /trust data-requires-role — assessor is a meeting role, /trust is a system-wide audit dashboard (admin+auditor only per POLISH-03 matrix)
- [Phase 14-visual-polish]: POLISH-01: members.js converted to AgToast.show() (27 call sites); adoption now at 9 pages
- [Phase 14-visual-polish]: 14-02: Remove hex fallbacks from var(--token, #hex) in Shadow DOM — tokens guaranteed-present via shell.js load order
- [Phase 16-accessibility-deep-audit]: Phase 16-01: Parametrized axeAudit matrix to 22 pages via PAGES array; extraDisabledRules plumbing ready for per-page waivers (D-10)
- [Phase 16-accessibility-deep-audit]: Phase 16-01: trust.htmx.html uses loginAsAdmin fallback (auditor/assessor not in fixtures) — to validate at baseline run in 16-02
- [Phase 16]: 16-02: baseline captured via Docker (bin/test-e2e.sh), 5 unique rule-ids fixed across admin/public/vote/operator — final 26/26 GREEN, no waivers needed
- [Phase 16]: Phase 16-03: Focus-trap spec injects deterministic ag-modal via page.evaluate() to decouple from fixture-specific modal triggers; shadow DOM check uses two-branch pattern (slotted closest + host.shadowRoot.activeElement) matching ag-modal Light+Shadow hybrid
- [Phase 16]: 16-04: contrast audit runner gated by CONTRAST_AUDIT env; requires Docker playwright image (host libs missing)
- [Phase 16]: 16-05: v1.3-A11Y-REPORT.md declares partial WCAG 2.1 AA conformance — structural+keyboard conformant, contrast deferred to token remediation phase
- [Phase 16]: 16-02: bind-mount public/ into app container via dev-only docker-compose.override.yml — production image bakes public/ read-only, dev edits were invisible
- [Phase 16]: 16-02: ag-searchable-select forwards host aria-label to inner [role=combobox] for accessible name on all consumers
- [Phase 17]: 17-02: document-level eIDAS chip click delegation (panel-visibility independent)
- [Phase 17-loose-ends-phase-12]: LOOSE-01 root cause: loadSettings used POST {action:list} which raced CSRF/middleware; fix swaps to GET ?action=list and adds window.__settingsLoaded handshake
- [Phase 17-loose-ends-phase-12]: Phase 12 SUMMARY audit: 6 findings, 2 already resolved by 17-01/17-02, 3 deferred to v2 (V2-OVERLAY-HITTEST, V2-TRUST-DEPLOY, V2-CSP-INLINE-THEME), 0 fix-now

### Existing Infrastructure

- design-system.css: 5,258 lines, three @layer stack (base/components/v4), oklch values already present as trailing comments on every primitive
- 23 Web Components with Shadow DOM — inherit tokens but fallback hex literals require manual update after palette changes
- 25 per-page CSS files — ~15 hardcoded hex/rgba values identified: analytics.css, meetings.css, hub.css, vote.css, public.css, users.css
- critical-tokens inline styles in 22 HTML files — 6 hex values that prevent flash-of-wrong-color on load
- color-mix(in srgb, ...) calls in design-system.css — need upgrade to color-mix(in oklch, ...)
- v1.3-CONTRAST-AUDIT.json baseline: 316 contrast violations across 22 pages, 42 unique (fg, bg) pairs, dominant `#988d7a` muted-foreground
- 4 fat controllers pending split: MeetingsController 687, MeetingWorkflowController 559, OperatorController 516, AdminController 510
- ImportService pattern validated in v1.0 Phase 3 — reusable blueprint for Phase 6 splits
- SecurityProvider::headers() called before router dispatch — correct injection point for CSP nonce

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% structural ceiling (3 exit()-based controllers)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None — awaiting Phase 1 plan generation.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-04-10T06:08:40.290Z
Stopped at: Completed 02-01-PLAN.md
Resume file: None

**Next action:** Approve visual checkpoint (Task 4 of 01-03) then `/gsd:transition` to Phase 2

**Files written this session:**

- `.planning/ROADMAP.md` (v1.4 section appended)
- `.planning/STATE.md` (updated with v1.4 context)
- `.planning/REQUIREMENTS.md` (traceability section populated)
