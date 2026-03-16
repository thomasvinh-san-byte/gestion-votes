# Phase 14: Integration Bug Fixes - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning
**Source:** v2.0 Milestone Audit (direct bug fix — no discussion needed)

<domain>
## Phase Boundary

Fix 3 broken navigation/script wiring issues identified in the v2.0 milestone audit. All fixes are one-line changes — no new features, no refactoring.

</domain>

<decisions>
## Implementation Decisions

### Sidebar Paramètres link (partials/sidebar.html line 109)
- Change `href="/admin.htmx.html?tab=settings"` → `href="/settings.htmx.html"`
- Change `data-page="parametres"` → `data-page="settings"` (settings.htmx.html uses `data-page="settings"`)

### Mobile bottom nav Paramètres link (shell.js line 440)
- Change href from `/admin.htmx.html?tab=settings` → `/settings.htmx.html`
- Change page value from whatever it is → `settings` (to match data-page on settings page)

### Users page script path (users.htmx.html line 171)
- Change `src="/assets/js/pages/meeting-context.js"` → `src="/assets/js/services/meeting-context.js"`
- The file exists at `/assets/js/services/meeting-context.js` (6265 bytes)

### Claude's Discretion
- None — all fixes are precisely defined by the audit

</decisions>

<code_context>
## Existing Code Insights

### Files to Modify
- `public/partials/sidebar.html` line 109: sidebar Paramètres nav-item
- `public/assets/js/core/shell.js` line 440: mobile bottom nav items array
- `public/users.htmx.html` line 171: script tag src attribute

### Verification Points
- settings.htmx.html exists at `/public/settings.htmx.html` with `data-page="settings"`
- shell.js line 693 (search palette) already correctly references `/settings.htmx.html`
- `meeting-context.js` exists at `/public/assets/js/services/meeting-context.js`

### Integration Points
- Sidebar partial is loaded by shared.js on all 14 shell pages
- Mobile bottom nav is rendered by shell.js on all shell pages
- No other pages reference the wrong paths (footer accessibility link already correct)

</code_context>

<specifics>
## Specific Ideas

No specific requirements — all fixes are exact one-line corrections defined by the audit.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 14-integration-fixes*
*Context gathered: 2026-03-16 via audit gap closure (no discussion needed)*
