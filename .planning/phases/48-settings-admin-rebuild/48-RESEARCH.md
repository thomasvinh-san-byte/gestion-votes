# Phase 48: Settings/Admin Rebuild — Research

**Researched:** 2026-03-22
**Domain:** Settings page (sidebar tabs, auto-save, policies), Admin page (KPI cards, user management CRUD)
**Confidence:** HIGH — all findings verified against live source files

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Settings page: vertical sidebar tabs (200px) — rules, communication, security, accessibility tabs on left, content on right
- Settings page: horizontal field groups — label left, input right, consistent with wizard field patterns
- Settings page: per-tab save button — save changes per tab section, success toast on save
- Settings page: 1200px max-width centered — consistent with hub page
- Admin page: horizontal KPI row of 4 cards — total members, sessions, votes, active users. Large mono values, subtle icon
- Admin page: full-width data table for user management — columns: name, email, role, status, actions (edit/deactivate). Sortable headers
- Admin page: inline modal/panel for user CRUD — click "Add user" or "Edit" opens side panel with form fields
- Admin page: single column with sections — KPIs at top, then user management table below
- Shared: full dark mode parity via tokens — consistent with all rebuilt pages
- Shared: toast notifications on save — success/error toast on form submission, field-level validation inline
- Shared: responsive: stack to single column at 768px — settings sidebar tabs become horizontal, admin table scrolls horizontally

### Claude's Discretion
- Exact HTML structure and element hierarchy
- CSS class naming
- Whether to refactor settings.js/admin.js or just update selectors
- Toast notification implementation details
- Modal/panel animation and positioning
- Settings tab content organization within each tab
- Admin table sorting implementation

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| REB-06 | Settings/Admin — complete HTML+CSS+JS rewrite, all settings save correctly, admin KPIs wired, user management functional | Full DOM ID inventory, API endpoints, CSS patterns — all verified |
| WIRE-01 | Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets | Critical: admin_settings.php endpoint does NOT exist — must be created as part of this phase |
</phase_requirements>

---

## Summary

Phase 48 rewrites two pages that share no HTML code but share the same JS infrastructure (utils.js, shared.js, AgToast, Shared.openModal). The settings page is a sidebar-tab layout that auto-saves form fields to a backend settings API. The admin page is a single-column layout with 4 KPI cards followed by a full-width user management table.

The single largest risk in this phase is that **`/api/v1/admin_settings.php` does not exist**. The existing settings.js calls this endpoint for `action: list`, `action: update`, `action: get_template`, `action: save_template`, `action: test_smtp`, `action: test_template`, and `action: reset_templates`. The email templates functionality has a real backend at `/api/v1/email_templates` (GET/POST/PUT/DELETE via `EmailTemplatesController`). A new `admin_settings.php` PHP endpoint must be created that routes settings reads/writes to tenant configuration storage.

The admin KPI row (total members, sessions, votes, active users) must be sourced from existing endpoints: `/api/v1/admin_users.php` (users list), `/api/v1/meetings.php` (sessions), and `/api/v1/members` (member count). The existing admin.js already does this but with DOM IDs that will change during the HTML rewrite.

**Primary recommendation:** Rebuild HTML+CSS for both pages first (Plan 01), then update JS selectors and create the missing admin_settings endpoint (Plan 02). Wire-verify both pages before closing the phase.

---

## Standard Stack

### Core (already in project — no installation needed)

| Asset | Location | Purpose |
|-------|----------|---------|
| design-system.css | `/assets/css/design-system.css` | Design tokens, kpi-card, btn, form-input, tag |
| pages.css | `/assets/css/pages.css` | kpi-card--N variants, dashboard-kpis grid |
| app.css | `/assets/css/app.css` | Imports design-system.css + pages.css |
| AgToast | `/assets/js/components/ag-toast.js` | `AgToast.show(message, type)` — success/error/info/warning |
| ag-popover | `/assets/js/components/ag-popover.js` | Help tooltips |
| ag-tooltip | `/assets/js/components/ag-tooltip.js` | Row action tooltips |
| Shared.openModal | `/assets/js/core/shared.js` | Confirm/form modals for CRUD |
| Shared.btnLoading | `/assets/js/core/shared.js` | Button loading state |
| Shared.validateAll / liveValidate | `/assets/js/core/shared.js` | Inline field validation |
| api() | `/assets/js/core/utils.js` | Fetch wrapper used by all page JS |

