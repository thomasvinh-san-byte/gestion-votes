# Phase 11: Post-Session & Records - Context

**Gathered:** 2026-03-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Align post-session, archives, and audit pages with wireframe v3.19.2. Post-session and archives pages already exist and need structural rework. Audit page is a new build from scratch. All three pages must match wireframe layout, interactions, and token usage.

From ROADMAP.md:
- Post-session: 4-step stepper with verification, validation, PV, envoi
- Archives: searchable cards with pagination and detail view
- Audit: filter by type, table/timeline toggle, event detail modal

</domain>

<decisions>
## Implementation Decisions

### Post-session: Wrapper & Navigation
- Keep separate `ps-panel` sections (don't wrap in single card) — existing JS toggles panels via `hidden` attribute
- Add a shared pinned nav footer bar below all panels showing "Étape X / 4" with Précédent/Suivant buttons
- Remove per-panel `ps-actions` blocks (replaced by shared footer)
- JS updates counter and button labels on step change

### Post-session: Step 1 (Vérification)
- Match wireframe exactly: success alert banner + simple 5-column results table
- Table columns: #, Résolution, Résultat (tag: Adoptée/Rejetée), Pour/Contre/Abst, Majorité
- Remove: 6-stat summary grid (`summaryStats`), checklist card (`verifyChecklist`), alerts card (`alertsCard`)
- Remove 7-column table, replace with 5-column wireframe layout
- Result tags use `.tag.tag-success` / `.tag.tag-danger` with check/x icons

### Post-session: Step 2 (Validation)
- Keep existing structure (irreversibility warning, KPIs, workflow state, validate/reject)
- No major changes needed — already close to wireframe

### Post-session: Step 3 (PV)
- Signataires: Replace card grid with inline readonly input fields in rows (2 per row: Président+Secrétaire, Scrutateur 1+Scrutateur 2) per wireframe
- Observations: Split single textarea into two separate fields: "Observations du président" + "Réserves et contestations" (warn border on réserves field)
- Add contestation deadline alert (`.alert.alert-warn`) between réserves and eIDAS: "Délai de contestation : le PV doit être notifié dans le délai prévu par vos statuts."
- eIDAS: Replace radio mode cards with chip-based selector (`.chip.active` pattern) with per-role sign buttons + signature counter tag ("0/2 signatures")
- Keep PV preview card with Generate button, PDF link, and integrity hash bar (not in wireframe but useful functionality)

### Post-session: Step 4 (Envoi & Archivage)
- Hybrid approach: Keep recipient selector dropdown (all present / all members / custom)
- Add prominent green-bordered PV summary card at top (title, stats, PDF download button)
- Replace exports grid with 2-column layout: left = exports list (7 items with download buttons per wireframe), right = archivage card
- Keep completion banner

### Audit page: Structure
- New standalone page: `audit.htmx.html` + `audit.css` + `audit.js`
- Follows project page pattern: app-shell, sidebar (`data-page="audit"`), header, main, footer, script chain (utils.js → shared.js → shell.js → audit.js)
- Page header: "Audit et Conformité" / "Journal de traçabilité"
- Header actions: conditional "Exporter la sélection (N)" button + "Tout exporter" button

### Audit page: KPI cards
- 4 KPI cards in `kpi-grid`: Intégrité (shield icon), Événements (activity icon), Anomalies (check-circle icon), Dernière séance (clock icon)
- Use existing `.kpi-card` / `.kpi-grid` pattern from design system

### Audit page: Filters & View toggle
- Filter pills row: Tous, Votes, Présences, Sécurité, Système — use `.filter-tab` pattern (same as archives type filter)
- View toggle: Tableau / Chronologie — use `.view-toggle` / `.view-toggle-btn` pattern (same as archives)

### Audit page: Table view
- Checkbox per row + select-all in header for bulk export
- Columns: checkbox, #, Horodatage (monospace), Événement (with severity dot: info=accent, success=green, danger=red, warn=amber), Utilisateur (tag), Empreinte (monospace hash)
- Rows clickable → open detail modal
- Pagination at bottom

### Audit page: Timeline view
- Vertical connector line with severity-colored dots per event
- Each event: title + timestamp on first line, category tag + user tag + hash on second line
- Chevron arrow on right for click-to-detail affordance
- Wrap in a `.card` container

### Audit page: Event detail modal
- Use existing modal pattern (`.modal` + `.modal-backdrop`)
- Content: 2×2 metadata grid (Horodatage, Catégorie, Utilisateur, Sévérité) in surface-alt boxes
- Description block in surface-alt
- Full SHA-256 hash block in surface-alt with monospace, accent color, word-break
- Footer: Fermer button + Exporter button

### Archives page
- No discussion requested — use wireframe as reference for any gaps during planning
- Existing page already has most wireframe features (cards/list toggle, search, year filter, pagination, exports modal)

### Claude's Discretion
- Exact spacing and padding values within design system token bounds
- Loading skeleton patterns for audit page
- Demo data structure for audit events (JS)
- Responsive breakpoints for audit table/timeline
- Archives page: minor alignment tweaks if needed

</decisions>

<specifics>
## Specific Ideas

- Post-session Step 1: wireframe shows a simple success alert "Tous les votes sont clos. Aucune anomalie détectée." before the table — include this pattern
- Post-session Step 3: wireframe eIDAS chips are "Signature avancée (eIDAS)", "Signature qualifiée (eIDAS+)", "Manuscrite (impression)" — use these exact labels
- Post-session Step 3: sign buttons per role — "Signer — Président", "Signer — Secrétaire"
- Audit table: severity dots are 6px circles inline with event text
- Audit timeline: connector line is 2px wide, dots are 10px, connector uses `var(--border-soft)` color
- Audit event categories use specific icons: Votes=vote, Présences=users, Sécurité=shield, Système=settings

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `postsession.htmx.html` + `postsession.css` + `postsession.js`: Full existing page to restructure (not rewrite from scratch)
- `archives.htmx.html` + `archives.css` + `archives.js`: Existing page, mostly complete
- `.filter-tab` / `.filter-tabs`: Reuse from archives for audit filter pills
- `.view-toggle` / `.view-toggle-btn`: Reuse from archives for audit table/timeline toggle
- `.kpi-grid` / `.kpi-card`: Reuse from design system for audit KPIs
- `.modal` / `.modal-backdrop`: Existing modal pattern for audit detail modal
- `Shared.emptyState()`: For audit empty state
- `.tag` / `.tag-success` / `.tag-danger` / `.tag-accent`: For result tags, category tags
- `.chip` / `.chip.active`: For eIDAS selector in post-session Step 3
- `ag-toast` / `addToast()`: For action confirmations

### Established Patterns
- One CSS file per page (postsession.css, archives.css, new audit.css)
- IIFE pattern for page JS
- `api()` global function for data fetching
- `hidden` attribute for show/hide (not `style.display`)
- Script chain: utils.js → shared.js → shell.js → page.js
- Design system tokens for all colors, spacing, typography
- `escapeHtml()` for user-facing text in innerHTML

### Integration Points
- Sidebar: `data-page="audit"` needs to be recognized (sidebar already has audit link from Phase 6)
- Post-session stepper JS: needs refactor to update shared footer nav instead of per-panel actions
- Audit page data: `api('/api/audit')` endpoint pattern — JS falls back to demo data if no backend

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 11-post-session-records*
*Context gathered: 2026-03-15*
