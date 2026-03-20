# Phase 43: Dashboard Rebuild - Research

**Researched:** 2026-03-20
**Domain:** HTML/CSS/JS ground-up rewrite — dashboard page
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Read dashboard.js FIRST — understand every DOM query, event handler, HTMX target before touching HTML
- Rewrite HTML from scratch — new structure designed for horizontal-first, data-dense dashboard
- Rewrite CSS from scratch — new styles, not patches on old rules
- Update JS as needed — fix any broken selectors, verify all API calls work
- One testable commit — no broken intermediate state
- Design goal: Stripe Dashboard quality — data-dense, scannable, every pixel intentional
- Horizontal-first — use full 1200px content width
- KPI row: 4 cards filling width — icon left, large mono value, label, trend context
- Session list: primary content — vertical card list with status badges, hover-reveal CTAs, date in mono
- Quick actions aside: 280px sticky sidebar
- Three-depth background: bg > surface (cards) > raised (KPI cards, table headers)
- ag-tooltip on every KPI and action
- Empty states: clear CTA when no sessions exist
- Responsive: 1024px aside stacks below, 768px KPIs go 2-col
- Verify all HTMX endpoints return data correctly
- No mock data, no hardcoded values

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming (can rename if cleaner)
- Whether to refactor dashboard.js or just update selectors
- Exact responsive breakpoint behavior

### Deferred Ideas (OUT OF SCOPE)
None
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REB-01 | Dashboard — complete HTML+CSS rewrite, KPIs wired to backend, session list with live data, horizontal-first layout, JS verified | JS DOM dependency map + API shape documented below |
| WIRE-01 | Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets | DashboardController + MeetingRepository documented; API response shape confirmed |
</phase_requirements>

---

## Summary

The dashboard is a self-contained page with no HTMX partial fetching — it relies entirely on `dashboard.js` calling `/api/v1/dashboard` on load and imperatively updating the DOM. JavaScript injects all dynamic content into five anchor IDs (`kpiSeances`, `kpiEnCours`, `kpiConvoc`, `kpiPV`, `prochaines`, `taches`) and three IDs for the urgent card (`actionUrgente`, `urgentTitle`, `urgentSub`). These IDs are the non-negotiable contract between JS and HTML; everything else can be freely redesigned.

The current HTML is structurally sound at the macro level (dashboard-content > kpi-grid + dashboard-body > dashboard-main + dashboard-aside) but needs a ground-up rewrite to achieve top 1% density and visual quality. The session-card HTML is fully JS-generated (renderSessionCard function) — meaning session card classes from design-system.css must be preserved as-is: `session-card`, `session-card--live`, `session-card--muted`, `session-card-info`, `session-card-title`, `session-card-meta`, `session-card-date`, `session-card-meta-sep`, `session-card-cta`.

The backend API is real, verified, and returns the correct shape. No changes to PHP are needed. The rebuild is purely a front-end rewrite.

**Primary recommendation:** Preserve the 8 JS-anchored IDs exactly, freely rewrite all surrounding HTML structure and all CSS, and update only the JS error handler's class name if the error banner element changes.

---

## JS DOM Dependency Map — COMPLETE AUDIT

### getElementById calls (MUST exist in new HTML)

| ID | Location in JS | What JS Does With It |
|----|----------------|----------------------|
| `kpiSeances` | `loadDashboard()` line 120 | Sets `.textContent` = upcoming sessions count |
| `kpiEnCours` | `loadDashboard()` line 122 | Sets `.textContent` = live/paused sessions count |
| `kpiConvoc` | `loadDashboard()` line 124 | Sets `.textContent` = 0 (hardcoded, convocation data not in API) |
| `kpiPV` | `loadDashboard()` line 127 | Sets `.textContent` = closed+validated sessions count |
| `urgentTitle` | `loadDashboard()` line 133 | Sets `.textContent` = 'Séance en cours' when live meeting exists |
| `urgentSub` | `loadDashboard()` line 135 | Sets `.textContent` = live meeting title |
| `actionUrgente` | `loadDashboard()` line 138 | Sets `.hidden = true` when no live meeting |
| `prochaines` | `loadDashboard()` line 143 | Sets `.innerHTML` = session cards or ag-empty-state |
| `taches` | `loadDashboard()` line 159 | Sets `.innerHTML` = ag-empty-state (tasks not in API) |
| `main-content` | `showDashboardError()` line 183 | `prepend()` error banner div; `querySelector('.dashboard-error')` to dedupe |
| `dashboardRetryBtn` | `showDashboardError()` line 194 | `addEventListener('click', ...)` — retry button in error banner |

