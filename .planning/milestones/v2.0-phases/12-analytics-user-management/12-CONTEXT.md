# Phase 12: Analytics & User Management - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Align the statistics page (analytics.htmx.html) and user management with wireframe v3.19.2. The analytics page already exists with Chart.js, KPI cards, year filter, and export buttons — this is visual alignment + missing wireframe features (trend arrows, proper donut/line charts). User management must be extracted from admin.htmx.html into a dedicated users.htmx.html page (same pattern as audit extraction in Phase 11), with role info panel, users table, add user, and pagination aligned to wireframe specs.

</domain>

<decisions>
## Implementation Decisions

### Users page extraction
- Extract users management from admin.htmx.html into dedicated users.htmx.html + users.css + users.js
- Follow Phase 11 audit extraction pattern: admin keeps compact "X users" summary + link to /users.htmx.html
- Remove users tab from admin — admin focuses on system config (meeting roles, policies, permissions, states, settings, system)
- Add "Utilisateurs" sidebar link under "Système" section, alongside Administration and Paramètres

### Statistics KPI trend arrows
- Trend arrows compare selected year vs previous year (year filter already exists)
- When year filter = "2026", trends compare 2026 vs 2025
- When year filter = "Toutes", trend arrows are hidden (no meaningful comparison)
- No additional UI controls needed — trend context follows the year filter

### Chart styling and colors
- Use existing semantic design tokens: Pour = COLORS.success (green), Contre = COLORS.danger (red), Abstention = COLORS.muted
- Donut chart for vote distribution (Pour/Contre/Abstention)
- Line graph for participation trends with monthly X-axis (12 data points per year)
- Chart.js already loaded — align chart config with wireframe appearance

### Export behavior
- Keep existing PDF export (window.print() with print stylesheet)
- Keep existing per-chart PNG export (Chart.js toBase64Image())
- Add CSV export button: raw session data, one row per session
- CSV columns: date, type, title, participants count, quorum %, resolutions count, adoption rate, Pour/Contre/Abstention totals, status
- CSV respects the year filter selection

### User avatars
- Initials-based avatars (already implemented) — no photo upload in this phase
- Style initials avatar per wireframe (circular, colored background)
- Structure avatar component to accept image URL later (photo support deferred)

### User editing
- Modal dialog for user edit (name, email, role, status)
- Consistent with app-wide modal pattern (ag-confirm, detail modals in audit/archives)
- Clean context switch — list stays stable behind modal overlay

### Roles in role info panel
- Keep current 4 roles: Admin, Opérateur, Auditeur, Observateur
- Do NOT add "Gestionnaire" from wireframe — too many roles increases confusion
- Role info panel (USR-01) describes these 4 roles with their actual permission descriptions
- Color-coded role tags match existing badge variants: Admin=accent, Opérateur=success, Auditeur=purple, Observateur=default

### Claude's Discretion
- Exact KPI card trend arrow styling and percentage display
- Chart.js configuration details (tooltips, legends, responsive breakpoints)
- Users table column widths and responsive behavior
- Modal form layout for user editing
- CSV generation approach (client-side vs server endpoint)
- How to handle the admin → users page transition (compact summary design)
- Skeleton loading states for users page
- Pagination implementation details for users list

</decisions>

<specifics>
## Specific Ideas

- UX priority throughout: lighten mental load is non-negotiable — fewer controls, clear actions, no ambiguity
- Users page follows same file pattern as audit page created in Phase 11: own HTML + CSS + JS, shared sidebar, component library import
- Monthly line graph = 12 data points for "how are we doing this year?" — clean and readable
- CSV export gives users raw data they can slice/pivot in Excel — most actionable format
- Modal for edit keeps the users list visually stable (no row shifting)

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `analytics.htmx.html` (353 lines): Year filter, PDF/PNG export buttons, Chart.js loaded, KPI overview cards, chart containers
- `analytics.css` (648 lines): KPI cards, chart containers, overview cards with success/warning/danger colors, progress bars, anomaly cards
- `analytics-dashboard.js`: Chart.js initialization with COLORS object (success, danger, muted, primary, warning, info), multiple chart types, export handlers
- `admin.htmx.html` users tab (lines ~207-307): Role info panel, add user form, search/filter, users table with skeleton loading
- `admin.js` (1453 lines): loadUsers(), renderUsersTable() with initials avatars, role badges, status toggles, edit/delete/password actions, filter by role, search
- `admin.css` (893 lines): User row styling, role badges, avatar styles, form layouts, tab styling
- `chart.umd.js`: Chart.js vendor library already available
- 20 web components in `/assets/js/components/` including ag-pagination, ag-toast, ag-confirm, ag-badge, ag-popover, ag-modal

### Established Patterns
- One CSS file per page (analytics.css, admin.css → new users.css)
- One JS file per page in /assets/js/pages/ (analytics-dashboard.js, admin.js → new users.js)
- IIFE pattern for page JS modules
- `var` keyword, global namespaces (match existing codebase style)
- hidden attribute for visibility (Phase 9+ cleanup pattern)
- Component import via `<script type="module" src="/assets/js/components/index.js"></script>`
- Shared.emptyState() for empty state rendering
- skeleton()/clearSkeleton() for loading states
- setNotif() → AgToast.show() for notifications
- escapeHtml() for XSS prevention in rendered HTML

### Integration Points
- Sidebar "Système" section — add Utilisateurs link before Administration
- Admin page — remove users tab, add compact user count summary + link to /users.htmx.html
- Analytics page — existing year filter drives KPI trend arrows and CSV export scope
- User API endpoints: /api/v1/admin_users.php (CRUD), /api/v1/admin_user_update.php, /api/v1/admin_user_delete.php
- Statistics API: /api/v1/analytics_dashboard.php (existing data source for charts/KPIs)
- ag-pagination component for users list pagination
- ag-confirm for delete user confirmation

### API Endpoints Available
- GET /api/v1/admin_users.php — list users
- POST /api/v1/admin_users.php — create user
- PUT /api/v1/admin_user_update.php — update user
- DELETE /api/v1/admin_user_delete.php — delete user
- GET /api/v1/analytics_dashboard.php — analytics data
- GET /api/v1/stats (if exists) — statistics data for KPIs

</code_context>

<deferred>
## Deferred Ideas

- User avatar photo upload (upload API, file storage, image validation) — separate feature phase
- Granularity toggle for line graph (Mois/Séance switch) — future enhancement
- "Gestionnaire" role addition — not needed, 4 roles sufficient

</deferred>

---

*Phase: 12-analytics-user-management*
*Context gathered: 2026-03-16*
