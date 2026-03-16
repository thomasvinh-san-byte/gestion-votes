---
phase: 17-demo-data-removal
verified: 2026-03-16T00:00:00Z
status: passed
score: 8/8 must-haves verified
re_verification: false
---

# Phase 17: Demo Data Removal — Verification Report

**Phase Goal:** The dashboard and audit pages show only real data from the database; every demo fallback is eliminated and replaced with a visible error state
**Verified:** 2026-03-16
**Status:** passed
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| #  | Truth                                                                                             | Status     | Evidence                                                                                                                             |
|----|---------------------------------------------------------------------------------------------------|------------|--------------------------------------------------------------------------------------------------------------------------------------|
| 1  | Dashboard KPI cards show real session counts from the database, not hardcoded 3/1/12/3            | VERIFIED   | KPIs computed from `meetings` array: `upcoming.length`, `live.length`, ended filter. `kpiConvoc=0` is a documented API limitation, not a demo value |
| 2  | When no sessions exist, the dashboard shows an empty state instead of demo session cards           | VERIFIED   | `prochaines` element gets `'Aucune séance à venir'` inline state when `upcoming.length === 0` (line 117)                            |
| 3  | When the API fails, the dashboard shows an error banner with a Réessayer button instead of demo data | VERIFIED | `showDashboardError()` present (4 references): toast + `.hub-error.dashboard-error` banner + `dashboardRetryBtn` wired to `loadDashboard()` |
| 4  | The tasks panel shows an empty state instead of demo task rows                                    | VERIFIED   | On every successful load, `taches.innerHTML = Shared.emptyState(...)` with title `'Aucune tâche en attente'` (lines 121-128)        |
| 5  | The DEMO_EVENTS constant no longer exists in audit.js                                             | VERIFIED   | `grep -c 'DEMO_EVENTS' audit.js` returns 0. 252-line constant confirmed deleted                                                     |
| 6  | When the audit API fails, an error state with retry is shown instead of demo events               | VERIFIED   | `showAuditError()` present (2 references): toast + `Shared.emptyState` in both `_tableBody` and `_timeline` + `auditRetryBtn` wired to `loadData()` |
| 7  | When no meeting_id is in the URL, a guidance message is shown instead of calling the API          | VERIFIED   | Guard at line 436: `if (!meetingId)` sets `_allEvents = []`, renders `'Sélectionnez une séance'` empty state in both views, returns early |
| 8  | When the API succeeds, real audit events render in both table and timeline views                   | VERIFIED   | `loadData()` calls `window.api('/api/v1/audit_log.php?meeting_id=...')`, maps `action_label`→`event`, `actor`→`user`, `created_at`→`timestamp`, then calls `populateKPIs()` and `applyFilters()` which feed `renderTable()`/`renderTimeline()` |

**Score:** 8/8 truths verified

---

### Required Artifacts

| Artifact                                     | Expected                                      | Status     | Details                                                                                           |
|----------------------------------------------|-----------------------------------------------|------------|---------------------------------------------------------------------------------------------------|
| `public/assets/js/pages/dashboard.js`        | Dashboard with zero demo fallback             | VERIFIED   | 177 lines, `showFallback` absent, `showDashboardError` defined + 3 call sites, `node -c` passes  |
| `public/assets/js/pages/audit.js`            | Audit page with zero demo fallback, real API  | VERIFIED   | 621 lines, `DEMO_EVENTS` absent, `showAuditError` defined + 1 call site, `node -c` passes        |

---

### Key Link Verification

| From                        | To                             | Via                               | Status  | Details                                                                                  |
|-----------------------------|--------------------------------|-----------------------------------|---------|------------------------------------------------------------------------------------------|
| `dashboard.js`              | `/api/v1/dashboard`            | `Utils.apiGet` in `tryLoad()`     | WIRED   | `api('/api/v1/dashboard')` at line 75 inside `tryLoad(1)` called from `loadDashboard()` |
| `audit.js`                  | `/api/v1/audit_log.php`        | `window.api()` in `tryLoad()`     | WIRED   | `window.api('/api/v1/audit_log.php?meeting_id=...')` at line 453 inside `tryLoad(1)`    |

