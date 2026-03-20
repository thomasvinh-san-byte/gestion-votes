# Phase 42: Stabilization - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix all v4.2 visual regressions and broken JS interactions. No new features, no redesign — pure bug fixing to restore a clean baseline before the ground-up rebuilds begin (Phases 43-48).

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure/bug-fix phase. The approach is:
1. Audit every page for visual regressions (broken layouts, misaligned elements, missing styles)
2. Audit every page for JS errors (broken event handlers, missing DOM elements, failed querySelector calls)
3. Fix each regression at the source — don't add workarounds
4. If a regression is in a page that will be rebuilt from scratch in Phases 43-48, mark it as "deferred to rebuild" instead of patching it now

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### All page files (audit targets)
- All `public/*.htmx.html` and `public/*.html` files
- All `public/assets/css/*.css` files
- All `public/assets/js/pages/*.js` files
- All `public/assets/js/components/*.js` files

### Key context
- `.planning/REQUIREMENTS.md` — FIX-01, FIX-02

</canonical_refs>

<code_context>
## Existing Code Insights

### Known Regression Sources (from v4.2)
- Admin page: table→flex migration in 41.4 (renderUsersTable changed to emit .user-row divs)
- Wizard: HTML restructuring in 41.2 (collapsed sections, form-grid-3) and 41.3 (680px cap removed)
- Analytics: HTML restructuring in 41.5 (hero chart extraction, donut horizontal layout)
- Hub: wrapper div added for quorum+motions side by side (41.3)
- All pages: form field class changes in 41.1 (textarea.form-input → form-textarea)
- All pages: inline style removal in 34-02 (mobile footer, onboarding tips)

### Pages Being Rebuilt (defer regressions)
- Dashboard (Phase 43), Login (Phase 44), Wizard (Phase 45), Operator (Phase 46), Hub (Phase 47), Settings/Admin (Phase 48)
- Regressions on these pages can be deferred — they'll be rewritten from scratch

### Pages NOT being rebuilt (fix now)
- Post-session, Analytics, Meetings, Archives, Audit, Members, Users, Help, Email templates, Landing, Public/Projector, Report, Trust, Validate, Doc, Vote
- Regressions on these pages must be fixed in this phase

</code_context>

<specifics>
## Specific Ideas

No specific requirements — audit and fix systematically.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 42-stabilization*
*Context gathered: 2026-03-20*
