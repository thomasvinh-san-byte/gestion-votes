# Phase 46: Operator Console Rebuild - Context

**Gathered:** 2026-03-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete ground-up rebuild of the operator console — new HTML structure, new CSS, JS verified and updated. SSE connection live, vote panel functional, agenda sidebar operational, all action buttons wired with tooltips. Top 1% console quality.

</domain>

<decisions>
## Implementation Decisions

### Layout & Structure
- Two-panel split layout — agenda/motions sidebar (280px) + main content area. Meeting bar full-width at top
- Horizontal tab bar — keep multi-tab approach (Résolutions, Présences, Paramètres, Dashboard, Speech) with refined styling
- Compact meeting bar — meeting selector, status badge, SSE indicator, clock, refresh. Streamlined
- Full available width — no max-width constraint, use all space for data-dense console

### Vote Panel & Real-time
- Vote panel: card with live counters — large vote counts (Pour/Contre/Abstention) with animated increments, progress bar, open/close buttons prominently placed
- SSE indicator: dot + label in meeting bar — green "Connecté" / red "Hors ligne", compact and always visible
- Delta badges: animated increment — shows +N when new votes arrive, auto-clears after 3s
- Action button tooltips: ag-tooltip on disabled buttons — explain WHY disabled (e.g. "Ouvrez d'abord la séance")

### Polish & Dark Mode
- Agenda sidebar: motion list with status badges — each motion shows title, majority type, vote status (pending/open/closed/adopted), clickable to load in main panel
- Execution mode: prominent execution card — when vote is open, vote panel takes visual priority with larger counters and colored border accent
- Full dark mode parity via tokens — all components use CSS tokens, dark mode automatic. SSE indicator stays vivid green/red
- Responsive: sidebar collapses to top tabs at 1024px — on narrower screens, sidebar becomes horizontal scrollable tab strip

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming
- How to handle the 6 JS files (refactor vs update selectors)
- Exact animation timing for vote count increments and delta badges
- Tab content rendering approach
- Responsive breakpoint details

</decisions>

<canonical_refs>
## Canonical References

### Current files (to be rewritten)
- `public/operator.htmx.html` — Current operator HTML (1072 lines)
- `public/assets/css/operator.css` — Operator styles (4679 lines)
- `public/assets/js/pages/operator-tabs.js` — Tab management + main logic (3371 lines)
- `public/assets/js/pages/operator-exec.js` — Execution/vote logic (956 lines)
- `public/assets/js/pages/operator-motions.js` — Motions/agenda (1413 lines)
- `public/assets/js/pages/operator-attendance.js` — Attendance management (666 lines)
- `public/assets/js/pages/operator-realtime.js` — SSE connection (259 lines)
- `public/assets/js/pages/operator-speech.js` — Speech/notes tab (272 lines)
- `public/partials/operator-live-tabs.html` — Live tabs partial
- `public/partials/operator-exec.html` — Execution partial

### Backend
- `public/api/v1/operator_workflow_state.php` — Workflow state API
- `public/api/v1/operator_open_vote.php` — Open vote API
- `public/api/v1/operator_anomalies.php` — Anomalies API
- `public/api/v1/operator_audit_events.php` — Audit events API

### Design system
- `public/assets/css/app.css` — Global styles and design tokens

</canonical_refs>

<code_context>
## Existing Code Insights

### JS Dependencies (MUST preserve)
- 6 separate JS files for different operator concerns (tabs, exec, motions, attendance, realtime, speech)
- SSE connection via EventSource in operator-realtime.js
- Meeting context loaded via meeting-context.js service
- Vote open/close/tally via API calls in operator-exec.js
- Motion CRUD and drag-drop reorder in operator-motions.js
- Attendance tracking with CSV import in operator-attendance.js
- Tab switching with content lazy-loading in operator-tabs.js
- ag-tooltip, ag-popover custom components used extensively

### What Changes
- ALL HTML structure — new layout with two-panel split
- ALL CSS rules — new styles for split layout, refined components
- JS selectors updated across all 6 files to match new HTML

### What Must NOT Break
- SSE connection establishment and live event handling
- Vote open/close/tally flow
- Meeting selector and status updates
- Tab navigation between all 5 tabs
- Motion CRUD operations
- Attendance management
- Delta badge increments on SSE events
- Action button disable/enable logic with tooltips
- Redirect logic from hub/dashboard

</code_context>

<specifics>
## Specific Ideas

- The operator console is a command center — it should feel like mission control, not a form page
- Live vote counts should be the visual centerpiece when a vote is open
- The SSE indicator must be always visible — operators need confidence the connection is live
- Disabled buttons with tooltips prevent frustration ("why can't I click this?")

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 46-operator-console-rebuild*
*Context gathered: 2026-03-22*
