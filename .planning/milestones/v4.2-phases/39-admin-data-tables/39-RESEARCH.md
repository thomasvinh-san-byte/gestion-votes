# Phase 39: Admin Data Tables — Research

**Researched:** 2026-03-20
**Domain:** Data table visual redesign — Members, Users, Audit, Archives
**Confidence:** HIGH (direct source reading, all 4 HTML/CSS/JS files read)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Linear table views reference for data density and readability
- JetBrains Mono for all dates, IDs, and numeric columns
- ag-tooltip on column headers explaining what each column means
- ag-badge for status/role indicators
- Hover-reveal action buttons on rows (pattern from dashboard session cards)
- Dramatic visible improvement over current generic table styling
- Role/status badges use semantic colors across all pages: admin=primary, operator=warning, member=neutral
- Consistent filter pattern across all 4 pages — pill buttons for categorical filters, search for text

**Members (DATA-03):**
- Stats bar: Member count, role distribution as colored badges, import status. Prominent at top with raised background
- Member cards/table: Each member row shows avatar (40px circle), name (semibold), email (muted), role badge (admin/operator/member), last activity date in mono
- Import section: Clean card with file upload area, import results with success/error counts
- Role tooltips: Each role badge has ag-tooltip explaining permissions
- Actions: Hover-reveal edit/delete buttons on each row. Bulk action toolbar when rows selected
- Search: Filter input above table with instant filtering

**Users (DATA-04):**
- Role panel: Visual summary of role distribution — card with colored segments or mini bar chart
- User table: Avatar, name, email, role badge, status badge (active/inactive/suspended), created date in mono
- Pagination: Clean pagination bar matching .table-page pattern
- Actions: Hover-reveal role change and status toggle per row
- Column tooltips: "Dernier accès" tooltip explaining "Date de la dernière connexion"

**Audit (DATA-01):**
- Toolbar: Status filter as pill buttons (like analytics period pills from Phase 38), date range picker, search. Clean horizontal layout
- Table: Timestamp in mono, user (avatar + name), action type as colored badge, target entity, details truncated with expand-on-click
- Timeline view toggle: Single clean control to switch between table and timeline views
- Column headers: ag-tooltip on each explaining the field ("Action — Type d'opération effectuée")
- Detail expansion: Click row to expand full detail panel below — not a modal

**Archives (DATA-02):**
- Filter bar: Status pills (Brouillon, Convoquée, Terminée, Archivée), date range, search
- Session cards: Reuse session-card pattern from meetings list (Phase 38) — title, date mono, type badge, status badge, hover-reveal CTA
- Pagination: Clean bar with count and page controls
- Empty state: When no sessions match filters — clear message with CTA to adjust filters

### Claude's Discretion
- Whether members page uses cards or table for the main view
- Exact column widths for audit log table
- Timeline view implementation details
- Whether to add sparklines to stats bars
- Bulk action toolbar design

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATA-01 | Audit log — redesign visuel (toolbar, table density, filtres, timeline view, column header tooltips) | Before/after specs documented below; ag-tooltip wrapping pattern confirmed from Phase 35-38 |
| DATA-02 | Archives — redesign visuel (card/table view, filtres, pagination, états session) | session-card pattern confirmed in design-system.css; archive-card-enhanced class exists but needs upgrade |
| DATA-03 | Members — redesign visuel (stats bar, member cards/table, import, rôles, action tooltips) | Existing member-card structure fully read; stats-bar needs elevation treatment |
| DATA-04 | Users — redesign visuel (role panel, avatar table, pagination) | roles-explainer-grid exists but needs visual density upgrade; user-row pattern confirmed |
</phase_requirements>

---

## Summary

All four pages share the `.table-page` wrapper from Phase 32 and use common design-system primitives (`.filter-tab`, `.table-card`, `.table-toolbar`, `.kpi-card`, `.session-card`). The current styling is functional but visually flat — headers lack visual weight, KPI cards lack icons and tooltips, filter pills exist but lack count badges, and action buttons are always-visible text instead of hover-revealed icon buttons.

The redesign target is Linear-quality table views: every row is scannable at a glance, every actionable element is discoverable on hover, every ambiguous column is explained by ag-tooltip, and data is typeset with JetBrains Mono where appropriate. The session-card pattern from Phase 38 (in `design-system.css`) is the gold standard for Archives cards. The ag-badge and ag-tooltip web components are already loaded via `components/index.js` on all four pages.

Each page has a clear separation between CSS (page-specific file) and JS (renderX() functions building innerHTML). Visual changes are pure CSS + HTML template changes in JS render functions — no API or business logic changes required.

**Primary recommendation:** Upgrade all 4 pages in a single CSS pass per page + minimal JS template updates for tooltip wrappers and badge upgrades. Three waves: Members+Users (structurally similar row lists), Audit (table + timeline special case), Archives (card pattern already closest to target).