### API Endpoints — Settings Page

| Endpoint | Method | Purpose | Exists? |
|----------|--------|---------|---------|
| `/api/v1/admin_settings.php` | POST `action:list` | Load all settings | **NO — must create** |
| `/api/v1/admin_settings.php` | POST `action:update` | Save one setting key/value | **NO — must create** |
| `/api/v1/email_templates` | GET | List email templates | YES |
| `/api/v1/email_templates` | POST | Create template | YES |
| `/api/v1/email_templates` | PUT | Update template | YES |
| `/api/v1/email_templates` | DELETE | Delete template | YES |
| `/api/v1/admin_quorum_policies.php` | GET | List quorum policies | YES |
| `/api/v1/admin_quorum_policies.php` | POST | Create/edit/delete quorum policy | YES |

### API Endpoints — Admin Page

| Endpoint | Method | Purpose | Exists? |
|----------|--------|---------|---------|
| `/api/v1/admin_users.php` | GET | List users + KPI counts | YES |
| `/api/v1/admin_users.php` | POST `action:create` | Create user | YES |
| `/api/v1/admin_users.php` | POST `action:update` | Edit user name/email/role | YES |
| `/api/v1/admin_users.php` | POST `action:toggle` | Activate/deactivate user | YES |
| `/api/v1/admin_users.php` | POST `action:delete` | Delete user | YES |
| `/api/v1/admin_users.php` | POST `action:set_password` | Set user password | YES |
| `/api/v1/meetings.php` | GET | Session list for KPI totals | YES |
| `/api/v1/members` | GET | Member list for total members KPI | YES |

---

## Architecture Patterns

### Recommended Project Structure

Both pages follow the same shell pattern used by dashboard, hub, and operator:

```
public/
├── settings.htmx.html     — rewrite (888 lines → ~300)
├── admin.htmx.html        — rewrite (1218 lines → ~350)
├── assets/css/
│   ├── settings.css       — rewrite (631 lines → ~250)
│   └── admin.css          — rewrite (1175 lines → ~350)
└── assets/js/pages/
    ├── settings.js        — update selectors, preserve all logic
    └── admin.js           — update selectors, preserve all logic
```

### Pattern 1: App Shell (matches all rebuilt pages)

```html
<div class="app-shell">
  <aside class="app-sidebar" data-include-sidebar data-page="settings"></aside>
  <header class="app-header" role="banner">
    <!-- breadcrumb + page-title + page-sub + header actions -->
  </header>
  <main class="app-main" id="main-content" role="main">
    <!-- page content -->
  </main>
</div>
```

Source: verified in dashboard.htmx.html, hub.htmx.html, operator.htmx.html

### Pattern 2: Settings Sidebar Tab Layout

```html
<div class="settings-layout">   <!-- grid: 200px 1fr -->
  <nav class="settings-sidenav" role="tablist" aria-label="Paramètres">
    <button class="settings-sidenav-item active"
            role="tab" aria-selected="true"
            data-stab="regles" aria-controls="stab-regles">
      <!-- icon + label -->
    </button>
    <!-- … more tabs … -->
  </nav>
  <div class="settings-panels">
    <section id="stab-regles" role="tabpanel" class="settings-panel">
      <!-- tab content -->
    </section>
    <!-- … more panels, hidden attr … -->
  </div>
</div>
```

**Critical:** settings.js matches tabs via `[data-stab]` and panels via `id="stab-{tabId}"`. These selectors MUST be preserved in the new HTML.

