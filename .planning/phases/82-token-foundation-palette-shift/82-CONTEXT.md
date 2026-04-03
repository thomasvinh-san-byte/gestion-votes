# Phase 82: Token Foundation + Palette Shift - Context

**Gathered:** 2026-04-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Promote all semantic color tokens from hex to oklch (via primitive var references), upgrade all 42 color-mix() calls from srgb to oklch, warm up dark mode surfaces to match the warm-neutral identity, compute derived states programmatically, and sync critical-tokens inline styles — all within design-system.css @layer base and .htmx.html critical-token blocks only. No per-page CSS changes.

</domain>

<decisions>
## Implementation Decisions

### oklch Migration Strategy
- Semantic tokens reference primitive vars (e.g., `--color-bg: var(--stone-200)`) rather than inline oklch values — primitives already carry oklch, so semantics inherit perceptual uniformity automatically
- No hex fallbacks for oklch — 95%+ global support, AG-VOTE targets modern browsers
- All 42 `color-mix(in srgb)` calls upgraded to `color-mix(in oklch)` in one pass (success criterion 4 requires zero srgb results)
- All `rgba()` calls in semantic tokens converted to oklch alpha syntax (e.g., `oklch(0.52 0.195 265 / 0.12)`)

### Warm Gray & Palette Tuning
- Stone palette hue range 75-95 kept as-is — already warm-neutral, reads well
- Indigo primary hue 265 unchanged — "officiel et confiance" identity depends on it
- Dark mode surfaces warmed up — shift from cool blue-tinted (hue ~260) to warm-dark (hue 75-80) to align with warm-neutral identity
- Gray chroma stays subtle at 0.006-0.030 — enough warmth to feel intentional, not enough to look beige

### Derived Tokens & Dark Mode Sync
- Derived hover/disabled states computed via `color-mix(in oklch, base%, black/white)` — self-updating when base changes
- Dark mode tokens manually overridden with explicit oklch values — dark mode needs intentional lightness inversion and reduced chroma that auto-derivation can't produce
- All 22 .htmx.html critical-tokens inline blocks updated in the same commit as token changes (prevents flash-of-wrong-color)
- Scope limited to design-system.css + critical-tokens only — per-page CSS hex cleanup deferred to Phase 84 (HARD-01)

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- Stone palette (--stone-50 through --stone-900) with oklch values already defined as duplicate declarations in primitives
- Blue/indigo palette (--blue-50 through --blue-800) with oklch values
- Green, amber, red, purple palettes all have oklch primitives
- 10 derived tint/shade tokens already using color-mix() pattern (lines 357-366)

### Established Patterns
- @layer base, components, v4 cascade ordering
- Semantic tokens in :root block (line 275+) reference hex values directly
- Dark mode overrides in [data-theme="dark"] block (line 588+) with complete token re-declarations
- Persona accent colors with -subtle and -text variants
- Sidebar tokens with rgba() alpha values

### Integration Points
- 22 .htmx.html files with critical-tokens inline `<style>` blocks containing hex values for flash prevention
- 23 Web Components with Shadow DOM — inherit design-system tokens but have fallback hex literals (Phase 84 scope)
- 25 per-page CSS files — some reference primitives directly (Phase 84 scope)

</code_context>

<specifics>
## Specific Ideas

No specific requirements — standard oklch migration with warm-neutral palette preserved.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