---

## Current State Inventory

### Members Page (DATA-03) — Current

**HTML structure:**
- `.stats-bar` with 6 `.stat-item` divs (Total, Actifs, Inactifs, Voix totales, Pouvoir moyen, Couv. email)
- `.members-onboarding` strip with 4 steps (hidden by default, shown conditionally)
- `.members-management` with inline create form + management tabs (Groupes / Importation CSV)
- `.members-layout` two-column: 280px filter sidebar + flex results area
- `.members-grid` with `.member-card` rows (avatar 36px rounded-9px, name+email, meta badges, hover-reveal actions)
- `.pagination` bar at bottom
- `<dialog>` member detail modal

**Current visual problems:**
- Stats bar: flat flex container, `--color-bg-subtle` background, no icons, no tooltips, no visual separation from content
- Member avatar: 36px with border-radius:9px (square feel), monochrome, no semantic color by member status beyond is-active
- Actions: `opacity: 0` on `.member-card-actions` — this is already hover-reveal (GOOD), but icon buttons are 30x30 with no tooltip
- Filter chips: `.filter-chip` exists with proper active state — good base, just needs count badges
- Stats bar stat-items have no icons and no tooltips
- Management tabs look like generic underline tabs, not visually distinct
- Import upload zone is functional but plain (no icon prominence, no state feedback)

### Users Page (DATA-04) — Current

**HTML structure:**
- `.roles-explainer` card with 4 role entries (tag + text description)
- `.table-toolbar` with search input + role filter select + count
- `.table-card` wrapping `.users-list` of `.user-row` elements
- `.table-pagination` with `<ag-pagination>` component

**JS renders (`renderUsersTable`):**
- `.user-avatar` with role-specific color class (avatar-admin, avatar-operator, avatar-auditor, avatar-viewer)
- `.role-badge` with role class (admin/operator/auditor/viewer)
- `.user-status-badge` (active/inactive)
- `.user-row-lastlogin` in plain text (NOT mono yet)
- `.user-row-actions` with THREE visible text buttons (Modifier/Désactiver/Supprimer) — always visible, not hover-reveal

**Current visual problems:**
- Roles explainer: uses old `.card` + `tag` classes, laid out as plain flex row — no visual weight
- Role filter is a `<select>` dropdown instead of pill buttons
- Last login column NOT using JetBrains Mono
- Action buttons always visible as text (not hover-reveal icons)
- No column header tooltips (user-row is a div list, not a table with `<th>`)
- User count displayed as muted text at end of toolbar flex row — easy to miss

### Audit Page (DATA-01) — Current

**HTML structure:**
- `.kpi-grid` with 4 `.kpi-card` (Intégrité, Événements, Anomalies, Dernière séance)
- `.filter-tabs` with 5 pill buttons (Tous, Votes, Présences, Sécurité, Système)
- `.table-toolbar` with search + sort select + `.view-toggle` (table/timeline icons)
- `.table-card` wrapping either `#auditTableView` (with `<table>`) or `#auditTimelineView`
- `.table-pagination` div (JS-rendered pagination)
- `<dialog>` event detail modal (old `.modal` div, not native dialog)

**Table columns:** checkbox | # | Horodatage | Événement | Utilisateur | Empreinte

**JS renders (`renderTable`):**
- Timestamp: `<span class="audit-timestamp">` — already mono (GOOD)
- Event cell: `.audit-event-cell` with `.audit-severity-dot` (6px circle) + plain text event name
- User cell: `<span class="tag tag-accent">` — uses generic tag, not ag-badge
- Hash cell: `.audit-hash-cell` — already mono, truncated (GOOD)
- No column header tooltips in `<th>` elements

**JS renders (`renderTimeline`):**
- `.audit-timeline-item` with `.audit-timeline-dot` + `.audit-timeline-content`
- Content has title + timestamp + meta tags + hash + chevron icon
- Clicking opens modal (old `.modal` div with backdrop div)

**Current visual problems:**
- KPI cards have icons but they are small `icon-text` size, not prominent
- KPI cards in archives.css define their own `.kpi-grid`/`.kpi-card`/`.kpi-value` overriding design-system — creates inconsistency
- Filter pills lack count badges showing events per category
- Table column headers have NO ag-tooltip wrappers
- Severity dot is only 6px — too small for visual communication
- User column uses `tag tag-accent` (generic) instead of ag-badge
- Detail opens in a `<div class="modal">` (old pattern) — CONTEXT.md wants inline expansion, but JS uses modal — this is a significant change
- Timeline dot size (10px) and connector line visible but lacks color coding on the timeline line itself

### Archives Page (DATA-02) — Current

