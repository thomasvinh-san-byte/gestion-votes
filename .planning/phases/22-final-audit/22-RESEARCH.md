# Phase 22: Final Audit - Research

**Researched:** 2026-03-18
**Domain:** Codebase cleanliness audit (DEMO_ constants, API call state handling)
**Confidence:** HIGH

## Summary

This research performs a full codebase audit to verify two requirements: (CLN-01) zero DEMO_ constants in production code, and (CLN-02) every API call site has loading, error, and empty states.

**CLN-01 is already satisfied.** A grep for `DEMO_` across all `.js`, `.php`, and `.html` files returns zero results. The only remaining `DEMO_` references are: (a) the `LOAD_DEMO_DATA` environment variable in infrastructure config files (docker-compose, .env, deploy scripts, docs) -- which is legitimate infra config, not a code constant; and (b) `MEETING_ID_DEMO_AZ` in `database/setup_demo_az.sh` -- a test fixture/seed script, explicitly excluded per the requirement "outside of test fixtures and comments."

**CLN-02 has gaps.** Most pages have good error handling, but several pages are missing one or more of the three required states (loading indicator, error message, empty state). The gaps are concentrated in pages that were wired later in the v3.0 cycle and a few older pages that were not revisited.

**Primary recommendation:** Create a systematic page-by-page fix plan targeting the ~8 pages with missing states, prioritizing the most user-visible gaps (hub loading indicator, postsession silent catches, public.js error handling).

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CLN-01 | Zero DEMO_ constants in codebase | ALREADY SATISFIED -- grep across all source files returns zero. Only infra config (LOAD_DEMO_DATA env var) and test seed script (setup_demo_az.sh) remain, both explicitly excluded. |
| CLN-02 | Every API call has loading, error, and empty states | GAPS IDENTIFIED -- 8 pages need fixes. Detailed findings below with specific file:line references. |
</phase_requirements>

## CLN-01 Audit: DEMO_ Constants

### Result: PASS (zero remaining)

**Search performed:** `grep -rn "DEMO_" --include="*.js" --include="*.php" --include="*.html" public/ src/` returns zero matches.

**Remaining DEMO_ references (all exempt):**

| File | Reference | Why Exempt |
|------|-----------|------------|
| `docker-compose.yml`, `.env.*`, `deploy/entrypoint.sh`, `render*.yaml` | `LOAD_DEMO_DATA` | Infrastructure environment variable, not a code constant |
| `bin/check-prod-readiness.sh` | `LOAD_DEMO_DATA` | Production readiness check script |
| `docs/DEPLOIEMENT_*.md`, `docs/DOCKER_INSTALL.md` | `LOAD_DEMO_DATA` | Documentation references |
| `database/setup_demo_az.sh` | `MEETING_ID_DEMO_AZ` | Test fixture / demo seed script |

**Previously removed (by prior phases):**
- Phase 16: Removed `DEMO_SESSION`, `DEMO_FILES` from `hub.js`
- Phase 17: Removed `DEMO_EVENTS` (252 lines) from `audit.js`, removed `showFallback` from `dashboard.js`

**Confidence:** HIGH -- direct grep verification of all source files.

## CLN-02 Audit: API Call State Handling

### Methodology

Every page JS file was checked for:
1. **Loading indicator** -- skeleton in HTML template, spinner, `Shared.btnLoading()`, or `aria-busy` during API request
2. **Error state** -- catch block with user-visible error message (toast, banner, inline message)
3. **Empty state** -- handling when API returns valid but empty data (zero items)

### Pages with COMPLETE state handling (no action needed)

| Page | JS File | Loading | Error | Empty | Notes |
|------|---------|---------|-------|-------|-------|
| Dashboard | `dashboard.js` | Skeleton in HTML | `showDashboardError()` + retry | "Aucune seance a venir" | Fully wired |
| Meetings | `meetings.js` | Skeleton in HTML | `Shared.withRetry()` | `Shared.emptyState()` | Uses retry wrapper |
| Operator | `operator-tabs.js` | Skeletons + `showTabLoading()` | `showTabError()` + toast | `showTabEmpty()` | Comprehensive |
| Operator Motions | `operator-motions.js` | Spinner on buttons | Toast + tab error | "Aucune resolution" | Complete |
| Operator Attendance | `operator-attendance.js` | Tab loading state | Toast + tab error | Empty participant list | Complete |
| Members | `members.js` | "Chargement..." text | Toast | `Shared.emptyState()` (filtered + absolute) | Complete |
| Users | `users.js` | Skeleton in HTML | `setNotif('error')` + inline | Empty handled via render | Complete |
| Admin | `admin.js` | "Chargement..." rows | `setNotif('error')` + inline | `Shared.emptyState()` per section | Complete |
| Analytics | `analytics-dashboard.js` | Skeletons + CSS spinner | `chartErrorHtml()` per chart | "Aucune donnee disponible" | Complete |
| Archives | `archives.js` | Spinner in HTML | `Shared.emptyState()` search | Empty year + absolute empty | Complete |
| Audit | `audit.js` | Spinner in HTML | `showAuditError()` + retry | "Aucun evenement" (filtered + guidance) | Complete |
| Trust | `trust.js` | Spinner in HTML | `setNotif('error')` per section | Per-section empty | Complete |
| Validate | `validate.js` | Spinner in HTML | `setNotif('error')` | "Already validated" state | Complete |
| Login | `login.js` | Login spinner | Toast on failure | N/A (login form) | Complete |
| Docs | `docs-viewer.js` | Skeleton + spinner | "Index non disponible" fallback | N/A (always has content) | Complete |
| Email Templates | `email-templates-editor.js` | N/A (lightweight) | Toast on error | `emptyState` element toggle | Complete |
| Settings | `settings.js` | Skeleton in HTML | Toast + inline error | N/A (form-based) | Complete |

