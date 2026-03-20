# Phase 40: Configuration Cluster — Research

**Researched:** 2026-03-20
**Domain:** Settings page, Admin page, Help/FAQ page — visual redesign
**Confidence:** HIGH (all files read directly, no speculation)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Notion/Clerk reference for settings pages — clean cards, generous whitespace, clear explanations
- ag-tooltip on every setting explaining what it does and its impact
- Dramatic visible improvement — not subtle refinements

**Settings/Admin (CORE-06):**
- Sidenav: 220px sticky sidebar. Active item: primary bg-subtle + left border accent + section icons
- Section cards: raised card with title, description, per-section save button in footer. 48px (--space-section) gap
- Form fields: labels above fields (14px semibold), helper text below in muted color. ag-tooltip info icon on every complex setting
- Toggle switches: clean toggle pattern (label + description side by side)
- Admin KPI cards: dashboard-style KPI row at top — total users, active sessions, storage used, system status. JetBrains Mono numbers, colored icons
- CNIL/security sections: info cards with blue/amber accents for compliance items
- Save feedback: success toast per section. Unsaved changes indicator on navigate away

**Email Templates (SEC-04):**
- Two-pane layout: Editor (flex:1) + preview panel (400px) — already set from Phase 33
- Template list: left sidebar or top tabs showing available templates with icons
- Editor: clean textarea/WYSIWYG with proper field labels and variable insertion buttons
- Preview panel: raised surface showing rendered email preview. "Envoyer un test" button
- Variable tooltips: each template variable has ag-tooltip explaining what it resolves to

**Help/FAQ (SEC-03):**
- Centered layout: 800px max-width (already from Phase 33)
- Category headers: clear section headings with icons per category
- Accordion: styled details/summary, padding 16px 24px question / 0 24px 24px answer. Smooth expand animation (already from Phase 33)
- Search: if applicable, input at top with instant filtering
- Contact/support card: at bottom — "Besoin d'aide ?" card with contact info or link

### Claude's Discretion
- Whether to use tabs or sidebar for email template selection
- Exact toggle switch implementation (CSS-only or component)
- Whether help page needs categories or flat list
- Admin KPI card count and metrics
- Save indicator implementation (toast vs inline)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CORE-06 | Settings/Admin — redesign visuel (sidenav, formulaires, KPI admin, section cards, setting explanation tooltips) | Settings.css/HTML fully read; Admin.css/HTML fully read; KPI pattern from design-system.css confirmed |
| SEC-04 | Email templates — redesign visuel (editor + preview grid) | email-templates.css read; template list/editor in settings.htmx.html confirmed; two-pane pattern exists |
| SEC-03 | Help/FAQ — redesign visuel (accordion, categories, search) | help.css/HTML fully read; accordion JS confirmed; tour grid, tabs, search all present |
</phase_requirements>

---

## Summary

Phase 40 covers three configuration-cluster pages: Settings (settings.htmx.html), Admin (admin.htmx.html), and Help/FAQ (help.htmx.html). All source files have been read directly — there is no speculation in these findings.

**Settings page** already has a 220px sticky sidenav + 720px content grid from Phase 32/33. The sidenav items have basic text labels with no icons. Active state uses `background: var(--color-primary-subtle)` but is missing the left-border accent. Toggle rows use raw `<input type="checkbox">` inline with a label — no visual toggle switch component exists. Cards lack per-section save buttons and section-description lines are present but not visually prominent. No ag-tooltips on any setting field.

**Admin page** (admin.htmx.html) uses the standard `.app-main .container` layout with no page-level sidenav. It has a 4-KPI dash-kpi strip at top (AG à venir, En cours, Convocations, PV en attente), a dashboard-style overview (onboarding banner, upcoming sessions, shortcuts), then a flat tab list (Rôles de séance, Politiques, Permissions, Machine à états, Système). KPI strip uses `.dash-kpi` pattern from admin.css (already JetBrains Mono values, colored icon backgrounds). Section tabs use `.admin-tab` with no visible panel in this reading — the users list uses `.users-table` from Phase 39. No ag-tooltips exist on any admin section.

