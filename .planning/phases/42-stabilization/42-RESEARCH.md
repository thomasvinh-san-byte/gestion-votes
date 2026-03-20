# Phase 42: Stabilization - Research

**Researched:** 2026-03-20
**Domain:** v4.2 regression audit — HTML/JS/CSS cross-reference for all "fix now" pages
**Confidence:** HIGH (direct file inspection of every relevant page and JS file)

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
All implementation choices are at Claude's discretion — pure infrastructure/bug-fix phase. The approach is:
1. Audit every page for visual regressions (broken layouts, misaligned elements, missing styles)
2. Audit every page for JS errors (broken event handlers, missing DOM elements, failed querySelector calls)
3. Fix each regression at the source — don't add workarounds
4. If a regression is in a page that will be rebuilt from scratch in Phases 43-48, mark it as "deferred to rebuild" instead of patching it now

### Claude's Discretion
All implementation choices

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| FIX-01 | Fix all v4.2 visual regressions — broken layouts, misaligned elements, missing styles | Audit complete — see Regression Catalog below |
| FIX-02 | Fix all v4.2 JS regressions — broken event handlers, DOM selectors, HTMX targets caused by HTML restructuring | Audit complete — see Regression Catalog below |
</phase_requirements>

---

## Summary

A direct inspection of every "fix now" page (post-session, analytics, meetings, archives, audit, members, users, help, email-templates, landing, public/projector, report, trust, validate, doc, vote) against their corresponding JS and CSS files was conducted. Pages to be rebuilt in Phases 43–48 (dashboard, login, wizard, operator, hub, admin/settings) were noted but not audited for fixes.

The primary finding is a **hard JavaScript crash on the trust page**: `trust.js` line 316 calls `.textContent` on `kpiMotions` which is null (the element does not exist in `trust.htmx.html`). This TypeError fires whenever a meeting with motions is loaded, breaking the entire motions table render.

All other "fix now" pages are structurally sound. The v4.1/v4.2 migrations (form-textarea, user-row flex layout, analytics restructuring, donut horizontal layout) are complete and CSS definitions match HTML class usage. No orphaned CSS classes found on fix-now pages.

**Primary recommendation:** Fix the `trust.js` null crash on `kpiMotions` as the only critical regression. The remaining items are cosmetic or silently-failing (no crash, no visible breakage).

---

## Regression Catalog

### CRITICAL — JS crashes (FIX-02)

#### REG-01: trust.js — `kpiMotions` null crash
- **File:** `public/assets/js/pages/trust.js:316`
- **Error:** `kpiMotions` is `null` — `getElementById('kpiMotions')` returns null because `id="kpiMotions"` does not exist in `trust.htmx.html`
- **Code:** `const kpi = document.getElementById('kpiMotions') || null;` then `kpi.textContent = motions.length;` — the `|| null` does not protect against `.textContent` on null
- **Trigger:** Fires on any meeting that has motions (i.e., every real session). The catch block in `loadMotions` catches the TypeError, so the motions table renders empty, but the error is silently swallowed.
- **Visible effect:** Motions table never populates (always shows "Sélectionnez une séance..."), even after selecting a meeting with motions
- **Fix:** Either (a) add null guard `if (kpi) kpi.textContent = motions.length;` or (b) add a `kpiMotions` element to the HTML. Option (b) is cleaner.
- **Status:** Must fix in Phase 42

---

### LOW — Silently failing (DOM references return null, guarded, no crash, no visible breakage)

