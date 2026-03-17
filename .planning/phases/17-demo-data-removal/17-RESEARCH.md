# Phase 17: Demo Data Removal - Research

**Researched:** 2026-03-16
**Domain:** Vanilla JS frontend cleanup — fallback removal, empty-state, error-state wiring
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **Error handling pattern**: Reuse Phase 16 pattern — toast + retry button, established in hub.js. API failure → 1 automatic retry after 2 seconds, then error banner with "Réessayer" button.
- **No demo data under any circumstance**: real data, empty state, or error state — the three valid states.
- **Dashboard**: KPI cards load from real `/api/v1/dashboard` endpoint. When no sessions: show empty state message. When API fails: show error toast + retry button (same as hub). Delete the wireframe demo fallback block at dashboard.js line 127+.
- **Audit page**: Delete `DEMO_EVENTS` constant entirely from audit.js. When API fails: show error state with retry, not demo events. When no events: show empty state. Both table and timeline views handle these states consistently.

### Claude's Discretion
- Exact empty state wording and layout
- Error banner HTML structure (follow hub.js showHubError pattern)
- Whether to extract a shared error/empty state utility or keep inline
- Loading skeleton approach during API calls

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| HUB-03 | Le dashboard affiche les compteurs de sessions réels depuis la base de données | dashboard.js already calls `/api/v1/dashboard` — remove `showFallback()` that overwrites real KPI values; API returns `data.meetings` array from which KPIs are computed |
| HUB-04 | Le dashboard affiche un état d'erreur explicite au lieu du fallback démo | Replace `showFallback()` call in `.catch()` with `showDashboardError()` — mirror `showHubError()` from hub.js |
| CLN-03 | Le fallback démo audit.js (DEMO_EVENTS) est supprimé et remplacé par un état d'erreur | Remove `var DEMO_EVENTS = [...]` block (lines 17–268) and replace the `_allEvents = DEMO_EVENTS` assignment in the catch block with an error-state render |
</phase_requirements>

---

## Summary

Phase 17 is pure deletion + wiring: remove hardcoded demo constants, then route the existing error/empty code paths to the correct UI states. No new APIs, no new components, and no new CSS classes are needed — the project already has everything required.

The dashboard (`dashboard.js`) already makes a real API call to `/api/v1/dashboard` on load. When that call succeeds, it correctly computes KPIs from `data.meetings` and renders the list. The only problem is that `.catch()` and the `if (!data || !data.ok)` branch both invoke `showFallback()`, which overwrites the DOM with hardcoded demo values. Deleting `showFallback()` and replacing those two call sites with an error-state renderer (matching hub.js `showHubError()`) is the entire dashboard change.

The audit page (`audit.js`) has a larger constant (`DEMO_EVENTS`, lines 17–268, 25 objects). The `loadData()` async function catches any failure and assigns `_allEvents = DEMO_EVENTS`. Deleting the constant and replacing the catch body with an error-state renderer — analogous to `showHubError()` — satisfies CLN-03. Because `renderTable` and `renderTimeline` already handle empty arrays via `Shared.emptyState()`, the empty-state case (successful API call returning zero items) is already handled correctly.

**Primary recommendation:** Delete `showFallback()` from dashboard.js and `DEMO_EVENTS` from audit.js; wire both catch paths to a `showXxxError()` function that follows hub.js `showHubError()` exactly (toast + prepended error banner with "Réessayer" button).

---

## Standard Stack

### Core (this phase touches only these files)
| File | Location | Role |
|------|----------|------|
| `dashboard.js` | `public/assets/js/pages/dashboard.js` | Dashboard KPIs + session list |
| `audit.js` | `public/assets/js/pages/audit.js` | Audit table + timeline |
| `hub.js` | `public/assets/js/pages/hub.js` | Reference implementation for error pattern |
| `shared.js` | `public/assets/js/core/shared.js` | `Shared.showToast()`, `Shared.emptyState()`, `Shared.skeleton()` |

### Installed utilities (no new installs needed)
| Utility | Available via | Purpose |
|---------|--------------|---------|
| `Shared.showToast(msg, type)` | `window.Shared` | Error toast (type `'error'`) |
| `Shared.emptyState(opts)` | `window.Shared` | Illustrated empty state HTML |
| `Shared.skeleton(el, opts)` | `window.Shared` | Skeleton loading shimmer |
| `window.api(url)` | global | HTTP calls (returns `{body, status}`) |
| `Utils.apiGet(url)` | `window.Utils` | Dashboard-specific helper (already used in dashboard.js line 75) |

