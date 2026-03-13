# Phase 9: Operator Console - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Redesign the operator page execution view to match wireframe v3.19.2: compact header with live indicators, KPI strip, progress track, resolution panel with 3 sub-tabs (Résultat/Avancé/Présences), right sidebar agenda list, quorum-loss blocking modal, and bottom action bar with keyboard shortcuts. Also refactor all inline styles to CSS classes across both setup and execution modes.

</domain>

<decisions>
## Implementation Decisions

### Setup vs Execution modes
- Keep both modes: Setup mode (tabs, hub, conformity checklist) remains as-is structurally; Execution mode is restyled to match wireframe
- Full replace on mode switch: execution view replaces entire main area (hide setup, show execution). No side-by-side
- Inline style cleanup applies to BOTH setup and execution modes (full page refactor)
- Claude's Discretion: Whether session selector is hidden or visible in execution mode

### Resolution panel layout
- Execution layout HTML goes directly in operator.htmx.html (drop the partial loading pattern for viewExec)
- Split layout: left flex:1 resolution panel + right 200px agenda sidebar
- Compact inline sub-tabs (border-bottom style) inside resolution card — NOT reusing main .tab-btn classes
- Clicking a resolution in sidebar resets to Résultat sub-tab
- Brief transition card (0.5-1s centered message) when advancing to next resolution after proclamation

### KPI strip and progress track
- New dedicated .op-kpi-strip CSS class with compact density, monospace values, uppercase labels — NOT reusing .hub-kpi or .result-card
- Progress track: horizontal bar with one segment per resolution, color-coded (green=voted, blue=voting, gray=pending)
- Progress track segments clickable for voted/voting resolutions (same navigation as sidebar)
- Tags row (quorum status, correspondance, procurations) always visible below KPI strip
- No debug/simulate buttons in production HTML — wireframe demo features excluded

### Quorum modal
- Use existing ag-confirm component (extended with custom variant for 3-button quorum layout + stat cards)
- Blocking overlay — no backdrop click dismiss
- 3 action buttons: Reporter (2e convocation), Suspendre 30 min, Continuer sous réserve (with risk warning)
- Stats display: Présents count, Inscrits count, Requis count

### Action bar
- Pinned at bottom of resolution card on ALL sub-tabs (not just Résultat)
- Two buttons: Proclamer and Vote toggle (Ouvrir/Fermer le vote)
- Keyboard shortcut badges visible on buttons (P for Proclamer, F for Vote toggle)
- Shortcuts only active when not in input/textarea, no meta/ctrl key held
- Proclamer fires immediately — no confirmation dialog. Auto-advances to next resolution with brief transition

### Inline styles cleanup
- Full refactor of ALL inline styles to CSS classes across entire operator.htmx.html
- Covers setup mode tabs, execution view, meeting bar, hub layout
- operator.css is the dedicated CSS file (already exists, ~900 lines)

### Claude's Discretion
- Session selector visibility in execution mode
- Exact transition animation duration and style
- Mobile responsive breakpoints for execution split layout
- How to extend ag-confirm for 3-button quorum variant
- CSS class naming conventions for new execution elements (op- prefix vs operator-)
- Whether to keep existing lifecycle-bar in execution mode or replace with wireframe header

</decisions>

<specifics>
## Specific Ideas

- The wireframe operator page is a focused live-session console — compact and information-dense
- Wireframe uses green border on header (2px solid var(--success)) to indicate "live" state
- Live dot animation (pulsing green) on resolution card title indicates active voting
- Secretary notes textarea in Avancé tab — CTA-style with placeholder
- "Passerelle art. 25-1" alert in Avancé tab for special voting threshold rules
- Wireframe shows explicit "Égalité Pour/Contre" warning with presidential casting vote reference (art. 22)
- Guidance message dynamically changes based on vote progress (waiting, threshold reached, all voted, proclaimed)

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `operator.htmx.html`: 900+ line page with bimodal setup/execution, 7 tabs, hub layout — needs refactoring not rewriting
- `operator.css`: ~900 lines of dedicated CSS with grid layout, meeting-bar, lifecycle-bar, tabs, hub styles
- `operator-exec.js`: Execution mode JS (currently separate module)
- `operator-tabs.js`: Tab switching logic
- `operator-attendance.js`: Attendance management
- `operator-motions.js`: Resolution/motion management
- `operator-realtime.js`: SSE real-time updates
- `operator-speech.js`: Speech/parole management
- `ag-confirm`: Phase 5 component — blocking dialog with icon variants (danger/warn/info)
- `ag-toast`: Phase 5 notification system
- `.progress-bar`: Phase 5 CSS pattern for progress visualization
- `.badge`, `.tag`: Phase 5 tag/badge system

### Established Patterns
- Bimodal layout: Setup (viewSetup) / Execution (viewExec) toggled by hidden attribute
- Tab panel pattern with role="tabpanel" and aria attributes
- Lazy-loaded partials via data-partial attribute (to be replaced with inline HTML for execution)
- IIFE pattern for page JS modules
- `var` keyword, global namespaces (Shared, Auth, Utils)
- One CSS file per page (operator.css)
- Design tokens from Phase 4 (colors, typography, shadows, borders)
- Hub classes shared between operator.css and hub.css

### Integration Points
- SSE (Server-Sent Events) for live voting updates via operator-realtime.js
- API endpoints: /api/v1/meetings/{id}, /api/v1/ballots, /api/v1/attendance
- Links to: public.htmx.html (room display), postsession.htmx.html (after closing)
- Hub link from sessions list and dashboard
- Keyboard event listeners for P/F shortcuts

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 09-operator-console*
*Context gathered: 2026-03-13*
