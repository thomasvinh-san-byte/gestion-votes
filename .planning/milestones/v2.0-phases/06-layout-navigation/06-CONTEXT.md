# Phase 6: Layout & Navigation - Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Align the application shell (sidebar, header, footer, mobile bottom nav) with wireframe v3.19.2. Most elements already exist — this phase aligns styling, organization, and behavior. Does NOT touch page-specific content (handled in Phase 7+).

</domain>

<decisions>
## Implementation Decisions

### Sidebar section organization (NAV-01, NAV-02)
- Follow exact wireframe 5-section grouping:
  1. **Préparation**: Dashboard, Sessions, Wizard (Créer séance)
  2. **Séance en direct**: Opérateur, Affichage salle — ONLY visible when a session is active
  3. **Après la séance**: Post-session, Archives
  4. **Contrôle**: Audit, Statistiques
  5. **Système**: Utilisateurs, Paramètres, Aide
- Sections are collapsible with collapse state saved to localStorage
- Hide unauthorized nav items entirely (don't show grayed-out items)
- Keep existing `data-include-sidebar` dynamic loading pattern
- Notification badges (count dots) on nav items with pending actions
- Rail (58px collapsed): icons only, no labels — labels appear on hover/expand
- Pin button positioned at top of sidebar, next to logo area

### Header content (NAV-03)
- Notification panel: action-focused dropdown (not side drawer)
  - Shows: pending convocations, quorum warnings, PV to send, session reminders
  - Bell icon with count badge
  - Dropdown panel (300-400px wide), max 5-6 items, "Voir tout" link at bottom
- Search: real-time results as you type (Cmd+K), already styled in design-system.css
- Theme toggle: already exists in shell.js
- Claude's Discretion: Whether to keep breadcrumb + page title or simplify to breadcrumb only

### Mobile bottom navigation (NAV-04)
- Exactly 5 fixed tabs: Dashboard, Sessions, Hub (Fiche), Opérateur, Paramètres
- Breakpoint: 768px — below this, sidebar hidden, bottom nav shown
- Active tab: primary blue color + filled icon (standard mobile pattern)
- No overflow menu — 5 tabs is the fixed set

### Footer (NAV-05)
- Minimal content: logo + version + help link + accessibility link
- Flows with page content (not sticky)
- Hidden on mobile viewports (bottom nav takes over)
- Accessibility link navigates to Settings > Accessibility tab (Phase 13 builds the page)

### Accessibility & ARIA (NAV-06)
- Skip-to-content link already exists — verify it works correctly
- ARIA landmark roles on nav, main, header, footer regions
- Claude's Discretion: Exact ARIA attributes and keyboard navigation details

### Claude's Discretion
- Header breadcrumb vs breadcrumb+title layout decision
- Exact notification panel item format and grouping
- Sidebar animation easing refinements
- ARIA attribute specifics and keyboard focus management
- Whether "Séance en direct" section shows with placeholder text when no session vs completely hidden

</decisions>

<specifics>
## Specific Ideas

- The wireframe HTML file (ag_vote_wireframe.html) is the pixel-perfect reference for layout dimensions and spacing
- Sidebar uses dark background (#0C1018) with light text — already tokenized in Phase 4 (--sidebar-bg, --sidebar-text, etc.)
- Header glassmorphism (backdrop-filter: blur(20px)) already implemented
- "Séance en direct" section appearing/disappearing dynamically creates a contextual sidebar that adapts to app state

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `shell.js` (799 lines): Full sidebar rail/expand/pin, mobile hamburger, bottom nav, theme toggle, drawer system
- `design-system.css`: Complete .app-sidebar, .app-header, .app-footer, .nav-item, .nav-group, .sidebar-fade, .bottom-nav, .skip-link CSS
- `app.css` (745 lines): Additional layout utilities
- `.search-overlay` + `.search-box`: Full Cmd+K search overlay with keyboard navigation styling
- `ag-page-header.js`: Web component for page headers

### Established Patterns
- Sidebar: fixed position, overlays on hover (no layout shift), uses CSS custom properties exclusively
- Nav items: `.nav-item` class with `.active` state and `::before` accent bar
- Nav groups: `.nav-group` with `.nav-group-label` and `.nav-group-chevron` for collapsible sections
- Dynamic sidebar loading: `data-include-sidebar` attribute with `data-page` for active state
- Mobile toggle: `.hamburger` button shows on mobile, `.mobile-nav-toggle` for slide-in sidebar
- Bottom nav: `.bottom-nav` class exists in CSS, shown at mobile breakpoints

### Integration Points
- Every page HTML uses `<aside class="app-sidebar" data-include-sidebar data-page="..."></aside>` pattern
- shell.js auto-initializes on DOMContentLoaded
- Header is inline in each page HTML (not dynamically loaded)
- Footer is inline in each page HTML
- Sidebar content loaded from a shared partial (sidebar.html or similar)

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 06-layout-navigation*
*Context gathered: 2026-03-12*
