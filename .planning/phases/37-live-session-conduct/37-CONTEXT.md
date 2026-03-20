# Phase 37: Live Session Conduct - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual redesign of the Operator console and Mobile voter ballot — the two real-time, high-stakes pages used during live voting sessions. Operator needs dense, actionable information. Voter needs focused simplicity with large touch targets. Both must feel premium under pressure.

</domain>

<decisions>
## Implementation Decisions

### Design Philosophy (carried from Phase 35-36)
- Reference-driven: Bloomberg Terminal density for operator, Apple Wallet simplicity for voter
- Top 1% under pressure — these pages are used in live sessions where mistakes cost time
- Tooltips on every operator action, confirmation states on every voter action
- Dramatic visible improvement over current state

### Operator Console Visual Redesign (CORE-03)
- **Agenda sidebar (280px):** Each motion as a compact card with number badge, title truncated, status indicator (pending/en cours/voté). Active motion highlighted with primary border-left accent. Scrollable independently from main area
- **Status bar:** Fixed at top — session status (En direct/Pause), connected members count, elapsed time. Use persona-colored accent stripe (operator = orange). Compact 40px height, high-contrast text
- **Tab navigation:** Clean horizontal tabs below status bar — Votes, Participants, Résultats. Active tab with bottom border accent, not background fill. Badge count on Participants tab showing connected/total
- **Live vote panel:** When a vote is open — large motion title, vote progress bar (pour/contre/abstention as colored segments), real-time tally numbers in JetBrains Mono, "Fermer le vote" as danger button
- **Action buttons:** Every action button has an ag-tooltip explaining what it does and current state. "Ouvrir le vote" prominent primary CTA when vote can be opened. Disabled buttons show tooltip explaining why
- **SSE indicators:** Live/reconnecting/offline status as a small colored dot in the status bar — green/amber/red. Delta badges (+N) on incoming votes use the ag-badge pulse animation
- **Guidance panels:** Post-vote and end-of-agenda guidance panels with clear next-step instructions, styled as info cards (not alerts)
- **Density:** This is the densest page — smaller fonts (13px base), tighter spacing (--space-3 gaps), more items per screen. Operator needs to see everything without scrolling

### Mobile Voter Ballot Visual Redesign (SEC-05)
- **Full-screen experience:** 100dvh, no browser chrome distraction. Clean header with session title and member name
- **Motion display:** Current motion title large and centered, description below in readable size. Motion number as a subtle badge
- **Vote buttons:** Minimum 72px tall, full-width stacked buttons — Pour (green), Contre (red), Abstention (amber), Blanc (neutral). Each button has an icon + label. Pressed state with immediate visual feedback (< 50ms)
- **Confirmation state:** After voting, show selected choice with a large checkmark, "Vote enregistré" confirmation text, and a subtle animation. Irreversibility notice in muted text below
- **Waiting state:** Between votes, show "En attente du prochain vote" with a subtle pulse animation. Session info visible
- **Results display:** After vote closes, show results with colored bar chart (reuse existing pattern). ADOPTÉ/REJETÉ verdict prominent
- **Speech/hand raise:** 72px circular button, fixed position, always accessible. Tooltip on long-press explaining the action
- **Typography:** clamp() fluid scaling (already from v4.1). Button labels large and clear. Motion title scales with viewport
- **Dark consideration:** Many assemblies happen in meeting rooms — voter screen should have a high-contrast option or default to slightly darker surface to reduce glare
- **Safe area:** env(safe-area-inset-bottom) on bottom elements for iPhone notch (already from v4.1)

### Claude's Discretion
- Exact operator status bar layout (flex items order)
- Vote progress bar segment colors and widths
- Voter button icon choices (checkmark, X, dash, circle)
- Whether to add a subtle haptic-style animation on vote button press
- Exact guidance panel content and styling

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Page files
- `public/operator.htmx.html` — Operator console HTML
- `public/vote.htmx.html` — Voter ballot HTML
- `public/assets/css/operator.css` — Operator styles
- `public/assets/css/vote.css` — Voter styles
- `public/assets/css/design-system.css` — Tokens, shell layout
- `public/assets/js/pages/operator.js` — Operator JS (SSE, vote management, agenda)
- `public/assets/js/pages/vote.js` — Voter JS (ballot, confirmation, results)
- `public/partials/operator-exec.html` — Operator execution panel partial

### Components
- `public/assets/js/components/ag-tooltip.js` — Tooltip component
- `public/assets/js/components/ag-badge.js` — Badge component (pulse for live)

### Requirements
- `.planning/REQUIREMENTS.md` — CORE-03, SEC-05

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable from Phase 35-36
- Gradient CTA button pattern (wizard Next, hub action, login CTA)
- ag-tooltip wrapping pattern for action buttons
- ag-badge status pills with pulse animation for live states
- JetBrains Mono for data numbers (KPI pattern)

### Operator Current State
- CSS Grid 3-row layout with 280px sidebar (Phase 32)
- Meeting bar with persona accent stripe
- Tab navigation with horizontal layout
- SSE connectivity indicators (live/reconnecting/offline)
- Delta vote badges (+N)
- Post-vote and end-of-agenda guidance panels

### Voter Current State
- 100dvh flex column layout (Phase 32)
- clamp() fluid typography (Phase 32)
- Fixed bottom nav with safe-area padding (Phase 32)
- 72px hand-raise button
- Vote buttons exist but need visual polish

### What Needs Visual Redesign
- Operator: agenda items need status indicators, action buttons need tooltips, live vote panel needs data prominence, SSE indicator needs refinement
- Voter: vote buttons need icon + label + immediate feedback, confirmation state needs polish, waiting state needs animation, results display needs drama

</code_context>

<specifics>
## Specific Ideas

- Operator console should feel like a mission control — dense but organized, every piece of info has a purpose
- Voter ballot should feel like Apple Pay confirmation — clean, focused, one clear action at a time
- The vote confirmation should feel satisfying — not just "done" but "your voice was heard"

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 37-live-session-conduct*
*Context gathered: 2026-03-20*