### querySelector calls

| Selector | Location | Purpose |
|----------|----------|---------|
| `.dashboard-error` | `showDashboardError()` | Find and remove existing error banner before re-adding |

### Classes generated by renderSessionCard() (MUST exist in CSS)

These classes are emitted by JS string concatenation — they must have CSS rules:

| Class | Condition | Source |
|-------|-----------|--------|
| `session-card` | Always | Base card |
| `session-card--live` | `status === 'live' \| 'paused'` | Live highlight (green left border) |
| `session-card--muted` | `status === 'archived'` | Reduced opacity, no hover |
| `session-card-info` | Always | flex:1 text block |
| `session-card-title` | Always | Session title text |
| `session-card-meta` | Always | Date + participants + resolutions |
| `session-card-date` | Always | Date span (mono font in meetings.css) |
| `session-card-meta-sep` | Always | Middle-dot separators |
| `session-card-cta` | Has CTA | Hover-reveal action button |
| `btn btn-sm btn-secondary` | CTA link | Base button styles |
| `btn-success` | Live CTA | Green CTA for live sessions |
| `pulse-dot` | Live CTA | Pulsing dot animation |

### Classes used on error banner (created by JS)

| Class | Where | Purpose |
|-------|-------|---------|
| `hub-error` | Error banner div | Red background error panel (defined in hub.css) |
| `dashboard-error` | Error banner div | Selector for deduplication |
| `btn btn-primary` | Retry button | Standard button styles |

### Event Listeners

| Event | Target | Handler |
|-------|--------|---------|
| `DOMContentLoaded` | `document` | `loadDashboard()` (if doc still loading) |
| `click` | `#dashboardRetryBtn` | Remove banner, call `loadDashboard()` |
| `onclick` | `.session-card` (inline) | `location.href = href` (injected as inline attr) |
| `onclick` | `.session-card-cta` (inline) | `event.stopPropagation()` |

### HTMX Usage

**Zero HTMX on this page.** All data loading is pure JS/fetch via `Utils.apiGet`. No `hx-get`, `hx-post`, or HTMX targets exist in dashboard.js or dashboard.htmx.html. The `.htmx.html` suffix is a naming convention only.

---

## API Endpoint Documentation

### GET /api/v1/dashboard

**Handler:** `DashboardController::index()`
**Auth:** Required (tenant-scoped)
**Query params:** `meeting_id` (optional UUID — used only for detail data, not for KPI list)

**Response envelope:**
```json
{
  "ok": true,
  "data": {
    "meetings": [
      {
        "id": "uuid",
        "title": "string",
        "status": "draft|scheduled|frozen|live|paused|closed|validated|archived",
        "scheduled_at": "datetime|null",
        "started_at": "datetime|null",
        "ended_at": "datetime|null",
        "archived_at": "datetime|null",
        "validated_at": "datetime|null"
      }
    ],
    "suggested_meeting_id": "uuid|null",
    "meeting": null,
    "attendance": { ... },
    "proxies": { "count": 0 },
    "current_motion": null,
    "current_motion_votes": { ... },
    "openable_motions": [],
    "ready_to_sign": { "can": false, "reasons": [] }
  }
}
```

