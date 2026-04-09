# Phase 17-03 — Phase 12 SUMMARY Audit Ledger

**Date:** 2026-04-09
**Scope:** `.planning/milestones/v1.2-phases/12-page-by-page-mvp-sweep/12-*-SUMMARY.md` (21 files, 12-01 through 12-21)
**Patterns searched (case-insensitive):** `documented but not`, `TODO`, `known issue`, `to revisit`, `not blocking`, `workaround`, `deferred`, `follow-up`, `follow up`, `separate concern`, `déféré`, `fragile`, `FIXME`, `hack`, `XXX`, `temporary`, `fallback`, `skip`, `disabled`, `ignore`, `bypass`, `force.?click`, `page\.evaluate`

## Summary

- Total raw grep matches: ~25
- Unique actionable findings: 6
- Resolved by 17-01 / 17-02: 2
- Fixed now (inline): 0
- Deferred to v2: 3
- Promoted to todo: 0
- Noise (false positives / deliberate design): remainder (grouped)

No "fix now" work was required — every remaining unresolved note either belongs to the two bugs already fixed in Wave 1 of Phase 17, or requires CSS/CSP/container-build work outside the scope of a closeout plan.

## Findings Table

| #   | File             | Line(s)      | Note (truncated)                                                                 | Classification       | Resolution                                     |
| --- | ---------------- | ------------ | -------------------------------------------------------------------------------- | -------------------- | ---------------------------------------------- |
| 1   | 12-01-SUMMARY.md | 63–73        | "Known Issue (documented, not blocking)" — settings loadSettings population bug  | resolved by 17-01    | commit `4fc667cd` (fix) + `3eb372d9` (test)    |
| 2   | 12-15-SUMMARY.md | 20, 96–99    | eIDAS chip clicks via `page.evaluate()` to bypass Playwright actionability       | resolved by 17-02    | commit `d120ba2e` (fix) + `36b6414c` (test)    |
| 3   | 12-02-SUMMARY.md | 12, 21, 81, 103–107 | `force: true` on `#btnBarRefresh` / `#btnCloseSession` — hidden `#opQuorumOverlay` intercepts pointer events | deferred to v2       | `V2-OVERLAY-HITTEST` in REQUIREMENTS.md        |
| 4   | 12-17-SUMMARY.md | 23, 35, 83, 110 | `page.evaluate(() => el.click())` for trust severity pills — modal overlay hit-test | deferred to v2       | `V2-OVERLAY-HITTEST` in REQUIREMENTS.md        |
| 5   | 12-17-SUMMARY.md | 104, 108–110 | `agvote-app` container serves April-8 minified trust.js predating audit chip + view-toggle handlers | deferred to v2       | `V2-TRUST-DEPLOY` in REQUIREMENTS.md           |
| 6   | 12-18-SUMMARY.md | 89–95        | Strict CSP blocks inline `data-theme='dark'` script; test accepts `['dark', null]` | deferred to v2       | `V2-CSP-INLINE-THEME` in REQUIREMENTS.md       |
| —   | 12-02, 12-16, 12-19, 12-15 (line 84) | various | Grep false positives: deliberate conditional assertions, documented acceptance of modal-not-clicked, URL param vs sessionStorage (intentional design), localStorage/sessionStorage key mismatch already fixed inline in 12-15 | noise                | no action — matches are inside resolved "Deviations / Auto-fixed" sections |

## Fixes Applied Inline

None. This is a closeout plan and no finding was small enough to justify an unplanned code edit without its own regression test. All actionable items either were already resolved by 17-01 / 17-02 or require non-trivial work (CSS/CSP/container rebuild) and are deferred to v2.

## Deferred to v2

1. **V2-OVERLAY-HITTEST** (findings #3 and #4) — Two critical-path specs (operator, trust) mitigate hidden-overlay pointer interception with `force: true` or `page.evaluate()`. The shared root cause is that modal overlays keep participating in the browser hit-test even when the `hidden` attribute is set, because their `display: flex` / `position: fixed` CSS is more specific than `[hidden] { display: none }`. Proper fix: overlay CSS that defers to `hidden` (either `display: none` while `[hidden]` or `pointer-events: none` + visibility management). Tracked in `REQUIREMENTS.md` and back-linked in `12-02-SUMMARY.md` and `12-17-SUMMARY.md`.

2. **V2-TRUST-DEPLOY** (finding #5) — The Docker image baked April 8 bundles a minified trust.js that predates commit `68329786`. Playwright can only test audit chip / view-toggle wiring structurally until the image is rebuilt and republished. Back-linked in `12-17-SUMMARY.md`.

3. **V2-CSP-INLINE-THEME** (finding #6) — The public page bootstraps dark-theme via an inline `<script>`, which strict CSP (`script-src 'self'`) blocks in the Docker test environment. Current test compromise (`expect(['dark', null]).toContain(...)`) is correct but weaker than production. Proper fix: nonce-based CSP or move the bootstrap to an external file. Back-linked in `12-18-SUMMARY.md`.

## Promoted Todos

None. None of the deferred items are in v1.3 scope — they all belong to the next milestone and are tracked in the `v2 Requirements` section of `REQUIREMENTS.md`.

## Acceptance

- [x] All 21 Phase 12 SUMMARY files grepped with the full pattern set
- [x] Every unique finding classified
- [x] LOOSE-01 (finding #1) marked `resolved by 17-01`
- [x] LOOSE-02 (finding #2) marked `resolved by 17-02`
- [x] Deferred findings (#3, #4, #5, #6) back-linked in their original SUMMARY files under a `## Post-milestone audit` section
- [x] Deferred findings added to `REQUIREMENTS.md` `v2 Requirements` list with explicit IDs
- [x] No Phase 12 SUMMARY contains an unresolved "documented but not fixed" marker without a cross-reference here
