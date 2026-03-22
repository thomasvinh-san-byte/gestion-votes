# Phase 48: Settings/Admin Rebuild - Context

**Gathered:** 2026-03-22
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete ground-up rebuild of the settings and admin pages — new HTML structure, new CSS, JS verified and updated. All settings persist correctly, admin KPIs load from real data, user management CRUD is functional. Top 1% admin experience.

</domain>

<decisions>
## Implementation Decisions

### Settings Page Layout
- Vertical sidebar tabs (200px) — rules, communication, security, accessibility tabs on left, content on right
- Horizontal field groups — label left, input right, consistent with wizard field patterns
- Per-tab save button — save changes per tab section, success toast on save
- 1200px max-width centered — consistent with hub page

### Admin Page Layout
- Horizontal KPI row of 4 cards — total members, sessions, votes, active users. Large mono values, subtle icon
- Full-width data table for user management — columns: name, email, role, status, actions (edit/deactivate). Sortable headers
- Inline modal/panel for user CRUD — click "Add user" or "Edit" opens side panel with form fields
- Single column with sections — KPIs at top, then user management table below

### Shared Polish
- Full dark mode parity via tokens — consistent with all rebuilt pages
- Toast notifications on save — success/error toast on form submission, field-level validation inline
- Responsive: stack to single column at 768px — settings sidebar tabs become horizontal, admin table scrolls horizontally

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming
- Whether to refactor settings.js/admin.js or just update selectors
- Toast notification implementation details
- Modal/panel animation and positioning
- Settings tab content organization within each tab
- Admin table sorting implementation

</decisions>

<canonical_refs>
## Canonical References

### Current files (to be rewritten)
- `public/settings.htmx.html` — Current settings HTML (888 lines)
- `public/assets/css/settings.css` — Settings styles (631 lines)
- `public/assets/js/pages/settings.js` — Settings JS (705 lines)
- `public/admin.htmx.html` — Current admin HTML (1218 lines)
- `public/assets/css/admin.css` — Admin styles (1175 lines)
- `public/assets/js/pages/admin.js` — Admin JS (1449 lines, user CRUD, KPIs)

### Design system
- `public/assets/css/app.css` — Global styles and design tokens

</canonical_refs>

<code_context>
## Existing Code Insights

### JS Dependencies (MUST preserve)
- settings.js handles tab switching, form submission per tab, settings persistence
- admin.js handles KPI loading from API, user table rendering, CRUD operations
- Both use the `api()` helper from utils.js
- ag-toast component for notifications
- ag-popover for help tooltips

### What Changes
- ALL HTML structure for both pages
- ALL CSS rules for both pages
- JS selectors updated to match new HTML

### What Must NOT Break
- Settings save/load per tab (rules, communication, security, accessibility)
- Admin KPI loading from real backend data
- User CRUD (create, edit, deactivate)
- Tab navigation on settings page
- Toast notifications on save success/error

</code_context>

<specifics>
## Specific Ideas

- Settings should feel like a well-organized control panel, not a dumping ground of options
- Admin KPIs should match the dashboard KPI style (large mono values, consistent cards)
- User management table should be scannable — role and status visible at a glance

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 48-settings-admin-rebuild*
*Context gathered: 2026-03-22*
