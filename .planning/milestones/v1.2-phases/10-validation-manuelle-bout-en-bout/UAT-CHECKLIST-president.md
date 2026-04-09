# UAT Checklist — PRESIDENT

**Login:** president@ag-vote.local / President2026!
**Date:**
**Tester:**

## Steps

- [ ] **Step 1**: Login as president. Expect: redirect to /hub or /dashboard.
- [ ] **Step 2**: Hub: live meeting visible (or empty state if no live meeting).
- [ ] **Step 3**: Navigate to /operator?meeting_id=... (use a live meeting from list).
- [ ] **Step 4**: Operator console loads with action bar.
- [ ] **Step 5**: View motions panel — at least one motion is listed.
- [ ] **Step 6**: If session is open: try opening a vote on a motion.
- [ ] **Step 7**: Verify the vote status badge updates when vote is launched.
- [ ] **Step 8**: Try closing a vote (if one is open).
- [ ] **Step 9**: View results panel — chart/numbers visible.
- [ ] **Step 10**: Logout.


## Issues found

(Note any deviations from expected behavior — screenshot or copy/paste error messages.
Phase 11 FIX-01 will use this list to scope the fix work.)

| # | Step | Issue | Severity (block/major/minor) |
|---|------|-------|------------------------------|
|   |      |       |                              |

## Verdict

- [ ] PASSED — all steps complete, app usable for this role
- [ ] PASSED WITH ISSUES — see issues table above
- [ ] FAILED — critical path broken
