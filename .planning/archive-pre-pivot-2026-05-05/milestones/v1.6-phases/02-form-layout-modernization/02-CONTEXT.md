# Phase 2: Form Layout Modernization - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Modernize form layouts across all 17 pages that contain forms. Apply multi-column grid layouts (2-3 columns on >1024px), ensure consistent compact field styling, and enforce that no single-column form exceeds 60% of viewport width. This is CSS/HTML work — no JS changes expected.

</domain>

<decisions>
## Implementation Decisions

### Column Strategy
- **D-01:** Use existing `form-grid-2`, `form-grid-3`, and `form-grid` (auto-fit) CSS classes from design-system.css — no new grid classes needed
- **D-02:** Default to `form-grid-2` for standard forms, `form-grid-3` for forms with many short fields (e.g., settings)
- **D-03:** Fields that need full width (textareas, file uploads) use existing `form-group-full` class

### Field Styling
- **D-04:** Ensure all inputs/selects/textareas use the existing `.form-input`, `.form-select`, `.form-textarea` classes from design-system.css
- **D-05:** No new field styles — normalize inconsistent usage of existing classes across pages

### Page Scope
- **D-06:** All 17 pages with forms are in scope: admin, analytics, archives, audit, email-templates, help, meetings, members, operator, postsession, report, settings, trust, users, validate, vote, wizard
- **D-07:** Wizard excluded from this phase — it gets its own Phase 3 treatment

### Width Constraint
- **D-08:** No single-column form layout may exceed 60% of viewport width (FORM-03)
- **D-09:** Forms in modals are exempt — modals have their own width constraints

### Responsive Breakpoints
- **D-10:** Use existing design-system.css breakpoints: form-grid-2 collapses at 480px, form-grid-3 at 640px
- **D-11:** No new media queries — leverage what exists

### Claude's Discretion
- Which form-grid variant to apply per page (2-col vs 3-col vs auto-fit)
- How to handle modal forms vs page-level forms
- Whether to add `form-group-full` to specific fields

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — FORM-01, FORM-02, FORM-03

### CSS Foundation
- `public/assets/css/design-system.css` lines 1803-2020 — Form group, form grid, and field styling definitions

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `form-grid-2` class — 2-column grid, collapses at 480px (already used in wizard.htmx.html)
- `form-grid-3` class — 3-column grid, collapses at 640px then 480px
- `form-grid` class — auto-fit with minmax(240px, 1fr), most flexible
- `form-group-full` class — spans full grid width for textareas, uploads
- `.form-input`, `.form-select`, `.form-textarea` — consistent field styling with hover/focus/error states
- `grid-cols-2`, `grid-cols-3` utility classes available

### Established Patterns
- Forms use `.form-group` wrappers with `.form-label` + `.form-input`
- Error states use `.is-error` / `.is-invalid` modifier classes
- Dark mode variants defined via `[data-theme="dark"]` selectors

### Integration Points
- Each page has its own CSS file (e.g., `admin.css`, `settings.css`) that may override or duplicate form styles
- Modal forms created dynamically by JS — their HTML structure may differ from page-level forms
- Some pages may use inline styles or ad-hoc grid definitions that need migration to design-system classes

</code_context>

<specifics>
## Specific Ideas

- From memory: screens are horizontal — use width aggressively, not stack vertically
- From memory: form fields look outdated despite page redesign — ensure consistent modern styling
- Functional first, pretty later — ensure forms are usable and well-laid-out, not pixel-perfect

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 02-form-layout-modernization*
*Context gathered: 2026-04-20*