**Installation:** None required — all utilities exist.

---

## Architecture Patterns

### Pattern 1: hub.js Error Pattern (canonical reference — HIGH confidence)

This is the locked pattern to replicate in both dashboard.js and audit.js.

```javascript
// Source: public/assets/js/pages/hub.js lines 371-391
function showHubError() {
  if (window.Shared && Shared.showToast) {
    Shared.showToast('Impossible de charger la séance.', 'error');
  }
  var content = document.getElementById('hubContent') || document.querySelector('.hub-main');
  if (content) {
    var banner = document.createElement('div');
    banner.className = 'hub-error';
    banner.innerHTML =
      '<p style="margin:0 0 12px;">Impossible de charger les données de la séance.</p>' +
      '<button class="btn btn-primary" id="hubRetryBtn">Réessayer</button>';
    content.prepend(banner);
    var retryBtn = document.getElementById('hubRetryBtn');
    if (retryBtn) {
      retryBtn.addEventListener('click', function () {
        banner.remove();
        loadData();
      });
    }
  }
}
```

The retry mechanism is: first attempt calls `tryLoad()`, on first failure waits 2 seconds and calls again, on second failure calls `showHubError()`:

```javascript
// Source: public/assets/js/pages/hub.js lines 428-436
} catch (e) {
  if (attempt === 1) {
    setTimeout(tryLoad, 2000);
  } else {
    showHubError();
  }
}
```

### Pattern 2: Shared.emptyState() for zero-results

Already used in audit.js `renderTable()` and `renderTimeline()` for empty filtered results:

```javascript
// Source: public/assets/js/pages/audit.js lines 350-356
if (!events || events.length === 0) {
  _tableBody.innerHTML = '<tr><td colspan="6">' + Shared.emptyState({
    icon: 'search',
    title: 'Aucun événement',
    description: 'Aucun événement ne correspond aux filtres sélectionnés.'
  }) + '</td></tr>';
  return;
}
```

The top-level empty case (API succeeds, returns `[]`) is handled by the same flow: `_allEvents = []` → `applyFilters()` → `renderCurrentView()` → `renderTable([])` triggers the existing empty-state render. No extra code needed for the zero-events case.

### Pattern 3: dashboard.js API call shape

```javascript
// Source: public/assets/js/pages/dashboard.js lines 68-125
function loadDashboard() {
  var api = (typeof Utils !== 'undefined' && Utils.apiGet) ? Utils.apiGet : null;
  if (!api) { showFallback(); return; }  // ← replace with showDashboardError()

  api('/api/v1/dashboard')
    .then(function (data) {
      if (!data || !data.ok) { showFallback(); return; }  // ← replace with showDashboardError()
      var d = data.data;
      var meetings = Array.isArray(d.meetings) ? d.meetings : [];
      // ... KPI computation from meetings array ...
    })
    .catch(function () {
      showFallback();  // ← replace with showDashboardError()
    });
}
```

Three call sites to replace. The `if (!api)` guard check is a legacy path — in production `Utils.apiGet` is always defined, but to be safe it should also show an error state.

### API response shapes (verified from PHP controllers)

**Dashboard** (`/api/v1/dashboard` → `DashboardController::index()`):
```json
{
  "ok": true,
  "data": {
    "meetings": [ { "id": "uuid", "status": "draft|live|ended|archived", "title": "...", "scheduled_at": "..." } ],
    "suggested_meeting_id": "uuid|null"
  }
}
```

**Audit** (`/api/v1/audit_log.php` → `AuditController::timeline()`):
- Requires `?meeting_id=UUID` parameter — returns 400 if missing
- Returns `{ "ok": true, "data": { "items": [...], "total": N } }`
- Each item has: `id`, `timestamp` (= `created_at`), `action_label` (not `event`), `actor` (not `user`), no `hash`/`severity` fields

