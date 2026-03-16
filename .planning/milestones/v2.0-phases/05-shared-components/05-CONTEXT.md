# Phase 5: Shared Components - Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Align all existing shared UI components (web components + utilities) with wireframe v3.19.2 "Acte Officiel". Every component must use design system tokens, work in both light and dark themes, and match the wireframe visual spec. This phase modifies existing components — it does NOT create new page layouts or touch page-specific code.

</domain>

<decisions>
## Implementation Decisions

### Modal & Confirm (COMP-01, COMP-02)
- Style changes only — do not restructure ag-modal's HTML/JS architecture
- Light animation on open/close: CSS fade + scale with --duration-fast (150ms)
- ag-confirm: implement 3 distinct variants (danger/warn/info) with matching icons and colors
- Claude's Discretion: Backdrop style (opaque vs blur)

### Toast system (COMP-03)
- Position: top-right, stacked downward
- Auto-dismiss: 5 seconds for success/info, 8 seconds for warn/error
- Manual close: × button on each toast
- Max 3 visible simultaneously — oldest dismissed when 4th arrives
- Design: colored left border bar + icon + text + close button (per wireframe)
- 4 variants: success, warn, error, info — each with semantic color from design system

### Tags & Badges (COMP-05)
- Align existing ag-badge on design system tokens (--color-danger-subtle, --color-success-subtle, etc.)
- Ensure all wireframe variants exist: danger, success, warn, accent, purple

### Empty State (COMP-04)
- Claude's Discretion: Whether to keep Shared.emptyState() as utility function or convert to <ag-empty-state> web component
- Must render: icon + title + subtitle + CTA button (matching wireframe)

### Progress bars (COMP-06)
- Align existing ag-mini-bar on wireframe style
- Add standard progress bar component if missing (used for quorum display in operator)

### Popover menus (COMP-07)
- Style changes only — align shadow, border-radius, background on wireframe tokens
- Do not change JS positioning logic (ag-popover is already 407 lines)

### Tour system (COMP-09)
- Style changes only — align tour bubble/highlight appearance on wireframe
- Do not change navigation logic or data-tour attribute system (used in 29 files)

### Session expiry banner (COMP-08)
- Style changes only — align ob-banner appearance on wireframe
- Keep existing "Rester connecté" / "Déconnexion" actions

### Cross-cutting decisions
- Tokenize ALL hardcoded hex colors in component styles — replace with --color-* tokens
- Dark theme verification MANDATORY for every component in this phase
- All components must work correctly in both [data-theme="dark"] and light mode

### Claude's Discretion
- Empty state implementation approach (utility function vs web component)
- Modal backdrop style (opaque rgba vs blur glassmorphism)
- Whether ag-mini-bar needs a sibling progress bar component or can be extended
- Specific icon choices for confirm dialog variants
- Toast animation style (slide-in vs fade-in)

</decisions>

<specifics>
## Specific Ideas

- The wireframe HTML file (ag_vote_wireframe.html) is the pixel-perfect reference for component appearances
- Toast design uses colored left border bar (like VS Code notifications)
- Confirm dialogs have distinct icons per variant: shield/warning for danger, triangle for warn, info-circle for info
- All components inherit Phase 4 design tokens — no new token definitions needed

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ag-modal` (181 lines): Web component with open/close, backdrop, Escape key handling
- `ag-confirm` (163 lines): Confirmation dialog extending modal pattern
- `ag-toast` (239 lines): Toast notification with variants and auto-dismiss
- `ag-badge` (153 lines): Tag/badge component with color variants
- `ag-popover` (407 lines): Dropdown menu with positioning logic
- `ag-mini-bar` (56 lines): Mini bar chart for vote distribution
- `Shared.emptyState()`: Utility function generating empty state HTML (used in 12 files)
- Tour system: data-tour attributes in 29 HTML/JS files
- `ob-banner`: Session expiry banner in design-system.css + auth-ui.js

### Established Patterns
- All components are web components extending HTMLElement (ag- prefix)
- Styles are inline in component JS or reference design-system.css classes
- Components use --color-* tokens from Phase 4 design system
- IIFE pattern for page modules, global namespaces (Shared, Auth, Utils)

### Integration Points
- Components are registered in components/index.js
- design-system.css provides base component styles (modal, toast, etc.)
- Page JS files import and use these components directly
- 20 web components total in components/ directory

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 05-shared-components*
*Context gathered: 2026-03-12*
