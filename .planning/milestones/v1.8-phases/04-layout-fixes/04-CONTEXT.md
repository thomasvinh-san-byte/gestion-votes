# Phase 4: Layout Fixes - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Three targeted layout fixes: (1) landing hero compact, (2) meeting type radio→select, (3) KPI dead code cleanup. No new features, no palette changes.

</domain>

<decisions>
## Implementation Decisions

### Landing Hero (UI-10)
- **D-01:** Change `.hero { min-height: 100vh }` to `min-height: auto` in landing.css
- **D-02:** Reduce hero padding so roles section is visible without scrolling on 1080p
- **D-03:** Keep the 2-panel layout (text left, login right) — just compact vertically

### Radio→Select (UI-11)
- **D-04:** Replace 5 meeting type radio buttons with `<select class="form-select">` on 3 pages:
  - `operator.htmx.html` (settings panel, lines ~430-443)
  - `meetings.htmx.html` (edit modal, lines ~208-224)
  - `wizard.htmx.html` (step 0, type selection)
- **D-05:** Options: AG ordinaire, AG extraordinaire, Conseil, Bureau, Autre
- **D-06:** Keep the same `name` attribute and value format for backend compatibility

### KPI Cleanup (UI-12)
- **D-07:** Delete the dead `.kpi-card` definition in design-system.css (lines ~2582-2610) — overridden by pages.css
- **D-08:** Delete the dead `.kpi-value` color modifiers (`.primary`, `.success`, etc.) — non-functional when pages.css parent is present
- **D-09:** Keep the pages.css `.kpi-card` definition as the single source of truth

### Claude's Discretion
- Exact hero padding/margin values for 1080p fit
- Whether to add a scroll indicator (chevron down) on hero
- Select default value handling (pre-selected option)

</decisions>

<canonical_refs>
## Canonical References

### Requirements
- `.planning/REQUIREMENTS.md` — UI-10, UI-11, UI-12

### Key Files
- `public/assets/css/landing.css` lines 59-70 — hero min-height and padding
- `public/operator.htmx.html` lines ~430-443 — radio buttons
- `public/meetings.htmx.html` lines ~208-224 — radio buttons
- `public/wizard.htmx.html` — meeting type radio section
- `public/assets/css/design-system.css` lines ~2582-2610 — dead KPI code
- `public/assets/css/pages.css` lines ~1035-1075 — active KPI code

</canonical_refs>

<code_context>
## Existing Code Insights

### Hero Current State
```css
.hero { min-height: 100vh; padding: calc(header-height + space-8) space-6 space-12; }
```
Content is ~300px tall but fills entire viewport.

### Radio Buttons Current State
5 radio inputs with labels, wrapped in a div. Values: ag_ordinaire, ag_extraordinaire, conseil, bureau, autre.

### KPI Definitions
- design-system.css: `.kpi-card { text-align: center; padding: space-5 }` — DEAD (overridden)
- pages.css: `.kpi-card { text-align: left; padding: space-6; }` with icon slot — ACTIVE

</code_context>

<specifics>
## Specific Ideas

No specific references — standard layout fixes.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 04-layout-fixes*
*Context gathered: 2026-04-20*
