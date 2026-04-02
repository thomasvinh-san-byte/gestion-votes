# Phase 40: Configuration Cluster - Context

**Gathered:** 2026-03-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Complete visual redesign of Settings/Admin, Email templates, and Help/FAQ pages. Configuration pages that must feel Notion-quality — clean section cards, clear explanations, no configuration anxiety. Every setting gets a tooltip explaining its impact.

</domain>

<decisions>
## Implementation Decisions

### Design Philosophy (carried from Phase 35-39)
- Notion/Clerk reference for settings pages — clean cards, generous whitespace, clear explanations
- ag-tooltip on every setting explaining what it does and its impact
- Dramatic visible improvement — not subtle refinements

### Settings/Admin Visual Redesign (CORE-06)
- **Sidenav:** 220px sticky sidebar with clean section items. Active item highlighted with primary bg-subtle + left border accent. Section icons next to labels
- **Section cards:** Each settings section as a raised card with title, description, and per-section save button in footer. Cards visually separated with --space-section (48px) gap
- **Form fields:** Labels above fields (14px semibold), helper text below in muted color. Every complex setting has ag-tooltip on an info icon explaining its impact
- **Toggle switches:** Clean toggle pattern for boolean settings, with label and description side by side
- **Admin KPI cards:** Dashboard-style KPI row at top of admin page — total users, active sessions, storage used, system status. JetBrains Mono numbers, colored icons
- **CNIL/security sections:** Clear visual distinction — info cards with blue/amber accents for compliance items
- **Save feedback:** Success toast after saving each section. Unsaved changes indicator if user navigates away

### Email Templates Visual Redesign (SEC-04)
- **Two-pane layout:** Editor (flex: 1) + preview panel (400px) — already set from Phase 33
- **Template list:** Left sidebar or top tabs showing available templates (convocation, rappel, résultats, etc.) with icons
- **Editor:** Clean textarea/WYSIWYG with proper field labels and variable insertion buttons ({{nom}}, {{date}}, etc.)
- **Preview panel:** Raised surface background showing rendered email preview. "Envoyer un test" button
- **Variable tooltips:** Each template variable has ag-tooltip explaining what it resolves to

### Help/FAQ Visual Redesign (SEC-03)
- **Centered layout:** 800px max-width (already from Phase 33)
- **Category headers:** Clear section headings with icons for each FAQ category
- **Accordion:** Styled details/summary with proper padding (16px 24px question, 0 24px 24px answer). Smooth expand animation (already from Phase 33)
- **Search:** If applicable, search input at top with instant filtering across all FAQs
- **Contact/support card:** At bottom — "Besoin d'aide ?" card with contact info or link

### Claude's Discretion
- Whether to use tabs or sidebar for email template selection
- Exact toggle switch implementation (CSS-only or component)
- Whether help page needs categories or flat list
- Admin KPI card count and metrics
- Save indicator implementation (toast vs inline)

</decisions>

<canonical_refs>
## Canonical References

### Page files
- `public/settings.htmx.html` — Settings page HTML
- `public/admin.htmx.html` — Admin page HTML
- `public/help.htmx.html` — Help/FAQ HTML
- `public/assets/css/settings.css` — Settings styles
- `public/assets/css/admin.css` — Admin styles
- `public/assets/css/help.css` — Help styles
- `public/assets/css/email-templates.css` — Email template styles
- `public/assets/js/pages/settings.js` — Settings JS
- `public/assets/js/pages/admin.js` — Admin JS
- `public/assets/js/pages/help.js` — Help JS

### Requirements
- `.planning/REQUIREMENTS.md` — CORE-06, SEC-04, SEC-03

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable from Phase 35-39
- KPI card pattern (dashboard Phase 35) for admin stats
- ag-tooltip wrapping for settings explanations
- Section card pattern with save button in footer
- Period filter pills for template selection
- Accordion styling from Phase 33 help page

### Current State
- Settings: 220px sticky sidenav + 720px content (Phase 32/33)
- Email templates: 1fr+400px editor grid (Phase 33)
- Help: 800px centered, accordion with tokenized padding (Phase 33)
- Admin: KPI cards at top, section cards below

</code_context>

<specifics>
## Specific Ideas

- Settings should feel like Notion's settings — clean, no anxiety about breaking things
- Every setting tooltip should explain "what happens if I change this"
- Help page should let a user find an answer without scrolling through unrelated content

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 40-configuration-cluster*
*Context gathered: 2026-03-20*