**Email templates** live inside settings.htmx.html under the Communication tab (not a separate page). The current state is a vertical `settings-template-list` (5 rows with icon + name + "Éditer" ghost button), and when "Éditer" is clicked, a `templateEditor` card appears below with subject, body textarea, variable code tags, and Save/Cancel. No live preview pane. The `email-templates.css` file defines a two-pane `template-editor-body` grid (1fr + 400px) but this is used in a modal, not in the page itself. The variables show as plain `<code>` tags — no tooltip explaining what each resolves to.

**Help/FAQ** has a solid base from Phase 33: help-header, 800px centered content, search input, role-filtered tabs, faq-section/faq-item accordion with JS toggle, tour-grid cards, exports reference table, doc-links grid, and a help-support card. Missing: section headings lack the rich icon treatment used in later phases; the tour-grid section heading (`Tour guidé`) is present but the accordion section titles use a 2px bottom border underline that is less premium than the card-style used in other pages; the tab active state is a solid primary background which contrasts strongly against the inactive tabs (no pill/subtle treatment); the search input lacks the refined search-input-wrap with icon pattern used in later pages (it uses `.search-input-wrap` and `.search-input-icon` which ARE present in the HTML).

**Primary recommendation:** Three targeted work packages: (1) settings.css — add left-border accent to sidenav active items, add section icons to nav items, upgrade toggle rows to visual toggle switches, add info icon + ag-tooltip on every complex setting, add per-section save button in card footer, add unsaved-changes dot indicator in sidenav; (2) admin.css/HTML — add ag-tooltips to KPI cards and tab section labels, upgrade admin KPI strip to match the design-system kpi-card pattern with colored icons; (3) help.css — upgrade tab active state to filter-tab pill pattern, upgrade section title style, add category icon treatment, polish support card elevation.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vanilla CSS custom properties | Design system v2.0 | All styling via design tokens | No build step; project constraint |
| ag-tooltip Web Component | Project-defined | Hover tooltips on any element | Established in Phase 35+; all phases use it |
| Bricolage Grotesque | Loaded via Google Fonts | UI labels, headings | Project font stack |
| JetBrains Mono | Loaded via Google Fonts | KPI numbers, mono values | Project font stack |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| design-system.css kpi-card | v2.0 | KPI display cards | Use for any numeric metric (Phase 39 removed local overrides to use DS directly) |
| design-system.css filter-tab pattern | v2.0 | Pill-style tab filter buttons | Users page (Phase 39) and archives both use this |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CSS-only toggle switches | `<input type="checkbox">` raw | CSS-only toggle is pure CSS, no JS, no new component — preferred |
| Per-section save buttons | Auto-save on change (current) | Current auto-save stays; section footer button adds explicit affordance for confidence |

**Installation:** No new dependencies. All changes are CSS + HTML + minimal JS additions.

---

## Architecture Patterns

### Recommended Project Structure
```
public/
├── settings.htmx.html   — Add icons to sidenav items; add info icon wrappers for ag-tooltip
├── admin.htmx.html      — Wrap KPI values in ag-tooltip; add KPI admin strip if missing
├── help.htmx.html       — Replace .help-tab active with filter-tab pattern; upgrade section titles
├── assets/css/
│   ├── settings.css     — Toggle switch CSS; sidenav left-border accent; unsaved dot; card footer save
│   ├── admin.css        — ag-tooltip-compatible KPI strip enhancements
│   └── help.css         — Tab pill styling; section heading upgrade; support card elevation
```

### Pattern 1: Sidenav Active Item with Left-Border Accent
**What:** Left 3px border on active sidenav item communicates selection without background noise
**When to use:** Settings sidenav — replaces or supplements current `color-primary-subtle` background
**Example:**
```css
/* Source: Clerk/Notion-pattern — projects like Phase 37 operator sidebar */
.settings-sidenav-item.active {
  background: var(--color-primary-subtle);
  color: var(--color-primary);
  font-weight: 700;
  border-left: 3px solid var(--color-primary);
  padding-left: 9px; /* 12px - 3px border = same visual offset */
}

.settings-sidenav-item {
  border-left: 3px solid transparent; /* reserve space — no layout shift */
}
```

