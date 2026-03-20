# Phase 38: Results & History - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual redesign of Post-session, Analytics/Statistics, and Meetings list pages. These are the "after the session" pages — results review, historical trends, and session history navigation. Data presentation must match Stripe dashboard clarity.

</domain>

<decisions>
## Implementation Decisions

### Design Philosophy (carried from Phase 35-37)
- Stripe Dashboard reference for data presentation clarity and density
- JetBrains Mono for all numeric data (KPIs, percentages, counts, dates)
- ag-tooltip on every metric and action explaining what it means
- Dramatic visible improvement — not subtle refinements

### Post-Session Visual Redesign (CORE-05)
- **4-step stepper:** Horizontal at top (Résultats → Validation → PV → Archivage). Completed steps show green checkmark, active step has primary glow, pending steps are muted. Sticky while scrolling
- **Result cards:** Each motion result as a collapsible card. Header shows: motion number badge, title, ADOPTÉ (green) or REJETÉ (red) verdict badge large and prominent. Collapsed by default after first view
- **Vote breakdown:** Inside expanded card — colored bar chart (pour/contre/abstention segments), exact counts in JetBrains Mono, percentage next to each. Quorum status indicator
- **Section spacing:** 48px (--space-section) between major sections (Résultats, Validation, PV, Archivage). Each section has a clear heading with step number
- **PV section:** Preview card with document thumbnail/icon, download CTA button (gradient primary), generation status badge
- **Archival section:** Clean checklist of archival steps with checkmarks for completed items
- **Status tooltips:** Each stepper step has a tooltip explaining what happens at that stage

### Analytics Visual Redesign (DATA-05)
- **KPI row:** Top row of 4 KPI cards (like dashboard but for statistics — total sessions, votes cast, participation rate, average quorum). Each card with large mono number, trend indicator if data available, tooltip explaining the metric
- **Chart layout:** Responsive grid (min 2 columns at 1024px). Each chart in a card with a clear title, subtitle explaining the data, and the chart canvas with proper padding
- **Chart styling:** Chart.js canvases with consistent color palette using design-system tokens (primary for main data, muted for secondary). Grid lines subtle, axis labels in --text-xs
- **Data density:** Compact table below charts for detailed breakdown — session-by-session data. Uses .table-page shared structure from Phase 32
- **Time filters:** Clean filter bar at top — period selector (7j, 30j, 90j, 1an, Tout) as pill buttons, date range picker
- **Responsive:** Charts maintain 2-column minimum at 1024px. KPI row goes 2-col at 768px
- **Metric tooltips:** Every KPI and chart title has an ag-tooltip explaining what the metric means and how it's calculated

### Meetings List Visual Redesign (DATA-06)
- **Session cards:** Each session as a card with: title (semibold), date (mono), type badge (AG ordinaire/extraordinaire), status badge (brouillon/convoquée/en cours/terminée/archivée) using semantic ag-badge colors
- **Card density:** 12px gap between cards (--space-3). Cards show key info at a glance — no need to click to understand session state
- **Action buttons:** Hover-reveal pattern (like dashboard session cards from Phase 35). "Ouvrir" / "Reprendre" / "Voir résultats" depending on session state
- **Filter/sort toolbar:** Above the card list — status filter as pill buttons, sort by date/title, search field
- **Empty state:** When no sessions match filters, clear message with CTA to create a new session
- **Max-width:** 1200px centered (matching dashboard pattern)
- **Pagination:** If many sessions — bottom pagination bar with page numbers and count

### Claude's Discretion
- Chart.js color palette exact values
- Whether to add sparklines to KPI cards
- Post-session result card expand/collapse animation style
- Filter pill button active state styling
- Whether meetings list uses infinite scroll or pagination

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Page files
- `public/postsession.htmx.html` — Post-session HTML
- `public/analytics.htmx.html` — Analytics HTML
- `public/meetings.htmx.html` — Meetings list HTML (if exists, may be in dashboard)
- `public/assets/css/postsession.css` — Post-session styles
- `public/assets/css/analytics.css` — Analytics styles
- `public/assets/css/meetings.css` — Meetings styles
- `public/assets/js/pages/postsession.js` — Post-session JS
- `public/assets/js/pages/analytics.js` — Analytics JS (Chart.js integration)
- `public/assets/js/pages/meetings.js` — Meetings JS

### Components
- `public/assets/js/components/ag-tooltip.js` — Tooltip component
- `public/assets/js/components/ag-badge.js` — Badge component

### Requirements
- `.planning/REQUIREMENTS.md` — CORE-05, DATA-05, DATA-06

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable from Phase 35-37
- KPI card pattern with JetBrains Mono numbers + colored icons (dashboard)
- ag-badge status pills with semantic colors (session cards)
- Hover-reveal action buttons (dashboard session cards)
- Gradient CTA button pattern (wizard, hub, login)
- ag-tooltip wrapping pattern for metrics and actions
- Collapsible details/summary pattern (existing result cards)

### Current State
- Post-session: 4-step stepper (ps-stepper custom, not ag-stepper), result cards with details/summary, PV preview section, centered 900px layout (Phase 33)
- Analytics: Chart.js integration, KPI cards at top, charts-grid responsive (Phase 33), max-width 1400px
- Meetings: Card-based session list, 1200px max-width, gap: var(--space-3) (Phase 33)

</code_context>

<specifics>
## Specific Ideas

- Post-session result verdicts (ADOPTÉ/REJETÉ) should be readable from across a meeting room at 1080p — large, bold, colored
- Analytics should feel like Stripe's analytics dashboard — clean charts, scannable KPIs, no clutter
- Meetings list should let an admin find a specific session in under 3 seconds — clear filtering and scanning

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 38-results-and-history*
*Context gathered: 2026-03-20*
