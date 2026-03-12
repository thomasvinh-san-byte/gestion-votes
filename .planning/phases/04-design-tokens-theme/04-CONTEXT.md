# Phase 4: Design Tokens & Theme - Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Align all CSS custom properties in design-system.css with the exact values from wireframe v3.19.2 "Acte Officiel". Ensure dark/light theme switching works without visual artifacts. Add missing tokens. This phase only touches design-system.css — layout, components, and page CSS are handled in later phases.

</domain>

<decisions>
## Implementation Decisions

### Token naming convention
- Keep existing `--color-*` prefix convention (e.g., `--color-bg`, `--color-primary`, `--color-danger`)
- Do NOT migrate to wireframe's shorter names (`--bg`, `--accent`, `--danger`)
- Rationale: 20 CSS files + 20 web components already use `--color-*` — changing names would cause massive refactoring with no functional benefit

### Primary vs Accent naming
- Claude's Discretion: Decide whether to alias `--color-accent` to `--color-primary` or keep them separate
- The wireframe calls the main blue (#1650E0) "accent" — the codebase calls it "primary"

### Sidebar-specific tokens
- Add sidebar tokens matching wireframe: `--sidebar-bg`, `--sidebar-text`, `--sidebar-hover`, `--sidebar-active`, `--sidebar-border`
- Light theme values from wireframe: bg #0C1018, text rgba(255,255,255,.85), hover rgba(255,255,255,.1), active rgba(22,80,224,.3), border rgba(255,255,255,.08)
- Include dark theme variants

### Migration strategy
- Big bang update of design-system.css — change token values in place
- Do NOT touch app.css (Phase 6 scope)
- Do NOT touch page-specific CSS files (each page's phase handles its own CSS)
- Do NOT touch web component styles (Phase 5 scope)
- Claude's Discretion: Whether to tokenize hardcoded hex colors found in page CSS files

### Token value precision
- Pixel-perfect alignment with wireframe values for ALL tokens: colors, shadows, transitions, focus styles
- Compare every token value against wireframe and replace any that differ
- Shadows: use exact wireframe values (shadow-xs, shadow-sm, shadow-md, shadow-lg)
- Transitions: align durations with wireframe specifications
- Focus visible: use wireframe's double-ring style (0 0 0 2px white, 0 0 0 4px rgba(22,80,224,.4))

### Border radius
- Claude's Discretion: Evaluate wireframe radius values (6px/8px/10px/999px) vs current (6px/8px/12px/16px/20px) and choose the best approach

### Dark theme
- Claude's Discretion: Evaluate current dark theme coverage and fill gaps as needed
- Claude's Discretion: Glass/glassmorphism — define token, decide if blur effect belongs in design system or layout phase

### Claude's Discretion
- Tag tokens (--tag-bg, --tag-text) — add if wireframe defines them
- Transition duration alignment details
- Border radius value decisions
- Dark theme completeness depth
- Glass effect implementation location
- Hardcoded color tokenization in page CSS files
- Any additional tokens discovered during wireframe comparison

</decisions>

<specifics>
## Specific Ideas

- The wireframe HTML file on origin/main (`ag_vote_wireframe.html`) is the pixel-perfect reference for all token values
- Compare every `:root` and `[data-theme="dark"]` block between wireframe and design-system.css
- The wireframe defines the "Acte Officiel" visual language — warm parchment backgrounds, ink-blue accents, subtle shadows

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `design-system.css`: Already has ~400 lines of tokens, very close to wireframe values
- Existing `[data-theme="dark"]` block at line 308 with full color overrides
- Existing font stack matches wireframe: Bricolage Grotesque, Fraunces, JetBrains Mono
- Layout tokens already match: `--sidebar-rail: 58px`, `--sidebar-expanded: 252px`, `--header-height: 56px`

### Established Patterns
- Token naming: `--color-*` prefix for all color tokens
- Semantic grouping: surfaces, text, borders, primary, success, warning, danger, info, purple, neutral
- Dark theme: full `:root` override block using `[data-theme="dark"]` selector
- Z-index scale: structured from 1 (base) to 10000 (skip-link)

### Integration Points
- design-system.css is loaded by every page via the app shell
- 18 page-specific CSS files reference these tokens
- 20 web components reference these tokens in their shadow DOM or external styles
- app.css references tokens for sidebar, header, and layout styling

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 04-design-tokens-theme*
*Context gathered: 2026-03-12*