### Pages with GAPS (action required)

#### 1. Hub (`hub.js`) -- Missing loading indicator
- **Loading:** NO skeleton or spinner in `hub.htmx.html` (0 matches). The page renders its shell immediately but the data area is blank until the API responds. No visual loading feedback.
- **Error:** GOOD -- `showHubError()` with banner + retry button + toast
- **Empty:** GOOD -- redirects if no session ID, shows "not found" for invalid ID
- **FIX:** Add skeleton or spinner to `hub.htmx.html` data areas (KPIs, checklist, documents)

#### 2. Post-session (`postsession.js`) -- Silent catches, missing loading indicator
- **Loading:** NO skeleton or spinner in `postsession.htmx.html`. Steps load data without visual feedback.
- **Error:** PARTIAL -- Step 1 `loadVerification` has a catch that tries a fallback, then a `/* silent */` catch (line 139). Step 3 `loadPV` summary fetch has `/* silent */` catch (line 353). Step 2 and Step 4 have proper error toasts.
- **Empty:** GOOD -- "Aucune resolution trouvee" in results table, "Aucune seance terminee" in picker
- **FIX:** (a) Add loading state to each step panel. (b) Replace silent catches in Step 1 and Step 3 with user-visible error indicators.

#### 3. Public projection (`public.js`) -- Missing error state for API calls
- **Loading:** N/A (auto-polling page, shows "Aucune seance en cours" while polling)
- **Error:** PARTIAL -- `loadResults()` (line 153) has error handling, but `pollMeetings()` (line 284) catch block at line 232/446 shows a generic error without retry guidance. The `heartbeat` (line 466) error is silently ignored (acceptable for background heartbeat).
- **Empty:** GOOD -- "Aucune seance en cours" with auto-refresh message
- **FIX:** Minor -- ensure poll errors show a reconnection indicator rather than silently failing

#### 4. Vote page (`vote.js`) -- Missing initial loading indicator
- **Loading:** NO skeleton or spinner in `vote.htmx.html`. The page relies on JS to render everything.
- **Error:** GOOD -- extensive error handling with toasts and inline messages
- **Empty:** GOOD -- "Aucune resolution en cours" message
- **FIX:** Add a loading skeleton or spinner to `vote.htmx.html` for initial page load

#### 5. Wizard (`wizard.js`) -- Missing loading during API submission
- **Loading:** NO skeleton in `wizard.htmx.html`, but the wizard is form-based (not data-loading). The submission at line 703 (`api('/api/v1/meetings', payload)`) uses `Shared.btnLoading` on the submit button. HOWEVER, the initial page load has no loading state (members list, etc. are user-input, not API-loaded).
- **Error:** GOOD -- catch block with toast at line 724
- **Empty:** N/A -- wizard creates data, doesn't list it
- **FIX:** MINOR -- wizard is form-input based, not a data-loading page. Loading indicator on submit button already exists. No significant gap.

#### 6. Report (`report.js`) -- Missing loading for iframe content
- **Loading:** NO skeleton or spinner in `report.htmx.html`. The iframe (`pvFrame`) loads content but has no loading indicator while it loads.
- **Error:** GOOD -- `setNotif('error')` for meeting info, inline error for email send
- **Empty:** GOOD -- `pvEmptyState` element exists for when no meeting is selected
- **FIX:** Add a loading spinner over the iframe area while content loads

#### 7. PV Print (`pv-print.js`) -- Partial error display
- **Loading:** No loading indicator (print page, loads data then renders)
- **Error:** PARTIAL -- individual fetch errors are collected into an `errors` array and displayed as "Chargement partiel (donnees manquantes : ...)" but no retry option
- **Empty:** N/A (print page renders whatever data is available)
- **FIX:** MINOR -- add a more prominent error banner when data is missing. This is a print page so loading indicator is less critical.