### Pattern 2: CSS-Only Toggle Switch
**What:** Visually rich toggle replacing raw checkbox. CSS-only via `<label>` + `<input type="checkbox">` with `appearance: none` and a `::after` thumb.
**When to use:** Every boolean setting in Settings and Admin pages. No new JS required.
**Example:**
```css
/* Source: design pattern used in Phase 37 vote confirmation toggle */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 36px;
  height: 20px;
  flex-shrink: 0;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
  position: absolute;
}

.toggle-track {
  position: absolute;
  inset: 0;
  background: var(--color-border);
  border-radius: 10px;
  transition: background var(--duration-normal);
  cursor: pointer;
}

.toggle-track::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--color-text-inverse);
  box-shadow: var(--shadow-sm);
  transition: transform var(--duration-normal);
}

.toggle-switch input:checked + .toggle-track {
  background: var(--color-primary);
}

.toggle-switch input:checked + .toggle-track::after {
  transform: translateX(16px);
}
```

HTML pattern for toggle row:
```html
<div class="settings-toggle-row">
  <div class="settings-toggle-header">
    <label class="toggle-switch">
      <input type="checkbox" id="settDoubleAuth">
      <span class="toggle-track"></span>
    </label>
    <div class="settings-toggle-label-group">
      <span class="settings-toggle-label">Double authentification obligatoire</span>
      <ag-tooltip text="Force une vérification en deux étapes pour chaque vote. Renforce la sécurité mais ralentit le processus." position="top">
        <svg class="icon icon-info-xs" ...><!-- info circle --></svg>
      </ag-tooltip>
    </div>
  </div>
  <p class="settings-toggle-desc text-sm text-muted">Exige une vérification en deux étapes pour tous les votes.</p>
</div>
```

### Pattern 3: Section Card with Footer Save Button
**What:** Each settings section card gets a card-footer div with "Enregistrer cette section" button + unsaved-changes dot.
**When to use:** Settings page — every card that has form fields. Admin KPI sections do not need save buttons.
**Example:**
```html
<div class="card mb-4">
  <div class="card-header">
    <h3 class="card-title">Sécurité du vote</h3>
    <p class="card-description">Options de sécurité appliquées lors des votes</p>
  </div>
  <div class="card-body">
    <!-- fields -->
  </div>
  <div class="card-footer">
    <button class="btn btn-primary btn-sm btn-save-section" data-section="vote-security">
      Enregistrer
    </button>
    <span class="unsaved-dot" hidden aria-label="Modifications non enregistrées"></span>
  </div>
</div>
```

```css
.card-footer {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: var(--space-3) var(--space-card);
  border-top: 1px solid var(--color-border);
  background: var(--color-bg-subtle);
}

.unsaved-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-warning);
}
```

### Pattern 4: ag-tooltip on Settings Info Icon
**What:** Wrap a small info SVG icon in `<ag-tooltip>` next to each label. Already used extensively from Phase 35 onwards.
**When to use:** Every complex setting field that benefits from explanation of impact.
**Example:**
```html
<label class="form-label" for="settQuorumThreshold">
  Seuil (%)
  <ag-tooltip text="Pourcentage de membres présents requis pour que le vote soit valide. En-dessous de ce seuil, la séance ne peut pas ouvrir de vote." position="top">
    <svg class="icon icon-info-xs" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  </ag-tooltip>
</label>
```

### Pattern 5: Filter-Tab Pills (Help page tabs)
**What:** Replace solid-background `.help-tab.active` with the `filter-tab` pill pattern used in Phase 39 (Users page, Archives page). Clean pill with subtle background, not solid primary.
**When to use:** Help page category tabs. Settings sidenav does NOT use this — sidenav items are different.
**Example:**
```css
/* Align with filter-tab from design-system v4 layer */
.help-tab {
  padding: 0.375rem 0.875rem;
  border: 1px solid var(--color-border);
  background: none;
  border-radius: var(--radius-full);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background var(--duration-fast), color var(--duration-fast), border-color var(--duration-fast);
}

.help-tab:hover {
  background: var(--color-bg-subtle);
  color: var(--color-text);
}

.help-tab.active {
  background: var(--color-primary-subtle);
  color: var(--color-primary);
  border-color: var(--color-primary);
  font-weight: 600;
}
```

