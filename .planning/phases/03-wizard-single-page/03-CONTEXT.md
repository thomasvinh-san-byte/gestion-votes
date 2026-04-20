# Phase 3: Wizard Single-Page - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Make the meeting creation wizard (4 steps) fit within a single viewport on a 1080p screen without vertical scrolling. Each step should be usable without scrolling. The wizard keeps its one-step-at-a-time navigation — the goal is compact content, not showing all steps simultaneously.

</domain>

<decisions>
## Implementation Decisions

### Step Visibility
- **D-01:** Keep one-step-at-a-time display with existing stepper navigation — no change to step toggling behavior
- **D-02:** Each individual step must fit within the viewport without scrolling on a 1080p (1920x1080) screen

### Content Density
- **D-03:** Apply `form-grid-2` from Phase 2 to wizard form groups — fields side-by-side instead of stacked
- **D-04:** Reduce vertical spacing (padding, margins) in wizard-specific CSS to gain vertical space
- **D-05:** Compact step headers — reduce title/subtitle size and spacing

### Navigation
- **D-06:** Keep existing stepper (wiz-step-item indicators at top) and step-nav (prev/next buttons at bottom)
- **D-07:** Stepper and navigation should be compact to maximize content area

### Claude's Discretion
- Exact spacing reductions (padding, margin, gap values)
- Whether to adjust font sizes for labels
- How to handle Step 1 (most fields) vs Step 3 (file upload + resolutions list) differently
- Whether the wizard needs a max-height constraint or just CSS compaction

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements
- `.planning/REQUIREMENTS.md` — WIZ-01

### Key Files
- `public/wizard.htmx.html` (518 LOC) — 4-step wizard HTML structure
- `public/assets/css/wizard.css` (1043 LOC) — wizard-specific styling
- `public/assets/js/pages/wizard.js` (1172 LOC) — step navigation, form handling
- `public/assets/css/design-system.css` lines 1976-2020 — form-grid classes (from Phase 2)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `form-grid-2` class — already used in wizard.htmx.html (4 instances), can be extended to more form groups
- `form-group-full` class — for fields that need full width (textareas, file uploads)
- `ag-stepper` component (94 LOC) — custom stepper web component, but wizard uses its own `wiz-step-item` pattern

### Established Patterns
- Steps toggled via `.active` class on `.wiz-step` divs
- Step transitions use `.slide-out` animation class
- Each step has: `.wiz-step-body` > `.step-content` > form groups
- Navigation: `.step-nav` with prev/next buttons and counter

### Integration Points
- wizard.css has its own `form-grid-2` equivalent at line 177 that may conflict with design-system.css
- `.wiz-date-time-row` uses a custom 2-col grid for date/time fields
- Step 1 (general info) has the most fields — primary compaction target
- Step 3 (resolutions) has a dynamic list + file upload — least compressible

</code_context>

<specifics>
## Specific Ideas

- From memory: wizard should fit on one page without scrolling
- From memory: screens are horizontal — use width aggressively
- Functional first — ensure wizard is usable on 1080p, not pixel-perfect

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-wizard-single-page*
*Context gathered: 2026-04-20*