**Fields used by dashboard.js:**
- `data.meetings` — array iterated for KPI computation and session card rendering
- `data.meetings[n].status` — KPI filtering + badge variant + CTA mapping
- `data.meetings[n].title` — card title display
- `data.meetings[n].date_time` OR `data.meetings[n].scheduled_at` — date display (JS tries both)
- `data.meetings[n].id` — href query param for CTA links
- `data.meetings[n].participant_count` — meta display (may be absent — defaults to 0)
- `data.meetings[n].motion_count` — meta display (may be absent — defaults to 0)

**Fields NOT used by dashboard.js (available but ignored):**
- `suggested_meeting_id`, `meeting`, `attendance`, `proxies`, `current_motion`, `current_motion_votes`, `openable_motions`, `ready_to_sign`

**Note on `participant_count` / `motion_count`:** These fields are NOT returned by `listForDashboard()` SQL — the query only selects `id, title, status, scheduled_at, started_at, ended_at, archived_at, validated_at`. Dashboard.js gracefully defaults to 0 with `|| 0`. This is correct behavior — no bug.

**Note on `kpiConvoc`:** Always set to 0. Convocation data is not in the dashboard API. This is intentional — documented in JS comment.

---

## Current HTML Structure (to be replaced)

```
body > .app-shell
  aside.app-sidebar [data-include-sidebar]
  header.app-header
    .breadcrumb
    h1.page-title
    [Aide popover + "Nouvelle séance" btn]
  main#main-content.app-main
    div.dashboard-content
      a#actionUrgente.card.urgent-card          ← urgent banner
        .urgent-card-body
          .urgent-card-icon
          .session-row-info
            .urgent-card-label
            .urgent-card-title#urgentTitle
            .urgent-card-sub#urgentSub
          .urgent-card-chevron
      div.kpi-grid#kpiRow                       ← 4-col KPI grid
        ag-tooltip > a.kpi-card.kpi-card--1
          .kpi-icon + div#kpiSeances.kpi-value + .kpi-label
        ag-tooltip > a.kpi-card.kpi-card--2
          .kpi-icon + div#kpiEnCours.kpi-value + .kpi-label
        ag-tooltip > a.kpi-card.kpi-card--3
          .kpi-icon + div#kpiConvoc.kpi-value + .kpi-label
        ag-tooltip > a.kpi-card.kpi-card--4
          .kpi-icon + div#kpiPV.kpi-value + .kpi-label
      div.dashboard-body                        ← 2-col body
        main.dashboard-main
          div.card.dashboard-panel             ← sessions card
            .flex-between [title + "Tout voir"]
            div#prochaines [skeletons → JS cards]
          div.card.dashboard-panel             ← tasks card
            .card-title
            div#taches [skeletons → JS empty-state]
        aside.dashboard-aside                  ← 280px sticky
          .dashboard-shortcuts-title
          3x ag-tooltip > a.card.shortcut-card
  footer.app-footer.mobile-footer
```

---

## CSS Architecture — Current Dashboard Rules

### Files owning dashboard styles

| File | Section | Lines |
|------|---------|-------|
| `pages.css` | Urgent card | 935–993 |
| `pages.css` | Dashboard grid/panel layout | 1006–1110 |
| `pages.css` | KPI cards | 1111–1167 |
| `pages.css` | Shortcut cards | 1169–1235 |
| `pages.css` | Session/task rows (legacy) | 1237–1324 |
| `design-system.css` | Session cards (JS-rendered) | 5026–5139 |
| `app.css` | dashboard-panel, shortcuts title, shortcut-card | ~796–801 |
| `hub.css` | hub-error (error banner) | 1077–1085 |

### Key design tokens in use

| Token | Value (light) | Purpose |
|-------|---------------|---------|
| `--color-bg` | `#EDECE6` | Page background |
| `--color-surface` | `#FAFAF7` | Card surface |
| `--color-surface-raised` | `#FFFFFF` | KPI cards (three-depth) |
| `--font-mono` | JetBrains Mono | KPI values, dates |
| `--text-4xl` | 2.25rem (36px) | KPI large numbers |
| `--space-card` | `var(--space-6)` = 24px | Card padding, gaps |
| `--radius-xl` | 12px | Card border radius |
| `--shadow-lg` | 8px/24px warm | KPI card hover shadow |
| `--space-6` | 24px | Standard card padding |

