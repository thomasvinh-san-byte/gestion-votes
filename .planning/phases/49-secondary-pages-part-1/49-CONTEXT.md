# Phase 49: Secondary Pages Part 1 - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Ground-up rebuild of 4 secondary pages: postsession, analytics, meetings list, archives. Same approach as v4.3 — read existing JS first, rewrite HTML+CSS+JS from scratch, verify backend wiring, browser test. Each page uses the established design language (gradient accent bars, shadow-md cards, hero patterns, sidebar tabs, token-based dark mode).

</domain>

<decisions>
## Implementation Decisions

### Approach (carried from v4.3)
- Read existing JS before touching HTML to understand DOM dependencies
- Rewrite HTML+CSS together in one commit per page
- Update JS selectors if DOM changes, add error banners
- Verify backend connections — no dead endpoints
- Browser test before marking done
- No broken intermediate states

### Page-Specific Notes
- **Postsession**: 4-step stepper, result cards with bar charts, PV generation button. Reference: v4.3 hub hero card pattern for header, wizard stepper pattern for steps.
- **Analytics**: Chart area + KPI grid. Reference: v4.3 dashboard KPI row pattern.
- **Meetings**: Session cards with status badges, filters. Reference: v4.3 dashboard session list.
- **Archives**: Data table with sticky header, pagination, search. Reference: v4.3 settings sidebar-tab pattern for filtering.

### Claude's Discretion
- Exact layout grids per page
- HTML restructuring decisions
- Which JS functions need DOM selector updates
- CSS class naming for new structure
- Responsive breakpoints per page

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets (from v4.3)
- Design system tokens (shadow-sm/md/lg, color-primary-subtle, gradient patterns)
- ag-empty-state, ag-quorum-bar, ag-stepper, ag-toast, ag-confirm components
- Dashboard KPI card pattern, hub hero card pattern, wizard slide transitions

### Pages to Rebuild
- `public/postsession.htmx.html` + `public/assets/js/pages/postsession.js` + `public/assets/css/postsession.css`
- `public/analytics.htmx.html` + `public/assets/js/pages/analytics.js` + `public/assets/css/analytics.css`
- `public/meetings.htmx.html` + `public/assets/js/pages/meetings.js` + `public/assets/css/meetings.css`
- `public/archives.htmx.html` + `public/assets/js/pages/archives.js` + `public/assets/css/archives.css`

</code_context>

<specifics>
## Specific Ideas

- Same v4.3 ground-up rebuild approach — proven pattern, no experimentation
- "Officiel et confiance" design language applies to all pages

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 49-secondary-pages-part-1*
*Context gathered: 2026-03-30*