### Pattern 3: KPI Card Row (reuse pages.css definitions)

```html
<div class="admin-kpis">   <!-- 4-column grid, defined in admin.css -->
  <div class="kpi-card kpi-card--1">
    <div class="kpi-icon"><!-- svg --></div>
    <div class="kpi-value" id="adminKpiUsers">-</div>
    <div class="kpi-label">Membres</div>
  </div>
  <!-- kpi-card--2, --3, --4 -->
</div>
```

**Critical DOM IDs** — admin.js reads these at runtime:
- `#adminKpiUsers` — total users count
- `#adminKpiAdmins` — admin role count
- `#adminKpiOperators` — operator role count
- `#adminKpiActive` — active in last 7 days count

Note: context says KPIs should show "total members, sessions, votes, active users" — this differs from what admin.js currently populates (users, admins, operators, active). Plan must decide: update admin.js KPI logic to match new KPI content, or keep existing DOM IDs and adjust labels only.

### Pattern 4: User Management Table (full-width)

```html
<section class="admin-users-section">
  <div class="section-header">
    <h2>Utilisateurs</h2>
    <div class="section-actions">
      <input id="searchUser" type="search" …>
      <select id="filterRole">…</select>
      <button id="btnCreateUser">…</button>
    </div>
  </div>
  <div id="usersListContainer">
    <!-- renderUsersTable() injects .user-row items here -->
  </div>
  <!-- pagination: #usersPrevPage, #usersPaginationPages, #usersNextPage, #usersPaginationInfo -->
</section>
```

**Critical DOM IDs** — admin.js references these by `getElementById` at module load time (not on-click):
- `#searchUser` — must exist in DOM at JS load
- `#filterRole` — must exist at JS load (addEventListener on line 344)
- `#btnCreateUser` — must exist at JS load
- `#newName`, `#newEmail`, `#newPassword`, `#newRole` — must exist for Shared.liveValidate
- `#usersListContainer` — render target
- `#usersPrevPage`, `#usersNextPage`, `#usersPaginationPages`, `#usersPaginationInfo` — pagination

### Pattern 5: Per-Tab Save Button + Unsaved Dot

settings.js tracks unsaved state per `.card` container and shows/hides `.unsaved-dot`. Section save via `.btn-save-section[data-section]` within a `.card`. New HTML must use:
- `.card` as save section container
- `.btn-save-section` as save trigger
- `.unsaved-dot` as change indicator

### Anti-Patterns to Avoid

- **Removing DOM IDs from JS-driven elements:** Both settings.js and admin.js query IDs at load time. Missing IDs cause silent null-pointer errors, not console errors.
- **Using `class="active"` instead of `hidden` attr for panel show/hide:** settings.js toggles `panel.hidden` — not classList.
- **Inlining CSS instead of tokens:** All color/spacing must use CSS custom properties from design-system.css.
- **Heavy admin.js initialization order:** `Shared.liveValidate` and `document.getElementById('filterRole').addEventListener(...)` run at script parse time, before DOMContentLoaded. New HTML must pre-render these elements, not inject them dynamically.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Modal dialogs | Custom overlay HTML | `Shared.openModal({title, body, confirmText, onConfirm})` | Already handles focus trap, backdrop, confirm/cancel |
| Toast notifications | Custom div injection | `AgToast.show(message, type)` | Already handles stacking, auto-dismiss, dark mode |
| Button loading state | Custom spinner code | `Shared.btnLoading(btn, true/false)` | Already handles disabled+spinner+restore |
| Field validation | Custom error HTML | `Shared.liveValidate(input, rules)` and `Shared.validateAll(specs)` | Already renders inline error messages |
| Help tooltips | title attribute | `<ag-tooltip text="...">` | Consistent with all other rebuilt pages |
| Fetch/API calls | Raw `fetch()` | `api(url, payload)` from utils.js | Handles CSRF, error normalization, JSON parse |