#### REG-02: trust.js — `kpiPresent` silent no-op
- **File:** `public/assets/js/pages/trust.js:280`
- **Code:** `var kpiPresent = document.getElementById('kpiPresent'); if (kpiPresent) kpiPresent.textContent = match[1];`
- **Issue:** `id="kpiPresent"` does not exist in `trust.htmx.html`. Guarded by null check — silent fail.
- **Visible effect:** None (the element doesn't exist so nothing breaks visually)
- **Status:** No user-visible impact. Could add element if desired but not required.

#### REG-03: trust.js — `kpiBallots` silent no-op
- **File:** `public/assets/js/pages/trust.js:384`
- **Code:** `var kpiBallots = document.getElementById('kpiBallots'); if (kpiBallots) kpiBallots.textContent = totalBallots;`
- **Issue:** `id="kpiBallots"` does not exist in `trust.htmx.html`. Guarded — silent fail.
- **Status:** No user-visible impact.

#### REG-04: trust.js — `integrityChecks` silent no-op
- **File:** `public/assets/js/pages/trust.js:270–273`
- **Code:** `const integrityChecksEl = document.getElementById('integrityChecks'); if (integrityChecksEl) { ... }`
- **Issue:** `id="integrityChecks"` does not exist in `trust.htmx.html`. Guarded — silent fail.
- **Status:** No user-visible impact.

#### REG-05: trust.js — `kpiChecks` falls back correctly
- **File:** `public/assets/js/pages/trust.js:248`
- **Code:** `const kpi = document.getElementById('kpiChecks') || document.getElementById('kpiEvents');`
- **Issue:** `kpiChecks` doesn't exist, but `kpiEvents` does (line 99 of trust.htmx.html). Fallback works.
- **Status:** No regression — works correctly.

---

### DEFERRED — Rebuild pages (do NOT fix, will be rewritten)

All regressions on the following pages are deferred to their respective rebuild phases:

| Page | Deferred To | Known Issues |
|------|-------------|--------------|
| `dashboard.htmx.html` | Phase 43 | Layout regressions from earlier phases |
| `login.html` | Phase 44 | N/A |
| `wizard.htmx.html` | Phase 45 | HTML restructuring in 41.2/41.3 (680px cap removed, collapsed sections, form-grid-3) |
| `operator.htmx.html` | Phase 46 | Restructuring, SSE wiring |
| `hub.htmx.html` | Phase 47 | Wrapper div for quorum+motions side-by-side (41.3) |
| `admin.htmx.html` / `settings.htmx.html` | Phase 48 | Table→flex migration in 41.4 |

---

### CONFIRMED OK — No regressions found

The following "fix now" pages were fully audited and have no regressions:

| Page | JS File | Verdict |
|------|---------|---------|
| `postsession.htmx.html` | `postsession.js` | All IDs match. Uses `hidden` attribute correctly. `form-textarea` class present. |
| `analytics.htmx.html` | `analytics-dashboard.js` | All IDs match. Donut horizontal layout, hero chart, anomaly cards — all CSS classes defined in analytics.css. |
| `meetings.htmx.html` | `meetings.js` | All IDs match. Modal IDs correct. `onboarding-tips` class defined in app.css. |
| `archives.htmx.html` | `archives.js` | All IDs match. `ag-pagination#archivesPager` present. Export modal IDs correct. |
| `audit.htmx.html` | `audit.js` | All IDs match. `selectAll`, `auditDetailModal`, detail field IDs all present. |
| `members.htmx.html` | `members.js` | All IDs match. Tab panel show/hide via `style.display` works (importPanel, groupFiltersField). Onboarding steps present. |
| `users.htmx.html` | `users.js` | `usersTableBody` div present. `.user-row` flex layout matches users.css. |
| `help.htmx.html` | `help-faq.js` | `faqSearch` present. Tab/section selectors match. |
| `email-templates.htmx.html` | (inline?) | `form-textarea` class correct. `mobile-footer` class present. |
| `index.html` (landing) | `landing.js` | Uses `landing-footer`, not `mobile-footer` — no regression. |
| `public.htmx.html` | `public.js` | Dynamic `style.width` on quorum fill/bar — set by JS, not broken. No mobile-footer used. |
| `report.htmx.html` | `report.js` | All IDs match. `meetingContext`, `notValidatedWarning`, `emailMsg` present with `style="display:none"`. |
| `validate.htmx.html` | `validate.js` | All IDs match. `validateModal` uses `hidden` attribute. All summary IDs present. |
| `docs.htmx.html` | `docs-viewer.js` | All IDs match (`docIndex`, `docContent`, `docTocRail`, `tocList`, breadcrumb IDs). |
| `vote.htmx.html` | `vote.js` | All IDs match. `blockedOverlay` is dynamically created (not in HTML — correct). |
| `trust.htmx.html` | `trust.js` | **See REG-01 above.** One crash, three silent no-ops. |

---

## Architecture Patterns

### Page Structure (fix-now pages)
All pages follow the standard shell:
```
app-shell > app-sidebar + app-header + app-main + app-footer.mobile-footer
+ drawer-backdrop + #drawer
```

### CSS Class System (v4.2 state)
- `textarea` elements use `class="form-textarea"` (not `textarea.form-input`) — migration complete
- `mobile-footer` is a modifier on top of `app-footer` — defined in app.css, not a standalone class
- `user-row` flex system (users.css) — replaces old `<tr>` table rows, matches users.js output
- `onboarding-tips` — defined in app.css line 767

### Trust Page Architecture
The trust page (`trust.htmx.html` + `trust.js`) is a self-contained audit/integrity viewer that duplicates some functionality of `audit.htmx.html` but with integrity checking. It has its own audit log section with different element IDs (`auditTableBody`, `auditLogFilter`, `auditViewToggle`, `auditTableView`, `auditTimelineView`, `auditEventModal`) — all present in HTML and referenced in trust.js.

The missing KPI elements (`kpiMotions`, `kpiPresent`, `kpiBallots`) are stats the JS tries to display but the HTML never included. They were presumably present in an older version of the HTML that was later restructured.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Null guard for missing DOM element | Custom error boundary | Add null check `if (el)` before `.textContent` | One-liner fix |
| Missing KPI element | Computed display logic | Add `<span id="kpiMotions">` to existing integrity dashboard | Matches existing card pattern |

---

## Common Pitfalls

### Pitfall 1: Trust page null crash on first meeting load
**What goes wrong:** Any user selecting a meeting on the trust page with ≥1 motion gets a TypeError. The catch block swallows it, but the motions table stays empty.
**Why it happens:** `kpiMotions` element was removed in an HTML restructuring pass without updating trust.js.
**How to avoid:** Always add null guard before `.textContent` assignment: `if (kpi) kpi.textContent = value;`
**Warning signs:** Motions table never populates on trust page despite selecting a session.

### Pitfall 2: `mobile-footer` vs `app-footer` confusion
**What goes wrong:** Pages using `class="app-footer mobile-footer"` are correct — `app-footer` provides base layout, `mobile-footer` provides logo sizing overrides. Pages NOT using `mobile-footer` (vote, public, landing, postsession) use page-specific footer layouts.
**How to avoid:** Don't add `mobile-footer` to pages that intentionally use a custom footer class.

### Pitfall 3: Form textarea class
**What goes wrong:** Using `class="form-input"` on a `<textarea>` still works (CSS rule at design-system.css:1906 covers it), but the canonical class is now `form-textarea`. Both are defined.
**How to avoid:** Use `form-textarea` for textareas. The migration is complete on all fix-now pages.

---

## Code Examples

### Fix for REG-01 (trust.js kpiMotions crash)

Option A — null guard only (minimal, no HTML change):
```javascript
// trust.js line 312–316 — current broken code:
const kpi = document.getElementById('kpiMotions') || null;
// ...
kpi.textContent = motions.length; // CRASHES when kpi is null

// Fix — add null guard:
const kpi = document.getElementById('kpiMotions') || null;
// ...
if (kpi) kpi.textContent = motions.length;
```

Option B — add element to HTML (recommended, consistent with integrity dashboard):
```html
<!-- In trust.htmx.html, inside .integrity-summary, add: -->
<div class="integrity-stat">
  <div class="integrity-stat-value" id="kpiMotions">&mdash;</div>
  <div class="integrity-stat-label">R&eacute;solutions</div>
</div>
```

---

## State of the Art

| Before v4.2 | After v4.2 | Impact |
|-------------|-----------|--------|
| `<textarea class="form-input">` | `<textarea class="form-textarea">` | Migration complete on fix-now pages |
| `<tr>` rows in users table | `.user-row` flex divs | users.js + users.css aligned |
| analytics donut inline layout | `donut-card--horizontal` + `donut-legend--vertical` | analytics.css defines all new classes |
| `id="drawerOverlay"` on backdrop | `class="drawer-backdrop"` with `data-drawer-close` | Both are present on analytics and docs pages — shell.js handles both |

---

## Open Questions

1. **Should kpiPresent / kpiBallots be added to trust.htmx.html?**
   - What we know: They're referenced in trust.js export report (line 538-539) but guarded — silent fail
   - What's unclear: Were these metrics intentionally removed from the HTML or accidentally lost?
   - Recommendation: Add them as `integrity-stat` items alongside `kpiEvents` — the export report will then show real values instead of `—`

2. **Trust page `integrityChecks` element**
   - What we know: trust.js line 270 tries to update `integrityChecks` CSS class based on pass rate — element doesn't exist
   - What's unclear: Was this meant to be the `integrityStatus` element (which does exist)?
   - Recommendation: Verify whether `integrityChecks` was renamed to `integrityStatus` and update trust.js reference accordingly

---

## Validation Architecture

No automated test infrastructure detected for this PHP project (no pytest.ini, jest.config, vitest.config, or `__tests__` directories). Validation is manual browser testing.

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | Notes |
|--------|----------|-----------|-------------------|-------|
| FIX-01 | Visual regressions resolved — no broken layouts | manual | n/a | Browser visual check on each fix-now page |
| FIX-02 | No JS errors in console on any fix-now page | manual | n/a | Open browser DevTools, load each page, check console |

### Manual Verification Checklist per Fix

For each fix applied:
1. Open the page in browser
2. Open DevTools console (F12)
3. Perform the action that previously triggered the regression
4. Confirm no errors in console
5. Confirm visual output matches expected state

For REG-01 (trust page):
- Select any meeting from the dropdown
- Confirm motions table populates with data
- Confirm no TypeError in console

---

## Sources

### Primary (HIGH confidence)
- Direct file inspection: `public/trust.htmx.html` — all element IDs enumerated
- Direct file inspection: `public/assets/js/pages/trust.js` — all getElementById calls enumerated
- Direct file inspection: `public/assets/css/trust.css` — all class definitions enumerated
- Direct file inspection: All other "fix now" pages listed above

### Secondary (MEDIUM confidence)
- `public/assets/css/design-system.css` — verified `form-textarea`, `app-footer` definitions
- `public/assets/css/app.css` — verified `mobile-footer`, `onboarding-tips` definitions
- `public/assets/css/analytics.css` — verified all Phase 41.5 new classes

---

## Metadata

**Confidence breakdown:**
- Regression catalog: HIGH — direct line-by-line inspection of all relevant files
- Fix recommendations: HIGH — standard JS null guard pattern
- Deferred list: HIGH — directly from CONTEXT.md

**Research date:** 2026-03-20
**Valid until:** 2026-04-20 (stable codebase, no active development on these files until Phase 42 work begins)