**HTML structure:**
- `.kpi-grid` with 5 `.kpi-card` (Total archivées, Avec PV, Cette année, Participation moy., Période) — archives.css defines its own `.kpi-grid`
- `.filter-tabs` with session type pills (Toutes, AG Ord., AG Extra., Conseil) — note: these are TYPE filters, not STATUS filters
- `.table-toolbar` with year filter select + search + view toggle
- `.table-card` wrapping `.archive-stats` (4 stat blocks) + `#archivesList`
- `.table-pagination`

**JS renders (`renderCardView`):**
- `.archive-card-enhanced` with header/body/footer sections
- Header: title + president + date + type badge + PV badge + status badge
- Body: `.archive-info-grid` with 4 stat blocks (Résolutions, Bulletins, Présents, Procurations)
- Footer: SHA hash + action buttons (PV, Audit, Détails)

**JS renders (`renderListView`):**
- Plain `<table>` with thead/tbody — minimal styling, uses generic `.table` class
- NO CSS for the list view table beyond base design-system table styles

**Current visual problems:**
- KPI value uses `var(--font-mono)` for value BUT archives.css overrides kpi-value to have center alignment and its own sizing — inconsistent with design-system kpi-card
- The `.archive-stats` inside `.table-card` duplicates KPI data already shown in the `.kpi-grid` above — redundant
- Type filter pills (Toutes/AG Ord./AG Extra./Conseil) are correct but CONTEXT.md wants STATUS pills (Brouillon, Convoquée, Terminée, Archivée) — this is a new filter to add
- Archive card footer shows SHA hash with `<code>` in plain text — small, hard to read
- Card hover effect is `translateY(-1px)` + border-color change — good but less polished than session-card pattern
- `.archive-card-enhanced:hover` does NOT use `--shadow-md` + border-primary combo from session-card
- The detailed info grid inside card body is good data density but font sizes are unclear
- Action buttons at bottom of each card need hover-reveal treatment

---

## Architecture Patterns

### Reuse Patterns from Phase 35-38

**Pattern 1: session-card (from design-system.css Phase 38)**
```css
.session-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border-radius: var(--radius-lg);
  background: var(--color-surface, #FAFAF7);
  border: 1px solid var(--color-border-subtle, #e8e7e2);
  transition: var(--transition-ui);
  cursor: pointer;
}
.session-card:hover {
  border-color: var(--color-border-strong);
  box-shadow: var(--shadow-md);
  background: var(--color-surface-raised);
}
.session-card .session-card-cta { opacity: 0; transform: translateX(4px); }
.session-card:hover .session-card-cta { opacity: 1; transform: translateX(0); }
```
Use for: Archives card view (replace `.archive-card-enhanced` hover pattern with session-card pattern).

**Pattern 2: ag-tooltip wrapping (from Phase 35+)**
```html
<ag-tooltip text="Date de la dernière connexion réussie" position="top">
  <span class="col-header-text">Dernier accès</span>
</ag-tooltip>
```
Use for: All column headers in Audit table `<th>`, Users column headers, Members stats bar items.

**Pattern 3: ag-badge for status (from Phase 35+)**
```html
<ag-badge variant="success">Actif</ag-badge>
<ag-badge variant="warning">Opérateur</ag-badge>
<ag-badge variant="primary">Admin</ag-badge>
```
Use for: All role badges and status badges in Users and Audit pages.

**Pattern 4: filter-tab with count badge (from analytics Phase 38)**
```html
<button class="filter-tab active" data-type="">
  Tous <span class="count">42</span>
</button>
```
Use for: Audit filter pills (add count), Archives type filter pills (add count).

**Pattern 5: KPI card with icon (from dashboard Phase 35)**
The kpi-card--N positional modifier pattern. Design-system kpi-card uses `padding: var(--space-5)`, `border-radius: var(--radius-lg)`.
Use for: Audit KPI cards (add larger icons, tooltips). Archives KPI cards (unify with design-system definition, remove archives.css override).

**Pattern 6: hover-reveal member actions**
Already exists in members.css:
```css
.member-card-actions { opacity: 0; transition: opacity var(--duration-fast); }
.member-card:hover .member-card-actions { opacity: 1; }
```
Confirm this pattern, add `ag-tooltip` to each action button, increase button touch target to 32px.

### Recommended Structure Per Page

```
Page Flow (all 4 pages):
  app-header          — Page title + subtitle + actions
  .stats-bar or       — KPI summary (elevated background, icons, tooltips)
    .kpi-grid
  .filter-tabs        — Categorical filter pills WITH count badges
  .table-toolbar      — Search input + sort/extra controls
  .table-card         — Main data view (table or cards)
    .table thead      — Sticky headers WITH ag-tooltip wrappers
    .table tbody      — 44px rows, hover highlight, hover-reveal actions
  .table-pagination   — Count + prev/next controls
```

---

## Before/After Specifications