### Anti-Patterns to Avoid
- **Rewriting auto-save logic in settings.js:** The auto-save already works. Adding section-save buttons should use a separate handler, not replace the existing `initAutoSave()`. Use `data-section` attribute to scope.
- **Local kpi-grid/kpi-card overrides in admin.css or settings.css:** Phase 39 lesson — remove local overrides and use `design-system.css` definitions directly. The `.kpi-grid` / `.kpi-card` / `.kpi-value` / `.kpi-label` classes are fully defined in design-system.css.
- **Adding class names that conflict with admin.css duplicate definitions:** `admin.css` and `settings.css` both define `.settings-majority-grid`, `.settings-level-card`, `.settings-template-list` etc. Ensure edits go to `settings.css` only; `admin.css` copies may need to be removed or left as-is to avoid breaking admin.htmx.html.
- **Adding unsaved indicator with `display:none` then showing with JS `style.display`:** Use `hidden` attribute + CSS `[hidden] { display: none !important }` pattern consistent with the rest of the codebase. Or use a `.is-dirty` class on the card.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Tooltip on settings fields | Custom popover JS | `<ag-tooltip>` Web Component | Already in every page since Phase 35 |
| KPI display cards | Local .stat-card styles | `.kpi-grid` + `.kpi-card` from design-system.css | Phase 39 established this as canonical — removed local overrides |
| Tab filter pills | New .tab-pill component | `.filter-tab` / `.filter-tab.active` from design-system.css | Archives and Users both use this exact pattern |
| Toggle switch | JS-driven toggle component | CSS-only via `input[type=checkbox]` + `label` + `::after` | No JS needed, no new component |
| Section accordion | Custom expand/collapse | Existing `.faq-item.open` + chevron pattern | Already in help.css + help-faq.js |

---

## Common Pitfalls

### Pitfall 1: Duplicate CSS Definitions Between admin.css and settings.css
**What goes wrong:** Both files define `.settings-majority-grid`, `.settings-form-grid`, `.settings-level-card`, `.settings-template-list`, and related classes. Editing one without the other causes inconsistency.
**Why it happens:** Settings used to live inside admin.htmx.html; CSS was duplicated when settings.htmx.html was extracted.
**How to avoid:** Settings page uses `settings.css` only. Admin page uses `admin.css` only. Do not add new shared classes — each file is standalone for its page.
**Warning signs:** When you grep for a class and find it in both files.

### Pitfall 2: ag-tooltip Grid Wrapper Incompatibility
**What goes wrong:** Wrapping a `.kpi-card` or grid item in `<ag-tooltip>` breaks the grid layout because `<ag-tooltip>` is a custom element that defaults to `display:inline`.
**Why it happens:** Phase 35 lesson — ag-tooltip creates an inline wrapper.
**How to avoid:** Use `.kpi-card--N` positional modifier classes or wrap ag-tooltip inside the card (on the label) rather than around the card. Same for settings form grid items.
**Warning signs:** Grid items suddenly have unexpected widths or collapse.

### Pitfall 3: Toggle Switch Input ID Collision
**What goes wrong:** The new toggle switch HTML wraps `<input>` inside `<label>`. The existing auto-save in `settings.js` uses `document.getElementById(ctrl.id)` to track values. If the input loses its `id` attribute, auto-save stops working silently.
**Why it happens:** Refactoring the toggle HTML structure.
**How to avoid:** Keep all `id` attributes on the `<input>` element unchanged. Only add the wrapper `<label class="toggle-switch">` and `<span class="toggle-track">` siblings. Do not rename or remove existing `id` values.
**Warning signs:** Settings save silently stops working after toggle refactor.

