# Milestones

## v4.1 Design Excellence (Shipped: 2026-03-19)

**Phases completed:** 10 phases, 34 plans, 0 tasks

**Key accomplishments:**
- (none recorded)

---

## v4.0 Clarity & Flow (Shipped: 2026-03-18)

**Scope:** Transform AG-VOTE into a self-explanatory, visually impressive application — zero training, officiel et confiance design language
**Phases:** 5 phases (25-29), 18 plans
**Timeline:** 1 day (2026-03-18)
**Requirements:** 55/55 complete
**Codebase:** 118 files changed, +14,955 / -1,170 lines

**Key accomplishments:**
1. PDF resolution documents — FilePond upload, authenticated serve endpoint, ag-pdf-viewer (inline/sheet/panel), SSE documentAdded events, Docker persistent volume
2. Guided UX layer — ag-empty-state Web Component (11 migrations), status-aware dashboard cards with lifecycle CTAs, 8 contextual help panels, 7 disabled button tooltips
3. Copropriété → generic AG vocabulary — 45+ occurrences across 21 files transformed, lot field removed, openKeyModal stub removed, PHPUnit weighted-vote regression test
4. Wizard UX overhaul — named stepper, optional steps, review card with "Modifier" links, 3 motion templates, progressive disclosure for voting power, autosave on blur
5. Hub enhancements — checklist blocked reasons, ag-quorum-bar with threshold tick, motions list with doc badges, convocation send with confirmation
6. Operator console live indicators — SSE connectivity (live/reconnecting/offline), delta vote badge (+N ▲), post-vote/end-of-agenda guidance panels
7. Voter full-screen ballot — data-vote-state machine, 72px stacked cards, optimistic feedback < 50ms, confirmation state, inline irreversibility notice
8. Result cards — collapsible details/summary, CSS-only bar charts, ADOPTÉ/REJETÉ verdict, threshold display
9. Design system — @layer (base/components/v4), 10 color-mix() derived tokens, dark mode parity, @starting-style animations, View Transitions
10. All-page CSS polish — 15 page CSS files audited, tokens replacing hardcoded colors, transitions capped ≤200ms, inline style audit

---

## v3.0 Session Lifecycle (Shipped: 2026-03-18)

**Phases completed:** 13 phases, 37 plans, 4 tasks

**Key accomplishments:**
- (none recorded)

---

## v2.0 UI Redesign (Shipped: 2026-03-16)

**Scope:** Align all pages and components with AG-Vote v3.19.2 "Acte Officiel" wireframe
**Phases:** 12 feature phases (4-13) + 3 gap closure phases (14-15), 37 plans
**Timeline:** 24 days (2026-02-21 → 2026-03-16)
**Requirements:** 54/54 complete
**Codebase:** 119 HTML/CSS/JS files, 59,330 LOC

**Key accomplishments:**
1. Design system with 64 CSS tokens, dark/light theme switching
2. Component library: modal, toast, confirm dialog, popover, progress bar, guided tour, session banner
3. App shell: sidebar rail/expand, header with search/notifications, mobile bottom nav, ARIA landmarks
4. Full session lifecycle: dashboard KPIs, session list/calendar, 4-step create wizard, session hub
5. Operator console: live KPI strip, resolution sub-tabs, agenda sidebar, quorum modal, P/F shortcuts
6. Post-session workflow: 4-step stepper, archives with pagination, audit log with table/timeline + CSV export
7. Statistics page with KPI trends, charts, and PDF export
8. Users management with role panel, avatar table, and pagination
9. Settings with 4 tabs (rules, communication, security, accessibility) including text size and high contrast
10. Help/FAQ with category accordion and 9 guided tour launcher cards

---