### MEMBERS (DATA-03)

#### Stats Bar
- **BEFORE:** Flat flex row, `--color-bg-subtle` background, plain `div.stat-value` + `div.stat-label`, no icons, no tooltips, no visual hierarchy. 6 stat items with different semantic colors (total=primary, active=success, inactive=muted) but no visual distinction beyond text color.
- **AFTER:** Surface-raised background (`var(--color-surface-raised)`), `border-bottom: 2px solid var(--color-border)`. Each `.stat-item` gets a 16px semantic icon above the value. Wrap each stat-item label in `<ag-tooltip>` with explanation. Active stat gets `--color-success-subtle` background tint. Stat values use `font-family: var(--font-mono)` for the numbers.

#### Member Avatar
- **BEFORE:** 36px, `border-radius: 9px` (squarish), only active members get green border. All inactive members are at `opacity: 0.5`.
- **AFTER:** 40px (increase from 36px), `border-radius: 50%` (circular — matches users page pattern and CONTEXT.md spec). Active gets success-color ring, inactive keeps muted background. Initial letters in `font-weight: 700`.

#### Member Row Actions (already hover-reveal — GOOD)
- **BEFORE:** `.member-action-icon` buttons 30x30px, no tooltip, opacity transition on card hover. Icons for edit/delete/link exist.
- **AFTER:** Keep hover-reveal. Upgrade to 32x32px. Add `<ag-tooltip text="Modifier ce membre" position="top">` wrapping each icon button. Add a "Voir le lien de vote" icon button.

#### Filter Chips (left sidebar)
- **BEFORE:** `.filter-chip` pills — good foundation. No count badges. Static labels only.
- **AFTER:** Add count badge `<span class="filter-chip-count">3</span>` inside each chip showing how many members match (updated by JS after filter). Use same `.count` class pattern as `.filter-tab .count`.

#### Management Tabs (Groupes / Importation CSV)
- **BEFORE:** Standard underline tab pattern, `border-bottom: 2px solid var(--color-border)`, active tab gets primary color underline. Tab panel uses plain background/border.
- **AFTER:** Tabs styled as pill-style tabs (segment control look) — remove underline, use `border-radius: 6px` active background. Or keep underline but add icon left of each label at full size. The mgmt-tab-panel gets `border-radius: 0 8px 8px 8px` with subtle shadow.

#### Upload Zone
- **BEFORE:** `border: 2px dashed var(--color-border)`, centered icon + text + hint. Hover gets primary border.
- **AFTER:** Increase vertical padding to 2.5rem. Add `background: var(--color-bg-subtle)` base (distinct from page bg). Upload icon gets `color: var(--color-primary)` always (not just on hover). Add file type hint "CSV" as a badge above the text.

---

### USERS (DATA-04)

#### Roles Explainer Panel
- **BEFORE:** `.card.roles-explainer` with `card-body` padding, flat flex row of 4 `role-explain` divs each showing `tag + text-xs`. Generic visual, no hierarchy.
- **AFTER:** Replace with a visual role distribution panel. Use 4 side-by-side cards with: role badge (ag-badge), role title (semibold), permission summary (small text), count of users in that role (mono number). Background `var(--color-surface-raised)`. This is the "role panel" specified in CONTEXT.md.

Layout:
```
┌─────────────┬──────────────┬─────────────┬──────────────┐
│ [Admin]  2  │[Opérateur] 3 │[Auditeur] 1 │[Observ.] 12  │
│ Accès complet│ Séances+votes│ Lecture audit│ Lecture seule│
└─────────────┴──────────────┴─────────────┴──────────────┘
```

#### Role Filter
- **BEFORE:** `<select class="form-input" id="filterRole">` dropdown with 5 options.
- **AFTER:** Replace with filter-tab pill buttons (matching audit page pattern). `<div class="filter-tabs" id="roleFilter">` with pills: Tous / Admin / Opérateur / Auditeur / Observateur. JS event listener switches from `change` to `click`. The `<select>` can remain hidden for progressive enhancement or be removed.

#### User Row Actions
- **BEFORE:** Three always-visible text buttons ("Modifier", "Désactiver"/"Activer", "Supprimer") in `.user-row-actions`. No hover trigger.
- **AFTER:** Hide by default (`opacity: 0`), reveal on `.user-row:hover`. Convert to icon buttons (32x32px) with ag-tooltip: edit icon + "Modifier", toggle icon + "Désactiver/Activer", trash icon + "Supprimer". Add `.user-row-actions { opacity: 0; transition: opacity var(--duration-fast); }` + `.user-row:hover .user-row-actions { opacity: 1; }` to users.css.

