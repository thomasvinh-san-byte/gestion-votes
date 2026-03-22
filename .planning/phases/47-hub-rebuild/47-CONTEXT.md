# Phase 47: Hub Rebuild - Context

**Gathered:** 2026-03-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete ground-up rebuild of the session hub page — new HTML structure, new CSS, JS verified and updated. Session lifecycle actions wired to real data, quorum bar showing live attendance, checklist reflecting actual state. Top 1% session detail page.

</domain>

<decisions>
## Implementation Decisions

### Layout & Structure
- Full-width card-based layout — session header card + two-column below (checklist + details), horizontal KPIs
- Visual progress bar quorum — colored bar showing attendance vs threshold, with numbers inline
- Hero card session header — session title, type, date, status badge prominently displayed
- 1200px max-width centered — appropriate for a detail page

### Checklist & Lifecycle Actions
- Vertical stepper checklist — each prerequisite (convocation sent, quorum reached, agenda locked) shows ✓/✗/○ with description
- Primary CTA at top + contextual actions in checklist — main "Ouvrir la séance" button prominent, smaller actions next to each checklist item
- Inline blocked reasons — clear text below each blocked item explaining why, with ag-tooltip for details
- "Aller à la console" CTA button — navigates to operator with meeting_id

### Data & Polish
- Compact attendance card with quorum bar — present/total count with visual progress toward quorum threshold
- Motions summary: count badge + list preview — N motions with first 3 titles visible, "Voir tout" link to operator
- Full dark mode parity via tokens — consistent with all rebuilt pages
- Responsive: stack to single column at 768px

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming
- Whether to refactor hub.js or just update selectors
- Exact quorum bar colors and thresholds
- Checklist item order and grouping
- Responsive breakpoint details

</decisions>

<canonical_refs>
## Canonical References

### Current files (to be rewritten)
- `public/hub.htmx.html` — Current hub HTML (299 lines)
- `public/assets/css/hub.css` — Hub styles (1311 lines)
- `public/assets/js/pages/hub.js` — Hub JS (658 lines, lifecycle actions, quorum, checklist)

### Design system
- `public/assets/css/app.css` — Global styles and design tokens

</canonical_refs>

<code_context>
## Existing Code Insights

### JS Dependencies (MUST preserve)
- hub.js loads meeting data via API
- Quorum bar calculation and rendering
- Checklist state from real session data
- Lifecycle actions (send convocation, open session, navigate to operator)
- Meeting context service integration
- Status badge updates

### What Changes
- ALL HTML structure — new card-based layout with hero header
- ALL CSS rules — new styles for checklist stepper, quorum bar, responsive
- JS selectors updated to match new HTML

### What Must NOT Break
- Meeting data loading via API
- Quorum calculation and display
- Checklist items reflecting real session state
- Lifecycle action buttons triggering correct backend operations
- Navigation to operator console with meeting_id
- Blocked reason display when actions can't proceed

</code_context>

<specifics>
## Specific Ideas

- The hub is the "mission briefing" before going live — it should answer "is this session ready?" at a glance
- Quorum bar is the visual centerpiece — operators need to see attendance progress immediately
- Blocked reasons prevent frustration — operators know exactly what they need to do next

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 47-hub-rebuild*
*Context gathered: 2026-03-22*
