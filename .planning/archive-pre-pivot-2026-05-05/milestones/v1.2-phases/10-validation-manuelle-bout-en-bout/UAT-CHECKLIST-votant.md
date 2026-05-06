# UAT Checklist — VOTANT

**Login:** votant@ag-vote.local / Votant2026! (session-based, OR token via vote URL)
**Date:**
**Tester:**

## Steps

- [ ] **Step 1**: Login as votant. Expect: redirect to vote-app or hub.
- [ ] **Step 2**: Navigate to /vote: vote app loads.
- [ ] **Step 3**: Meeting selector visible — pick a meeting if multiple.
- [ ] **Step 4**: Member selector (ag-searchable-select): searchable list of voters.
- [ ] **Step 5**: Pick a voter identity.
- [ ] **Step 6**: Wait for an open vote (or pick from list if motions are open).
- [ ] **Step 7**: Cast a vote: select option (Pour/Contre/Abstention), confirm.
- [ ] **Step 8**: See confirmation screen.
- [ ] **Step 9**: Verify vote is recorded (refresh page, vote remains submitted).


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