**Critical mismatch discovered:** The audit page `loadData()` calls `/api/v1/audit.php` (line 653) but the actual endpoint is `/api/v1/audit_log.php`. Furthermore, the response shape expected by audit.js (`res.body.data || res.body.items`, then fields `event`, `user`, `hash`, `severity`) does NOT match what `AuditController::timeline()` returns (`items` under `data`, with `action_label`, `actor`, no `hash`). This means **the audit API call was already broken before Phase 17** — it would always fall through to `DEMO_EVENTS`. Phase 17 must either:
  1. Fix the URL and field mapping while removing the fallback (correct approach), OR
  2. Acknowledge the mismatch in the error state

The CONTEXT.md says "audit.js already has API call — just needs DEMO_EVENTS fallback removal", implying the URL/field mapping fix is in scope as the minimum needed to get real data showing.

### Anti-Patterns to Avoid

- **Replacing demo data with zeros**: When API fails, show error state — not `0` or empty KPIs that look like real data
- **Silent failures**: Never swallow the catch and leave stale/empty DOM with no user feedback
- **Sharing one error banner element**: Each page needs its own; don't assume a DOM element ID exists
- **Leaving header comments stale**: dashboard.js line 1 and audit.js line 1 both have "Données démo en fallback" — update to "zero demo fallback"

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Toast notification | Custom toast HTML/JS | `Shared.showToast(msg, 'error')` | Already wired to `AgToast` web component |
| Empty state illustration | Custom SVG + layout | `Shared.emptyState({icon, title, description})` | Design-system consistent, animated fade-in |
| Retry loop | Custom timer logic | Hub.js 1-retry-after-2s pattern | Already tested, matches UX spec |
| Loading state | Blank DOM | `Shared.skeleton(el, opts)` | Prevents layout shift, accessible `aria-busy` |

**Key insight:** This phase is deletion-first. The less code added, the better. `Shared.emptyState()` and the hub error pattern are the only additions needed.

---

## Common Pitfalls

### Pitfall 1: Audit endpoint URL and field name mismatch
**What goes wrong:** audit.js calls `/api/v1/audit.php` (line 653), but the endpoint is at `/api/v1/audit_log.php`. Even if the URL were correct, the response's `items` are under `data.items`, not directly as `res.body.data`. Field names from the API (`action_label`, `actor`) differ from what audit.js expects (`event`, `user`). Without fixing this, the API call always fails and the error state is shown (still correct for Phase 17), but no real data will ever render.
**How to avoid:** Fix the URL to `/api/v1/audit_log.php` and either (a) map the response fields in `loadData()` or (b) update `renderTable`/`renderTimeline` to use the actual field names. Note that `meeting_id` is required by the API — audit.js must source this from URL params or `MeetingContext`.
**Warning signs:** Network tab shows 400 "missing_meeting_id" responses.

### Pitfall 2: dashboard.js "no Utils" guard shows error banner on page load
**What goes wrong:** Line 70-73 of dashboard.js checks `if (!api)` and calls `showFallback()`. If replaced with `showDashboardError()`, this fires immediately on pages where `Utils` loads late — but in this project, `utils.js` is always loaded before `dashboard.js` per the HTML `<script>` order.
**How to avoid:** Safe to keep the guard; just replace the call. Verify script load order in `dashboard.htmx.html` (confirmed: utils.js → shared.js → shell.js → dashboard.js).

### Pitfall 3: Error banner prepended multiple times on retry failure
**What goes wrong:** If `loadData()` is called again (via retry button) and fails again, a second banner is prepended.
**How to avoid:** Remove any existing `.hub-error` / `.dashboard-error` banner before prepending a new one. Hub.js avoids this because the retry button calls `banner.remove()` before calling `loadData()`. Follow the same pattern.

### Pitfall 4: `taches` (tasks) panel left empty with no feedback
**What goes wrong:** The dashboard tasks panel (`#taches`) is currently populated only by `showFallback()`. After removing the fallback, `loadDashboard()` never populates `#taches` (the API comment says "Tasks — not in dashboard API, leave existing placeholder content"). HTML skeletons remain in the DOM.
**How to avoid:** On successful API load, clear the task skeleton and show an empty state for the tasks panel. On error, the error banner handles the page — the task panel secondary state is less critical but should not show loading spinners forever.