---

## Critical Wiring Gap: admin_settings.php

**Confidence: HIGH (confirmed by routes.php exhaustive scan)**

The `admin_settings.php` endpoint called by settings.js does not exist. The settings page WILL fail to load saved settings and WILL fail to save any field on page load. This is a blocker for WIRE-01.

Resolution options:
1. Create `/public/api/v1/admin_settings.php` + `SettingsController` with `list`, `update`, `get_template`, `save_template`, `test_smtp`, `test_template`, `reset_templates` actions + database table `tenant_settings(tenant_id, key, value)`.
2. Stub the endpoint to return `ok: true` with an empty settings map on GET — sufficient for phase success criteria if the page loads without errors.
3. Rewire email templates to use existing `/api/v1/email_templates` (GET/POST/PUT/DELETE) instead.

**Recommended:** Option 1 (create real endpoint) for settings save/load + Option 3 (rewire email templates to existing endpoint). The phase success criteria requires settings to "persist after page reload" — a stub will not satisfy this.

---

## Common Pitfalls

### Pitfall 1: admin.js getElementById at parse time
**What goes wrong:** Lines like `document.getElementById('filterRole').addEventListener('change', loadUsers)` run immediately when the script is parsed, not inside DOMContentLoaded. If `#filterRole` is absent from the HTML, this throws `Cannot read properties of null` in the console — a console error that violates success criteria.
**Why it happens:** admin.js was written for a specific HTML structure. Any rename or removal of those IDs breaks initialization.
**How to avoid:** Preserve all critical DOM IDs listed in Architecture Pattern 4. If refactoring admin.js, wrap initialization in a null-guard or move to DOMContentLoaded.
**Warning signs:** `Uncaught TypeError: Cannot read properties of null (reading 'addEventListener')` in console on page load.

### Pitfall 2: settings.js panel.hidden vs classList
**What goes wrong:** `switchTab()` sets `panel.hidden = !isActive` — this uses the `hidden` HTML attribute, not CSS class. If CSS is added to `.settings-panel { display: none }` and the `hidden` attribute is not used correctly, panels may not show/hide.
**Why it happens:** Mixing attribute-based and class-based show/hide.
**How to avoid:** Keep `hidden` attribute on inactive panels in HTML. Do not add CSS rules that override `[hidden]` behavior.

### Pitfall 3: KPI content mismatch between context and admin.js
**What goes wrong:** CONTEXT.md specifies KPI cards showing "total members, sessions, votes, active users." The existing admin.js populates `#adminKpiUsers`, `#adminKpiAdmins`, `#adminKpiOperators`, `#adminKpiActive` from admin_users.php — showing users/admins/operators/active users, not members/sessions/votes.
**Why it happens:** The context decision differs from the current implementation.
**How to avoid:** New admin.js must fetch from `/api/v1/members` for total members count, `/api/v1/meetings.php` for sessions/votes, and use new DOM IDs for the new KPI cards. The old admin KPI DOM IDs (`adminKpiAdmins`, `adminKpiOperators`) can be removed.

### Pitfall 4: Email templates wired to non-existent endpoint
**What goes wrong:** settings.js calls `admin_settings.php` with `action:get_template`, `action:save_template`, `action:reset_templates`. These actions don't exist. The real email templates API is at `/api/v1/email_templates`.
**Why it happens:** Legacy wiring from a previous phase that never completed the backend.
**How to avoid:** In the new settings.js, rewire the communication tab email template functions to use `/api/v1/email_templates` with proper GET/POST/PUT/DELETE calls.

### Pitfall 5: Settings page has no real persistence layer
**What goes wrong:** Settings JS calls `admin_settings.php?action=list` on load to pre-populate fields. Without this endpoint, fields show empty/default values after reload — the page appears broken even though the HTML is fine.
**Why it happens:** The endpoint doesn't exist.
**How to avoid:** Create the endpoint before marking the phase complete. At minimum, return `ok: true, data: {}` so the page loads cleanly; implement actual persistence for full success.

