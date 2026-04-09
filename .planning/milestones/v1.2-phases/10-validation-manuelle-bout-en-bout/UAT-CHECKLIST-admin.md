# UAT Checklist — ADMIN

**Login:** admin@ag-vote.local / Admin2026!
**Date:**
**Tester:**

## Steps

- [ ] **Step 1**: Login: Open /login, enter credentials, submit. Expect: redirect to /dashboard or /admin (no error toast).
- [ ] **Step 2**: Dashboard loads: KPI tiles render with values (not "—"). No JS console errors (open DevTools).
- [ ] **Step 3**: Navigate to /settings: page loads, tabs visible (Regles, Communication, etc).
- [ ] **Step 4**: Settings - change a non-destructive value (e.g., toggle a boolean), click "Enregistrer". Expect: success feedback.
- [ ] **Step 5**: Navigate to /users: users table renders, role chips show counts.
- [ ] **Step 6**: Users - click "+ Ajouter un utilisateur": modal opens. Cancel without saving.
- [ ] **Step 7**: Navigate to /admin: admin dashboard loads, all sections visible.
- [ ] **Step 8**: Navigate to /audit: audit table renders, search input works.
- [ ] **Step 9**: Audit - type "test" in search field: list filters or shows "no results".
- [ ] **Step 10**: Logout: click logout button (sidebar or header). Expect: redirect to /login.
- [ ] **Step 11**: Verify post-logout: try /dashboard manually -> should redirect to /login.


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