#### 8. Landing (`landing.js`) -- Silent catch blocks
- **Loading:** Login spinner exists on form
- **Error:** PARTIAL -- `whoami.php` check at line 13 has `.catch(function() { /* ignore */ })` (line 32), which is acceptable (user just sees login form). Login error at line 77 shows toast. Post-login whoami at line 63 silently falls back to redirect (acceptable).
- **Empty:** N/A (auth page)
- **FIX:** NONE needed -- silent catches on auth check pages are intentional design (show login form on failure)

### Silent Catch Summary

| File | Line | Catch Content | Severity | Action |
|------|------|---------------|----------|--------|
| `postsession.js` | 139 | `/* silent */` -- fallback fetch failure in Step 1 | MEDIUM | Show error indicator |
| `postsession.js` | 353 | `/* silent */` -- meeting summary in Step 3 | LOW | Non-blocking, but should show warning |
| `postsession.js` | 306 | `/* signataire names remain as default */` | LOW | Acceptable -- graceful degradation |
| `public.js` | 472 | Heartbeat error silenced | LOW | Acceptable -- background task |
| `public.js` | 488/490 | Fullscreen API catch | NONE | Standard browser API pattern |
| `settings.js` | 588/609 | Empty catch on template preview | LOW | Preview failure, non-critical |
| `wizard.js` | 123/721 | localStorage clear/remove | NONE | Storage API robustness |
| `auth-ui.js` | 172/498 | MeetingContext.clear() | NONE | Cleanup robustness |
| `vote-ui.js` | 474/539 | Silent fail for polling | LOW | Acceptable for poll pattern |
| `admin.js` | 90 | Dashboard section optional | LOW | Intentionally optional |
| `landing.js` | 32 | whoami check catch | NONE | Intentional -- shows login form |
| `archives.js` | 503 | focus() catch | NONE | Focus robustness |

## Architecture Patterns

### Existing Error Handling Patterns in Codebase

The codebase already uses consistent patterns that should be followed for all fixes:

**Pattern 1: Toast + Banner + Retry (hub/dashboard pattern)**
```javascript
// Source: public/assets/js/pages/hub.js:371-391
function showHubError() {
  Shared.showToast('Impossible de charger...', 'error');
  var banner = document.createElement('div');
  banner.className = 'hub-error';
  banner.innerHTML = '<p>Impossible de charger...</p>' +
    '<button class="btn btn-primary" id="hubRetryBtn">Reessayer</button>';
  content.prepend(banner);
}
```

**Pattern 2: Shared.withRetry (meetings pattern)**
```javascript
// Source: public/assets/js/pages/meetings.js:88-102
Shared.withRetry({
  container: meetingsList,
  maxRetries: 1,
  errorMsg: 'Impossible de charger les seances',
  action: async function() { /* ... */ }
});
```

**Pattern 3: Operator tab states**
```javascript
// Source: public/assets/js/pages/operator-tabs.js:2588-2600
showTabLoading('participants', 'Chargement des participants...');
showTabError('participants', 'Erreur chargement.', 'loadAttendance');
showTabEmpty('ordre-du-jour', 'Aucune resolution');
```

**Pattern 4: Skeleton in HTML template (replaced by JS on load)**
```html
<!-- Source: public/dashboard.htmx.html:119-121 -->
<div class="skeleton skeleton-session"></div>
<div class="skeleton skeleton-session"></div>
<div class="skeleton skeleton-session"></div>
```

### Recommended Fix Pattern

For pages missing loading state, add HTML skeletons matching the existing project pattern. For pages with silent catches, replace with the toast pattern used elsewhere.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Loading indicators | Custom spinners | Existing `skeleton` CSS classes or `<ag-spinner>` component | Already in design system |
| Error banners | New error UI | `hub-error` class + `Shared.showToast()` pattern | Consistent UX |
| Empty states | Custom empty markup | `Shared.emptyState({ icon, title, description })` | Used across 10+ pages already |
| Button loading | Manual disable/enable | `Shared.btnLoading(btn, true/false)` | Handles spinner + disabled state |
| Retry logic | Custom retry loops | `Shared.withRetry()` or manual retry-once pattern | Already battle-tested |

## Common Pitfalls

### Pitfall 1: Over-counting DEMO_ references
**What goes wrong:** Counting `LOAD_DEMO_DATA` env var references as violations of CLN-01
**Why it happens:** Grep returns all matches including infra config
**How to avoid:** CLN-01 specifies "outside of test fixtures and comments" -- env vars in docker/deploy config are infrastructure, not code constants
**Warning signs:** Someone marks CLN-01 as failing based on grep of non-source files