---

## Code Examples

### Existing KPI card pattern (dashboard.htmx.html)
```html
<!-- Source: /public/dashboard.htmx.html lines 88–123 -->
<div class="dashboard-kpis">
  <a href="/meetings.htmx.html" class="kpi-card kpi-card--1" aria-label="AG à venir">
    <div class="kpi-icon"><!-- svg --></div>
    <div class="kpi-value" id="kpiSeances">-</div>
    <div class="kpi-label">AG à venir</div>
  </a>
  <!-- ... -->
</div>
```

### Existing KPI CSS (pages.css lines 1034–1081)
```css
/* Source: /public/assets/css/pages.css */
.kpi-card {
  padding: var(--space-6);
  display: flex; flex-direction: column; gap: var(--space-3);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
}
.kpi-card .kpi-value {
  font-family: var(--font-mono);
  font-size: var(--text-4xl);
  font-weight: 700;
  line-height: 1;
}
.kpi-card--1 .kpi-icon { background: var(--color-primary-subtle); color: var(--color-primary); }
.kpi-card--2 .kpi-icon { background: var(--color-danger-subtle); color: var(--color-danger); }
.kpi-card--3 .kpi-icon { background: var(--color-warning-subtle); color: var(--color-warning); }
.kpi-card--4 .kpi-icon { background: var(--color-accent-subtle, var(--color-primary-subtle)); color: var(--color-accent, var(--color-primary)); }
```

### settings.js tab matching (must preserve selectors)
```javascript
// Source: /public/assets/js/pages/settings.js lines 20–55
// Tabs: document.querySelectorAll('.settings-sidenav-item')
//   Each must have: data-stab="{tabId}"
// Panels: document.querySelectorAll('.settings-panel')
//   Each must have: id="stab-{tabId}"
// Active state: tab.classList.toggle('active', isActive)
// Panel hide: panel.hidden = !isActive  (not CSS class)
```

### admin.js DOM IDs that must exist in HTML (initialize at parse time)
```javascript
// Source: /public/assets/js/pages/admin.js lines 222–344
// These getElementById calls run at script parse time, no null guard:
document.getElementById('searchUser')         // line 222
document.getElementById('filterRole')         // line 344  — addEventListener at parse time
document.getElementById('usersListContainer') // line 429  — addEventListener at parse time
// These run inside btnCreateUser click handler (safe if btn exists):
document.getElementById('newName')
document.getElementById('newEmail')
document.getElementById('newPassword')
document.getElementById('newRole')
```

### AgToast usage pattern
```javascript
// Source: /public/assets/js/components/ag-toast.js
AgToast.show('Paramètre enregistré', 'success');
AgToast.show('Erreur de sauvegarde', 'error');
AgToast.show('Section enregistrée', 'success');
// Types: success | error | warning | info
```

### Settings layout CSS skeleton
```css
/* Source: /public/assets/css/settings.css lines 41–55 */
.settings-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: var(--space-card);
  align-items: start;
}
.settings-sidenav {
  position: sticky;
  top: 80px;
  align-self: start;
}
```

---

## State of the Art

| Old Approach | Current Approach | Impact |
|--------------|------------------|--------|
| Horizontal tab bar at top of settings | Vertical sidebar tabs (220px) | Already implemented in old settings.css — reuse `.settings-layout` pattern |
| admin.js KPIs: user counts only | New KPIs: members + sessions + votes + active users | Requires fetching from multiple endpoints; update admin.js KPI logic |
| Email templates via admin_settings.php stub | Email templates via real /api/v1/email_templates | JS rewire needed |
| 888-line settings HTML | Cleaner ~300-line structure | Ground-up rewrite |
| 1218-line admin HTML with onboarding banner, policies tabs, permissions matrix | Simplified: KPIs + user table only | Remove unused sections |

---