#### Last Login Column
- **BEFORE:** `<span class="user-row-lastlogin">` displays plain text date from `toLocaleDateString('fr-FR', ...)`.
- **AFTER:** Add `font-family: var(--font-mono)` to `.user-row-lastlogin` in users.css. Wrap column header in ag-tooltip: `<ag-tooltip text="Date de la dernière connexion réussie" position="top">Dernier accès</ag-tooltip>` (in JS render function for the header row, or add a static header row above `.users-list`).

**IMPORTANT:** The users list is rendered as `.users-list` (flex column of divs), not a `<table>` with `<thead>`. There is no header row. To add column headers with tooltips, must either:
1. Add a static `.users-list-header` div row (matching `.user-row` layout) above `#usersTableBody`
2. Or convert to a real `<table>` — AVOID, too invasive

**Recommendation:** Add a `.users-list-header` div to the HTML (above `#usersTableBody`), styled to match `.user-row` but with muted label text and ag-tooltip wrappers on each column position.

---

### AUDIT (DATA-01)

#### KPI Cards
- **BEFORE:** 4 `.kpi-card` with small icon + large value + small label. Generic styling, no color coding for anomalies, no tooltips on cards.
- **AFTER:** Intégrité card gets green value when 100% (already via JS). Anomalies card gets red value color when > 0. Add `<ag-tooltip>` wrapper on each kpi-card label: "Intégrité — Pourcentage d'événements avec hash valide". Add subtle icon treatment: icon becomes 24px, positioned top-right of card.

#### Filter Pills
- **BEFORE:** `.filter-tab` pills without count badges. Active state works.
- **AFTER:** Add count badges. JS `populateFilterCounts()` function tallies events per category and adds `<span class="count">N</span>` inside each tab button. Count badge updates when events load.

#### Table Column Headers
- **BEFORE:** Plain `<th>` text: "#", "Horodatage", "Événement", "Utilisateur", "Empreinte"
- **AFTER:** Each `<th>` wrapped with ag-tooltip:
  - `#` → `<ag-tooltip text="Numéro séquentiel de l'événement" position="bottom">#</ag-tooltip>`
  - `Horodatage` → `<ag-tooltip text="Date et heure précise de l'événement (UTC)" position="bottom">Horodatage</ag-tooltip>`
  - `Événement` → `<ag-tooltip text="Type et description de l'action enregistrée" position="bottom">Événement</ag-tooltip>`
  - `Utilisateur` → `<ag-tooltip text="Compte utilisateur à l'origine de l'action" position="bottom">Utilisateur</ag-tooltip>`
  - `Empreinte` → `<ag-tooltip text="Hash SHA-256 garantissant l'intégrité de l'entrée" position="bottom">Empreinte</ag-tooltip>`
  These go in the HTML `<thead>` (static, not JS-rendered).

#### Event Type in Table
- **BEFORE:** `.audit-event-cell` has a 6px severity dot + plain event text.
- **AFTER:** Replace severity dot (6px) with a proper ag-badge for the category. The event text stays. The severity dot upgrades to 8px with `box-shadow` ring for better visibility.

Actually: Keep severity dot on left + add category badge (ag-badge) to the right of the event text:
```
[●] Vote de résolution  [VOTES]
```

#### User Column in Table
- **BEFORE:** `<span class="tag tag-accent">username</span>` — generic tag.
- **AFTER:** Add initials avatar (24px circle) + name: `<span class="audit-user-cell"><span class="audit-user-avatar">JD</span><span>Jean Dupont</span></span>`. The avatar uses the same role-color pattern as users page.

#### Detail Panel (IMPORTANT: modal vs inline)
- **BEFORE:** Click row → opens `.modal` div with backdrop. The modal is a DIV, not `<dialog>`. Has separate backdrop div. Opened/closed by JS toggling `display: none`.
- **AFTER per CONTEXT.md:** "Click row to expand full detail panel below — not a modal." This requires replacing the modal pattern with an inline expandable row.

**Implementation approach:** When a row is clicked, insert a `.audit-detail-row` `<tr>` immediately after the clicked row with `colspan="6"`. The detail panel slides open. Second click collapses it. Keep the existing `<div class="modal">` hidden as fallback or remove.

This IS a JS change (renderTable + click handler) but CSS-driven (CSS transition on max-height or display). The detail panel HTML is already well-structured (2x2 grid + description + hash).

#### Timeline View
- **BEFORE:** `.audit-timeline-item` with absolute-positioned dot, border connector line. Timeline content uses plain surface background. Chevron icon for click hint.
- **AFTER:**
  - Timeline connector line gets severity-based color segments (CSS gradient or per-item dot color)
  - Timeline content card upgrades: add `border-left: 3px solid <severity-color>` on `.audit-timeline-content`
  - Timeline time display in JetBrains Mono (already using `.audit-timestamp` which has `font-family: var(--font-mono)` — GOOD)
  - Timeline dot upgrades to 12px with `border: 2px solid var(--color-surface)` and `box-shadow: 0 0 0 2px <severity-color>` ring
  - Timeline card hover gets `box-shadow: var(--shadow-sm)` + slight border-color change