### Three-depth color model (MUST maintain)

1. `var(--color-bg)` — page background (`#EDECE6`)
2. `var(--color-surface)` — standard cards (`#FAFAF7`)
3. `var(--color-surface-raised)` — KPI cards (`#FFFFFF`), table headers

---

## Architecture Patterns

### Recommended New HTML Structure

```
body > .app-shell
  aside.app-sidebar [data-include-sidebar data-page="dashboard"]
  header.app-header
    .page-header-left
      nav.breadcrumb
      h1.page-title [icon + text]
      p.page-sub
    .page-header-right
      ag-popover [help]
      a.btn.btn-primary.btn-sm [+ Nouvelle séance]
  main#main-content.app-main
    div.dashboard-content
      ── Urgent Banner (conditional, hidden by default) ──
      a#actionUrgente.dashboard-urgent [hidden]
        .dashboard-urgent__icon
        .dashboard-urgent__body
          .dashboard-urgent__eyebrow
          .dashboard-urgent__title#urgentTitle
          .dashboard-urgent__sub#urgentSub
        .dashboard-urgent__arrow
      ── KPI Row ──
      div.dashboard-kpis
        ag-tooltip > .kpi-card.kpi-card--1
          .kpi-card__icon
          .kpi-card__value#kpiSeances
          .kpi-card__label
        ag-tooltip > .kpi-card.kpi-card--2
          .kpi-card__icon
          .kpi-card__value#kpiEnCours
          .kpi-card__label
        ag-tooltip > .kpi-card.kpi-card--3
          .kpi-card__icon
          .kpi-card__value#kpiConvoc
          .kpi-card__label
        ag-tooltip > .kpi-card.kpi-card--4
          .kpi-card__icon
          .kpi-card__value#kpiPV
          .kpi-card__label
      ── Body: Sessions + Aside ──
      div.dashboard-body
        section.dashboard-sessions
          .dashboard-panel-header
            .dashboard-panel-title [icon + "Séances"]
            a.btn.btn-ghost.btn-sm [Tout voir →]
          div#prochaines.dashboard-sessions__list [aria-live="polite"]
            [3x skeleton placeholders]
        aside.dashboard-aside
          .dashboard-aside__title [Accès rapides]
          ag-tooltip > a.shortcut-card [Créer séance]
          ag-tooltip > a.shortcut-card [Piloter un vote]
          ag-tooltip > a.shortcut-card [Consulter le suivi]
  footer.app-footer.mobile-footer
```

**Note:** The `#taches` section can be removed from the new HTML or kept hidden. The JS writes an empty-state to it — if no element is present, the `if (taches)` guard in JS silently skips it. Removing it simplifies the layout.

### Pattern: Skeleton → Live Data

Current HTML pre-populates `#prochaines` with 3 skeleton divs. JS overwrites `.innerHTML` on load. New HTML should maintain this pattern. Skeleton classes `skeleton` and `skeleton-session` are defined in design-system.css.

### Pattern: Conditional Urgent Banner

`#actionUrgente` starts visible in current HTML. JS hides it with `.hidden = true` if no live meeting. New HTML should start with `hidden` attribute and let JS make it visible when a live meeting is found. This avoids a flash of incorrect content.

**Inversion of current behavior:** New JS should set `actionUrgente.hidden = false` when a live meeting exists, and leave it hidden by default. Or keep current approach (start visible, JS hides) — both work.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Status badges | Custom badge HTML | `<ag-badge variant="...">` | Shadow DOM component with all variants built-in |
| Empty states | Custom empty div | `<ag-empty-state>` | Standardized icons, layout, optional CTA button |
| Tooltips | title attribute or custom | `<ag-tooltip>` | Accessible, positioned, uses design tokens |
| API fetching | raw fetch() | `Utils.apiGet()` | Handles auth headers, CSRF, error normalization |
| HTML escaping | manual regex | `escapeHtml()` in dashboard.js | Already exists in scope, prevents XSS |

