# Phase 1: Palette et Tokens - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Replace the warm stone/parchment color palette with a modern cool slate/gray palette in design-system.css. Migrate persona colors from raw hex to oklch. Ensure dark mode continues to work.

</domain>

<decisions>
## Implementation Decisions

### Target Palette (UI-01)
- **D-01:** Background: `--stone-200` (#EDECE6, hue 95) → slate equivalent `#f1f5f9` (hue ~220)
- **D-02:** Surface: `--stone-50` (#FAFAF7) → `#f8fafc` (near-white cool)
- **D-03:** Surface-raised stays pure white `oklch(1 0 0)` — no change needed
- **D-04:** Target aesthetic: Tailwind slate palette — clean, professional, modern SaaS

### Token Migration (UI-02)
- **D-05:** Replace `--stone-*` variable VALUES in-place — all references auto-update
- **D-06:** Rename the CSS custom properties from `--stone-*` to `--slate-*` for clarity
- **D-07:** Text tokens: `--stone-900` (#151510) → slate-900 `#0f172a`, `--stone-700` (#52504A) → slate-700 `#334155`
- **D-08:** Border tokens: `--stone-400` (#CDC9BB) → slate-300 `#cbd5e1`, `--stone-500` (#BCB7A5) → slate-400 `#94a3b8`
- **D-09:** Hue shift: stone (85-95) → slate (~220) across all oklch values

### Persona Colors (UI-03)
- **D-10:** Convert light-mode persona hex to oklch: e.g., `#6366f1` → `oklch(0.55 0.24 265)`
- **D-11:** Keep dark-mode persona colors as-is — they already use color-mix(oklch) pattern
- **D-12:** Subtle variants: convert hex subtle backgrounds to oklch too

### Dark Mode
- **D-13:** Dark mode bg is already at hue 78 (cooler than light mode hue 95) — adjust to ~220 for consistency
- **D-14:** Verify dark mode text/border tokens transpose correctly after migration

### Claude's Discretion
- Exact oklch values for each slate step (must maintain visual hierarchy)
- Whether to keep dual hex+oklch declarations or oklch-only
- Minor adjustments to semantic colors (success, warning, danger) if needed for contrast on new background

</decisions>

<canonical_refs>
## Canonical References

### Requirements
- `.planning/REQUIREMENTS.md` — UI-01, UI-02, UI-03

### Key Files
- `public/assets/css/design-system.css` lines 140-160 — stone palette definition
- `public/assets/css/design-system.css` lines 320-350 — light mode semantic tokens
- `public/assets/css/design-system.css` lines 620-640 — dark mode semantic tokens
- `public/assets/css/design-system.css` lines 430-460 — persona colors light mode
- `public/assets/css/design-system.css` lines 715-735 — persona colors dark mode

</canonical_refs>

<code_context>
## Existing Code Insights

### Current Stone Palette (light mode)
```
--stone-50:  #FAFAF7  oklch(0.969 0.006 95)
--stone-100: #F2F0EB  oklch(0.950 0.009 95)
--stone-200: #EDECE6  oklch(0.922 0.013 95)  ← main background
--stone-300: #E5E3D8  oklch(0.893 0.017 90)
--stone-400: #CDC9BB  oklch(0.830 0.022 87)
--stone-500: #BCB7A5  oklch(0.760 0.028 85)
--stone-600: #857F72  oklch(0.590 0.022 82)
--stone-700: #52504A  oklch(0.420 0.012 78)  ← main text
--stone-800: #3A3832  oklch(0.310 0.014 78)
--stone-900: #151510  oklch(0.180 0.012 75)
```

### Target Slate Palette
```
--slate-50:  #f8fafc  oklch(0.984 0.003 247)
--slate-100: #f1f5f9  oklch(0.968 0.007 247)
--slate-200: #e2e8f0  oklch(0.929 0.013 255)  ← new background
--slate-300: #cbd5e1  oklch(0.869 0.022 252)
--slate-400: #94a3b8  oklch(0.704 0.04 256)
--slate-500: #64748b  oklch(0.554 0.046 257)
--slate-600: #475569  oklch(0.446 0.043 257)
--slate-700: #334155  oklch(0.372 0.044 257)  ← new text
--slate-800: #1e293b  oklch(0.279 0.041 260)
--slate-900: #0f172a  oklch(0.208 0.042 265)
```

### How Tokens Cascade
- `--color-bg: var(--stone-200)` → used by `.landing`, `.app-main`, body backgrounds
- `--color-surface: var(--stone-50)` → used by `.card`, `.modal`, form backgrounds
- `--color-text: var(--stone-700)` → used by body text, paragraphs
- Changing the `--stone-*` values propagates everywhere automatically

</code_context>

<specifics>
## Specific Ideas

- Use Tailwind's slate scale as reference but adjust for oklch consistency
- The background should be noticeably lighter/cooler than current parchment — users should immediately see the difference
- Sidebar already has its own dark background — sidebar colors may need separate review

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 01-palette-et-tokens*
*Context gathered: 2026-04-20*