### Pitfall 4: Card-Footer in admin.htmx.html
**What goes wrong:** admin.htmx.html does not include `settings.css` — it only includes `admin.css`. Any new `.card-footer` styles added to `settings.css` will not apply to admin page sections.
**Why it happens:** Each page loads its own CSS file.
**How to avoid:** Add `.card-footer` rule to both `settings.css` and `admin.css`, or (better) add it to `design-system.css` since it is a generic card component. Check if `design-system.css` already defines `.card-footer` before adding.
**Warning signs:** Card footer appears correctly on settings page but not on admin page.

### Pitfall 5: Help Page Tour Grid (Guided Tours are NOT deferred)
**What goes wrong:** The help page contains a "Tour guidé" section with 11 tour-card links. The user explicitly rejected guided tours in project decisions, but these links are already present in the HTML and launch `?tour=1` query params.
**Why it happens:** Tour cards were added before the user rejected guided tours globally.
**How to avoid:** Do NOT remove the tour section — the rejection was about not building NEW tours, not removing existing ones. Phase 40 scope is visual redesign only. Leave tour section in place, just improve the visual treatment.
**Warning signs:** Removing tour-grid or tour-card HTML would be a functionality change outside phase scope.

### Pitfall 6: Email Template Editor Location
**What goes wrong:** There is no separate email-templates page. The template list and editor both live inside `settings.htmx.html` under the Communication tab. The `email-templates.css` file defines a full two-pane modal editor — this is a separate modal-based editor, not inline in the settings page.
**Why it happens:** Historical design had a dedicated email-templates page; the current implementation consolidated into settings.
**How to avoid:** For Phase 40, upgrade the in-page editor experience inside the Communication tab. The `template-editor-body` two-pane layout (1fr + 400px) from `email-templates.css` should be adapted for the inline editor panel (not the modal). Decision for planner: either move editor into a proper two-pane inline panel, or keep it as-is and just upgrade the visual quality.
**Warning signs:** Assuming email-templates.htmx.html exists as a separate page — it does not.

---

## Code Examples

Verified patterns from the actual codebase:

### Sidenav Item with Section Icon (Current vs. Target)
Current HTML (no icon):
```html
<button class="settings-sidenav-item active" data-stab="regles" ...>Règles</button>
```

Target HTML (with icon + left-border accent via CSS):
```html
<button class="settings-sidenav-item active" data-stab="regles" ...>
  <svg class="icon" width="16" height="16" ...><!-- scales/balance icon --></svg>
  Règles
</button>
```

Target CSS addition:
```css
.settings-sidenav-item {
  border-left: 3px solid transparent; /* reserve space */
}
.settings-sidenav-item.active {
  background: var(--color-primary-subtle);
  color: var(--color-primary);
  font-weight: 700;
  border-left: 3px solid var(--color-primary);
  padding-left: 9px; /* 12 - 3 = 9 */
}
```

### KPI Strip for Admin Page (Existing .dash-kpi pattern)
The admin page already has a working `.dash-kpis` / `.dash-kpi` strip at lines 89-119 of admin.htmx.html. The four metrics are: AG à venir, En cours, Convocations, PV en attente. These use JetBrains Mono via `.dash-kpi-value` (font-family: var(--font-mono)) and colored icon backgrounds via `.kpi-primary/.kpi-danger/.kpi-warning/.kpi-success`. The `admin.css` already defines all of these.

The decision (CONTEXT.md) calls for a "KPI admin row" with total users, active sessions, storage used, system status. This is an ADDITIONAL KPI row, separate from the existing dashboard KPIs. It should go above or replace the current `adminUsersCount` compact card (line 200-209 of admin.htmx.html).

### Variable Tag with ag-tooltip in Template Editor
Current (plain `<code>` tags):
```html
<code class="text-xs">&#123;&#123;nom&#125;&#125;</code>
```

Target (variable as clickable badge with tooltip):
```html
<ag-tooltip text="Nom complet du destinataire, tel que saisi dans la fiche membre" position="top">
  <button class="variable-tag" type="button" data-var="{{nom}}">{{nom}}</button>
</ag-tooltip>
```

The `.variable-tag` class already exists in `email-templates.css` with hover → primary color styling. JS needs a click handler that inserts the variable at textarea cursor position.

### CNIL Level Card with Info Accent (Current vs. Target)
Current: basic border + center-aligned text stack. No visual distinction from other setting cards.

