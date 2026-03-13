# Phase 8: Session Wizard & Hub - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Build/align the 4-step session creation wizard (wizard.htmx.html) and the session hub page (hub.htmx.html) with wireframe v3.19.2. Wizard: 4-step accordion with stepper, form validation, CSV import, resolution management, recap. Hub: status bar, main action card, KPI cards, preparation checklist, documents panel. Includes full HTML/CSS/JS refactoring of both pages (inline styles to CSS classes, JS cleanup).

</domain>

<decisions>
## Implementation Decisions

### Wizard step interaction (WIZ-01)
- Strict validation: required fields must be filled before 'Suivant' enables — prevents incomplete sessions
- Claude's Discretion: Accordion behavior (true accordion vs progressive reveal with collapsible completed steps)
- Claude's Discretion: Stepper visual (checkmark vs color change for completed steps)

### Wizard form fields (WIZ-02, WIZ-03, WIZ-04)
- Step 2 (Membres): CSV import UX at Claude's discretion (file picker + preview recommended)
- Step 2 (Membres): Edit button per row (not inline cell editing) for member table
- Step 3 (Ordre du jour): Drag-and-drop reorder for resolutions
- Step 3 (Ordre du jour): Global default voting rule set in Step 1, each resolution inherits but can override
- Claude's Discretion: Secret ballot toggle placement and UX

### Wizard recap and creation (WIZ-05)
- Claude's Discretion: Whether Step 4 shows 'Télécharger PDF' alongside 'Créer' (wireframe mentions it)
- After clicking 'Créer': redirect to hub.htmx.html with success toast ('Séance créée avec succès')
- Auto-save drafts to localStorage — wizard progress saved client-side, resumable if user leaves

### Hub status bar (HUB-01)
- Stages should represent meaningful, distinct lifecycle milestones — number should be logical for clear navigation without overwhelming the user
- Claude's Discretion: Exact number of stages (current hub has 6, wireframe may suggest different grouping)
- Claude's Discretion: Whether segments are clickable or visual-only
- Claude's Discretion: Color scheme (distinct per stage vs accent progression)

### Hub main action card (HUB-02)
- Fully dynamic: CTA text, icon, and color all change based on current session stage
- Always shows the ONE most important next action (e.g., 'Envoyer les convocations', 'Lancer le vote')

### Hub KPI cards (HUB-03)
- 4 KPI cards: participants, resolutions, quorum needed, convocations
- Claude's Discretion: Whether to reuse .kpi-card from Phase 7 or create hub-specific variant

### Hub checklist (HUB-04)
- Minimize mental workload: auto-check items when data confirms completion, manual only for human-judgment steps
- Full preparation list: Titre défini, Date fixée, Membres ajoutés, Résolutions créées, Convocations envoyées, Documents attachés

### Hub documents panel (HUB-05)
- Display only — list documents with download links, no upload functionality from hub

### Hub layout structure
- Claude's Discretion: Page organization (status bar → action → KPIs → checklist + docs, or alternative)
- Claude's Discretion: Whether to keep the identity banner (session title, date, place) or simplify

### Inline styles cleanup
- Full refactor of ALL inline styles to CSS classes on BOTH wizard.htmx.html and hub.htmx.html
- Full JS refactor of wizard.js and hub.js alongside HTML/CSS
- Claude's Discretion: Whether wizard gets dedicated wizard.css or shares with meetings.css (codebase pattern is one CSS per page)

### Claude's Discretion
- Accordion behavior (true accordion vs progressive reveal)
- Stepper completed step visual
- CSV import UX pattern
- Secret ballot toggle placement
- PDF download on recap step
- Status bar stage count and colors
- Status bar clickability
- KPI card reuse vs hub-specific
- Hub layout organization
- Identity banner retention
- CSS file organization (wizard.css vs meetings.css)

</decisions>

<specifics>
## Specific Ideas

- Both pages already exist with substantial structure — this is alignment and refactoring, not creation from scratch
- The wireframe HTML file (ag_vote_wireframe.html) is the pixel-perfect reference
- Drag-and-drop for resolution reordering is a wireframe nice-to-have now promoted to required
- Global voting rule default + per-resolution override reduces repetitive form filling
- Draft auto-save uses localStorage (not server API) — simple, no backend changes needed
- Hub action card should feel urgent/prominent — it's the primary navigation mechanism for session lifecycle

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `wizard.htmx.html`: Full 4-step wizard with stepper, forms for all steps — heavy inline styles
- `wizard.js`: Session creation logic, step navigation, form validation, API calls
- `hub.htmx.html`: Session hub with identity banner, stage-based layout, 6-stage progress bar, KPI cards, checklist, documents — heavy inline styles
- `hub.js`: Hub data loading, stage management, checklist, document panel logic
- `.kpi-card` CSS class from Phase 7: Reusable KPI card pattern in pages.css
- `ag-popover`, `ag-modal`, `ag-toast`: Shared components from Phase 5
- `Shared.emptyState()`: Empty state utility
- `meetings.css`: Rewritten in Phase 7 — wizard currently loads this file

### Established Patterns
- IIFE pattern for page JS modules
- `var` keyword, global namespaces (Shared, Auth, Utils)
- One CSS file per page (dashboard has pages.css, meetings has meetings.css)
- Phase 7 established inline-to-CSS refactoring pattern
- Strict validation on wizard forms (existing behavior)
- localStorage for UI persistence (sidebar pin, banner dismiss)

### Integration Points
- Wizard 'Créer' → POST /api/v1/meetings → redirect to hub.htmx.html?id={meetingId}
- Hub loads data from /api/v1/meetings/{id} endpoint
- Hub links to: operator.htmx.html, postsession.htmx.html
- Dashboard shortcuts link to wizard.htmx.html
- Sessions list links to hub.htmx.html for each session

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-session-wizard-hub*
*Context gathered: 2026-03-13*
