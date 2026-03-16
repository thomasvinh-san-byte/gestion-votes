# Phase 13: Settings & Help - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Extract settings from admin.htmx.html into a dedicated settings.htmx.html page, restructure into 4 wireframe-aligned tabs (Règles, Communication, Sécurité, Accessibilité), make settings editable with auto-save. Align help/FAQ page with wireframe: expand FAQ content, align tour launcher cards to cover all major pages, and verify tour targets work on each page.

</domain>

<decisions>
## Implementation Decisions

### Settings page extraction
- Extract settings from admin.htmx.html into dedicated settings.htmx.html + settings.css + settings.js
- Follow Phase 11/12 extraction pattern: admin keeps remaining tabs (meeting roles, policies, permissions, states, system)
- Update sidebar "Paramètres" link from admin.htmx.html?tab=settings to /settings.htmx.html
- Remove settings tab and all 6 settings sub-tabs from admin.htmx.html

### Settings tab restructure — 4 tabs
- Merge current 6 sub-tabs into 4 wireframe-aligned tabs:
  - **Règles** = vote rules (Art. 24/25/26/26-1 majority types, quorum settings) + distribution keys + double auth toggle + double approval toggle
    - Section 1: Sécurité du vote (double auth, double approval toggles)
    - Section 2: Quorum (base, percentage threshold)
    - Section 3: Types de majorité (Art. 24/25/26/26-1 cards)
    - Section 4: Clés de répartition (distribution keys)
  - **Communication** = mail settings + general settings merged
    - Support email, email templates preview, notification preferences (SET-02)
  - **Sécurité** = existing security tab
    - 2FA management, session timeout (SET-03)
  - **Accessibilité** = existing accessibility tab
    - Text size (A/A+/A++), high contrast toggle, focus indicators (SET-04)

### Settings save behavior — auto-save
- Auto-save on change: each toggle/input triggers immediate API call
- Success feedback via ag-toast (brief success toast)
- Error handling: error toast + field revert on failure
- No save buttons needed — reduces mental load
- Remove the "Module en cours de développement" read-only warning banner

### Accessibility settings — dual level
- Settings page configures tenant-level defaults (what all users get by default)
- Users can override with personal preferences (via profile or header quick-toggle)
- Tenant defaults stored in database, user overrides in localStorage or user profile

### FAQ content
- Static HTML (not API-driven) — content is part of the page
- Expand FAQ content to 3-5 items per category minimum
- Categories: Général, Opérateur, Vote, Membres, Sécurité (keep existing)
- Tone: professional and concise — 2-3 sentences max, technical where needed
- Align accordion styling with wireframe (category filter tabs, search)

### Tour launcher cards
- Expand from current 7 cards to 8+ covering all major pages
- Keep existing: Séances, Membres, Opérateur, Post-Session, Statistiques, Audit, Administration
- Add: Dashboard, Hub (session hub)
- Each card links to target page with ?tour=1 to auto-start ag-tour
- Verify that each target page has working data-tour step attributes
- Fix any missing tour step definitions on target pages

### Claude's Discretion
- Exact tab content layout within each settings tab
- How to merge mail + general sub-tabs into Communication tab
- FAQ item content (specific questions and answers to add)
- Tour card visual styling and responsive grid
- How distribution keys UI fits within the Règles tab
- API endpoint design for settings persistence
- Error state handling for auto-save failures
- How user-level accessibility overrides interact with tenant defaults

</decisions>

<specifics>
## Specific Ideas

- UX priority: lighten mental load is non-negotiable — auto-save removes "did I save?" anxiety
- Settings extraction follows the same pattern as audit (Phase 11) and users (Phase 12) — proven approach
- Règles tab becomes the single source of truth for all voting rules configuration
- FAQ tone matches existing professional style — short, direct, technical where needed
- Tour verification ensures the help page tours actually work end-to-end, not just link to pages

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `admin.htmx.html` settings panel (lines 406-865): 6 sub-tabs with vote rules, distribution keys, security, mail, general, accessibility — all content to extract
- `admin.css` (893 lines): Settings tab styles (.settings-tabs, .settings-tab, .settings-panel, .settings-majority-grid, etc.)
- `admin.js` (1453 lines): Settings sub-tab switching, majority type display, distribution key management
- `help.htmx.html` (546 lines): Tour grid (7 cards), FAQ search, category tabs, accordion FAQ items
- `help.css`: Tour card grid, FAQ accordion, search, category tabs
- `help.js`: FAQ search/filter, accordion toggle, category tab switching
- `ag-tour` web component (Phase 5): Step-by-step walkthrough with data-tour targets
- `ag-toast` component: Notification toasts for auto-save feedback

### Established Patterns
- Page extraction: audit (Phase 11), users (Phase 12) — own HTML + CSS + JS files
- IIFE pattern for page JS, `var` keyword, escapeHtml() for XSS prevention
- `hidden` attribute for visibility, design tokens for all values
- Auto-save pattern: toggle change → fetch API → toast feedback
- Sidebar navigation under "Système" section

### Integration Points
- Sidebar: update "Paramètres" href from admin.htmx.html?tab=settings to /settings.htmx.html
- Admin page: remove settings tab button and all settings panels
- Settings API: needs endpoints for reading/writing tenant settings (may need to create or extend existing)
- Accessibility: tenant defaults via API, user overrides via localStorage
- Tour targets: data-tour attributes on Dashboard, Hub, Séances, Membres, Opérateur, Post-Session, Statistiques, Audit, Administration pages
- ag-tour component: ?tour=1 URL param triggers auto-start
- shell.js: update Cmd+K search index with "Paramètres" entry pointing to new page

### API Endpoints Available
- GET/PUT /api/v1/admin_settings.php — likely settings CRUD (to verify)
- Existing admin.js handles settings display — extract save logic to settings.js

</code_context>

<deferred>
## Deferred Ideas

- API-driven FAQ (admin can edit FAQ entries from a CMS) — future enhancement
- Per-user settings profiles with sync across devices — future enhancement

</deferred>

---

*Phase: 13-settings-help*
*Context gathered: 2026-03-16*
