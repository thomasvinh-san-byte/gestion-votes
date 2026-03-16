# Phase 15: Operator Console Wiring & Verification Gaps - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix operator API endpoint wiring, timer element ID mismatch, quorum warning reset bug, create retroactive Phase 6 VERIFICATION.md for NAV-01 through NAV-06, and close tech debt items (standalone #fff, wrong radius fallback, SUMMARY frontmatter gaps). This is a gap closure phase from the milestone audit.

</domain>

<decisions>
## Implementation Decisions

### Operator API endpoint fix
- Switch `loadMeetingContext()` in `operator-tabs.js:393` from `/api/v1/meetings.php?id=X` to `/api/v1/wizard_status?meeting_id=X` — same endpoint Phase 14 established for hub.js
- Add `opened_at` field to the `wizard_status` backend response: update `WizardRepository::getMeetingBasics()` SQL query to include `opened_at` column, and include it in `DashboardController::wizardStatus()` response array
- Align response parsing to match Phase 14's hub.js pattern: `res.body?.ok && res.body?.data` shape
- Normalize the `wizard_status` flat response to the shape operator expects for `currentMeeting` — map `meeting_title` → `title`, `meeting_status` → `status`, etc. (same approach as hub.js `mapApiDataToSession`)
- Wire `updateHeader()` to also populate `opExecTitle` with the real meeting title from the API response
- On API failure during live session: show a persistent error banner/overlay (not just a toast) since operator is running a live session console

### Timer bug fix
- Minimal fix: update `startSessionTimer()` in `operator-tabs.js:2264` to target `'opExecTimer'` instead of `'execSessionTimer'`
- Do NOT consolidate the two timer functions (startSessionTimer in tabs, startExecTimer in exec) — they serve slightly different contexts. Just fix the ID.

### Quorum warning reset
- Reset `O.quorumWarningShown = false` in `loadMeetingContext()` (operator-tabs.js:382) when switching meetings
- Within a single meeting, quorum warning fires once only (not on re-loss after regain). Reset is per-meeting-switch only.
- Also reset any other per-meeting state flags that may be stale when switching meetings

### Phase 6 retroactive verification
- Create `06-VERIFICATION.md` in `.planning/phases/06-layout-navigation/` by code-review pass against NAV-01 through NAV-06
- If minor gaps found (missing ARIA attribute, footer link, etc.), fix them inline as part of Phase 15
- Fix `06-01-SUMMARY.md` frontmatter: populate empty `requirements_completed` with NAV-01, NAV-02 (and any others covered by that plan)

### Tech debt cleanup — #fff to CSS var
- Fix ALL standalone `#fff` usages in CSS files that should be using a CSS var — not just the one audit-flagged instance
- Leave raw hex values in `:root` token definitions alone (those are the correct definitions of tokens like `--color-text-inverse: #ffffff`)
- Known instances to fix:
  - `wizard.css` lines 80, 85, 106: `color: #fff` → `color: var(--color-text-inverse)`
  - `help.css` line 352: `color: #fff` → `color: var(--color-text-inverse)`
  - `design-system.css:827`: `color: #fff` → `color: var(--color-text-inverse)`
  - `design-system.css:3495`: `color: var(--color-text-inverse, #fff)` — already has var, acceptable as fallback
  - `design-system.css:250,379`: `--sidebar-text-hover: #fff` — token definition, leave as-is
  - `public.css:789,793`: `color-mix(in srgb, ..., #fff)` — mixing with white is intentional for gradient lightening, leave as-is
  - `email-templates.css:174`: `background: var(--color-surface, #fff)` — fallback pattern, acceptable

### Tech debt cleanup — radius fallback
- Fix `ag-confirm.js:70`: `var(--radius-lg, 16px)` → `var(--radius-lg, 0.625rem)` per design token system

### Tech debt cleanup — SUMMARY frontmatter
- Fix Phase 10 SUMMARYs (10-01, 10-02): populate empty `requirements_completed` with DISP-01/02, VOTE-01/02/03 as appropriate
- Fix Phase 06 SUMMARY (06-01): populate `requirements_completed` — covered in Phase 6 verification area above

### Claude's Discretion
- Exact error banner design for operator API failure (persistent vs dismissible, color, position)
- Which additional per-meeting state flags to reset alongside `quorumWarningShown` in `loadMeetingContext`
- How to structure the `wizard_status` → `currentMeeting` normalization function (inline vs named helper)
- Whether any NAV requirements need minor code fixes during verification (depends on findings)

</decisions>

<specifics>
## Specific Ideas

- The operator API endpoint fix mirrors exactly what Phase 14 did for hub.js — same endpoint, same normalization pattern
- The timer bug is a direct consequence of Phase 9 renaming the element from `execSessionTimer` to `opExecTimer` without updating all references
- The quorum warning reset should happen at the top of `loadMeetingContext()` before the API call, alongside the existing `_hasAutoNavigated = false` reset
- Phase 6 verification is retroactive — the code was built and works, just never formally documented

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `hub.js` `mapApiDataToSession()`: Normalization function for `wizard_status` response — pattern to follow for operator
- `hub.js:401`: Working `wizard_status` API call pattern to copy
- `DashboardController::wizardStatus()`: Backend endpoint to extend with `opened_at`
- `WizardRepository::getMeetingBasics()`: SQL query to add `opened_at` column to
- `operator-tabs.js:2262-2294`: `startSessionTimer()` — the function with the wrong element ID
- `operator-exec.js:432-433`: `O.quorumWarningShown` flag that needs reset on meeting switch
- `operator-tabs.js:382-407`: `loadMeetingContext()` — the function to fix endpoint and add resets

### Established Patterns
- `api()` global function: `const { body } = await api(url)` returns `{ body: { ok, data } }` shape
- IIFE modules with `var` keyword and global namespaces (Shared, Auth, Utils)
- One CSS file per page (operator.css, wizard.css, etc.)
- Design tokens from Phase 4 with `var(--token, fallback)` pattern
- `O` namespace for operator state (O.quorumWarningShown, O.currentMeetingStatus, etc.)

### Integration Points
- `wizard_status` endpoint used by: hub.js (Phase 14), operator-tabs.js checklist (already), and now operator-tabs.js loadMeetingContext (this phase)
- `WizardRepository` backend: needs `opened_at` added to `getMeetingBasics()` query
- Phase 6 layout files: `app.css`, sidebar JS, header JS, mobile nav, footer — to be verified against NAV requirements
- SUMMARY frontmatter: `.planning/phases/06-*/06-01-SUMMARY.md`, `.planning/phases/10-*/10-01-SUMMARY.md`, `.planning/phases/10-*/10-02-SUMMARY.md`

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 15-operator-wiring-verification*
*Context gathered: 2026-03-13*
