# Phase 51: Utility Pages - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Ground-up rebuild of 5 utility page groups: help/FAQ, email-templates, public/projector, report/PV, trust/validate/docs. Same v4.3 approach.

</domain>

<decisions>
## Implementation Decisions

### Approach (carried from v4.3)
- Read existing JS before touching HTML
- Rewrite HTML+CSS, update JS selectors if needed
- Verify backend, browser test before done

### Page-Specific Notes
- **Help/FAQ**: Accordion with search. Simpler page — may not need JS changes.
- **Email templates**: Editor with preview panel. Reference: settings sidebar-tab pattern.
- **Public/Projector**: Projection-optimized full-screen results. Large typography. No sidebar/header.
- **Report/PV**: Print-ready layout at 880px max-width. `@media print` considerations.
- **Trust/Validate/Docs**: Verification status display. Simple informational pages.

### Claude's Discretion
- All implementation choices — pure rebuild phase

</decisions>

<code_context>
## Existing Code Insights

### Pages to Rebuild
- `public/help.htmx.html` + `help.css`
- `public/email-templates.htmx.html` + `email-templates.css` + `email-templates-editor.js`
- `public/public.htmx.html` + (inline or public.css)
- `public/report.htmx.html` + (inline or report.css)
- `public/trust.htmx.html` + `public/validate.htmx.html` + `public/docs.htmx.html`

</code_context>

<specifics>
## Specific Ideas

Same v4.3 approach. Last phase of v4.4.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