Target: Add a left-border accent and icon treatment to communicate compliance severity:
```css
.settings-security-card {
  border-left: 4px solid var(--color-info);
  background: var(--color-info-subtle);
}

.settings-cnil-card {
  border-left: 4px solid var(--color-warning);
  background: var(--color-warning-subtle);
}
```

### FAQ Section Title Upgrade
Current:
```css
.faq-section-title {
  border-bottom: 2px solid var(--color-primary);
  font-size: 1.1rem;
  font-weight: 600;
}
```

Target: Remove underline, use left-border accent (consistent with settings sidenav pattern):
```css
.faq-section-title {
  /* remove border-bottom */
  border-left: 4px solid var(--color-primary);
  padding-left: 0.75rem;
  margin-left: -0.75rem; /* optical alignment */
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-text);
}
```

---

## Before → After Specification Per Element

### Settings Page (settings.htmx.html + settings.css)

| Element | Before | After |
|---------|--------|-------|
| Sidenav active item | `background: primary-subtle`, no border, no icon | Left 3px primary border + primary-subtle bg + section icon SVG (16px) |
| Sidenav inactive item | Plain text, no border | `border-left: 3px solid transparent` (reserved space, no shift) |
| Toggle rows (`settings-toggle-row`) | Raw `<input type="checkbox">` inline with label text | Visual toggle switch (36×20px pill) + label + description on same row |
| Complex settings labels | Plain `<label class="form-label">` | Label + 14px info icon wrapped in `<ag-tooltip text="[impact description]">` |
| Section cards | No footer save button | `card-footer` with "Enregistrer" btn-primary btn-sm + unsaved-dot indicator |
| Majority type cards | Static info cards, no ag-tooltip | Each card gets ag-tooltip on title explaining the article legal reference |
| CNIL level selector | Plain border + circle number | Left 4px border accent (info/warning/danger per level), description tooltip |
| Template variable tags | `<code class="text-xs">` plain | `.variable-tag` button (clickable insert) + ag-tooltip per variable |
| Template editor | Full-width textarea below list | Keep inline, but add `.template-editor-preview` pane at 400px right (two-pane split within the card body) |

### Admin Page (admin.htmx.html + admin.css)

| Element | Before | After |
|---------|--------|-------|
| Admin KPI row at top | 4 KPIs: AG à venir, En cours, Convocations, PV en attente — no ag-tooltip | Wrap each KPI value label in `<ag-tooltip>` explaining the metric |
| Compact users card (line 200) | Plain text count + "Gérer les utilisateurs" link | Upgrade to KPI row: Total utilisateurs, Admins, Opérateurs, Actifs today — JetBrains Mono numbers, colored icons |
| Admin tab labels | Plain text labels (Rôles de séance, Politiques, Permissions, etc.) | Each tab gets a small section icon (16px) + ag-tooltip on hover explaining what the tab manages |
| Header | Simple h1 + h2, Actualiser + ? buttons | Add page-title with `.bar` accent (consistent with settings.htmx.html header pattern), add breadcrumb |
| State machine visualization | `.state-flow-visual` with colored node pills | Nodes already have color coding — add ag-tooltip on each state node explaining what actions are possible |

### Help/FAQ Page (help.htmx.html + help.css)

