---
gsd_state_version: 1.0
milestone: v1.3
milestone_name: Polish Post-MVP
status: executing
stopped_at: Completed 16-05-PLAN.md — phase 16 done
last_updated: "2026-04-09T09:56:18.702Z"
last_activity: 2026-04-09 -- Completed 16-05-PLAN.md (v1.3-A11Y-REPORT.md, phase 16 done, A11Y-03 satisfied)
progress:
  total_phases: 10
  completed_phases: 3
  total_plans: 10
  completed_plans: 10
  percent: 100
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-09)

**Core value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.
**Current focus:** Phase 17 — Loose Ends Phase 12 (next)

## Current Position

Phase: 16 (accessibility-deep-audit) — COMPLETE
Plan: 5 of 5 done (phase closed)
Status: Phase 16 complete — ready for Phase 17
Last activity: 2026-04-09 -- Completed 16-05-PLAN.md (v1.3-A11Y-REPORT.md, 7 sections, A11Y-03 satisfied)

Progress: [██████████] 100%

## Accumulated Context

### Decisions

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
- [Phase 16]: 16-02: bind-mount public/ into app container via dev-only docker-compose.override.yml — production image bakes public/ read-only, dev edits were invisible
- [Phase 16]: 16-02: ag-searchable-select forwards host aria-label to inner [role=combobox] for accessible name on all consumers
- [Phase 16]: 16-05: v1.3-A11Y-REPORT.md declares partial WCAG 2.1 AA conformance — structural+keyboard conformant, contrast deferred to token remediation phase

### Existing Infrastructure

- design-system.css: 5,258 lines, three @layer stack (base/components/v4), oklch values already present as trailing comments on every primitive
- 23 Web Components with Shadow DOM — inherit tokens but fallback hex literals require manual update after palette changes
- 25 per-page CSS files — ~15 hardcoded hex/rgba values identified: analytics.css, meetings.css, hub.css, vote.css, public.css, users.css
- critical-tokens inline styles in 22 HTML files — 6 hex values that prevent flash-of-wrong-color on load
- color-mix(in srgb, ...) calls in design-system.css — need upgrade to color-mix(in oklch, ...)

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% structural ceiling (3 exit()-based controllers)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

- Phase 84 HARD-03: exact list of .htmx.html files with critical-tokens blocks should be verified before planning (research said 22 files)
- (cleared 2026-04-09) Phase 16-02 blocker resolved: bin/test-e2e.sh runs Playwright in Docker; docker-compose.override.yml now bind-mounts public/ so dev HTML edits are picked up without rebuild.

## Session Continuity

Last session: 2026-04-09T09:56:18.698Z
Stopped at: Completed 16-05-PLAN.md — phase 16 done
Resume file: None
