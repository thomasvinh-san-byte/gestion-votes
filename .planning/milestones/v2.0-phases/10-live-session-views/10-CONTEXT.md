# Phase 10: Live Session Views - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Restyle the room display (public.htmx.html) and voter tablet/mobile view (vote.htmx.html) to align with wireframe v3.19.2 design tokens. Both pages already exist with full functionality — this is a visual alignment and token pass, plus adding a present/absent toggle to the voter view.

</domain>

<decisions>
## Implementation Decisions

### Room display styling
- Keep the existing header bar (status badge, meeting title, clock, controls) — restyle to design tokens, do NOT remove it
- Respect the theme toggle — do NOT force dark background. User can choose light or dark via existing toggle button
- Result bars: switch from vertical to horizontal orientation per wireframe
- Keep the resolution tracker pills at the bottom — useful for audience progress awareness
- Secret vote block: restyle to design tokens (colors, fonts, borders), keep existing layout (lock icon, title, participation bar)

### Voter view touch UX
- No bottom navigation — the voter page is a focused single-purpose interface, not a multi-tab app
- Keep all 4 vote buttons (Pour, Contre, Abstention, Blanc) — Blanc is a legal voting option in French assemblies
- Restyle buttons to design tokens (Phase 4 colors, radius, shadows) — keep current sizes which are already touch-friendly
- Keep the bottom-sheet confirmation overlay — standard mobile pattern, thumb-friendly

### Real-time data flow
- No countdown timer for vote closing — operator controls open/close, countdown is a functional feature outside UI redesign scope
- Keep existing participation progress bar on voter view, restyle to design tokens
- Room display timer: keep current clock time display, do NOT switch to session elapsed time
- Tokenize public.css — replace all hardcoded colors/fonts with design system CSS custom properties

### Present/absent toggle
- Place toggle in voter footer area near member info — compact, doesn't interfere with voting
- When voter marks absent: disable vote buttons and show message — prevents voting while absent (legal consistency)
- Toggle is instant — no confirmation dialog needed (presence is easily reversible)
- Call attendance API to update server — real self-service, operator sees the change in their attendance panel

### Inline styles cleanup
- Full cleanup of all inline styles on both pages — replace with CSS classes or hidden attributes (same approach as Phase 9 operator page)
- App-footer on both pages: replace style="display:none" with hidden attribute (keep HTML per Phase 6 pattern)

### Accessibility
- Quick ARIA audit on both pages: ensure labels, live regions, focus management on confirmation overlay
- Fix any issues found during audit

### Claude's Discretion
- Exact horizontal bar sizing and animation for room display results
- How to handle the mode toggle (Vote/Resultats) styling
- Mobile responsive breakpoints for room display
- Presence toggle visual design (switch, button, chip — pick what fits the footer area)
- ARIA audit scope and prioritization of fixes

</decisions>

<specifics>
## Specific Ideas

- Room display result bars should be horizontal per wireframe v3.19.2 — more readable on wide projection screens
- Voter page is a focused interface — no navigation distractions
- Present/absent toggle is a new UI element but backed by existing attendance API
- Both pages already load event-stream.js for SSE — no changes to real-time infrastructure

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `public.htmx.html`: 210-line room display with header, motion section, vote bars, quorum visual, decision section, resolution tracker, meeting picker
- `public.css`: Dedicated CSS for room display (projection-body, projection-header, bar-chart, etc.)
- `public.js`: Room display JS with SSE integration, bar chart updates, meeting picker
- `vote.htmx.html`: 282-line voter interface with context bar, motion card, vote buttons, confirmation overlay, member footer
- `vote.css`: Dedicated CSS for voter view (vote-app, vote-btn, confirm-sheet, etc.)
- `vote.js` + `vote-ui.js`: Voter JS modules with SSE, vote submission, confirmation flow
- `event-stream.js`: SSE core module used by both pages
- Design system tokens from Phase 4 (CSS custom properties in design-system.css)

### Established Patterns
- One CSS file per page (public.css, vote.css)
- SSE via event-stream.js for real-time updates
- IIFE pattern for page JS modules
- `var` keyword, global namespaces
- hidden attribute for visibility (Phase 9 cleanup pattern)
- Both pages have data-page-role attribute for page identification
- Both pages use inline SVG icons (not icon sprite) for some elements

### Integration Points
- Operator page links to public.htmx.html via "Projection" button
- vote.htmx.html loaded via invitation link or direct URL with vote token
- Both pages consume SSE events from same server endpoints
- Attendance API (POST /api/v1/attendance) for present/absent toggle
- ag-searchable-select component used in vote.htmx.html for member/meeting selection

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 10-live-session-views*
*Context gathered: 2026-03-13*