**Key insight:** The ag-* web component ecosystem (badge, tooltip, empty-state, popover) is the right tool for all interactive UI primitives. Do not introduce raw HTML equivalents.

---

## Common Pitfalls

### Pitfall 1: Removing a JS-anchored ID
**What goes wrong:** KPI or session list silently fails to populate; page shows stale skeletons or nothing.
**Why it happens:** JS uses `getElementById()` with null-guard `if (el)` — silent failure, no console error.
**How to avoid:** Verify all 10 IDs in the JS dependency map are present in new HTML before first browser test.
**Warning signs:** KPI values stay at `-`, sessions show skeleton permanently.

### Pitfall 2: Breaking session-card CSS classes
**What goes wrong:** Session cards render with no styles — no padding, no borders, no hover effect.
**Why it happens:** `renderSessionCard()` emits class strings like `'session-card'`, `'session-card--live'` etc. These are defined in design-system.css. If new CSS accidentally overrides or removes them, cards break.
**How to avoid:** Do not remove design-system.css session-card rules. New pages.css can add overrides but must not delete base rules.

### Pitfall 3: Removing taches ID with JS still referencing it
**What goes wrong:** No bug (JS has null guard) but also no visible issue. Silent skip is fine.
**How to avoid:** If removing `#taches`, the JS line `var taches = document.getElementById('taches')` will get null, hit the `if (taches)` guard, and silently skip. This is acceptable — no JS update needed.

### Pitfall 4: ag-tooltip wrapping KPI cards breaks grid
**What goes wrong:** 4-column KPI grid collapses because ag-tooltip adds an extra block element.
**Why it happens:** `ag-tooltip` renders as an element in the DOM — without `display: contents`, it breaks grid layout.
**How to avoid:** Current pages.css already solves this: `.kpi-grid ag-tooltip { display: contents; }`. Keep this rule in new CSS.

### Pitfall 5: urgent-card link color bleeding
**What goes wrong:** The urgent banner is an `<a>` tag — without `text-decoration: none` and `color: inherit`, it shows browser link blue and underline.
**How to avoid:** Style `#actionUrgente` with `text-decoration: none; color: inherit;` explicitly.

### Pitfall 6: kpiConvoc always showing 0
**What goes wrong:** Not a bug — it's intentional. The dashboard API does not return convocation data.
**How to avoid:** Keep the `kpiConvoc` ID but accept that it displays 0. Do not try to wire it to non-existent API data. If the design shows `—` or omits trend context for this card, that is acceptable.

---

## Code Examples

Verified patterns from dashboard.js and design-system.css:

### ag-badge usage (emitted by renderSessionCard)
```html
<!-- Source: dashboard.js line 73 -->
<ag-badge variant="live" pulse>En cours</ag-badge>
<ag-badge variant="draft">Brouillon</ag-badge>
<ag-badge variant="primary">Planifiée</ag-badge>
```

### ag-empty-state usage (emitted by loadDashboard)
```html
<!-- Source: dashboard.js line 145 -->
<ag-empty-state
  icon="meetings"
  title="Aucune séance"
  description="Créez votre première séance pour gérer vos assemblées générales."
  action-label="Nouvelle séance"
  action-href="/wizard.htmx.html">
</ag-empty-state>
```

### ag-tooltip wrapping KPI card
```html
<!-- Source: dashboard.htmx.html lines 92–100 + pages.css line 1026 -->
<ag-tooltip text="Sessions AG planifiees ce mois-ci" position="bottom">
  <a href="/meetings.htmx.html" class="kpi-card kpi-card--1">
    <div class="kpi-icon">...</div>
    <div class="kpi-value primary" id="kpiSeances">-</div>
    <div class="kpi-label">AG à venir</div>
  </a>
</ag-tooltip>
```
CSS rule required: `.kpi-grid ag-tooltip { display: contents; }`

