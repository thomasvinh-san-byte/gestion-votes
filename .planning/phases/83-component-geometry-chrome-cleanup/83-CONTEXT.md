# Phase 83: Component Geometry + Chrome Cleanup - Context

**Gathered:** 2026-04-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Consolidate all border-radius to a single --radius-base token (8px), reduce shadow vocabulary from 9 levels to 3 named levels (sm/md/lg), convert border colors to alpha-based oklch for adaptive depth, and replace ag-spinner with CSS skeleton shimmer on dashboard KPI cards and session list. Design-system.css changes + page CSS + dashboard.js/HTML for shimmer.

</domain>

<decisions>
## Implementation Decisions

### Radius Consolidation
- --radius-base set to 8px (current --radius-lg) — most common value, matches modern UI patterns
- Remove all component-alias tokens (--radius-btn, --radius-card, --radius-panel, --radius-modal, --radius-toast, --radius-tooltip, --radius-tag, --radius-input) — use --radius-base directly everywhere
- Keep --radius-full (9999px) for pill shapes only (badge, chip, avatar) — different semantic intent
- Replace ALL hardcoded border-radius values in component CSS (3px, 6px, 2px, 9px, 50%) with var(--radius-base) or var(--radius-full) as appropriate

### Shadow & Border System
- Reduce shadow scale to 3 named levels: --shadow-sm (current --shadow-xs), --shadow-md (current --shadow-md), --shadow-lg (current --shadow-xl)
- Drop intermediate shadow levels: --shadow-2xs, --shadow-sm (old), --shadow (unnumbered), --shadow-lg (old), --shadow-2xl
- Keep --shadow-inner and --shadow-inset-sm as utility shadows (pressed state, inset fields)
- Keep --shadow-focus and --shadow-focus-danger unchanged (accessibility, not elevation)
- Border alpha approach: oklch(0 0 0 / 0.08) for light mode, oklch(1 1 0 / 0.08) for dark mode — adapts to any background

### Skeleton Shimmer
- Scope: dashboard KPI cards (4) + session list (first 3-5 placeholder rows)
- Implementation: CSS-only @keyframes shimmer with gradient pseudo-element, no JS component
- Trigger: .loading class on container, HTMX adds during swap via hx-indicator
- prefers-reduced-motion: static gray placeholder (no animation), still shows layout structure

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- design-system.css already has 9 shadow levels (lines 406-428) with warm shadow-color
- Dark mode shadow overrides at lines 695-707
- Component radius aliases at lines 526-535
- ag-spinner Web Component at public/assets/js/components/ag-spinner.js

### Established Patterns
- @layer base, components, v4 cascade ordering
- Shadow tokens use rgb(var(--shadow-color) / alpha) pattern
- Radius aliases reference primitive --radius-* vars
- Dashboard uses HTMX for content loading (hx-get, hx-swap, hx-indicator)

### Integration Points
- 25 per-page CSS files use shadow tokens and border-radius
- 23 Web Components with Shadow DOM reference shadow/radius tokens
- dashboard.htmx.html and dashboard.js for shimmer implementation
- operator.htmx.html session list may also benefit from shimmer (but SC#4 only mentions dashboard)

</code_context>

<specifics>
## Specific Ideas

- User wants login page redesigned as 2-panel layout (site name/description + form) — noted but OUT OF SCOPE for this phase
- Shimmer gradient: `linear-gradient(90deg, transparent, oklch(1 0 0 / 0.06), transparent)` sliding left to right

</specifics>

<deferred>
## Deferred Ideas

- Login page 2-panel redesign — user request from Phase 82 checkpoint, should be its own phase
- Shimmer on operator console session list — only dashboard specified in SC#4

</deferred>
