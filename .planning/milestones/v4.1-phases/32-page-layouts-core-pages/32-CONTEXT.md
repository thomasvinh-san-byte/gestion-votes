# Phase 32: Page Layouts — Core Pages - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Rebuild the 6 highest-traffic pages (dashboard, wizard, operator console, data tables, settings/admin, mobile voter) with proper grid/flex specs, the three-depth background model, and correct density for each page's use case. CSS + HTML restructuring only — no new features, no backend changes.

</domain>

<decisions>
## Implementation Decisions

### Three-Depth Background Model
- **Layer 1 — Body (bg):** `var(--color-bg)` (#EDECE6 light) — the page canvas, visible between cards and in margins
- **Layer 2 — Surface:** `var(--color-surface)` (#FAFAF7 light) — cards, panels, sidebars — the primary content container
- **Layer 3 — Raised:** `var(--color-surface-raised)` (#FFFFFF light) — elevated elements inside cards: form inputs, table headers, selected rows, nested panels
- Apply systematically: `.app-main` = bg, `.card` = surface, form inputs/table headers = raised
- Dark mode: tokens auto-derive — no per-page dark blocks needed

### Content Width Constraints
- **Dashboard:** `max-width: 1200px; margin: 0 auto` on content wrapper
- **Wizard:** Form track `max-width: 680px; margin: 0 auto`, fields inside capped at `max-width: 480px`
- **Operator console:** No max-width — fluid layout fills available space (operator needs maximum screen real estate)
- **Data tables:** `max-width: 1400px; margin: 0 auto` — tables need width but not infinite stretch
- **Settings:** Content column `max-width: 720px` — forms don't benefit from extra width
- **Mobile voter:** No max-width needed — mobile viewport is the constraint

### Dashboard Layout (LAY-01)
- KPI row: `display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-card)` (24px)
- Below KPIs: session list as vertical card stack (not side-by-side grid)
- Right aside: 280px sticky sidebar for quick-actions — `position: sticky; top: 80px`
- Main + aside: `display: grid; grid-template-columns: 1fr 280px; gap: var(--space-card)`
- At 1024px breakpoint: aside stacks below main, KPIs go to 2-column
- KPI cards use `var(--color-surface-raised)` to stand out from the card surface

### Wizard Layout (LAY-02)
- Stepper: `ag-stepper` above the form card, outside the scrollable area (sticky)
- Form card: centered at 680px, `var(--color-surface)` background
- Fields inside: `max-width: 480px` — forms don't need full card width
- Footer nav: sticky to bottom of viewport (not card) — `position: sticky; bottom: 0; background: var(--color-surface)`
- Back/Next buttons: `justify-content: space-between` in footer
- No sidebar competes — the wizard is a focused, single-column experience

### Operator Console Layout (LAY-03)
- CSS Grid 3-row layout: `grid-template-rows: auto auto 1fr` (status bar, tab nav, main content)
- Left agenda sidebar: `280px` fixed width, scrollable independently
- Grid: `grid-template-columns: 280px 1fr` with `grid-template-areas`
- Status bar: fixed at top of the grid, full width across sidebar + main — `background: var(--color-surface-raised)`
- Tab nav: below status bar, full main width
- Main area: fluid, scrolls independently from sidebar
- At 768px: sidebar collapses to off-canvas drawer

### Data Tables Layout (LAY-04)
- Shared structure: `.table-page` wrapper → toolbar card → table card → pagination bar
- Toolbar: `display: flex; align-items: center; justify-content: space-between; gap: var(--space-4)` — search left, actions right
- Table: sticky 40px header (`var(--color-surface-raised)` background), 48px row height
- Numeric columns: `text-align: right; font-family: var(--font-mono)` via `.col-num` utility (from Phase 31)
- Pagination: `display: flex; justify-content: space-between; padding: var(--space-3) var(--space-card)` — count left, page controls right
- All 4 table pages (audit, archives, members, users) share this exact structure

### Settings Layout (LAY-05)
- Two-column: `display: grid; grid-template-columns: 220px 1fr; gap: var(--space-card)`
- Left sidenav: 220px, `position: sticky; top: 80px; align-self: start` — stays visible while content scrolls
- Content: `max-width: 720px` — section cards stacked vertically
- Each section: `.card` with its own save button in `.card-footer`
- At 768px: sidenav converts to horizontal scrollable tab bar above content

### Mobile Voter Layout (LAY-06)
- `height: 100dvh` (already implemented)
- Vote buttons: `min-height: 72px` — large touch targets
- Typography: `font-size: clamp(0.875rem, 2.5vw, 1.125rem)` for body text — fluid scaling
- Headings: `font-size: clamp(1.125rem, 3.5vw, 1.5rem)`
- Safe area: `padding-bottom: env(safe-area-inset-bottom, 16px)` on bottom nav
- Bottom nav: `position: fixed; bottom: 0` — always visible, not in scroll flow
- No horizontal scrolling at 375px — all content uses `padding: 0 var(--space-4)` (16px) side margins

### Claude's Discretion
- Exact transition timing for layout changes at breakpoints
- Whether to use `container-query` or `@media` for responsive behavior
- Animation for sidebar collapse/expand
- Exact gap sizes within cards (as long as they use spacing tokens)
- Whether to add subtle borders between the three background layers

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Page HTML files
- `public/dashboard.htmx.html` — Dashboard page structure
- `public/wizard.htmx.html` — Wizard page structure
- `public/operator.htmx.html` — Operator console structure
- `public/audit.htmx.html` — Audit table page
- `public/archives.htmx.html` — Archives table page
- `public/members.htmx.html` — Members table page
- `public/users.htmx.html` — Users table page
- `public/settings.htmx.html` — Settings page structure
- `public/vote.htmx.html` — Mobile voter page

### Page CSS files
- `public/assets/css/pages.css` — Dashboard styles (lines 1006-1143)
- `public/assets/css/wizard.css` — Wizard layout
- `public/assets/css/operator.css` — Operator console layout
- `public/assets/css/audit.css` — Audit table styles
- `public/assets/css/archives.css` — Archives table styles
- `public/assets/css/members.css` — Members table styles
- `public/assets/css/users.css` — Users table styles
- `public/assets/css/settings.css` — Settings layout
- `public/assets/css/vote.css` — Mobile voter layout

### Design System
- `public/assets/css/design-system.css` — Shell layout (lines 863-1254), component specs, token definitions

### Requirements
- `.planning/REQUIREMENTS.md` — LAY-01 through LAY-06 specifications

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- App shell (`.app-shell`, `.app-sidebar`, `.app-main`) — shared across all admin pages, flex-column based
- `.card` / `.card-header` / `.card-body` / `.card-footer` — universal card structure (Phase 31 refreshed)
- `.table` / `.table-wrapper` — base table styles with sticky headers (Phase 31 refreshed)
- `.col-num` utility — right-aligned monospace numbers (Phase 31 added)
- Three-depth tokens already defined in `:root` — just not systematically applied

### Established Patterns
- Fixed sidebar (58px rail, 252px expanded) — overlays content, not grid column
- `data-page-role` attribute on body — used for page-specific CSS scoping
- `.container` class used inconsistently (audit has it, others don't)
- Operator console is the ONLY page attempting CSS Grid for shell — others all use flexbox
- Mobile voter is completely separate from the admin shell

### Integration Points
- `[data-page-role="X"]` selectors scope page-specific overrides
- `.app-main` is the content insertion point for all admin pages
- HTMX partials load inside `.app-main` via `hx-target`
- The sidebar and header are shared HTML includes

### Known Issues to Resolve
- Operator console: CSS Grid override on flex container is inert (latent bug)
- Dashboard: no content max-width, KPI grid not responsive below 1024px
- Wizard: stepper scrolls away, footer not viewport-sticky
- Data tables: each page duplicates table structure CSS — no shared base
- Settings: sidenav has no responsive behavior, no sticky
- Mobile voter: no clamp() typography, bottom nav in scroll flow, no safe-area padding

</code_context>

<specifics>
## Specific Ideas

- Linear/Notion as reference for dashboard density and card spacing
- Clerk/Stripe as reference for settings page layout (sidenav + content)
- The three-depth model should be immediately visible: bg is warm stone, cards are off-white, inputs/headers are pure white — three distinct tonal layers

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 32-page-layouts-core-pages*
*Context gathered: 2026-03-19*
