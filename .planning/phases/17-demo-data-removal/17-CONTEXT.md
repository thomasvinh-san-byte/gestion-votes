# Phase 17: Demo Data Removal - Context

**Gathered:** 2026-03-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Dashboard and audit pages show only real data from the database. Every demo fallback constant is deleted and replaced with proper empty states (when no data exists) and error states (when the backend is unreachable). No new features — pure cleanup.

**Requirements:** HUB-03, HUB-04, CLN-03

**Success Criteria:**
1. Dashboard session count KPIs reflect actual session counts from the database
2. When no sessions exist, dashboard shows empty state (not demo cards)
3. When backend is unreachable, dashboard shows explicit error state (not fake data)
4. Audit page shows real events; DEMO_EVENTS gone; error state on API failure

</domain>

<decisions>
## Implementation Decisions

### Error handling pattern
- **Reuse Phase 16 pattern**: toast + retry button, established in hub.js
- API failure → 1 automatic retry after 2 seconds, then error banner with "Reessayer" button
- No demo data shown under any circumstance — real data, empty state, or error state

### Dashboard behavior
- KPI cards load from the real `/api/v1/dashboard` endpoint
- When no sessions exist: show empty state message (not zeros pretending to be real)
- When API fails: show error toast + retry button (same pattern as hub)
- Delete the wireframe demo fallback block at dashboard.js line 127+

### Audit page behavior
- Delete `DEMO_EVENTS` constant entirely from audit.js
- When API fails: show error state with retry, not demo events
- When no audit events exist: show empty state message
- Both table and timeline views handle the empty/error states consistently

### Claude's Discretion
- Exact empty state wording and layout
- Error banner HTML structure (follow hub.js showHubError pattern)
- Whether to extract a shared error/empty state utility or keep inline
- Loading skeleton approach during API calls

</decisions>

<specifics>
## Specific Ideas

- Follow the exact same error handling pattern from Phase 16's hub.js — toast + retry banner, no demo fallback
- Dashboard header comment says "GO-LIVE-STATUS: ready" with "Donnees demo en fallback" — update this header
- Audit header says "Loads audit events with demo fallback" — update this header

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `hub.js::showHubError()`: Error banner with retry button pattern — reuse or replicate
- `hub.js::loadData()`: 1-retry-then-error pattern — reuse approach
- `Shared.showToast()`: Toast system for error/success messages
- `dashboard.js`: Already has API call to `/api/v1/dashboard` — just needs fallback removal
- `audit.js`: Already has API call — just needs DEMO_EVENTS fallback removal

### Established Patterns
- Frontend uses `window.api(url)` for all API calls
- Toast system uses `Shared.showToast(msg, type)`
- Error banners use `.hub-error` class pattern (from Phase 16)
- All JS files use IIFE pattern with `'use strict'`

### Integration Points
- `dashboard.js` line 127+: Demo fallback block to delete
- `audit.js` line 17-90+: DEMO_EVENTS constant to delete
- `audit.js` line 660-661: Fallback assignment to replace with error state

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 17-demo-data-removal*
*Context gathered: 2026-03-16*