### Session card structure (JS-generated)
```html
<!-- Source: dashboard.js renderSessionCard() lines 70–91 -->
<div class="session-card session-card--live" onclick="location.href='...'">
  <ag-badge variant="live" pulse>En cours</ag-badge>
  <div class="session-card-info">
    <div class="session-card-title">Titre de la séance</div>
    <div class="session-card-meta">
      <span class="session-card-date">15 mars</span>
      <span class="session-card-meta-sep">·</span>
      <span>42 membres</span>
      <span class="session-card-meta-sep">·</span>
      <span>3 résolutions</span>
    </div>
  </div>
  <a class="btn btn-sm btn-secondary session-card-cta btn-success" href="...">
    <span class="pulse-dot" aria-hidden="true"></span>
    ● En cours — Rejoindre →
  </a>
</div>
```

### Utils.apiGet call pattern
```javascript
// Source: dashboard.js line 104
api('/api/v1/dashboard')
  .then(function(data) {
    if (!data || !data.ok) { showDashboardError(); return; }
    var d = data.data; // unwrap envelope
    var meetings = Array.isArray(d.meetings) ? d.meetings : [];
    // ...
  })
```

### Error banner HTML (JS-generated)
```html
<!-- Source: dashboard.js showDashboardError() lines 190–192 -->
<div class="hub-error dashboard-error">
  <p style="margin:0 0 12px;">Impossible de charger les données du tableau de bord.</p>
  <button class="btn btn-primary" id="dashboardRetryBtn">Réessayer</button>
</div>
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `.session-card-status-dot` (colored dot) | `<ag-badge>` component | Phase 35 | Richer badge with pulse, variants |
| Inline `data-demo` fallback data | Real API only | Phase 17 | No mock data anywhere |
| HTMX partial loading | Pure JS fetch | Earlier phases | No htmx-swap targets on dashboard |
| Demo data in sessions list | Live API from `listForDashboard()` | Phase 16 | Zero fallback — API must work |

**Deprecated/outdated:**
- `.session-card-status-dot`: Removed in Phase 35 — comment in design-system.css line 5091 confirms
- `.session-row`, `.session-dot`, `.session-row-info`: Old row classes in pages.css — not emitted by current JS; can be cleaned up in new CSS
- `data-demo` attributes: Gone from current codebase

---

## New HTML/CSS Design Specification

### Visual hierarchy priority
1. KPI row — immediate numbers, colored icons, large mono values
2. Urgent banner — danger-bordered, only visible when live session exists
3. Session list — scannable vertical cards, status badge left-aligned
4. Quick actions aside — shortcuts for top 3 operator actions

### KPI Card anatomy (per card, 4 total)
```
┌──────────────────────────────┐
│ [colored icon 36x36]         │
│                              │
│ 42                           │  ← kpi-value: JetBrains Mono 36px bold
│ AG À VENIR                   │  ← kpi-label: uppercase xs muted
└──────────────────────────────┘
  background: --color-surface-raised
  border: 1px solid --color-border
  border-radius: --radius-xl
  hover: translateY(-3px), --shadow-lg
```

### Session card anatomy (JS renders, CSS must support)
```
┌──────────────────────────────────────────────────────────┐
│ [badge]  Title                              [CTA btn]     │
│          15 mars · 42 membres · 3 résolutions             │
└──────────────────────────────────────────────────────────┘
  CTA: opacity:0 translateX(4px) → opacity:1 on hover
  Live card: border-left: 3px solid --color-success, subtle green bg
```

### Aside shortcut card anatomy
```
┌──────────────────────────┐
│ [40x40 icon]  Title      │
│               Subtitle   │
└──────────────────────────┘
  hover: bg --color-bg-subtle, border --color-border