---

### ARCHIVES (DATA-02)

#### KPI Cards
- **BEFORE:** archives.css defines its own `.kpi-grid`/`.kpi-card`/`.kpi-value` overriding design-system. Values centered, no icons. `kpi-value` uses `var(--font-mono)`.
- **AFTER:** Remove the archives.css kpi overrides and use the design-system definitions. Add icons (same pattern as audit). Add ag-tooltip on labels. Ensure 5-card grid wraps correctly (minmax 150px).

#### Filter Pills (TYPE filter)
- **BEFORE:** `.filter-tabs` with type pills: Toutes / AG Ord. / AG Extra. / Conseil. Active state works.
- **AFTER per CONTEXT.md:** Add STATUS pills alongside or replace with: Brouillon, Convoquée, Terminée, Archivée. Since archives only shows archived/validated sessions, the status filter should filter by `status` field in the API response. Add count badges to both type and status pills.

**Implementation:** Keep the existing type filter. Add a second filter row for status:
```html
<div class="filter-tabs" id="archiveStatusFilter">
  <button class="filter-tab active" data-status="">Tous statuts</button>
  <button class="filter-tab" data-status="validated">Validée</button>
  <button class="filter-tab" data-status="archived">Archivée</button>
  <button class="filter-tab" data-status="pv_sent">PV envoyé</button>
</div>
```

#### Duplicate Stats (REMOVE)
- **BEFORE:** `.archive-stats` inside `.table-card` shows Total/Avec PV/Résolutions/Bulletins — duplicates the KPI grid above.
- **AFTER:** Remove `.archive-stats` div from HTML. The data is already in the KPI grid. This simplifies the page and removes confusion. JS function `updateStats()` can update the KPI cards only.

#### Archive Card (renderCardView)
- **BEFORE:** `.archive-card-enhanced` with header (surface-raised bg) / body (info grid) / footer (SHA + buttons). Hover: `translateY(-1px)` + primary border. NOT using session-card pattern.
- **AFTER:** Migrate card styling to session-card quality:
  - Card hover: `box-shadow: var(--shadow-md)` + `border-color: var(--color-border-strong)` (not primary, too heavy)
  - Header: Remove separate `surface-raised` background on header — use single surface for whole card
  - Add left accent border for status: `border-left: 3px solid <status-color>` (green for archived, blue for validated, orange for pv_sent)
  - Dates in `font-family: var(--font-mono)` (currently in plain text via `fmtDate()`)
  - Action buttons (PV, Audit, Détails) → hover-reveal: hide at `.archive-card-actions { opacity: 0 }`, show at `.archive-card-enhanced:hover .archive-card-actions { opacity: 1 }`
  - SHA hash at footer → style with mono + subtle background (already has `.archive-sha` class in CSS but not used in `.archive-card-enhanced` JS render)

#### List View (renderListView)
- **BEFORE:** Raw `<table class="table">` inline in template literal. No CSS beyond `.table` base. No sorting, no status colors.
- **AFTER:** Add `.archive-list-table` class to the table. Add column header ag-tooltip wrappers. Use `font-family: var(--font-mono)` for date column cells. Add status badge in PV column using ag-badge.

#### Empty State
- **BEFORE:** `<ag-empty-state>` component with generic icon + message. Already implemented in JS.
- **AFTER:** For filter-activated empty state, add a CTA button inside the empty state description: "Effacer les filtres" that resets all filters. This is a JS-only change (render function modification).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tooltips on column headers | Custom CSS tooltip | `<ag-tooltip>` web component | Already in codebase, accessible, consistent |
| Status/role indicators | Custom span with inline styles | `<ag-badge variant="...">` | Web component already loaded on all 4 pages |
| Date formatting | Custom formatDate | Existing `formatTimestamp()` / `fmtDate()` | Already in each JS file |
| Filter count badges | Custom counter | `.count` span inside `.filter-tab` | Pattern established in design-system.css |
| Mono dates | Inline `font-family` style | `.audit-timestamp`, `.user-row-lastlogin`, CSS font-family rule | CSS class approach, not inline styles |
| Hover-reveal actions | JS mouseenter/mouseleave | CSS `opacity: 0` + `:hover { opacity: 1 }` | CSS-only, already used in members.css |

---

## Common Pitfalls

### Pitfall 1: archives.css kpi-card overrides design-system
**What goes wrong:** `archives.css` defines `.kpi-grid`, `.kpi-card`, `.kpi-value`, `.kpi-label` locally, overriding the design-system definitions. This creates inconsistent sizing.
**How to avoid:** Remove the local kpi definitions from archives.css. Use only design-system tokens. The archives page kpi-grid already has the right HTML structure.
**Warning signs:** If kpi cards look different size/style between audit and archives pages.

