---
phase: 47-hub-rebuild
verified: 2026-03-22T18:00:00Z
status: human_needed
score: 12/12 must-haves verified
re_verification:
  previous_status: gaps_found
  previous_score: 10/12
  gaps_closed:
    - "Hub hero meta line shows real date, place, and meeting type from database"
    - "Hub motions card shows first 3 motion titles from motions_for_meeting API"
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "Load hub page with a valid meeting_id that has a non-AG meeting type (AGE or AGO), verify type badge"
    expected: "Type badge shows actual meeting type label derived from meeting_type field (e.g., AG EXTRAORDINAIRE)"
    why_human: "Requires live session with non-ag_ordinaire meeting_type to confirm the replace(/_/g,' ').toUpperCase() display path"
  - test: "Load hub page with a meeting that has at least 1 motion, verify motions card appears"
    expected: "Motions card shows count badge and up to 3 motion titles; Voir tout link points to operator page"
    why_human: "Requires live database with motion records to confirm motions_for_meeting fetch returns items and card renders"
  - test: "Verify dark mode toggle on hub page"
    expected: "All elements switch correctly via CSS tokens — no hardcoded colors visible"
    why_human: "Visual verification required; CSS is all-token but runtime rendering must be confirmed"
  - test: "Resize to 768px viewport"
    expected: "Two-column layout stacks to single column, hero actions wrap"
    why_human: "Responsive layout requires visual/browser confirmation"
---

# Phase 47: Hub Rebuild Verification Report

**Phase Goal:** The session hub is fully rebuilt — session lifecycle actions wired to real data, quorum bar showing live attendance, checklist reflecting actual state
**Verified:** 2026-03-22T18:00:00Z
**Status:** human_needed (all automated checks passed; 4 browser confirmations remain)
**Re-verification:** Yes — after gap closure via plan 47-03

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Hub page loads session data from wizard_status API and displays title, date, place, badges | VERIFIED | `WizardRepository.getMeetingBasics()` SQL now includes `scheduled_at, location, meeting_type`; `wizardStatus()` api_ok includes all three; `mapApiDataToSession()` maps them to `date`, `place`, `type_label` |
| 2 | Two-column layout: 280px checklist left, quorum + motions right | VERIFIED | hub.htmx.html `hub-body` grid; hub.css `grid-template-columns: 280px 1fr` (unchanged from prior verification) |
| 3 | Quorum bar uses ag-quorum-bar web component with numeric percentage | VERIFIED | `<ag-quorum-bar id="hubQuorumBar">` in HTML; `renderQuorumBar()` sets current/required/total attrs (unchanged) |
| 4 | Motions section shows count badge and first 3 motion titles with Voir tout link | VERIFIED | `loadData()` fires `window.api('/api/v1/motions_for_meeting?meeting_id=...')` async after primary data loads; `renderMotionsList()` receives real `items` array and limits to 3 |
| 5 | Primary CTA (hubMainBtn) and secondary CTA (hubOperatorBtn) prominent in hero | VERIFIED | Both wired in `applySessionToDOM()`; `hubOperatorBtn.href` set to operator page with meeting_id (unchanged) |
| 6 | Dark mode via tokens — no hardcoded colors | VERIFIED | hub.css: 0 hardcoded hex values; all colors via `var(--color-*)` (unchanged) |
| 7 | Responsive: stacks to single column at 768px | VERIFIED | `@media (max-width: 768px)` sets `grid-template-columns: 1fr` (unchanged) |
| 8 | Hub loads session data from wizard_status and applies to DOM | VERIFIED | `loadData()` Promise.all with wizard_status + invitations_stats + workflow_check (unchanged) |
| 9 | Quorum bar shows real present_count vs required threshold | VERIFIED | `mapApiDataToSession()` maps `data.present_count`; `renderQuorumBar()` updates ag-quorum-bar attrs (unchanged) |
| 10 | Checklist shows 3 items with correct done/blocked/pending state from real data | VERIFIED | `renderChecklist()` drives data-check items from invitations_stats + workflowData (unchanged) |
| 11 | Send convocations calls /api/v1/invitations_send_bulk (not dead endpoint) | VERIFIED | `invitations_send_bulk` POST call present; dead `/meetings/{id}/convocations` absent (unchanged) |
| 12 | Aller a la console navigates to operator.htmx.html?meeting_id=... | VERIFIED | `operatorBtn.href = '/operator.htmx.html?meeting_id=' + sessionId` (unchanged) |

**Score:** 12/12 truths verified

### Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Repository/WizardRepository.php` | getMeetingBasics() returns scheduled_at, location, meeting_type | VERIFIED | Line 19: SELECT includes `scheduled_at, location, meeting_type` — confirmed in code |
| `app/Controller/DashboardController.php` | wizardStatus() api_ok includes scheduled_at, location, meeting_type | VERIFIED | Lines 185-187: three new fields appended to api_ok array — confirmed in code |
| `public/assets/js/pages/hub.js` | mapApiDataToSession maps date/place/type_label; loadData calls motions_for_meeting | VERIFIED | Lines 450-471: scheduled_at mapping with fr-FR formatting, location to place, meeting_type to type_label; lines 532-539: async motions_for_meeting fetch — confirmed in code |

### Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `WizardRepository.php` | `DashboardController.php` | `getMeetingBasics()` return consumed by `wizardStatus()` | VERIFIED | `$m['scheduled_at']`, `$m['location']`, `$m['meeting_type']` referenced at lines 185-187; SELECT confirmed at line 19 |
| `hub.js` | `/api/v1/motions_for_meeting` | `window.api()` call in loadData() | VERIFIED | `window.api('/api/v1/motions_for_meeting?meeting_id=' + ...)` at line 532 with `.then` handler passing `items` to `renderMotionsList()` |
| `hub.js` | `/api/v1/wizard_status` | `window.api()` fetch in loadData() | VERIFIED | Unchanged from prior verification — 3 occurrences |
| `hub.js` | `/api/v1/invitations_send_bulk` | POST on send convocations click | VERIFIED | Unchanged from prior verification |
| `hub.js` | `/api/v1/invitations_stats` | GET for convocation checklist state | VERIFIED | Unchanged from prior verification |
| `hub.js` | `/api/v1/meeting_workflow_check` | GET for blocked reasons | VERIFIED | Unchanged from prior verification |

### Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| REB-05 | 47-01, 47-02, 47-03 | Hub — complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional, checklist with real data, hero meta from real API data | SATISFIED | HTML/CSS/JS fully rewritten; quorum bar and checklist functional with real data; date/place/type fields now returned from wizard_status and rendered in hero; REQUIREMENTS.md marks as [x] |
| WIRE-01 | 47-02, 47-03 | Every rebuilt page has verified API connections — no dead endpoints, no mock data | SATISFIED | All 5 API connections verified; dead endpoint fixed in 47-02; motions_for_meeting wired in 47-03; REQUIREMENTS.md marks as [x] |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `hub.js` | 331 | `return null` in loadWorkflowCheck catch block | Info | Graceful error fallback — renderChecklist handles null workflowData correctly; not a stub |

No TODO/FIXME/PLACEHOLDER/console.log/empty-handler stubs found. No regressions detected in previously-verified truths.

### Task Commits Verified

| Commit | Description | Exists |
|--------|-------------|--------|
| `23cfa56` | feat(47-03): extend wizard_status API with scheduled_at, location, meeting_type | Yes |
| `d1dd434` | feat(47-03): wire hub.js date/place/type from API and fetch motions_for_meeting | Yes |

### Human Verification Required

#### 1. Type badge with non-AG meeting type

**Test:** Load hub page with a meeting_id whose `meeting_type` is something other than `ag_ordinaire` (e.g., `ag_extraordinaire`)
**Expected:** Type badge shows the uppercase label derived from meeting_type — e.g., "AG EXTRAORDINAIRE" — not the hardcoded fallback "AG"
**Why human:** Requires a live database row with a non-default meeting_type; the JS path is code-verified but display can only be confirmed with real data

#### 2. Motions card with real motion data

**Test:** Load hub page with a meeting_id that has at least 1 motion in the database
**Expected:** Motions card becomes visible, shows count badge and up to 3 motion titles; "Voir tout" link points to operator page
**Why human:** Requires live database with motion records; the motions_for_meeting fetch is code-verified but card rendering depends on API returning a non-empty items array

#### 3. Dark mode visual check

**Test:** Toggle dark mode on the hub page and inspect each card, badge, and quorum bar
**Expected:** All elements render correctly with token-derived colors; no visible hardcoded colors in light or dark mode
**Why human:** CSS is all-token but rendering correctness requires browser confirmation

#### 4. Responsive layout at 768px

**Test:** Resize browser to 768px width
**Expected:** Two-column layout stacks to single column; hero actions wrap full-width; quorum and motions cards stack below checklist
**Why human:** Breakpoint logic is code-verified but visual stacking requires browser confirmation

### Re-verification Summary

Both gaps from the initial verification are closed:

**Gap 1 — Hero meta fields (CLOSED):** `getMeetingBasics()` SQL now fetches `scheduled_at`, `location`, and `meeting_type`. `wizardStatus()` passes all three in its `api_ok()` payload. `mapApiDataToSession()` maps `data.scheduled_at` to a fr-FR formatted `dateDisplay`, `data.location` to `place`, and `data.meeting_type.replace(/_/g,' ').toUpperCase()` to `type_label`. The DOM application at line 349 now receives a real type_label instead of always falling back to "AG".

**Gap 2 — Motions array (CLOSED):** `loadData()` no longer relies on `sessionData.motions` (which was always empty because wizard_status only returned a count). It now fires a separate `window.api('/api/v1/motions_for_meeting?meeting_id=...')` call after primary data loads, passes the real `items` array to `renderMotionsList()`, and falls back to an empty render on error. The old synchronous `renderMotionsList(sessionData.motions, sessionId)` call has been removed.

No regressions found in the 10 truths that passed initial verification. All existing wiring (checklist, quorum bar, lifecycle CTAs, convocation button, operator navigation) is unchanged and confirmed present.

---
_Verified: 2026-03-22T18:00:00Z_
_Verifier: Claude (gsd-verifier)_
