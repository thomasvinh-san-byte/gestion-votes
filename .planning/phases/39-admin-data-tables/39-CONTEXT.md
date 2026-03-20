# Phase 39: Admin Data Tables - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual redesign of Members, Users, Audit log, and Archives pages. Dense tabular data pages that must handle Linear-quality table views — readable density, actionable rows, visible filters, tooltips on column headers and actions.

</domain>

<decisions>
## Implementation Decisions

### Design Philosophy (carried from Phase 35-38)
- Linear table views reference for data density and readability
- JetBrains Mono for all dates, IDs, and numeric columns
- ag-tooltip on column headers explaining what each column means
- ag-badge for status/role indicators
- Hover-reveal action buttons on rows (pattern from dashboard session cards)
- Dramatic visible improvement over current generic table styling

### Members Page (DATA-03)
- **Stats bar:** Member count, role distribution as colored badges, import status. Prominent at top with raised background
- **Member cards/table:** Each member row shows avatar (40px circle), name (semibold), email (muted), role badge (admin/operator/member), last activity date in mono
- **Import section:** Clean card with file upload area, import results with success/error counts
- **Role tooltips:** Each role badge has ag-tooltip explaining permissions
- **Actions:** Hover-reveal edit/delete buttons on each row. Bulk action toolbar when rows selected
- **Search:** Filter input above table with instant filtering

### Users Page (DATA-04)
- **Role panel:** Visual summary of role distribution — card with colored segments or mini bar chart
- **User table:** Avatar, name, email, role badge, status badge (active/inactive/suspended), created date in mono
- **Pagination:** Clean pagination bar matching .table-page pattern
- **Actions:** Hover-reveal role change and status toggle per row
- **Column tooltips:** "Dernier accès" tooltip explaining "Date de la dernière connexion"

### Audit Log Page (DATA-01)
- **Toolbar:** Status filter as pill buttons (like analytics period pills from Phase 38), date range picker, search. Clean horizontal layout
- **Table:** Timestamp in mono, user (avatar + name), action type as colored badge, target entity, details truncated with expand-on-click
- **Timeline view toggle:** Single clean control to switch between table and timeline views
- **Column headers:** ag-tooltip on each explaining the field ("Action — Type d'opération effectuée")
- **Detail expansion:** Click row to expand full detail panel below — not a modal

### Archives Page (DATA-02)
- **Filter bar:** Status pills (Brouillon, Convoquée, Terminée, Archivée), date range, search
- **Session cards:** Reuse session-card pattern from meetings list (Phase 38) — title, date mono, type badge, status badge, hover-reveal CTA
- **Pagination:** Clean bar with count and page controls
- **Empty state:** When no sessions match filters — clear message with CTA to adjust filters

### Claude's Discretion
- Whether members page uses cards or table for the main view
- Exact column widths for audit log table
- Timeline view implementation details
- Whether to add sparklines to stats bars
- Bulk action toolbar design

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Page files
- `public/members.htmx.html` — Members page HTML
- `public/users.htmx.html` — Users page HTML
- `public/audit.htmx.html` — Audit log HTML
- `public/archives.htmx.html` — Archives HTML
- `public/assets/css/members.css` — Members styles
- `public/assets/css/users.css` — Users styles
- `public/assets/css/audit.css` — Audit styles
- `public/assets/css/archives.css` — Archives styles
- `public/assets/js/pages/members.js` — Members JS
- `public/assets/js/pages/users.js` — Users JS
- `public/assets/js/pages/audit.js` — Audit JS
- `public/assets/js/pages/archives.js` — Archives JS

### Design system
- `public/assets/css/design-system.css` — .table-page shared base, component specs

### Requirements
- `.planning/REQUIREMENTS.md` — DATA-01, DATA-02, DATA-03, DATA-04

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable from Phase 35-38
- Session-card pattern with hover-reveal CTA (meetings list Phase 38)
- Period filter pills (analytics Phase 38)
- KPI stats bar pattern (dashboard Phase 35)
- ag-tooltip wrapping for column headers and actions
- ag-badge for status/role indicators
- JetBrains Mono for dates and numbers
- .table-page shared base structure (Phase 32)

### Current State
- All 4 pages use .table-page wrapper from Phase 32
- Sticky 40px headers with surface-raised background
- 48px row height
- .col-num utility for right-aligned numbers
- Members has stats bar above table
- Archives uses card-based layout
- Audit has filter tabs above table card

</code_context>

<specifics>
## Specific Ideas

- These 4 pages should feel like Linear's table views — scannable, dense but not cramped, every row actionable
- Role/status badges should use the same semantic colors across all pages (admin=primary, operator=warning, member=neutral)
- Consistent filter pattern across all 4 pages — pill buttons for categorical filters, search for text

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 39-admin-data-tables*
*Context gathered: 2026-03-20*
