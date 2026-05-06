---
phase: 05-js-audit-et-wiring-repair
plan: 01
subsystem: ui
tags: [javascript, dom, selectors, audit, wiring, htmx]

# Dependency graph
requires: []
provides:
  - "Complete JS/HTML ID contract inventory (05-ID-CONTRACTS.md) covering 32 page JS files and 5 core JS files"
  - "Fixed vote.js selector: getElementById('vote-buttons') matching HTML id=\"vote-buttons\""
  - "Documented 12 ORPHAN IDs removed in v4.2 regression for Phase 6 repair tracking"
affects:
  - "06-ui-design-system"
  - "05-js-audit-et-wiring-repair"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ID contract audit methodology: extract JS selectors, extract HTML IDs, cross-reference, mark OK/ORPHAN/MISMATCH"
    - "JS adapts to HTML: fix JS selectors to match HTML IDs, never change HTML IDs to match broken JS"

key-files:
  created:
    - ".planning/phases/05-js-audit-et-wiring-repair/05-ID-CONTRACTS.md"
  modified:
    - "public/assets/js/pages/vote.js"

key-decisions:
  - "Fix only confirmed MISMATCH in this plan — ORPHAN IDs removed from HTML are tracked but deferred to Phase 5 plan 02 or Phase 6"
  - "JS selector convention: camelCase for JS-targeted IDs (standard), kebab-case exceptions are vote-buttons, main-content, auth-banner"
  - "Dynamic/JS-generated ORPHAN IDs are acceptable and not bugs — elements created by JS, queried by JS"

patterns-established:
  - "Audit-first before HTML modifications: always run ID contract audit before restructuring HTML to prevent v4.2-style regressions"

requirements-completed:
  - WIRE-01
  - WIRE-02

# Metrics
duration: 25min
completed: 2026-04-07
---

# Phase 5 Plan 01: JS Audit et Wiring Repair — ID Contracts Summary

**Complete JS/HTML ID contract inventory across 32 page JS files confirming 207 OK selectors, 1 MISMATCH fixed (vote-buttons), and 12 v4.2 regression orphans documented**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-04-07T13:30:00Z
- **Completed:** 2026-04-07T14:05:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Generated comprehensive ID contract inventory (05-ID-CONTRACTS.md) covering all JS files in pages/ and core/ directories
- Audited ~230 getElementById/querySelector calls and cross-referenced against 22 HTML pages
- Fixed the one confirmed MISMATCH: `getElementById('voteButtons')` → `getElementById('vote-buttons')` in vote.js line 852
- Documented 12 ORPHAN IDs from v4.2 regression (removed from HTML but still referenced in JS) for future repair
- Documented 15 JS-generated ORPHAN IDs as acceptable (self-contained patterns)

## Task Commits

Each task was committed atomically:

1. **Task 1: Generate ID contract inventory across all JS files** - `e737cda2` (docs)
2. **Task 2: Fix confirmed broken selectors identified in inventory** - `2181c69f` (fix)

## Files Created/Modified

- `.planning/phases/05-js-audit-et-wiring-repair/05-ID-CONTRACTS.md` - Complete JS/HTML ID cross-reference inventory with 734 lines of audit data
- `public/assets/js/pages/vote.js` - Fixed `getElementById('voteButtons')` to `getElementById('vote-buttons')` (line 852)

## Decisions Made

- Fix only the confirmed MISMATCH in this plan; ORPHAN IDs removed from HTML are documented and deferred to plan 02 or Phase 6 for targeted repair
- JS adapts to HTML (not the reverse) — always fix JS selectors to match HTML IDs
- Dynamic JS-generated elements creating their own IDs are acceptable ORPHAN patterns (not bugs)

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- 05-ID-CONTRACTS.md is the definitive source of truth for ID contracts — Phase 6 HTML modifications must consult it
- 12 ORPHAN IDs from v4.2 regression are documented: `cMeeting`, `cMember`, `execQuorumBar`, `execSpeakerTimer`, `proxyStatGivers`, `proxyStatReceivers`, `tabCountProxies`, `opPresenceBadge`, `taches` (dashboard), `usersPaginationInfo` (admin), `app_url` (settings), `appUrlLocalhostWarning` (settings)
- These orphans cause silent null returns in JS — not crashes, but broken functionality — target for plan 02

---
*Phase: 05-js-audit-et-wiring-repair*
*Completed: 2026-04-07*
