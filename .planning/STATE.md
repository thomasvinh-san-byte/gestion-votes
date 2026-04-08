---
gsd_state_version: 1.0
milestone: v1.2
milestone_name: Bouclage et Validation Bout-en-Bout
status: executing
stopped_at: Completed 08-test-infrastructure-docker/08-02-PLAN.md
last_updated: "2026-04-07T00:05:00.000Z"
last_activity: 2026-04-07 -- Completed 08-02 bin/test-e2e.sh wrapper
progress:
  total_phases: 4
  completed_phases: 0
  total_plans: 3
  completed_plans: 2
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-08)

**Core value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.
**Current focus:** Phase 08 — Test Infrastructure Docker

## Current Position

Phase: 08 (Test Infrastructure Docker) — EXECUTING
Plan: 2 of 3
Status: Executing Phase 08
Last activity: 2026-04-07 -- Completed 08-02 bin/test-e2e.sh wrapper

Progress: [░░░░░░░░░░] 0% (v1.2: 0/4 phases)

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
- [Phase 06-02]: .login-orb changed from position: fixed to position: absolute — orb scoped to brand panel, not viewport
- [Phase 06-02]: Tagline updated to 'Gestion des votes pour votre association' per DESIGN-02 copywriting contract
- [Phase 07-playwright-coverage]: Pin @playwright/test to exact 1.59.1 (no caret) per TEST-03 exact version requirement
- [Phase 07-playwright-coverage]: color-contrast disabled in axeAudit — visual contrast is design-token scope, structural accessibility is the target
- [Phase 07-playwright-coverage]: axeAudit filters to critical/serious violations only — moderate/minor are not CI-blocking
- [Phase 07-01]: Strategy C mandatory for public.htmx.html SSE pages — networkidle can never resolve, use domcontentloaded + waitForSelector
- [Phase 08-test-infrastructure-docker]: Playwright jammy image pinned to v1.59.1 — matches @playwright/test 1.59.1 in package.json, avoids Alpine musl browser hell
- [Phase 08-test-infrastructure-docker]: profiles: [test] gates tests service — never starts on plain docker compose up
- [Phase 08-02]: exec docker compose propagates playwright exit code cleanly — no wrapper masking
- [Phase 08-02]: $* (not $@) inside bash -lc for arg forwarding — $@ produces separate argv tokens that don't compose in inner shell
- [Phase 08-02]: --project=chromium hardcoded in wrapper — enforces chromium-only scope per locked Phase 8 decision

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

## Session Continuity

Last session: 2026-04-07T00:05:00.000Z
Stopped at: Completed 08-test-infrastructure-docker/08-02-PLAN.md
Resume file: None