---

### Requirements Coverage

| Requirement | Source Plan | Description                                                                              | Status     | Evidence                                                                              |
|-------------|-------------|------------------------------------------------------------------------------------------|------------|---------------------------------------------------------------------------------------|
| HUB-03      | 17-01-PLAN  | Le dashboard affiche les compteurs de sessions réels depuis la base de données           | SATISFIED  | KPIs computed from meetings array; no hardcoded values; marked `[x]` in REQUIREMENTS.md |
| HUB-04      | 17-01-PLAN  | Le dashboard affiche un état d'erreur explicite au lieu du fallback démo                 | SATISFIED  | `showDashboardError()` with toast, banner, and retry button; marked `[x]` in REQUIREMENTS.md |
| CLN-03      | 17-02-PLAN  | Le fallback démo audit.js (DEMO_EVENTS) est supprimé et remplacé par un état d'erreur   | SATISFIED  | DEMO_EVENTS fully deleted; `showAuditError()` implemented; marked `[x]` in REQUIREMENTS.md |

All 3 requirements declared across both plans are accounted for. No orphaned requirements for Phase 17 were found in REQUIREMENTS.md.

---

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `dashboard.js` | 94 | `kpiConvoc` hardcoded to `0` | Info | Intentional — convocation data is not present in the dashboard API. Documented in plan decision and inline comment. Not a demo value; reflects real API limitation. No action required. |

No blockers or warnings found. The single info item is a documented API gap, not a demo fallback.

---

### Human Verification Required

The following behaviors cannot be verified by static analysis:

#### 1. Dashboard KPI display with live database data

**Test:** Navigate to the dashboard page while connected to a database with real sessions.
**Expected:** KPI cards display counts matching actual rows in the meetings table.
**Why human:** Cannot run the full PHP/JS stack in this environment.

#### 2. Error banner visible on API failure

**Test:** Block `/api/v1/dashboard` (e.g., by temporarily pointing to an invalid URL or taking down the backend) and reload the dashboard.
**Expected:** After ~2 seconds (retry delay), a red error banner appears with a "Réessayer" button. Clicking it re-attempts the load.
**Why human:** Requires a running environment with controllable network failure.

#### 3. Audit guidance state when no meeting_id in URL

**Test:** Open the audit page directly (no `?meeting_id=` in URL).
**Expected:** Both table and timeline views display the "Sélectionnez une séance" guidance message. No API call is made (check browser network tab).
**Why human:** Requires a browser to confirm DOM rendering and absence of network request.

#### 4. Audit real events render after successful API call

**Test:** Open the audit page with a valid `?meeting_id=UUID` pointing to a meeting with logged events.
**Expected:** Events appear in the table view with real timestamps, user names, and action labels from the database.
**Why human:** Requires a live database with audit_log rows.

---

### Gaps Summary

No gaps. All automated checks pass:

- `grep -c 'showFallback|DEMO_' dashboard.js` → 0
- `grep -c 'DEMO_EVENTS' audit.js` → 0
- `grep -c 'showDashboardError' dashboard.js` → 4 (definition + 3 call sites)
- `grep -c 'showAuditError' audit.js` → 2 (definition + 1 call site)
- `grep -c 'audit_log.php' audit.js` → 1
- `grep -c "'/api/v1/audit.php'" audit.js` → 0
- `node -c dashboard.js` → PASS
- `node -c audit.js` → PASS
- Retry-once pattern (2s delay before second attempt) confirmed in both files
- Field mapping (`action_label`, `actor`, `created_at`) confirmed in audit.js
- Commits `7f05a89` (dashboard) and `5f4d981` (audit) both exist in git history

The phase goal is fully achieved in the codebase. Both files serve only real API data, empty states, or explicit error states. Every demo fallback has been replaced.

---

_Verified: 2026-03-16_
_Verifier: Claude (gsd-verifier)_
