# Phase 3: Coherence Cross-Pages - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Unify three cross-page inconsistencies: (1) version numbers, (2) footer accent typo, (3) modal patterns. No visual design changes — just consistency.

</domain>

<decisions>
## Implementation Decisions

### Version Unification (UI-07)
- **D-01:** Create a single PHP constant or config value for the app version
- **D-02:** PageController::serveFromUri injects version via string replacement when serving .htmx.html pages
- **D-03:** Remove all hardcoded version strings from HTML files (v3.19, v4.3, v4.4 in 21 footers + v3.19 in sidebar)
- **D-04:** Landing page index.html: replace hardcoded v5.0 with the same version source
- **D-05:** Use the current tag version (v1.8) or a simple "v2.0" for the unified display version — Claude decides

### Footer Accent Fix (UI-08)
- **D-06:** Replace "Accessibilite" with "Accessibilité" in all 13 pages: analytics, docs, email-templates, help, hub, members, postsession, public, report, trust, validate, vote, wizard
- **D-07:** Simple find-and-replace — no structural changes

### Modal Unification (UI-09)
- **D-08:** Target pattern: `<div class="modal-backdrop" hidden>` sibling to `<div class="modal" role="dialog" aria-modal="true" hidden>`
- **D-09:** Pages to migrate: validate (validate-modal-backdrop), trust (audit-modal-overlay), email-templates (template-editor without role), meetings (backdrop wrapping modal as child)
- **D-10:** Keep `<ag-modal>` web component (users) and `<dialog>` native (members) as-is — they are valid modern patterns, not regressions
- **D-11:** Add `role="dialog"` and `aria-modal="true"` where missing

### Claude's Discretion
- Exact version string to display (e.g., "v2.0", "v1.8", or dynamically from git tag)
- Whether to use a PHP constant, .env variable, or config file for version source
- How to handle index.html version (static file, not served through PHP — may need build-time injection or JS fetch)

</decisions>

<canonical_refs>
## Canonical References

### Requirements
- `.planning/REQUIREMENTS.md` — UI-07, UI-08, UI-09

### Key Files
- `app/Controller/PageController.php` — serves HTMX pages, injection point for version
- `public/partials/sidebar.html` — sidebar version display (v3.19)
- All 21 `public/*.htmx.html` — footer version strings
- `public/index.html` — landing page footer (v5.0)
- Pages with non-standard modals: validate, trust, email-templates, meetings

</canonical_refs>

<code_context>
## Existing Code Insights

### Version Locations (from audit)
- Sidebar: `v3.19` in `partials/sidebar.html:141`
- 10 pages: `v3.19` in footer
- 6 pages: `v4.3` in footer
- 5 pages: `v4.4` in footer
- Landing: `v5.0` in `index.html:344`

### Modal Patterns to Migrate
1. **validate**: `validate-modal-backdrop` + `validate-modal` — rename classes
2. **trust**: `audit-modal-overlay` + `audit-modal` — rename classes
3. **email-templates**: `template-editor` div without role — add role="dialog"
4. **meetings**: backdrop IS the parent wrapping modal — restructure to siblings

### Patterns to Keep
- `<ag-modal>` (users) — web component, handles its own ARIA
- `<dialog>` (members) — native HTML dialog, proper semantics

</code_context>

<specifics>
## Specific Ideas

No specific references — standard consistency cleanup.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-coherence-cross-pages*
*Context gathered: 2026-04-20*
