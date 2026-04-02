# Phase 34: Quality Assurance Final Audit - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Phase Boundary

Run an objective quality checklist against every page in the application. Fix all violations found. Ensure the 4 untouched CSS files (app.css, archives.css, report.css, users.css) are brought up to the same standard as the rest. The milestone is verifiably complete when every page passes all 5 QA criteria.

</domain>

<decisions>
## Implementation Decisions

### QA-01: 6 AI Anti-Pattern Checks (per page)
- **No uniform shadows:** Different components on the same page must use different shadow levels (cards=sm, modals=xl, etc.)
- **No uniform radii:** Button, card, badge, modal must each show a distinct border-radius on the same page
- **Spatial hierarchy present:** Sections have clear visual separation via spacing tokens (--space-section vs --space-card vs --space-field)
- **Color for signal:** Status badges, alerts, and states use semantic colors (success/warning/danger/info) — not decorative color
- **Weight contrast:** Page title (700) > section title (600) > body (400) — visible hierarchy
- **Hover has transform:** Interactive elements use shadow elevation or translateY, not just color change alone
- Audit ALL pages: dashboard, wizard, operator, hub, post-session, analytics, help, email-templates, meetings, audit, archives, members, users, settings, vote, login, landing, public, trust, validate, doc, report

### QA-02: Three-Depth Background Enforcement
- Every page must show exactly 3 tonal layers: body (--color-bg), surface (--color-surface), raised (--color-surface-raised)
- Catch the 4 untouched CSS files: app.css, archives.css, report.css, users.css — ensure they use the three-depth model
- Verify via grep: every page CSS uses at least 1 `var(--color-surface-raised)` reference for elevated elements

### QA-03: Fraunces Font Discipline
- Fraunces display font used ONLY on `<h1>` page titles — `font-family: var(--font-display)`
- NEVER on section headings (h2-h6), card titles, subheadings, or any other element
- Grep all CSS + HTML for `font-display` or `Fraunces` usage and verify each occurrence is on h1 only
- If violations found: replace with `var(--font-body)` (Bricolage Grotesque)

### QA-04: Dark Mode Visual Parity
- Toggle `data-theme="dark"` — every page must look intentionally designed
- No pure black backgrounds (#000) — use dark stone tones from --color-bg dark token
- No invisible borders (border-color same as background)
- No washed-out text (ensure sufficient contrast ratios)
- Check the 4 untouched files specifically — they may have hardcoded colors that don't respond to dark mode
- Dark mode is already auto-derived via Phase 30 tokens — this is a verification + fix pass

### QA-05: Performance & Accessibility Standards
- All CSS transitions ≤ 200ms — grep for `transition` and verify durations use `var(--duration-fast)` (150ms) or `var(--duration-normal)` (200ms)
- Focus rings ≥ 3:1 contrast — `var(--shadow-focus)` already meets this (verified in Phase 31)
- Zero `style=""` attributes in production HTML — grep all .htmx.html and .php template files
- Fix any inline styles found by moving them to appropriate CSS files

### Untouched Files Catch-Up
- **app.css**: Global shell styles — verify three-depth model on shared elements, check for hardcoded colors
- **archives.css**: Table page — verify `.table-page` wrapper applied (HTML was restructured in Phase 32), check for legacy styles
- **report.css**: Report/PV page — apply three-depth model, check dark mode, verify font usage
- **users.css**: Table page — same treatment as archives

### Claude's Discretion
- Order of page auditing (can batch by similarity)
- Whether to create a formal audit report or just fix-and-commit
- How to handle edge cases in print-only CSS (acceptable exceptions)
- Whether to add missing `var(--color-surface-raised)` to pages that currently only have 2 depth layers

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### All CSS files to audit
- `public/assets/css/design-system.css` — Token definitions, component specs (source of truth)
- `public/assets/css/app.css` — Global shell (UNTOUCHED — needs audit)
- `public/assets/css/archives.css` — Archives page (UNTOUCHED — needs audit)
- `public/assets/css/report.css` — Report/PV page (UNTOUCHED — needs audit)
- `public/assets/css/users.css` — Users page (UNTOUCHED — needs audit)
- All other page CSS files (already touched but need QA verification)

### HTML files to audit for inline styles
- `public/*.htmx.html` — All HTMX page files
- `app/Templates/*.php` — PHP template files

### Requirements
- `.planning/REQUIREMENTS.md` — QA-01 through QA-05 specifications

</canonical_refs>

<code_context>
## Existing Code Insights

### What's Already Done
- Phase 30: All tokens structured, dark mode auto-derives via semantic tokens
- Phase 31: All 8 component types have differentiated specs (distinct radii, shadows, heights)
- Phase 32-33: All 12 page layouts rebuilt with three-depth model, max-width constraints, responsive grids
- `var(--shadow-focus)` unified across all interactive elements
- `var(--duration-fast)` and `var(--duration-normal)` transition tokens defined

### Known Gaps to Check
- 4 untouched CSS files may have hardcoded colors, missing depth layers, or legacy styles
- Some pages may still use `Fraunces` on non-h1 elements (from v2.0 or v3.0 era)
- Inline `style=""` attributes may exist in older HTML files
- Dark mode may have invisible borders where border-color wasn't tokenized

### Audit Tools Available
- `grep` for pattern matching across all files
- `git log` to verify which files were/weren't modified
- No automated visual regression testing — rely on grep + manual visual checks

</code_context>

<specifics>
## Specific Ideas

- The audit should produce zero violations — this is the "shipping gate" for v4.1
- Priority: fix violations first, then verify fixes — not just report problems
- The 4 untouched files are the highest risk — audit them first

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 34-quality-assurance-final-audit*
*Context gathered: 2026-03-19*
