# UAT Checklist — OPERATOR

**Login:** operator@ag-vote.local / Operator2026!
**Date:**
**Tester:**

## Steps

- [ ] **Step 1**: Login as operator. Expect: redirect to /dashboard or /hub.
- [ ] **Step 2**: Dashboard: stats load, no console errors.
- [ ] **Step 3**: Navigate to /meetings: meetings list renders. Click "Nouvelle reunion" -> wizard opens at /wizard.
- [ ] **Step 4**: Wizard step 1: fill title + date, click "Suivant".
- [ ] **Step 5**: Wizard step 2: skip or fill members.
- [ ] **Step 6**: Wizard step 3: skip or fill resolutions.
- [ ] **Step 7**: Wizard final: click "Aller au hub" -> redirect to /hub?meeting_id=...
- [ ] **Step 8**: Hub: meeting title visible, status badge shows current state.
- [ ] **Step 9**: Navigate to /members: members table loads. Click "+ Ajouter": form visible.
- [ ] **Step 10**: Add a fake member (Test User / test@example.com) and save. Expect: appears in list.
- [ ] **Step 11**: Navigate to /operator?meeting_id=...: operator console loads with action bar (Actualiser button).
- [ ] **Step 12**: Click "Actualiser" - no JS errors, page state preserved.
- [ ] **Step 13**: Click "Ouvrir la séance" (if in setup mode): meeting transitions to live.
- [ ] **Step 14**: Verify a vote can be launched (look for vote launch button on a motion).
- [ ] **Step 15**: Logout.


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