| Element | Before | After |
|---------|--------|-------|
| Help header | `.help-header` with bg-surface + border-bottom + plain h1 | Upgrade to full-width hero zone: larger h1 (Fraunces or Bricolage Grotesque 700), subtitle, maybe a search-prominently-centered layout |
| Search input | `.search-input-wrap` already with icon — already reasonably styled | Increase visual weight: larger input (48px height), full-width within content area, prominent shadow on focus |
| Category tabs | Solid primary background on active tab | Filter-tab pill pattern: `border-radius: full`, `border: 1px solid`, active = `primary-subtle + primary border` |
| Tab border-bottom | `border-bottom: 1px solid var(--color-border)` on `.help-tabs` | Remove hard border, let filter-tabs float with gap (consistent with Phase 39) |
| FAQ section title | `border-bottom: 2px solid var(--color-primary)` underline | Left 4px border accent, remove underline, 700 weight heading |
| FAQ question padding | `padding: var(--space-4) var(--space-card)` — using space tokens | Keep padding, add smooth chevron rotation (already present), ensure 16px/24px matches spec |
| FAQ answer background | `background: var(--color-surface-raised)` | Keep raised surface, add left-border accent in `var(--color-primary)` at 2px for visual continuity |
| Tour cards | `.tour-card` with plain border | Add `box-shadow: var(--shadow-sm)` on hover (already exists), but increase `.tour-icon` prominence: 44px instead of 40px, stronger primary color |
| Doc links section | `.doc-links` with `background: var(--color-bg-subtle)` | Upgrade to a proper `.card` with `card-header` ("Documentation") and `card-body` doc-links-grid |
| Support card | `background: var(--color-primary-subtle)` + `border: 1px solid var(--color-primary)` | Upgrade: stronger visual, add icon, ensure it feels like a CTA card (consistent with Phase 35 patterns) |

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| admin.css duplicate of settings styles | settings.css extracted, settings.htmx.html uses settings.css only | Phase 32/33 | Don't add new shared code to admin.css |
| Inline admin.css kpi-card overrides | design-system.css `.kpi-grid` / `.kpi-card` used directly | Phase 39 | Use DS classes, not local stat-card |
| Flat settings tabs (top horizontal strip) | 220px sticky sidenav | Phase 32/33 | Sidenav pattern locked |
| Plain `<code>` variable tags in template editor | variable-tag clickable button pattern (in email-templates.css) | Phase 33 | Variable tags should be clickable inserts |
| Help tour cards without role filtering | `data-required-role` attribute + JS applyRoleVisibility | Phase 33 | Role visibility already works — preserve |

**Deprecated/outdated:**
- `.stat-card` / `.stat-card-value` / `.stat-card-label` in admin.css: the `stats-grid` + `stat-card` pattern is still present in admin.css but has been superseded by the design-system `.kpi-grid` pattern. For admin page KPI additions, use `.kpi-grid` / `.kpi-card`.
- `.settings-tabs` / `.settings-tab` classes in admin.css: These are copies of the settings page tabs that existed before settings was extracted to its own page. They are still referenced in admin.css but settings.css now has its own version. The admin page no longer uses `.settings-tabs` directly (settings tab switching is in settings.htmx.html).

---

## Open Questions

1. **Email template editor: inline two-pane vs modal two-pane**
   - What we know: `email-templates.css` defines a `.template-editor-body` (1fr + 400px) for a full-screen modal. The settings page uses an inline `#templateEditor` card that appears below the list.
   - What's unclear: Should the Phase 40 redesign bring the full two-pane modal to the settings Communication tab, or keep the inline approach but add a 400px preview pane within the inline card?
   - Recommendation: Inline two-pane within the card body is cleaner and avoids modal complexity. Add `grid-template-columns: 1fr 400px` to `#templateEditor .card-body` and add the preview div alongside the form. Reuse `.template-editor-preview` CSS from email-templates.css.

2. **Admin page — where exactly does the new admin KPI row go?**
   - What we know: Line 200-209 of admin.htmx.html has a compact users card showing `-- utilisateurs` and a link to users.htmx.html.
   - What's unclear: CONTEXT.md says "total users, active sessions, storage used, system status" but the System tab (admin tab 5) already has a `.stats-grid` with some of these.
   - Recommendation: Replace the compact users card (lines 199-209) with a 4-KPI strip using `.kpi-grid` + `.kpi-card`. Values: Total utilisateurs (link to users page), Sessions actives, Politiques de quorum count, Dernière connexion admin. This adds value without duplicating the system tab's detailed stats.

3. **Settings page card-footer — does auto-save conflict with explicit save?**
   - What we know: `settings.js` auto-saves on every field change with debounce. Adding a per-section save button would create two save paths.
   - What's unclear: Do we disable auto-save when section-save button exists, or keep both?
   - Recommendation: Keep auto-save running silently. The section-save button is a confidence affordance — it explicitly saves and shows a success toast. Auto-save handles the case where user navigates away. The unsaved-dot shows when any field in the section has changed (JS watches `input`/`change` events on children). No conflict since both call the same API endpoint.