### Pitfall 2: Users list is divs, not a table — column headers don't exist
**What goes wrong:** Adding `<th>` with ag-tooltips to a table that doesn't exist. The `.users-list` is a flex column of `.user-row` divs. There is no `<thead>`.
**How to avoid:** Add a `.users-list-header` div in the HTML before `#usersTableBody`. Style it to match the column layout of `.user-row`. Do NOT convert to a real `<table>`.

### Pitfall 3: Audit detail modal vs inline expansion
**What goes wrong:** The CONTEXT.md says "Click row to expand full detail panel below — not a modal." But the current JS uses an old-style `<div class="modal">`. Removing the modal and adding inline expansion requires changes to both `audit.js` and `audit.html`.
**How to avoid:** Keep the modal HTML hidden (display:none) as fallback. In `renderTable()`, change click handler to inject a `.audit-detail-inline` `<tr>` after the clicked row instead of calling `openDetailModal()`. Remove old backdrop div.

### Pitfall 4: Members avatar size change breaks layout
**What goes wrong:** Changing `.member-avatar` from 36px to 40px increases row height. `.member-card` has `padding: 0.625rem 1.25rem` — the avatar already fits with gap. But if the row height is already 48px (from `.table-card .table tbody tr`), adding 40px avatar in a 48px row still works.
**How to avoid:** Test at mobile viewport — on small screens, the meta section is `display: none`, so only avatar + name + actions. 40px fits in 48-52px total row height with 0.625rem padding.

### Pitfall 5: filter-chip vs filter-tab — two separate patterns
**What goes wrong:** Members page uses `.filter-chip` (different from `.filter-tab`) for status filtering. Confusing if trying to add `.count` class which only works with `.filter-tab`.
**How to avoid:** Add `.filter-chip .count` rule to members.css mirroring the design-system `.filter-tab .count` rule. Do NOT rename filter-chip to filter-tab (would require JS changes to `.querySelector('.filter-chip')`).

### Pitfall 6: ag-tooltip on disabled buttons
**What goes wrong:** The "Importer" button has `disabled` attribute. Wrapping it in `<ag-tooltip>` with the existing text is already done in the HTML (`<ag-tooltip text="Disponible après sélection...">`) — this is correct and should be kept.
**How to avoid:** Leave existing ag-tooltip on import button as-is. It's already the right pattern.

### Pitfall 7: Archives status filter needs API support
**What goes wrong:** Adding status pills (Validée/Archivée/PV envoyé) requires the API to return a `status` field per session. If `status` isn't in the API response, the filter silently shows all sessions.
**How to avoid:** Add client-side filter only (filter `filteredArchives` by `m.status === selectedStatus`). The API already returns sessions with status for validated/archived/pv_sent. Filter operates on `allArchives` in memory.

---

## Code Examples

### ag-tooltip on table header
```html
<!-- Source: existing ag-tooltip.js component in codebase -->
<th class="audit-col-timestamp">
  <ag-tooltip text="Date et heure précise de l'événement (UTC)" position="bottom">
    Horodatage
  </ag-tooltip>
</th>
```

### hover-reveal user actions (CSS addition to users.css)
```css
/* Source: members.css pattern — .member-card-actions */
.user-row-actions {
  opacity: 0;
  transition: opacity var(--duration-fast);
}
.user-row:hover .user-row-actions {
  opacity: 1;
}
@media (hover: none) {
  .user-row-actions { opacity: 1; }
}
```

### filter-tab with count badge (JS pattern)
```javascript
// Source: design-system.css .filter-tab .count pattern
function renderFilterWithCounts(events) {
  var cats = { '': 0, votes: 0, presences: 0, securite: 0, systeme: 0 };
  events.forEach(function(e) {
    cats['']++;
    if (cats[e.category] !== undefined) cats[e.category]++;
  });
  document.querySelectorAll('#auditTypeFilter .filter-tab').forEach(function(btn) {
    var type = btn.dataset.type;
    var countEl = btn.querySelector('.count') || document.createElement('span');
    countEl.className = 'count';
    countEl.textContent = cats[type] || 0;
    if (!btn.querySelector('.count')) btn.appendChild(countEl);
  });
}
```

### audit inline detail row (CSS)
```css
/* New pattern for Phase 39 — replaces modal */
.audit-detail-inline td {
  padding: 0;
  background: var(--color-bg-subtle);
  border-bottom: 2px solid var(--color-primary);
}
.audit-detail-panel {
  padding: var(--space-4) var(--space-card);
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}
.audit-detail-panel.is-open {
  animation: slide-down 150ms var(--ease-standard);
}
@keyframes slide-down {
  from { opacity: 0; transform: translateY(-4px); }
  to   { opacity: 1; transform: translateY(0); }
}
```