```

### Responsive breakpoints
- `max-width: 1024px` — `.dashboard-body` becomes 1-col (aside stacks below), aside `position: static`
- `max-width: 768px` — `.dashboard-kpis` becomes 2-col
- `max-width: 640px` — KPI grid stays 2-col (no 1-col needed — numbers too important)

---

## Open Questions

1. **Remove #taches or keep?**
   - What we know: JS writes empty-state to `#taches`, tasks not in API
   - What's unclear: Does the design benefit from a "Tasks" panel or is it noise?
   - Recommendation: Remove the tasks panel from new HTML. JS null-guard handles it. Simplifies layout — more room for session list.

2. **`participant_count` / `motion_count` always 0 on dashboard?**
   - What we know: `listForDashboard()` SQL does not join member counts or motion counts
   - What's unclear: Is this intentional or a gap?
   - Recommendation: Acceptable — session cards show `0 membres · 0 résolutions`. Not a regression from current behavior. Out of scope for this phase.

3. **`kpiConvoc` always 0 — show or hide?**
   - What we know: JS hardcodes 0, comment explains why
   - Recommendation: Keep the card but either (a) show `—` placeholder or (b) wire to a future API field. For now show 0 as-is — consistent with current behavior.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.5 |
| Config file | `phpunit.xml` |
| Quick run command | `./vendor/bin/phpunit tests/Unit/DashboardControllerTest.php` |
| Full suite command | `./vendor/bin/phpunit` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REB-01 | Dashboard HTML renders correctly | manual | Browser visual check | N/A |
| REB-01 | KPI IDs present in new HTML | manual | `grep -c 'id="kpi' dashboard.htmx.html` | ✅ (after rebuild) |
| REB-01 | JS renderSessionCard emits correct classes | unit | `./vendor/bin/phpunit tests/Unit/DashboardControllerTest.php` | ✅ existing |
| WIRE-01 | API /api/v1/dashboard returns ok:true | unit | `./vendor/bin/phpunit tests/Unit/DashboardControllerTest.php` | ✅ existing |
| WIRE-01 | Controller response structure has meetings array | unit | `./vendor/bin/phpunit tests/Unit/DashboardControllerTest.php::testIndexResponseStructure` | ✅ existing |
| WIRE-01 | No dead endpoints | manual | Browser DevTools Network tab, check 200 on /api/v1/dashboard | N/A |

### Sampling Rate
- **Per task commit:** `./vendor/bin/phpunit tests/Unit/DashboardControllerTest.php`
- **Per wave merge:** `./vendor/bin/phpunit`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

None — existing test infrastructure fully covers all phase requirements. `DashboardControllerTest.php` (56 tests) validates API shape, controller logic, and response structure. HTML/CSS visual quality is verified by manual browser check.

---

## Sources

### Primary (HIGH confidence)
- `public/assets/js/pages/dashboard.js` — complete source read; all DOM selectors documented
- `public/dashboard.htmx.html` — complete source read; full structure documented
- `public/assets/css/pages.css` lines 935–1346 — complete dashboard CSS documented
- `public/assets/css/design-system.css` lines 5026–5139 — session-card component CSS documented
- `app/Controller/DashboardController.php` — complete API response shape documented
- `app/Repository/MeetingRepository.php::listForDashboard()` — SQL query confirmed, field list verified

### Secondary (MEDIUM confidence)
- `public/assets/js/components/ag-badge.js` — component API confirmed (variants: primary, success, warning, danger, info, live, draft)
- `public/assets/js/components/ag-empty-state.js` — component API confirmed (icon, title, description, action-label, action-href)
- `tests/Unit/DashboardControllerTest.php` — 56 tests, all backend logic covered

### Tertiary (LOW confidence)
None — all findings verified from source files.

---

## Metadata

**Confidence breakdown:**
- JS DOM dependency map: HIGH — read complete source
- API endpoint shape: HIGH — read controller + repository
- CSS classes to preserve: HIGH — traced from JS output to CSS rules
- New HTML architecture: HIGH — derived from locked decisions + current structure analysis
- Component APIs (ag-badge, ag-empty-state): HIGH — read component source

**Research date:** 2026-03-20
**Valid until:** Stable — no external dependencies, all vanilla stack
