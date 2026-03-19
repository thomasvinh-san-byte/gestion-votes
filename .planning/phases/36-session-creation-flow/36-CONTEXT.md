# Phase 36: Session Creation Flow - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual redesign of the Wizard (session creation) and Hub (session management) pages. These are the core session lifecycle pages — the wizard creates sessions, the hub manages them before going live. Must feel as polished as Linear's issue creation flow.

</domain>

<decisions>
## Implementation Decisions

### Design Philosophy (carried from Phase 35)
- Reference-driven: Linear (issue creation flow), Notion (clean forms), Stripe (progression)
- Top 1% = intentional composition, generous whitespace, clear visual hierarchy
- Tooltips for guidance on every field/action, no guided tours
- Before/after contrast must be immediately visible in the browser

### Wizard Visual Redesign (CORE-02)
- **Stepper:** Active step clearly highlighted with primary color fill + checkmark on completed steps. Connector lines show progression. Step labels visible, not just numbers. Current step name as subtitle below page title
- **Form card:** Clean white surface card, generous internal padding (32px), subtle shadow. Form sections visually separated by light dividers or background color shifts
- **Field layout:** Labels above fields (14px semibold), generous vertical gap between fields (24px). Helper text below fields in muted color. Required indicators subtle (not red asterisk)
- **Field tooltips:** ag-tooltip on info icons next to complex field labels — "Quorum minimum" gets a tooltip explaining what it means
- **Progressive disclosure:** Optional/advanced sections collapsed by default with a clean "Options avancées" toggle — not a cluttered form
- **Step navigation:** Sticky footer with clear Back/Next buttons, space-between layout. Next button is primary gradient (like login CTA), Back is ghost. Step indicator in footer showing "Étape 2 sur 4"
- **Review step:** Final review card with all entered data in a clean summary table. "Modifier" links next to each section — not a wall of text
- **Motion templates:** Template selector should feel like Linear's template picker — clean cards with icon + title + description, not a dropdown
- **Micro-interactions:** Step transition with subtle fade, field focus with ring animation, validation with smooth border-color change
- **Empty form state:** When starting fresh, show welcoming placeholder text in the first field, not a blank intimidating form

### Hub Visual Redesign (CORE-04)
- **Sidebar stepper:** Vertical stepper showing session preparation progress. Active step has a colored dot + bold label, completed steps have checkmarks, pending steps are muted. Sticky at top while content scrolls
- **Quorum bar:** The most prominent element — large progress bar with clear percentage, colored segments (green when met, amber approaching, red below). Tooltip explaining quorum rules
- **Checklist:** Each preparation item as a card with checkbox, title, description, and status badge. Completed items have a subtle strikethrough or muted style — not just a checked box
- **Session info header:** Session title, date, type, and status displayed prominently at the top. Status as a large colored badge
- **Action buttons:** "Lancer la session" (primary CTA) prominently placed when all prerequisites met. Disabled with tooltip explaining what's missing when blocked
- **Convocation section:** Clean card with member count, send status, and resend action. Tooltip on the count explaining who hasn't been notified
- **Motions list:** Each motion as a compact card showing title, type (ordinaire/extraordinaire), and attached document indicator (PDF icon if docs exist)
- **Responsive:** At 768px stepper stacks above content as a horizontal progress bar

### Claude's Discretion
- Exact animation durations for step transitions
- Whether to use icons or just text in stepper labels
- Exact shading for completed checklist items
- Quorum bar height and label positioning
- Motion card density in the hub

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Page files
- `public/wizard.htmx.html` — Wizard HTML structure
- `public/hub.htmx.html` — Hub HTML structure
- `public/assets/css/wizard.css` — Wizard styles
- `public/assets/css/hub.css` — Hub styles
- `public/assets/css/design-system.css` — Tokens, component specs, shell layout
- `public/assets/js/pages/wizard.js` — Wizard JS (step logic, validation, templates)
- `public/assets/js/pages/hub.js` — Hub JS (checklist, quorum, motions)

### Components
- `public/assets/js/components/ag-stepper.js` — Stepper component (Phase 31 refreshed)
- `public/assets/js/components/ag-tooltip.js` — Tooltip component
- `public/assets/js/components/ag-badge.js` — Badge component

### Requirements
- `.planning/REQUIREMENTS.md` — CORE-02, CORE-04

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable from Phase 35
- ag-tooltip pattern for field guidance (same as dashboard KPI tooltips)
- Gradient CTA button pattern from login (reuse for wizard "Next" button)
- Colored icon badge pattern from dashboard KPIs (reuse for hub checklist status)

### Wizard Current State
- 4-step wizard with ag-stepper horizontal at top
- Form card centered at 680px max-width (Phase 32)
- Step nav sticky at bottom (Phase 32)
- Motion templates exist as cards in step 2
- Progressive disclosure with checkbox toggles on voting power section

### Hub Current State
- 220px sidebar stepper + main content grid (Phase 33)
- Quorum bar with ag-quorum-bar component
- Checklist items as cards
- Convocation send with confirmation modal
- Motions list with document badges

### What Needs Visual Redesign
- Wizard stepper: generic steps → polished Linear-quality progression
- Wizard forms: functional but crowded → generous whitespace + field tooltips
- Wizard step nav: basic buttons → gradient CTA with step counter
- Hub stepper sidebar: basic list → polished vertical progression indicator
- Hub quorum bar: functional → visually prominent with clear color coding
- Hub checklist: basic cards → styled preparation items with status badges
- Hub action buttons: generic → prominent CTA with disabled state tooltips

</code_context>

<specifics>
## Specific Ideas

- The wizard should feel like creating an issue in Linear — focused, clean, no cognitive overhead
- The hub should feel like a project dashboard in Notion — clear status, actionable items, no confusion about what's next
- Quorum bar must be the visual anchor of the hub page — it's the most important information

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 36-session-creation-flow*
*Context gathered: 2026-03-19*