### Pitfall 5: Audit `meeting_id` requirement
**What goes wrong:** `AuditController::timeline()` requires `?meeting_id=UUID` and returns 400 if absent. The audit page is a global view — it does not have a specific meeting context by design.
**How to avoid:** Either (a) use a different endpoint that doesn't require meeting_id (e.g., `admin_audit_log.php` if it exists), or (b) check whether `audit_log.php` accepts absence of `meeting_id` in the current implementation. The current audit.js calls `/api/v1/audit.php` without `meeting_id`, which maps to a non-existent endpoint. A global audit endpoint may need to be identified or the audit page's scope clarified.

---

## Code Examples

### showDashboardError() — dashboard.js addition

```javascript
// Mirrors hub.js showHubError() — container is the main content area
function showDashboardError() {
  if (window.Shared && Shared.showToast) {
    Shared.showToast('Impossible de charger le tableau de bord.', 'error');
  }
  var content = document.getElementById('main-content');
  if (content) {
    // Remove any previous error banner
    var prev = content.querySelector('.dashboard-error');
    if (prev) prev.remove();
    var banner = document.createElement('div');
    banner.className = 'hub-error dashboard-error';
    banner.innerHTML =
      '<p style="margin:0 0 12px;">Impossible de charger les données du tableau de bord.</p>' +
      '<button class="btn btn-primary" id="dashboardRetryBtn">Réessayer</button>';
    content.prepend(banner);
    var retryBtn = document.getElementById('dashboardRetryBtn');
    if (retryBtn) {
      retryBtn.addEventListener('click', function () {
        banner.remove();
        loadDashboard();
      });
    }
  }
}
```

### loadDashboard() after demo removal

```javascript
function loadDashboard() {
  var api = (typeof Utils !== 'undefined' && Utils.apiGet) ? Utils.apiGet : null;
  if (!api) { showDashboardError(); return; }

  api('/api/v1/dashboard')
    .then(function (data) {
      if (!data || !data.ok) { showDashboardError(); return; }
      var d = data.data;
      var meetings = Array.isArray(d.meetings) ? d.meetings : [];
      // ... existing KPI computation unchanged ...
      // Handle tasks panel empty state (no tasks in API):
      var taches = document.getElementById('taches');
      if (taches) {
        taches.innerHTML = Shared.emptyState({
          icon: 'generic',
          title: 'Aucune tâche en attente',
          description: 'Les tâches automatiques apparaîtront ici.'
        });
      }
    })
    .catch(function () {
      showDashboardError();
    });
}
```

### showAuditError() — audit.js addition

```javascript
function showAuditError() {
  if (window.Shared && Shared.showToast) {
    Shared.showToast('Impossible de charger les événements d\'audit.', 'error');
  }
  // Show error in the table body (table view)
  if (_tableBody) {
    _tableBody.innerHTML =
      '<tr><td colspan="6">' +
        Shared.emptyState({
          icon: 'generic',
          title: 'Erreur de chargement',
          description: 'Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.'
        }) +
        '<div style="text-align:center;margin-top:8px;">' +
          '<button class="btn btn-secondary btn-sm" id="auditRetryBtn">Réessayer</button>' +
        '</div>' +
      '</td></tr>';
    var retryBtn = document.getElementById('auditRetryBtn');
    if (retryBtn) retryBtn.addEventListener('click', function() { loadData(); });
  }
  // Show error in timeline view too
  if (_timeline) {
    _timeline.innerHTML =
      Shared.emptyState({
        icon: 'generic',
        title: 'Erreur de chargement',
        description: 'Impossible de contacter le serveur.'
      });
  }
}
```

### audit.js loadData() after demo removal