## Open Questions

1. **Does a tenant_settings database table exist?**
   - What we know: No `admin_settings.php` endpoint and no `SettingsController` in the app layer.
   - What's unclear: Whether a `tenant_settings` table exists in the schema.
   - Recommendation: Check `database/migrations/` at plan time. If no table exists, Plan 02 must create both migration + endpoint.

2. **New admin KPI labels vs existing DOM IDs**
   - What we know: CONTEXT says "total members, sessions, votes, active users" — but admin.js uses `#adminKpiUsers`/`#adminKpiAdmins`/`#adminKpiOperators`/`#adminKpiActive`.
   - What's unclear: Whether to keep old IDs (with updated labels) or introduce new IDs with new fetch logic.
   - Recommendation: New IDs for new KPI content (`#adminKpiMembers`, `#adminKpiSessions`, `#adminKpiVotes`, `#adminKpiActive`). Update admin.js KPI block to fetch from `/api/v1/members` + `/api/v1/meetings.php`.

3. **Settings tab "Règles" content scope**
   - What we know: Current settings.html has quorum policies CRUD inside the rules tab.
   - What's unclear: Whether the rebuild keeps quorum policies inline or moves them to a modal-only flow.
   - Recommendation: Keep quorum policy list inline in the rules tab (`.settings-panel` → `settingsQuorumList`). The existing JS expects `#settingsQuorumList` and `#btnAddQuorumPolicy`.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | None detected (PHP/vanilla JS project, no test runner configured) |
| Config file | none |
| Quick run command | Manual browser verification |
| Full suite command | Manual browser verification |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REB-06 | Settings tabs switch and content visible | manual | Browser: click each tab | N/A |
| REB-06 | Settings fields save and persist on reload | manual | Browser: change field, reload, verify | N/A |
| REB-06 | Admin KPI cards show real counts | manual | Browser: check counts match user/meeting data | N/A |
| REB-06 | User create/edit/deactivate works | manual | Browser: CRUD walkthrough | N/A |
| WIRE-01 | No JS console errors on page load | manual | Browser DevTools console | N/A |
| WIRE-01 | Settings save endpoint responds ok:true | manual | Browser Network tab | N/A |

### Sampling Rate
- Per task commit: Open page in browser, check no console errors
- Per wave merge: Full manual walkthrough of both pages
- Phase gate: All success criteria verified in browser before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `admin_settings.php` endpoint — settings.js cannot function without it (WIRE-01 blocker)
- [ ] Possible `tenant_settings` DB migration — check database/migrations/ before writing PHP

---

## Sources

### Primary (HIGH confidence)
- `/public/assets/js/pages/settings.js` — full source read, all DOM selectors inventoried
- `/public/assets/js/pages/admin.js` — full source read, all DOM selectors + API calls inventoried
- `/app/routes.php` — full routes table, confirmed admin_settings.php absence
- `/app/Controller/AdminController.php` — confirmed users CRUD actions and signatures
- `/public/assets/css/pages.css` lines 1034–1081 — KPI card CSS patterns
- `/public/dashboard.htmx.html` — KPI card HTML reference
- `/public/assets/css/settings.css` — existing sidebar tab CSS patterns
- `/public/assets/js/core/shared.js` — Shared.openModal, liveValidate, btnLoading signatures
- `/public/assets/js/components/ag-toast.js` — AgToast.show() signature

### Secondary (MEDIUM confidence)
- `.planning/phases/48-settings-admin-rebuild/48-CONTEXT.md` — user decisions

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all libraries verified from source files
- Architecture: HIGH — DOM IDs and selectors verified from live JS files
- Pitfalls: HIGH — confirmed from routes.php (missing endpoint) and admin.js line-by-line analysis
- API wiring: HIGH — routes.php exhaustively confirmed which endpoints exist/don't exist

**Research date:** 2026-03-22
**Valid until:** 2026-04-22 (stable PHP project, tokens-based CSS)