### stats bar elevation pattern
```css
/* Source: members.css .stats-bar + Phase 35 dashboard KPI pattern */
.stats-bar {
  background: var(--color-surface-raised);
  border-bottom: 1px solid var(--color-border);
  padding: 1rem 1.5rem;
}
.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
}
.stat-icon {
  color: var(--color-text-muted);
  width: 16px;
  height: 16px;
}
.stat-value {
  font-family: var(--font-mono);
  font-size: var(--text-2xl);
  font-weight: 700;
  line-height: 1;
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Always-visible text action buttons | Hover-reveal icon buttons | Phase 35+ (members already done) | Cleaner rows, less cognitive load |
| Generic `<span class="tag">` for status | `<ag-badge variant="...">` | Phase 35 | Semantic, consistent colors |
| Plain column header text | ag-tooltip wrapped headers | Phase 35+ (all pages) | Self-explanatory columns |
| Modal for detail views | Inline expansion in table | Phase 39 (audit) | Context preserved, less disruptive |
| Select dropdown for filters | Filter pill buttons | Phase 38 (analytics period) | Visual, scannable, touch-friendly |
| Separate detail/info page | Card-level hover-reveal CTA | Phase 38 (meetings list) | Reduces navigation friction |

---

## Open Questions

1. **Audit modal → inline expansion scope**
   - What we know: CONTEXT.md says "not a modal". Current JS uses `openDetailModal()` extensively.
   - What's unclear: Whether to keep the modal as fallback on mobile (where inline rows are cramped) or use a full replacement.
   - Recommendation: Implement inline expansion for desktop (>768px). On mobile, trigger a sheet drawer or keep modal. Flag in plan.

2. **Members avatar: circular vs rounded-square**
   - What we know: CONTEXT.md says "40px circle". Current code uses `border-radius: 9px` (square). Users page already uses `border-radius: 50%` (circular).
   - What's unclear: Does the members page need visual differentiation from users page? No — they're both people.
   - Recommendation: Change to `border-radius: 50%` in members.css to align with users page and CONTEXT.md spec.

3. **Archives status filter — data availability**
   - What we know: `renderCardView` uses `m.status` field. API returns sessions at various stages.
   - What's unclear: What `status` values actually appear in the archives endpoint response.
   - Recommendation: Implement client-side filter with graceful handling of unknown status values. The filter shows "Tous" by default so it's safe regardless of data.

---

## Validation Architecture

`workflow.nyquist_validation` is not set in `.planning/config.json` — treat as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | No automated test framework detected (vanilla PHP/JS project) |
| Config file | none |
| Quick run command | Manual browser check of each page |
| Full suite command | Visual inspection of all 4 pages in browser |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DATA-01 | Audit table shows mono timestamps, category badges, ag-tooltip headers, inline detail expansion | manual-visual | n/a | ❌ Wave 0 |
| DATA-02 | Archives cards use session-card hover pattern, status filter pills work, no duplicate stats | manual-visual | n/a | ❌ Wave 0 |
| DATA-03 | Members stats bar elevated, avatar 40px circular, hover-reveal actions with tooltips | manual-visual | n/a | ❌ Wave 0 |
| DATA-04 | Users role panel shows distribution, role filter as pills, hover-reveal actions, mono dates | manual-visual | n/a | ❌ Wave 0 |

Manual-only justification: No test runner configured. All four requirements are pure visual/UX — browser rendering is the only valid verification medium.

### Wave 0 Gaps
None — no test infrastructure to set up. Verification is visual inspection via browser.

---

## Sources

### Primary (HIGH confidence)
- Direct file reads: `public/members.htmx.html`, `public/users.htmx.html`, `public/audit.htmx.html`, `public/archives.htmx.html`
- Direct file reads: `public/assets/css/members.css`, `users.css`, `audit.css`, `archives.css`
- Direct file reads: `public/assets/js/pages/members.js`, `users.js`, `audit.js`, `archives.js`
- Direct file reads: `public/assets/css/design-system.css` (session-card, filter-tab, table-page, kpi-card patterns)
- Direct file reads: `public/assets/js/components/ag-tooltip.js`, `ag-badge.js`
- `.planning/phases/39-admin-data-tables/39-CONTEXT.md` — locked decisions

### Secondary (MEDIUM confidence)
- `.planning/STATE.md` — accumulated lessons from Phase 35-38
- `.planning/REQUIREMENTS.md` — DATA-01 through DATA-04 requirements

---

## Metadata

**Confidence breakdown:**
- Current state inventory: HIGH — all files read directly
- Before/after specs: HIGH — based on direct code reading
- Architecture patterns: HIGH — confirmed from design-system.css and prior phase files
- Open questions: MEDIUM — flagged for planner decision

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable vanilla stack)