---

## Validation Architecture

> `workflow.nyquist_validation` key is absent from `.planning/config.json` — treated as enabled.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Manual browser validation (no automated test framework configured) |
| Config file | None |
| Quick run command | Open settings.htmx.html, admin.htmx.html, help.htmx.html in browser |
| Full suite command | Visual inspection of all three pages in light + dark mode |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CORE-06 | Settings sidenav has icons + left-border active accent | visual | Manual: settings.htmx.html | ❌ Wave 0 |
| CORE-06 | Toggle switches render as visual pills (not raw checkboxes) | visual | Manual: settings.htmx.html | ❌ Wave 0 |
| CORE-06 | ag-tooltip appears on hover over info icons in settings | visual | Manual: hover all info icons | ❌ Wave 0 |
| CORE-06 | Admin KPI row shows 4 metrics with JetBrains Mono numbers | visual | Manual: admin.htmx.html | ❌ Wave 0 |
| CORE-06 | Auto-save still functions after toggle HTML refactor | functional | Manual: change toggle, check toast | ❌ Wave 0 |
| SEC-04 | Template editor shows two-pane (editor + preview) | visual | Manual: Communication tab → Éditer | ❌ Wave 0 |
| SEC-04 | Variable tags are clickable and insert at cursor | functional | Manual: click variable tag | ❌ Wave 0 |
| SEC-03 | Help tabs use filter-tab pill style | visual | Manual: help.htmx.html | ❌ Wave 0 |
| SEC-03 | FAQ section titles have left-border accent, no underline | visual | Manual: help.htmx.html | ❌ Wave 0 |
| SEC-03 | Support card is visually prominent at bottom | visual | Manual: scroll to bottom | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** Open affected page in browser, verify specific element
- **Per wave merge:** Full visual pass of all three pages in light + dark mode
- **Phase gate:** All three pages pass visual inspection before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] No automated test infrastructure — all validation is manual browser inspection
- [ ] Dark mode must be tested for: toggle switch thumb visibility, left-border accent contrast, ag-tooltip background

*(No framework install needed — visual validation only)*

---

## Sources

### Primary (HIGH confidence)
- Direct file read: `/public/settings.htmx.html` — full HTML structure, all 4 tabs
- Direct file read: `/public/admin.htmx.html` — full HTML structure, dashboard + tabs
- Direct file read: `/public/help.htmx.html` — full HTML structure, all sections
- Direct file read: `/public/assets/css/settings.css` — all 408 lines
- Direct file read: `/public/assets/css/admin.css` — all 1026 lines
- Direct file read: `/public/assets/css/help.css` — all 432 lines
- Direct file read: `/public/assets/css/email-templates.css` — all 251 lines
- Direct file read: `/public/assets/js/pages/settings.js` — auto-save, tabs, CRUD logic
- Direct file read: `/public/assets/js/pages/admin.js` — dashboard, tabs, KPI loading
- Direct file read: `/public/assets/js/pages/help-faq.js` — tab switching, accordion, search
- Direct file read: `/public/assets/css/design-system.css` — kpi-card pattern (lines 2438-2474)
- Direct grep: ag-tooltip usage patterns across all HTML pages
- Direct read: `.planning/phases/40-configuration-cluster/40-CONTEXT.md` — locked decisions

### Secondary (MEDIUM confidence)
- STATE.md accumulated context: Phase 39 lesson about kpi-grid/kpi-card overrides (confirmed by direct read of design-system.css)
- STATE.md: ag-tooltip wrapping incompatibility with grid items (confirmed by Phase 35 lesson note)

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries confirmed by direct file reads
- Architecture: HIGH — before/after specs derived from actual HTML/CSS, no speculation
- Pitfalls: HIGH — 4 of 6 pitfalls are exact PROJECT lessons from STATE.md accumulated context; 2 are derived from code structure observation
- Before/After specs: HIGH — every "before" state was read directly from source files

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable vanilla CSS stack; design system tokens don't change between phases)
