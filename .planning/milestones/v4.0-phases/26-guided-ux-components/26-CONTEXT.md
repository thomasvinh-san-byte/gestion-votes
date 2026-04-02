# Phase 26: Guided UX Components - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Build a self-explanatory UX layer where the design itself guides users — no bolted-on tours. Replace 9 unwired btnTour buttons with contextual help panels. Add status-aware dashboard cards with next-action CTAs. Implement ag-empty-state component for all empty containers. Add disabled button explanations via tooltips. Ensure every screen communicates "what to do next" through its design, not through overlays.

**Philosophy:** If you need a tour, the design failed. The UI must be self-explanatory.

</domain>

<decisions>
## Implementation Decisions

### Tour Buttons → Contextual Help
- **NO Driver.js tours** — the design guides naturally, not through sequential step tours
- **9 btnTour buttons** on dashboard, members, postsession, operator, analytics, hub, meetings, wizard → transform into contextual help panels (not sequential tours)
- The help panel shows page-relevant hints/tips — not a walkthrough
- **ag-hint (?) tooltips** are acceptable but sparingly — only for genuinely technical/legal terms (majorité absolue, quorum, scrutin secret) that can't be simplified by rewording
- ag-tooltip (existing component) is sufficient for technical term hints — no new ag-hint component needed
- Driver.js NOT needed — remove from the stack for this phase

### Empty States (ag-empty-state)
- **Tone:** Professional and encouraging — "Aucune séance créée — Créez votre première séance pour démarrer"
- **Visual:** Sober SVG icon above text (existing emptyState() helper already has icon support)
- **Action button:** Secondary (outline/ghost) — the empty state guides without shouting
- **ag-empty-state Web Component** replaces the `Shared.emptyState()` helper function gradually
- Every list/table that can be empty must have a proper empty state with heading + description + action

### Status-Aware Dashboard Cards
- **Card CTA:** One action button at the bottom of each session card, text changes by lifecycle state:
  - draft → "Compléter →"
  - scheduled → "Enregistrer les présences →"
  - frozen → "Ouvrir la console →"
  - live → "● En cours — Rejoindre →" (with green pulse dot)
  - closed → "Générer le PV →"
  - validated → "Archiver →"
  - archived → no action, card muted
- **Role-aware views:** Admin sees stats + user management section first. Operator sees session cards first. Separate layout per role.
- **Live session card:** Visually distinct — colored border + pulsing green dot + "● En cours" text. Stands out from other cards.

### Disabled Button Explanations
- **Mechanism:** Tooltip on hover via existing ag-tooltip component
- **Wording pattern:** "Disponible après [condition]" — tells the user what to DO, not what's missing
  - "Disponible après ajout des résolutions"
  - "Disponible après enregistrement des présences"
  - "Disponible après clôture de tous les votes"
- Apply to all primary action buttons that can be locked (freeze, open vote, validate, archive, etc.)

### Inline Contextual Help
- Technical terms get (?) ag-tooltip: majorité absolue, quorum, scrutin secret, procuration, tantième → voix
- **Sparingly** — if a label needs a hint, first try to rewrite the label. Hint is the fallback.
- No ag-hint component — ag-tooltip is sufficient

### Claude's Discretion
- ag-empty-state internal implementation (slots, attributes)
- Help panel design for btnTour replacement
- Exact empty state content per page (heading, description, action text)
- Which technical terms get tooltips (use judgment — only truly opaque terms)
- Dashboard card layout specifics (grid, gap, responsive breakpoints)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing components and patterns
- `public/assets/js/core/shared.js` — `emptyState()` helper function (lines ~277+) — the pattern ag-empty-state replaces
- `public/assets/js/components/ag-tooltip.js` — Existing tooltip component for disabled button explanations and term hints
- `public/assets/css/design-system.css` — Tour overlay CSS at lines 3741-3940 (may need repurposing for help panel)
- `public/assets/js/components/ag-popover.js` — Popover component (potential base for help panel)

### Pages with btnTour buttons (all 9)
- `public/dashboard.htmx.html` (line 49)
- `public/members.htmx.html` (line 45)
- `public/postsession.htmx.html` (line 56)
- `public/operator.htmx.html` (line 63)
- `public/analytics.htmx.html` (line 59)
- `public/hub.htmx.html` (line 56)
- `public/meetings.htmx.html` (line 49)
- `public/wizard.htmx.html` (line 54)

### Dashboard JS
- `public/assets/js/pages/dashboard.js` — Current dashboard rendering (needs status-aware card CTAs)

### Research
- `.planning/research/FEATURES.md` — UX patterns: status-aware dashboards (Pattern 3), empty states (Pattern 4), disabled button explanations (Pattern 5), contextual inline help (Pattern 5)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Shared.emptyState({icon, title, desc, actionHtml})` — Already renders empty states with SVG icon, heading, description, action. ag-empty-state extends this as a Web Component.
- `ag-tooltip` component — Already handles hover tooltips. Use for disabled button explanations.
- `ag-popover` component — Can be adapted for the help panel that replaces btnTour.
- Tour CSS in design-system.css (lines 3741-3940) — Overlay, spotlight, bubble styles already exist. Can repurpose for help panel.

### Established Patterns
- Web Components follow `AgXxx extends HTMLElement` with shadow DOM
- Page scripts use IIFE + var pattern
- `emptyState()` is called from 15+ page scripts — ag-empty-state must be backward-compatible

### Integration Points
- `dashboard.js` — Add status-aware card rendering with lifecycle-based CTAs
- All 9 pages with `btnTour` — Wire to help panel instead of tours
- All page scripts calling `emptyState()` — Gradually replace with `<ag-empty-state>`
- All disabled buttons across hub, wizard, operator — Add tooltip data attributes

</code_context>

<specifics>
## Specific Ideas

- "Guided as in the design by ITSELF guides you. I think guided tours are over the top. We need to see what they actually bring. The design should guide the user naturally without them realising."
- "Hints are good it's just that it must not be overused" — hints only for genuinely opaque technical/legal terms
- Admin vs operator get **separate dashboard layouts**, not just different permissions on the same view

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 26-guided-ux-components*
*Context gathered: 2026-03-18*
