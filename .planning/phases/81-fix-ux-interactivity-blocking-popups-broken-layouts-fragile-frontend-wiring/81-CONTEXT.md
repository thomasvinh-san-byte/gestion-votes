# Phase 81: Fix UX interactivity — blocking popups, broken layouts, fragile frontend wiring - Context

**Gathered:** 2026-04-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix systemic UX issues across the entire application: blocking/inconsistent popups, broken page layouts that don't exploit horizontal space, fragile frontend-backend wiring (silent failures, missing feedback), and visual incoherence between pages. The goal is to make all pages feel like one cohesive product with professional-grade interactivity.

</domain>

<decisions>
## Implementation Decisions

### Popups & confirmations
- **D-01:** Choose ONE confirmation design pattern (AgConfirm.ask() vs inline confirm vs undo toast) and apply it uniformly across all pages. Eliminate any remaining window.confirm() or window.alert() usage entirely.
- **D-02:** All modales must close on backdrop click, Escape key, and have proper focus trap. Fix ag-modal behavior if needed.
- **D-03:** Reduce confirmation fatigue — only confirm truly destructive/irreversible actions. Minor actions should execute directly with feedback toast.

### Layouts & mise en page
- **D-04:** Wizard stays multi-step but must be corrected — horizontal field layout, fluid transitions, no per-step scrolling. Use width aggressively.
- **D-05:** Form fields use stacked labels (label above input) but on 2-3 column CSS grid where space allows. Not single-column stacking.
- **D-06:** Page width strategy is context-dependent: operator/dashboard can go full-width, form pages and settings can use max-width. Claude decides per page.
- **D-07:** ALL pages must exploit horizontal space. Screens are horizontal — layouts must reflect this.

### Frontend wiring & API
- **D-08:** Every fetch/API call must have visible feedback: loading indicator during request, success toast or UI update on completion, error toast on failure. No silent failures.
- **D-09:** Claude standardizes the feedback pattern per interaction type: loading+toast for creates/deletes, optimistic for toggles/quick actions, as fits context.
- **D-10:** Form validation follows codebase standards and best practices — HTML5 native as baseline, JS custom for complex rules. No double-submit.
- **D-11:** SSE/real-time connections must reconnect reliably and show connection status when disconnected.
- **D-12:** Navigation/state changes must not lose data — warn on unsaved changes where relevant.

### Coherence visuelle
- **D-13:** Two-pass approach: first consolidate CSS design tokens (spacing, radius, shadows) and enforce their usage via @layer cascade, then audit each page for compliance.
- **D-14:** Components (buttons, cards, tables, badges) must use consistent variants — eliminate ad-hoc styling overrides.
- **D-15:** Transitions/animations: Claude decides appropriate timing and easing per context (modals, toasts, tabs, loading states). Professional feel, not flashy.

### Claude's Discretion
- Specific confirmation pattern choice (AgConfirm vs inline vs undo toast) — as long as it's ONE pattern applied everywhere
- Per-page width decisions (full-width vs max-width)
- Animation timing and easing per component type
- Validation approach (HTML5 vs JS) per form complexity
- Toast audit — fix whatever is broken
- Loading state implementation (spinner, skeleton, disabled state)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Design System
- `public/assets/css/design-system.css` — CSS token hierarchy (primitive→semantic→component), @layer system, three-depth background model
- `public/assets/css/app.css` — Global layout styles and shell CSS

### Web Components (interaction layer)
- `public/assets/js/components/ag-modal.js` — Modal dialog with Shadow DOM, focus trap, open/close
- `public/assets/js/components/ag-confirm.js` — Promise-based confirmation dialog (AgConfirm.ask())
- `public/assets/js/components/ag-toast.js` — Toast notification system (AgToast.show())
- `public/assets/js/components/ag-popover.js` — Popover component
- `public/assets/js/components/ag-spinner.js` — Loading spinner
- `public/assets/js/components/ag-stepper.js` — Wizard stepper component

### Core JS Infrastructure
- `public/assets/js/core/shell.js` — App shell, navigation, page loading
- `public/assets/js/core/shared.js` — Shared utilities across pages
- `public/assets/js/core/utils.js` — Utility functions
- `public/assets/js/core/event-stream.js` — SSE/EventSource management
- `public/assets/js/core/page-components.js` — Component registration

### Key Page Modules (highest interaction density)
- `public/assets/js/pages/operator-tabs.js` — 3165-line god file, 82+ functions (highest fix priority)
- `public/assets/js/pages/wizard.js` — Session creation wizard
- `public/assets/js/pages/settings.js` — Settings page
- `public/assets/js/pages/admin.js` — Admin page
- `public/assets/js/pages/members.js` — Member management
- `public/assets/js/pages/meetings.js` — Meeting list
- `public/assets/js/pages/vote.js` — Voter interface

### Codebase Analysis
- `.planning/codebase/CONCERNS.md` — Known tech debt including operator-tabs.js god file, no JS modules, inline styles
- `.planning/codebase/CONVENTIONS.md` — Coding conventions and patterns

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **ag-modal**: Full-featured modal with Shadow DOM, focus trap, Escape handling — needs audit for backdrop-click close
- **ag-confirm**: Promise-based AgConfirm.ask() with variant support (danger, warning, info, success) — good pattern, needs universal adoption
- **ag-toast**: AgToast.show() static method with auto-dismiss — functional, needs audit for coverage gaps
- **ag-stepper**: Wizard stepper component — needs layout fix but core logic exists
- **ag-spinner**: Loading spinner — may need more usage across async operations

### Established Patterns
- Shadow DOM on all Web Components — CSS isolation, styling via CSS custom properties only
- Global IIFE pattern with window.* bridges (window.OpS, window.Utils, window.Shared, window.Shell)
- @layer (base/components/v4) CSS cascade — design tokens exist but may not be enforced everywhere
- Per-page CSS files (25 files) alongside per-page JS modules (31 files)

### Integration Points
- All pages loaded via shell.js as .htmx.html partials
- SSE managed by event-stream.js with Redis fan-out
- API endpoints at public/api/v1/ (150+ PHP files)
- Component registration via page-components.js index.js

</code_context>

<specifics>
## Specific Ideas

- User explicitly stated: "Il faut choisir un design pattern et le solidifier" — consistency is the #1 priority
- User memories confirm: screens are horizontal, layouts must use width aggressively (not vertical stacking)
- User memories confirm: form fields look outdated, wizard should fit better, UX needs general improvement
- Previous v4.2 broke design + JS interactions — v4.3 fixed some regressions but systemic issues remain

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 81-fix-ux-interactivity-blocking-popups-broken-layouts-fragile-frontend-wiring*
*Context gathered: 2026-04-03*
