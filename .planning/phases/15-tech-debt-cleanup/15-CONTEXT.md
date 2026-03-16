# Phase 15: Tech Debt Cleanup - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix non-API frontend tech debt from the v2.0 milestone audit. Frontend-only changes (HTML, CSS, JS, SVG) — no PHP/backend files. Specifically: missing SVG icons, script type attribute cleanup, and query parameter wiring. CSS fallbacks and orphaned loads were audited clean and are excluded.

</domain>

<decisions>
## Implementation Decisions

### Missing SVG Icons
- Add 4 missing Lucide icons to public/assets/icons.svg: #icon-help-circle, #icon-pause, #icon-smartphone, #icon-plus-circle
- Source SVG paths from the Lucide icon set for consistency with existing icons
- icon-pause uses Lucide outline style (two vertical bars, stroke-based) — matches operator page icon style
- icon-help-circle is used on 8+ pages — verify rendering on each page after adding
- icon-smartphone and icon-plus-circle: standard Lucide versions, no special treatment

### Script type="module" Removal
- Remove type="module" from all 16 inline script tags across .htmx.html pages
- Codebase uses var/IIFE pattern with global namespaces (Shared, Auth, Utils) — type="module" is misleading and semantically wrong
- Bulk find-and-replace across all 16 files, then verify with smoke check after
- Affected files: admin, analytics, archives, audit, docs, email-templates, help, meetings, members, operator, postsession, report, trust, users, validate, vote

### Query Parameter Wiring
- Claude's discretion: decide whether to wire up ?tab=notifications reading in admin.js or remove the parameter from shell.js — pick what fits codebase patterns best
- This is a frontend-only fix either way

### Scope Exclusions
- CSS token fallbacks: audited clean, no action needed
- Orphaned loads: audited clean, no action needed
- API-related tech debt: explicitly out of scope (frontend-only phase)
- No PHP/backend changes permitted

### Claude's Discretion
- Query param fix approach (wire up vs remove)
- Exact verification strategy for icon rendering
- Order of operations for the fixes

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- public/assets/icons.svg: SVG sprite sheet with all app icons — add new <symbol> elements here
- Lucide icon set: all 4 missing icons exist in Lucide and match the existing icon style

### Established Patterns
- Icons use <svg><use href="/assets/icons.svg#icon-name"></use></svg> pattern throughout
- Inline scripts on .htmx.html pages are IIFE-wrapped with var declarations and global namespace access
- shell.js generates navigation URLs with optional query parameters

### Integration Points
- icons.svg loaded by every page via the app shell
- Script tags are inline at the bottom of each .htmx.html page
- shell.js notification bell links to admin.htmx.html?tab=notifications

</code_context>

<specifics>
## Specific Ideas

- All 4 icons should come from Lucide to maintain visual consistency
- Verify icon-help-circle renders correctly on all 8+ pages that reference it (wizard, postsession, operator, members, dashboard, hub, analytics, meetings)
- Bulk type="module" removal preferred over per-file approach

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 15-tech-debt-cleanup*
*Context gathered: 2026-03-16*
