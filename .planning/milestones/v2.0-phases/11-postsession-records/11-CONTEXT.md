# Phase 11: Post-Session & Records - Context

**Gathered:** 2026-03-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Align the post-session stepper (postsession.htmx.html), archives page (archives.htmx.html), and a new dedicated audit page (audit.htmx.html) with wireframe v3.19.2 design tokens. Post-session and archives pages already exist with full functionality — this is primarily visual alignment, inline styles cleanup, and component integration. The audit page must be extracted from admin.htmx.html into its own dedicated page following the PageAudit wireframe spec.

</domain>

<decisions>
## Implementation Decisions

### Post-session stepper navigation
- Linear navigation with back-nav: users can revisit completed steps but cannot skip ahead
- Forward button enabled only when current step's checklist/actions are complete
- Step indicators show done/active/pending states (matching wireframe clickable completed steps + pinned nav footer)
- Keep the existing 4-step flow: Vérification → Validation → Procès-verbal → Envoi & Archivage

### PV signature (eIDAS)
- Full eIDAS signature modes: simple, advanced, and qualified — all 3 levels rendered per wireframe
- Each signataire picks their signature level from the 3 modes
- Visual treatment for each mode (icon differentiation, level badge)
- Keep existing signataire cards with status updates

### Post-session inline styles cleanup
- Full cleanup of all inline styles on postsession.htmx.html (28K of HTML) — replace with CSS classes
- Follow Phase 9/10 pattern: style="display:none" → hidden attribute
- Move all styling to postsession.css using design tokens

### Archives refinements
- Alignment only — no new features beyond what's already implemented
- Clean up inline styles on archives.htmx.html → move to archives.css
- Ensure proper component usage: ag-pagination, ag-badge for type badges
- Keep existing cards/list toggle, type filter tabs, year filter, search, KPIs, detail modal, pagination

### Audit page — new dedicated page
- Create audit.htmx.html + audit.css + audit.js following established page pattern
- Extract audit functionality from admin.htmx.html (lines ~1021-1045) — keep admin's audit section as a summary link to the new page
- Navigation placement: under "Contrôle" group alongside "Statistiques" (per wireframe breadcrumbs)
- Features per wireframe PageAudit:
  - Event type filters: Tous, Votes, Présences, Sécurité, Système
  - View toggle: Table / Timeline
  - Search and sort
  - Table rows: date/time, user, action, resource, status, details button
  - Event detail modal with SHA-256 fingerprints
  - Export selection (checkbox-based)
- Use existing API endpoints: /api/v1/audit_log.php, /api/v1/admin_audit_log.php, etc.

### Sidebar navigation update
- Add "Audit" link under "Contrôle" section in sidebar (alongside existing "Statistiques")
- Audit page is context-agnostic (no meeting_id parameter needed, like archives)

### Component integration
- Use ag-toast for notifications (already wired via setNotif() → AgToast.show())
- Use ag-confirm for validation/archive confirmation dialogs
- Use ag-pagination for archives and audit pagination
- Use ag-badge for meeting type badges and audit event type tags
- Use ag-popover for contextual help where needed
- Import via existing component index.js bundle

### Claude's Discretion
- Exact stepper CSS animation and transition details
- Audit timeline view visual layout (vertical timeline with event cards)
- Audit table column widths and responsive behavior
- How to handle the admin → audit page link/redirect
- Event detail modal layout within audit page
- SHA-256 fingerprint display formatting

</decisions>

<specifics>
## Specific Ideas

- Post-session stepper already has full 4-step JS controller (goToStep, checklist, validation KPIs, PV generation, PDF export, email distribution, archive action) — focus is on CSS tokenization and inline cleanup
- Archives JS already handles filtering, search, KPIs, cards/list rendering, detail modal — focus is on inline cleanup and component adoption
- Audit page follows same creation pattern as archives: own HTML + CSS + JS files, shared sidebar, component library import
- Existing audit API endpoints already support filtering, pagination, and export — new page wires to these
- Admin page audit section becomes a compact summary with "Voir le journal complet" link to /audit.htmx.html

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `postsession.htmx.html` (28K): 4-step stepper with meeting picker, verification, validation, PV generation, send & archive
- `postsession.css` (8.8K): Stepper, panels, validation KPIs, signature modes, results table, action buttons
- `postsession.js` (19K, 469 lines): Full controller with goToStep(), meeting picker, checklist, KPIs, PV, PDF, email, archive
- `archives.htmx.html` (12K): KPI cards, type filter tabs, cards/list toggle, search, year filter, pagination, detail modal
- `archives.css` (9.4K): Card styling, filters, tabs, badges, stats grid, pagination, KPI cards
- `archives.js` (18K, 471 lines): loadArchives(), applyFilters(), view toggle, KPI updates, search, detail modal, empty states
- `admin.htmx.html` (audit section at lines ~1021-1045): Filter select, search input, scrollable list, pagination controls
- 20 web components in `/assets/js/components/` (160K total) including ag-toast, ag-confirm, ag-pagination, ag-badge, ag-popover

### Established Patterns
- One CSS file per page (postsession.css, archives.css → new audit.css)
- One JS file per page in /assets/js/pages/ (postsession.js, archives.js → new audit.js)
- IIFE pattern for page JS modules
- `var` keyword, global namespaces (match existing codebase style)
- hidden attribute for visibility (Phase 9+ cleanup pattern)
- Component import via `<script type="module" src="/assets/js/components/index.js"></script>`
- Shared.emptyState() for empty state rendering
- skeleton()/clearSkeleton() for loading states
- Retry logic & error handling in API calls

### Integration Points
- Operator page "Clôturer la séance" → meeting status: closed → postsession page
- Post-session Step 4 completion banner → links to /archives.htmx.html
- Meeting status flow: live → closed → validated → archived
- MeetingContext service stores session ID for page-to-page navigation
- Archives & audit pages are context-agnostic (no meeting_id)
- Sidebar navigation organized in 5 sections per Phase 6 — audit goes under "Contrôle"

### API Endpoints Available
- POST /api/v1/meeting_validate.php — validate results
- GET /api/v1/export_pv_html.php — PV HTML generation
- GET /api/v1/audit_export.php — audit CSV export by meeting
- GET /api/v1/archives_list.php — list archived meetings
- POST /api/v1/meetings_archive.php — archive a meeting
- GET /api/v1/audit_log.php — system audit log
- GET /api/v1/admin_audit_log.php — admin-filtered audit log
- GET /api/v1/meeting_audit_events.php — meeting-specific audit events
- GET /api/v1/audit_verify.php — verify audit integrity

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 11-postsession-records*
*Context gathered: 2026-03-15*
