# Phase 31: Component Refresh - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Restyle the 8 shared UI component types (buttons, cards, tables, form inputs, modals, toasts, badges, steppers) so each has intentional, differentiated visual specs using Phase 30 design-system tokens exclusively. Reconcile Web Component internal styles with design-system.css. No new components — restyle existing ones.

</domain>

<decisions>
## Implementation Decisions

### Style Source (CSS vs Web Components)
- Web Components keep Shadow DOM but MUST use CSS custom properties (`var(--token)`) for ALL visual values
- Zero hardcoded px/hex/rgb inside ag-modal.js, ag-toast.js, ag-badge.js, ag-stepper.js
- design-system.css `:root` tokens are the single source of truth for values
- Component Shadow DOM styles reference tokens via `var()` — debuggable in DevTools (you see `var(--radius-card)` not `12px`)
- CSS design-system component classes (.modal, .toast, .badge, .stepper) stay as the canonical spec; Web Components consume them

### Dimensions & Heights
- Claude decides heights following shadcn/Radix/Polaris best practices
- CMP spec values (36px buttons, 48px rows, 36px inputs) are targets, not hard constraints
- Consistency between components is more important than matching an exact pixel value
- All heights use design-system spacing tokens

### Radius Mapping (standard differentiation)
- Badge: `var(--radius-full)` (pill)
- Button: `var(--radius-md)` (6px)
- Input: `var(--radius-md)` (6px)
- Card: `var(--radius-xl)` (12px)
- Modal: `var(--radius-xl)` (12px)
- Toast: `var(--radius-lg)` (8px)
- Differentiation comes from radius + shadow + height combined, not radius alone

### Hover & Elevation Model
- Interactive components change shadow level on hover: cards sm→md, elevated elements md→lg
- `translateY(-1px)` lift reserved for clickable cards only — not buttons, not badges
- Buttons use gradient shift + shadow deepening on hover (no vertical lift)
- Table rows use background-color change only (subtle primary tint)
- No gratuitous animations — every effect signals interactivity

### Focus Ring (unified)
- Double-ring pattern everywhere: 2px gap (surface color) + 2px ring (primary color)
- Applied uniformly: buttons, inputs, selects, textareas, cards, modal close buttons
- Works on all backgrounds (light/dark) because gap uses surface color
- Replaces current inconsistent approach (buttons: offset+ring, inputs: box-shadow)
- WCAG AA compliant focus indicators

### Claude's Discretion
- Exact transition durations and easing curves (within design-system transition tokens)
- Toast slide-in direction and animation specifics
- Stepper connector line thickness and gap
- Table alternating row implementation (keep or remove)
- Dark mode component-specific adjustments beyond token inheritance

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Design System
- `public/assets/css/design-system.css` — Token definitions (:root), all component CSS classes, current values to update

### Web Components (reconcile these)
- `public/assets/js/components/ag-modal.js` — Modal component: backdrop rgba(0,0,0,0.45) vs CSS 0.35, radius 16px vs CSS 12px, padding divergence
- `public/assets/js/components/ag-toast.js` — Toast component: width 240-340px hardcoded, animation timing differs from CSS
- `public/assets/js/components/ag-badge.js` — Badge component: font-weight 700 vs CSS 500, padding 3px 10px vs CSS 4px 8px, gap 6px vs CSS 4px
- `public/assets/js/components/ag-stepper.js` — Stepper component: completely different visual system (20px dots + connectors) vs CSS (28px circles + card boxes)

### Requirements
- `.planning/REQUIREMENTS.md` — CMP-01 through CMP-08 specifications

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- 4 Web Components (ag-modal, ag-toast, ag-badge, ag-stepper) — restyle, don't rewrite
- design-system.css component layer (lines ~1390-2561) — buttons, cards, tables, forms, modals, toasts, badges, steppers all defined
- Phase 30 token system — all primitive/semantic/component-alias tokens available

### Established Patterns
- IIFE + var pattern for JS — no ES modules
- One CSS per page — component CSS lives in design-system.css only
- Web Components use Shadow DOM with inline template strings
- Buttons already have a 5-size system (.btn-xs through .btn-xl)

### Integration Points
- Every page CSS imports app.css which cascades from design-system.css
- Web Components are registered globally and used across multiple pages
- Stepper used in: wizard (ag-stepper), hub, post-session pages
- Modal used across: operator, admin, settings, members pages
- Toast used across: all pages via global ag-toast instance

### Known Inconsistencies to Resolve
- ag-modal.js: backdrop 0.45 vs CSS 0.35, radius 16px vs 12px, header padding 14px vs 16px
- ag-badge.js: weight 700 vs CSS 500, padding 3px/10px vs 4px/8px
- ag-stepper.js: two completely different visual systems (card-boxes vs connector-lines)
- ag-toast.js: width range hardcoded, animation timing differs
- Focus ring: buttons use offset+width, inputs use box-shadow — unify to double-ring
- Some button sizes use legacy `var(--radius)` instead of semantic tokens

</code_context>

<specifics>
## Specific Ideas

- Priority is debuggability — when you inspect a component in DevTools, every value should trace back to a design-system token
- shadcn/Radix/Polaris as dimensional references for heights and spacing
- Linear/Jira as density reference for tables (from CMP-03 requirement)
- Sonner as behavioral reference for toasts (from CMP-06 requirement)

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 31-component-refresh*
*Context gathered: 2026-03-19*
