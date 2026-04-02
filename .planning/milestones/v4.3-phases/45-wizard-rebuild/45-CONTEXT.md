# Phase 45: Wizard Rebuild - Context

**Gathered:** 2026-03-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete ground-up rebuild of the session creation wizard — new HTML structure, new CSS, JS verified and updated. All 4 steps fit the viewport at 1024px, form submissions create real sessions, stepper is functional, horizontal field layout throughout. Top 1% design quality.

</domain>

<decisions>
## Implementation Decisions

### Layout & Step Fitting
- Full-width 900px content track — wider than current 680px to use horizontal space, fit more fields side-by-side without scrolling
- Slide + fade step transitions — horizontal slide between steps for wizard feel, refined from current wizFadeIn
- Segmented pill stepper bar — keep current sticky segmented stepper with filled/active/pending states, refined with connector lines between steps
- Compact sections with collapsible advanced — advanced rules collapsed by default, tighter spacing, each step's primary content fits viewport at 1024px without scroll

### Form Fields & Horizontal Layout
- 3-column grid for short fields — type/date/time on one row, place/address on one row. Wide fields (title, description) stay full-width
- Traditional labels above — consistent with form density needs (wizard has too many fields for floating labels)
- Member add form: single horizontal row — name, email, voting power, add button all on one line
- Resolution add form: compact inline panel — title + majority on one row, description below, add button right-aligned, expandable from "+" button

### Features & Polish
- Keep template grid — refine card styling with better hover states and icons, keep the 3 motion templates
- Review step: summary table with edit buttons — structured recap showing all data with inline "Modifier" links back to relevant step
- Full dark mode parity — all wizard-specific components get dark variants
- Inline field errors + step-level banner — per-field red borders on validation failure, plus summary banner at step top listing all errors

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming (can rename if cleaner)
- Whether to refactor wizard.js or just update selectors
- Exact slide animation timing and easing
- Stepper connector line implementation (CSS pseudo-elements vs SVG)
- Responsive breakpoint behavior (how 3-col goes to 2-col or 1-col)

</decisions>

<canonical_refs>
## Canonical References

### Current files (to be rewritten)
- `public/wizard.htmx.html` — Current wizard HTML (500 lines, 4 steps)
- `public/assets/css/wizard.css` — Wizard page styles
- `public/assets/js/pages/wizard.js` — Wizard JS (step navigation, validation, API submission, drag-drop, localStorage draft)

### Backend
- `public/api/v1/wizard_status.php` — Wizard status API
- API endpoints called by wizard.js for session creation

### Design system
- `public/assets/css/app.css` — Global styles and design tokens
- `public/assets/css/design-system.css` — Tokens, components, utilities

### External dependencies
- FilePond 4.32.12 (CDN) — PDF upload for resolution documents
- ag-toast, ag-popover — Custom Web Components

</canonical_refs>

<code_context>
## Existing Code Insights

### JS Dependencies (MUST preserve)
- wizard.js manages step navigation (showStep), form validation, localStorage draft (DRAFT_KEY), drag-drop reorder for resolutions
- In-memory state: members[], resolutions[] arrays
- Motion templates: MOTION_TEMPLATES array with 3 templates
- API submission creates real session via POST
- FilePond integration for document uploads on resolutions
- CSV import for member list
- Voting power toggle (progressive disclosure)
- Step counter update, stepper highlight logic
- DOM IDs: wizTitle, wizType, wizDate, wizTimeHH, wizTimeMM, wizPlace, wizAddr, wizQuorum, wizDefaultMaj, wizStepper, step0-step3, btnNext0-2, btnPrev1-3, btnCreate, etc.

### What Changes
- ALL HTML structure — new layout with 900px track, horizontal field grids
- ALL CSS rules — new styles for wider track, refined stepper, slide transitions
- JS selectors updated to match new HTML — verify all DOM queries work
- Step 4 recap rebuilt as structured summary table with edit links

### What Must NOT Break
- 4-step navigation flow (showStep function)
- Form validation per step (required fields)
- Session creation API call on Step 4 submit
- localStorage draft save/restore
- Member add/remove/CSV import
- Resolution add/remove/reorder (drag-drop)
- Resolution template application
- FilePond document upload
- Voting power toggle
- Redirect to hub after creation

</code_context>

<specifics>
## Specific Ideas

- Every step must fit in viewport at 1024px — no scrolling to reach the "Suivant" button
- Horizontal field layout is the priority — type/date/time should be on ONE row, not stacked
- The wizard should feel fast and lightweight, not form-heavy
- Slide transitions between steps give a premium wizard feel

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 45-wizard-rebuild*
*Context gathered: 2026-03-22*