### Pitfall 2: Treating all silent catches as bugs
**What goes wrong:** Replacing intentional silent catches (localStorage, MeetingContext.clear, fullscreen API) with error toasts, creating noisy UX
**Why it happens:** Blanket "no silent catch" rule without understanding intent
**How to avoid:** Only flag catches on API data-loading calls. Browser API robustness catches (storage, focus, fullscreen) are correct as-is.

### Pitfall 3: Adding loading state to pages that don't need it
**What goes wrong:** Adding spinners to form-based pages (wizard, settings) that don't load data on init
**Why it happens:** Treating all pages equally regardless of whether they fetch data on load
**How to avoid:** Only pages that fetch and display data on initial load need loading indicators. Form-based pages need button loading (already have it).

## Prioritized Fix List

### Priority 1: User-visible data pages
1. **hub.htmx.html** -- Add skeleton loading to KPI strip, checklist section, documents section
2. **postsession.js** -- Replace silent catches in Step 1 (line 139) and Step 3 (line 353) with visible error indicators; add loading state to step panels

### Priority 2: Voter/public-facing pages
3. **vote.htmx.html** -- Add loading skeleton for initial page render
4. **public.js** -- Add reconnection indicator for poll errors

### Priority 3: Secondary pages
5. **report.htmx.html** -- Add loading spinner for iframe content area
6. **pv-print.js** -- Improve error banner visibility (minor)

### NOT requiring fixes
- **wizard.js** -- Form-based, button loading already exists
- **landing.js** -- Auth page, silent catches are intentional
- **help-faq.js** -- No API calls at all
- **settings.js** -- Form-based, has inline error handling
- **email-templates-editor.js** -- Has empty state and error toast

## Code Examples

### Adding skeleton loading to hub.htmx.html
```html
<!-- Add inside the KPI strip area -->
<div id="hubKpis" aria-busy="true">
  <div class="skeleton skeleton-kpi"></div>
  <div class="skeleton skeleton-kpi"></div>
  <div class="skeleton skeleton-kpi"></div>
</div>

<!-- Add inside checklist area -->
<div id="hubChecklist" aria-busy="true">
  <div class="skeleton skeleton-text skeleton-w-80"></div>
  <div class="skeleton skeleton-text skeleton-w-60"></div>
  <div class="skeleton skeleton-text skeleton-w-70"></div>
</div>
```

### Replacing silent catch in postsession.js Step 1
```javascript
// BEFORE (line 139):
} catch (e2) { /* silent */ }

// AFTER:
} catch (e2) {
  setNotif('error', 'Impossible de charger les resultats de verification.');
}
```

### Adding loading indicator to vote.htmx.html
```html
<!-- Add inside the main vote content area -->
<div id="voteContent">
  <div class="text-center p-6">
    <ag-spinner size="lg" label="Chargement..."></ag-spinner>
  </div>
</div>
```

## Open Questions

1. **Should LOAD_DEMO_DATA env var be considered in scope for CLN-01?**
   - What we know: The requirement says "outside of test fixtures and comments." LOAD_DEMO_DATA is an environment variable controlling demo data seeding at deploy time.
   - Recommendation: NO -- this is infrastructure config, not a code constant. The requirement targets in-code DEMO_ constants like DEMO_SESSION, DEMO_EVENTS, DEMO_FILES which have all been removed.

2. **Should pv-print.js error handling be enhanced?**
   - What we know: It's a print-preview page that degrades gracefully with partial data
   - Recommendation: LOW priority -- add a slightly more prominent error indicator, but the graceful degradation pattern is acceptable for a print page.

## Sources

### Primary (HIGH confidence)
- Direct grep of entire codebase for `DEMO_` pattern
- Line-by-line reading of all 30+ JS page controllers in `public/assets/js/pages/`
- Inspection of all 20 `.htmx.html` page templates for skeleton/spinner elements
- Cross-reference with prior phase verification documents (Phase 16, 17)

### Codebase Files Examined
- All files in `public/assets/js/pages/*.js` (30 files)
- All files in `public/*.htmx.html` (20 files)
- `public/assets/js/core/utils.js`, `shared.js`, `shell.js`
- `database/setup_demo_az.sh`
- Infrastructure config: `docker-compose.yml`, `.env.*`, `deploy/entrypoint.sh`

## Metadata

**Confidence breakdown:**
- CLN-01 (DEMO_ constants): HIGH -- exhaustive grep, zero results in source files
- CLN-02 (loading states): HIGH -- every page JS file and HTML template examined
- Fix recommendations: HIGH -- based on existing patterns already proven in the codebase

**Research date:** 2026-03-18
**Valid until:** Indefinite (codebase audit, not library version research)