```javascript
async function loadData() {
  try {
    var res = await window.api('/api/v1/audit_log.php');  // fix URL
    if (res && res.body && res.body.ok && (res.body.data || {}).items) {
      _allEvents = res.body.data.items || [];
    } else {
      throw new Error('No data');
    }
  } catch (e) {
    console.warn('[audit.js] API unavailable:', e.message || e);
    showAuditError();
    return;  // do not call populateKPIs/applyFilters on error
  }

  populateKPIs();
  applyFilters();
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `showFallback()` in dashboard.js | `showDashboardError()` | Phase 17 | HUB-03, HUB-04 satisfied |
| `_allEvents = DEMO_EVENTS` in audit.js | `showAuditError()` + empty `_allEvents` | Phase 17 | CLN-03 satisfied |
| Header comment "Données démo en fallback" | "zero demo fallback — real API only" | Phase 17 | Code clarity |

**Deprecated/outdated after Phase 17:**
- `showFallback()` function in dashboard.js: deleted entirely
- `var DEMO_EVENTS = [...]` in audit.js (lines 17–268): deleted entirely
- `console.warn('[audit.js] API unavailable, using demo data:', ...)` log message: replaced with error state log

---

## Open Questions

1. **Audit endpoint scope — RESOLVED**
   - What we know: `admin_audit_log.php` → `AdminController::auditLog()` is admin-action only (user CRUD, role assignments, policy changes). It does NOT contain session voting/attendance events. The correct endpoint for audit events is `audit_log.php` → `AuditController::timeline()`, which requires `?meeting_id=UUID`.
   - Resolution: audit.js must source `meeting_id` from the URL query string (or `MeetingContext`). When no `meeting_id` is present in the URL, show a "Sélectionnez une séance pour afficher son journal d'audit" empty state without calling the API. When `meeting_id` is present, call `/api/v1/audit_log.php?meeting_id=UUID` and map response fields (`action_label` → display label, `actor` → user column).

2. **Tasks panel (`#taches`) after demo removal**
   - What we know: The dashboard API does not return task data. The tasks panel shows skeleton shims in HTML. `showFallback()` was the only code populating it with content.
   - Resolution: Show `Shared.emptyState()` with title "Aucune tâche automatique" to prevent infinite skeleton shimmer. No new API work required.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Playwright (E2E, browser-based) |
| Config file | `tests/e2e/playwright.config.js` |
| Quick run command | `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js` |
| Full suite command | `cd tests/e2e && npx playwright test --project=chromium` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| HUB-03 | Dashboard KPI shows real count (not `3`/`1`/`12`/`3`) | E2E smoke | `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js` | Partial (existing test checks overflow only) |
| HUB-04 | Dashboard shows error banner when API fails | E2E | `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js` | Partial — new test needed |
| CLN-03 | `DEMO_EVENTS` symbol absent from audit.js bundle | Static grep | `grep -c 'DEMO_EVENTS' public/assets/js/pages/audit.js` (expect 0) | N/A — grep check |
| CLN-03 | Audit page shows error state when API fails | E2E | `cd tests/e2e && npx playwright test --project=chromium specs/audit-regression.spec.js` | Partial — audit-regression tests UX, not data |

### Sampling Rate
- **Per task commit:** `grep -c 'DEMO_EVENTS\|showFallback' public/assets/js/pages/audit.js public/assets/js/pages/dashboard.js` (expect all zeros)
- **Per wave merge:** `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js specs/audit-regression.spec.js`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/e2e/specs/dashboard.spec.js` — needs a new test: "KPI values are not hardcoded demo values (3, 1, 12, 3)" and "error banner shown when API returns non-ok" — existing test only checks horizontal overflow
- [ ] `tests/e2e/specs/audit.spec.js` — no audit page test exists; need smoke test confirming `DEMO_EVENTS` strings do not appear in rendered output and error state renders on API failure

---

## Sources

### Primary (HIGH confidence)
- Direct file reads — `dashboard.js`, `audit.js`, `hub.js`, `shared.js` (full source inspected)
- `DashboardController.php` — API response shape verified from PHP source
- `AuditController.php` — audit API shape and `meeting_id` requirement verified from PHP source
- `dashboard.htmx.html` — DOM element IDs (`#kpiSeances`, `#prochaines`, `#taches`, `#main-content`) verified
- `audit.js` line references — `DEMO_EVENTS` lines 17–268, catch block lines 659–661

### Secondary (MEDIUM confidence)
- Playwright config and existing test files — framework/command structure confirmed by file read
- `REQUIREMENTS.md` — requirement descriptions and current status

### Tertiary (LOW confidence)
- None — all material claims are backed by direct file inspection

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all files read directly, no assumptions
- Architecture patterns: HIGH — hub.js error pattern read verbatim, API response shape verified from PHP
- Pitfalls: HIGH — audit endpoint mismatch verified by comparing audit.js line 653 vs AuditController routes; field mismatch verified by comparing DEMO_EVENTS shape vs `AuditController::formatEvents()` output shape
- Validation: MEDIUM — Playwright framework confirmed, but new tests are gaps to fill in Wave 0

**Research date:** 2026-03-16
**Valid until:** 2026-04-16 (stable codebase — no external dependencies, all local files)
