# Phase 43: Dashboard Rebuild - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete ground-up rebuild of the Dashboard page — new HTML structure, new CSS, JS verified and updated, KPIs and session list wired to live backend data. Not patches on existing code — a full rewrite that achieves top 1% design quality.

</domain>

<decisions>
## Implementation Decisions

### Approach: Ground-Up Rewrite
- **Read dashboard.js FIRST** — understand every DOM query, event handler, HTMX target before touching HTML
- **Rewrite HTML from scratch** — new structure designed for horizontal-first, data-dense dashboard
- **Rewrite CSS from scratch** — new styles, not patches on old rules
- **Update JS as needed** — fix any broken selectors, verify all API calls work
- **One testable commit** — no broken intermediate state

### Dashboard Design Vision
- **Stripe Dashboard quality** — data-dense, scannable, every pixel intentional
- **Horizontal-first** — use full 1200px content width
- **KPI row:** Horizontal cards with icon left, large mono value, label, trend context. 4 cards filling width
- **Session list:** Primary content — vertical card list with status badges, hover-reveal CTAs, date in mono
- **Quick actions aside:** 280px sticky sidebar — actionable shortcuts, not link dumping
- **Three-depth background:** bg → surface (cards) → raised (KPI cards, table headers)
- **Tooltips:** ag-tooltip on every KPI and action explaining what it means
- **Empty states:** Clear CTA when no sessions exist
- **Responsive:** 1024px aside stacks below, 768px KPIs go 2-col

### Backend Wiring (WIRE-01)
- Verify all HTMX endpoints return data correctly
- KPI values populated by dashboard.js — verify API calls
- Session list rendered by JS — verify renderSessionCard() works with new HTML
- No mock data, no hardcoded values

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming (can rename if cleaner)
- Whether to refactor dashboard.js or just update selectors
- Exact responsive breakpoint behavior

</decisions>

<canonical_refs>
## Canonical References

### Current files (to be rewritten)
- `public/dashboard.htmx.html` — Current dashboard HTML
- `public/assets/css/pages.css` — Dashboard styles (lines 1006+)
- `public/assets/js/pages/dashboard.js` — Dashboard JS (DOM queries, API calls, rendering)

### Design system
- `public/assets/css/design-system.css` — Tokens, components, utilities

</canonical_refs>

<code_context>
## Existing Code Insights

### JS Dependencies (MUST preserve)
- dashboard.js uses getElementById/querySelector for KPI updates, session list rendering
- HTMX targets for partial page updates
- ag-badge, ag-tooltip components available
- renderSessionCard() function generates session card HTML

### What Changes
- ALL HTML structure
- ALL CSS rules for dashboard
- JS selectors updated to match new HTML

</code_context>

<specifics>
## Specific Ideas

- The dashboard should answer "what's happening right now?" in 2 seconds of looking at it
- KPIs should be the first thing the eye hits — large, clear, colored
- Session list should be scannable — status visible without clicking

</specifics>

<deferred>
## Deferred Ideas

None

</deferred>

---

*Phase: 43-dashboard-rebuild*
*Context gathered: 2026-03-20*
