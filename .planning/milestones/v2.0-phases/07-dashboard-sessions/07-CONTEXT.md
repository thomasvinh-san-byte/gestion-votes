# Phase 7: Dashboard & Sessions - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Align the dashboard page (dashboard.htmx.html) and sessions page (meetings.htmx.html) with wireframe v3.19.2 "Acte Officiel". Dashboard: 4 KPI cards, urgent action card, 2-column layout (upcoming sessions + task list), 3 shortcut cards. Sessions: list/calendar view toggle, filter pills with counts, session list items with full metadata, calendar month grid, empty state. Includes full JS refactoring of both dashboard.js and meetings.js.

</domain>

<decisions>
## Implementation Decisions

### KPI card styling (DASH-01)
- Create a reusable `.kpi-card` CSS class in pages.css — shared by dashboard, hub, and operator pages
- Cards are clickable links (existing `<a>` tag pattern)
- Claude's Discretion: Hover effect (lift+shadow vs background shift)
- Claude's Discretion: Shortcut card accent colors (distinct per card vs uniform)

### Urgent action card (DASH-02)
- Conditional display only — card is hidden when no urgent action exists
- Refactor from inline styles to proper CSS classes

### Dashboard layout (DASH-03, DASH-04)
- Refactor ALL inline styles in dashboard.htmx.html to proper CSS classes in pages.css
- 2-column grid: upcoming sessions (left) + task list with priority colors (right)
- 3-column shortcut cards below (Créer séance, Piloter vote, Piste audit)
- Refactor dashboard.js alongside HTML/CSS changes — clean up data fetching logic
- Keep simple skeleton rectangles for loading state (no content-shaped skeletons)
- Claude's Discretion: Task priority colors (red=urgent, orange=high, blue=normal)

### Sessions list items (SESS-03)
- Each item shows: status dot, title, date, participants count, resolutions count, quorum, status tag, popover action menu
- Responsive hiding: all fields on desktop; hide quorum + resolutions on tablet; show only status dot, title, date, and tag on mobile
- Popover menu has contextual actions depending on session status:
  - Draft: Edit, Delete
  - Scheduled: View, Cancel
  - Completed: View, Archive
- Claude's Discretion: Sort options (at minimum date newest/oldest)

### Filter pills (SESS-02)
- Pills: Toutes, À venir, En cours, Terminées — each with dynamic count
- Counts update dynamically from API (not just initial load)
- Per-filter empty states with contextual messages (e.g., "Aucune séance en cours")

### List/calendar toggle (SESS-01)
- Toggle between list and calendar views
- Search bar on sessions page
- Claude's Discretion: Search behavior (real-time filter vs submit-based — depends on data fetching pattern)

### Calendar view (SESS-04)
- Claude's Discretion: Level of interactivity (month navigation vs static display)
- Sessions appear as color-coded dots with count (green=upcoming, red=live, gray=done)
- Clicking a day shows a popover with session details for that day
- Claude's Discretion: Whether filter pills apply to calendar view
- Dark theme: just inherit token colors, no special calendar treatment

### Empty state (SESS-05)
- Uses existing Shared.emptyState() pattern
- Claude's Discretion: CTA content (create session button vs multi-action)
- Per-filter contextual empty states when active filter yields no results

### Meetings page cleanup
- Remove the inline 4-step creation wizard from meetings.htmx.html (wizard.htmx.html exists at Phase 8)
- Remove the stats bar — replace entirely with filter pills + search + sort bar
- Full JS rewrite of meetings.js for new layout: list/calendar toggle, dynamic filter counts, sort, popover menus, calendar rendering
- Onboarding banner: keep but improve — show only for new users (no sessions yet), auto-dismiss once first session is created (not just localStorage toggle)

### Claude's Discretion
- KPI card hover effect style
- Shortcut card accent color approach
- Task list priority color scheme
- Sort dropdown options
- Search bar real-time vs submit behavior
- Calendar interactivity level
- Calendar filter pill integration
- Empty state CTA design

</decisions>

<specifics>
## Specific Ideas

- Both pages already exist with significant structure — this is alignment and refactoring, not creation from scratch
- The wireframe HTML file (ag_vote_wireframe.html) is the pixel-perfect reference
- KPI card pattern should be reusable: dashboard has 4, hub has 4 (HUB-03), operator has KPI strip
- Calendar uses color dots (compact) with popover detail on click — not full event labels
- Onboarding banner should be data-driven (check session count) not just localStorage flag

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `dashboard.htmx.html`: Has KPI row, urgent action card, 2-column grid, shortcuts — all with inline styles
- `dashboard.js`: Data fetching for KPIs, sessions, tasks — needs refactoring
- `meetings.htmx.html`: Has stats bar, onboarding banner, inline wizard — wizard to be removed
- `meetings.js`: Session CRUD, wizard logic, table rendering — full rewrite needed
- `meetings.css`: Page-specific styles — will need significant updates
- `Shared.emptyState()`: Utility for empty states, used in 12 files
- `ag-popover`: Web component for dropdown menus (407 lines)
- `ag-badge`: Tag/badge component with color variants

### Established Patterns
- IIFE pattern for page JS modules
- `var` keyword, global namespaces (Shared, Auth, Utils)
- DOM-centric: direct getElementById/querySelector
- localStorage for UI persistence
- Skeleton divs for loading states
- `data-tour` attributes for guided tour targets

### Integration Points
- Dashboard links to: meetings.htmx.html, operator.htmx.html, hub.htmx.html, postsession.htmx.html, wizard.htmx.html
- Sessions page links to: individual session pages (hub, operator)
- Both pages use app shell from Phase 6 (sidebar, header, footer)
- Both pages load design-system.css + app.css + pages.css

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 07-dashboard-sessions*
*Context gathered: 2026-03-13*
