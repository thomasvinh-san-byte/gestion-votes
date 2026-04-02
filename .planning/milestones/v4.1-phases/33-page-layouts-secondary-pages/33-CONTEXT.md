# Phase 33: Page Layouts — Secondary Pages - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Apply the Phase 32 layout language (three-depth background, max-width constraints, grid specs, correct density) to the 6 secondary pages: hub, post-session, analytics, help/FAQ, email templates, meetings list. CSS + HTML restructuring only — no new features, no backend changes.

</domain>

<decisions>
## Implementation Decisions

### Inherited from Phase 32
- Three-depth background model: `.app-main` = bg, `.card` = surface, elevated elements = raised
- All heights, spacing, and component specs from Phase 30/31 tokens
- Responsive breakpoints: 768px (mobile collapse), 1024px (tablet adjustments)
- Shared `.table-page` base for any page using data tables

### Hub Layout (LAY-07)
- Two-column layout: sidebar stepper (220px) + main content
- `display: grid; grid-template-columns: 220px 1fr; gap: var(--space-card)`
- Stepper in sidebar: ag-stepper vertical orientation, sticky at `top: 80px`
- Quorum progress bar: prominent placement at top of main content, `var(--color-surface-raised)` background to stand out
- Checklist items: card-based with proper spacing (`var(--space-card)` gap)
- At 768px: sidebar stacks above main, stepper becomes horizontal

### Post-Session Layout (LAY-08)
- Single-column centered layout: `max-width: 900px; margin: 0 auto`
- Four-step stepper at top (ag-stepper horizontal, checkmarks on completed steps)
- Result cards: collapsible via `<details>` or accordion pattern
- Inter-section spacing: `var(--space-section)` (48px) between major sections
- Cards use `.card` with `var(--color-surface)` — collapsible content inside
- Stepper sticky at top while scrolling through sections

### Analytics Layout (LAY-09)
- Grid layout: `display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--space-card)`
- KPI cards at top: same pattern as dashboard (raised background, 4-col or responsive auto-fit)
- Chart areas: `.card` containers with proper padding for chart canvas
- At 1024px: minimum 2-column layout (not single-column stack) — use `minmax(320px, 1fr)` to prevent collapse
- `max-width: 1400px; margin: 0 auto` for content constraint

### Help/FAQ Layout (LAY-10)
- Single-column centered: `max-width: 800px; margin: 0 auto`
- Accordion: proper `<details>/<summary>` with styled padding (not browser defaults)
- Summary: `padding: var(--space-4) var(--space-card)` (16px 24px), font-weight semibold, cursor pointer
- Content: `padding: 0 var(--space-card) var(--space-card)` — indented from summary
- No layout shift on expand: use CSS `overflow: hidden` + height transition or `content-visibility`
- Search input above accordions if applicable

### Email Templates Layout (LAY-11)
- Two-column: editor (flex: 1) + preview panel (400px)
- `display: grid; grid-template-columns: 1fr 400px; gap: var(--space-card)`
- Preview panel: `var(--color-surface-raised)` background, bordered iframe or rendered preview
- Editor: standard form layout with proper field spacing
- At 768px: preview stacks below editor

### Meetings List Layout (LAY-12)
- Reuse dashboard sessions pattern: vertical card list or `.table-page` structure
- Status badges: `ag-badge` component with semantic variants (success/warning/danger/info) from Phase 31
- Card density: same as dashboard sessions — `var(--space-3)` (12px) gap between items
- `max-width: 1200px; margin: 0 auto` — same as dashboard
- Filter/sort toolbar above the list

### Claude's Discretion
- Whether meetings list uses cards or table view (choose based on current HTML structure)
- Exact chart container heights in analytics
- Animation for accordion expand/collapse
- Whether help page needs a search box
- Exact KPI card count for analytics page

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Page HTML files
- `public/hub.htmx.html` — Hub page structure
- `public/postsession.htmx.html` — Post-session page structure
- `public/analytics.htmx.html` — Analytics/statistics page
- `public/help.htmx.html` — Help/FAQ page
- `public/admin.htmx.html` — Admin page (includes email templates section)
- `public/meetings.htmx.html` — Meetings list page (if exists, otherwise in dashboard)

### Page CSS files
- `public/assets/css/hub.css` — Hub layout
- `public/assets/css/postsession.css` — Post-session layout
- `public/assets/css/analytics.css` — Analytics/statistics layout
- `public/assets/css/help.css` — Help/FAQ layout
- `public/assets/css/email-templates.css` — Email templates layout
- `public/assets/css/meetings.css` — Meetings layout

### Phase 32 Reference (established patterns)
- `public/assets/css/pages.css` — Dashboard layout pattern (reference for meetings list)
- `public/assets/css/settings.css` — Sidenav layout pattern (reference for hub)
- `public/assets/css/design-system.css` — Shared `.table-page` base, component specs, tokens

### Requirements
- `.planning/REQUIREMENTS.md` — LAY-07 through LAY-12 specifications

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `.table-page` shared base from Phase 32 — can be reused for meetings list if table-based
- Dashboard KPI grid pattern from Phase 32 — reusable for analytics KPI row
- Settings sidenav pattern from Phase 32 — reusable for hub sidebar
- ag-stepper component (Phase 31 refreshed) — used in hub and post-session
- ag-badge component (Phase 31 refreshed) — used in meetings list status badges

### Established Patterns from Phase 32
- Three-depth background: bg → surface → raised (systematically applied)
- Content max-width: 1200px (dashboard-type), 900px (single-column), 1400px (wide content)
- Sticky sidebar: `position: sticky; top: 80px; align-self: start`
- Responsive grid collapse at 768px
- CSS Grid for two-column layouts, flexbox for single-column flow

### Integration Points
- Hub stepper connects to session lifecycle state
- Post-session stepper reflects completion progress
- Analytics charts use Chart.js (existing integration)
- Email templates editor is within admin page, not a standalone page

</code_context>

<specifics>
## Specific Ideas

- Hub quorum bar should be the most visually prominent element — use `var(--color-surface-raised)` + larger padding + accent border
- Post-session result cards should feel like the dashboard session cards — consistent visual weight
- Analytics should not collapse to single column at 1024px — minimum 2 columns always

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 33-page-layouts-secondary-pages*
*Context gathered: 2026-03-19*
