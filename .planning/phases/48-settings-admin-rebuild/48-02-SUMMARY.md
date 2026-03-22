---
phase: 48-settings-admin-rebuild
plan: 02
subsystem: api
tags: [php, sqlite, javascript, settings, kpi, admin, user-management]

# Dependency graph
requires:
  - phase: 48-01
    provides: settings.htmx.html + admin.htmx.html rebuilt HTML structure with new KPI IDs
  - phase: 43-dashboard-rebuild
    provides: kpi-card/kpi-card--N CSS pattern referenced in admin.css
provides:
  - public/api/v1/admin_settings.php — settings persistence API endpoint
  - app/Controller/SettingsController.php — settings controller (list, update, template stubs)
  - app/Repository/SettingsRepository.php — tenant_settings key/value CRUD
  - database/migrations/20260322_tenant_settings.sql — tenant_settings table DDL
  - public/assets/js/pages/admin.js — rebuilt JS with 4-endpoint KPI fetch + user CRUD only
affects:
  - settings.js calls admin_settings.php for list/update/template/smtp actions — now resolved
  - admin.js populates adminKpiMembers/Sessions/Votes/Active — now wired to real endpoints

# Tech tracking
tech-stack:
  added: []
  patterns:
    - SettingsRepository ensureTable() pattern — SQLite table creation in constructor, no migration runner
    - loadAdminKpis() Promise.all pattern — 3 parallel API fetches for KPI row
    - Null-guard pattern — all parse-time getElementById+addEventListener wrapped in if(el) guards

key-files:
  created:
    - public/api/v1/admin_settings.php
    - app/Controller/SettingsController.php
    - app/Repository/SettingsRepository.php
    - database/migrations/20260322_tenant_settings.sql
  modified:
    - app/Core/Providers/RepositoryFactory.php
    - public/assets/js/pages/admin.js

key-decisions:
  - "SettingsRepository.ensureTable() runs in constructor — SQLite CREATE TABLE IF NOT EXISTS, no external migration runner needed"
  - "get_template/save_template/test_smtp/reset_templates actions are stubs — real email templates remain at /api/v1/email_templates"
  - "loadAdminKpis() uses Promise.all with 3 parallel fetches: members + meetings + admin_users"
  - "adminKpiVotes uses motions_count per meeting if available, falls back to closed+validated+archived meeting count"
  - "admin.js filterAndRenderUsers() applies both text search AND role filter — original only applied text search"
  - "btnCreateUser toggles .admin-create-form hidden attr when fields empty, submits when fields filled"

patterns-established:
  - "Settings API proxy pattern: admin_settings.php delegates to SettingsController::settings() via handle()"
  - "KPI multi-fetch: Promise.all([members, meetings, users]) with independent null-checks per result"
  - "admin.js null-guard: var el = getElementById(); if (el) { el.addEventListener(...); }"

requirements-completed: [REB-06, WIRE-01]

# Metrics
duration: 25min
completed: 2026-03-22
---

# Phase 48 Plan 02: Settings + Admin Backend Wiring Summary

**admin_settings.php backend endpoint with SQLite tenant_settings table, admin.js rebuilt for 4-KPI multi-endpoint fetch and user CRUD only (1449 lines to 570, -61%)**

## Performance

- **Duration:** 25 min
- **Started:** 2026-03-22T17:39:32Z
- **Completed:** 2026-03-22T18:04:00Z
- **Tasks:** 2 of 3 (Task 3 is a browser verification checkpoint)
- **Files modified:** 6

## Accomplishments
- Created admin_settings.php API endpoint with SettingsController + SettingsRepository — settings now persist in SQLite tenant_settings table
- Rewrote admin.js: replaced initDashboard/initUserKpis with loadAdminKpis() using Promise.all for 3 endpoints (members/meetings/admin_users), removed all dead sections (meeting roles, vote policies, permissions matrix, state machine, system status, demo reset, guide drawer)
- All parse-time getElementById+addEventListener calls null-guarded — no console errors on admin page load

## Task Commits

Each task was committed atomically:

1. **Task 1: Create admin_settings.php backend endpoint with database table** - `bd1aadd` (feat)
2. **Task 2: Update admin.js KPI logic and trim unused sections** - `dd17c00` (feat)

## Files Created/Modified
- `database/migrations/20260322_tenant_settings.sql` — CREATE TABLE tenant_settings with UNIQUE(tenant_id, key)
- `app/Repository/SettingsRepository.php` — listByTenant, upsert, get methods + ensureTable() in constructor
- `app/Controller/SettingsController.php` — list, update, get_template, save_template, test_smtp, reset_templates actions
- `app/Core/Providers/RepositoryFactory.php` — added settings() accessor + SettingsRepository use statement
- `public/api/v1/admin_settings.php` — API proxy file
- `public/assets/js/pages/admin.js` — rebuilt: 570 lines (was 1449, -61%)

## Decisions Made
- SettingsRepository creates tenant_settings table in constructor (ensureTable) rather than requiring a migration runner — suitable for SQLite single-file DB
- Email template actions (get_template, save_template, test_smtp, reset_templates) return stubs — real email templates live at /api/v1/email_templates, no data loss
- admin.js filterAndRenderUsers() now applies role filter in addition to text search (the original only text-searched, ignoring the filterRole dropdown)
- btnCreateUser toggles inline form visibility when form fields are empty, submits when they have values — handles the new admin.htmx.html layout where the create form is always in the DOM

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] filterRole dropdown was not wired to filter function**
- **Found during:** Task 2 (admin.js rewrite)
- **Issue:** Original admin.js wired filterRole to `loadUsers` (full API refetch) but the new filterAndRenderUsers() applies both text search and role filter together for better UX
- **Fix:** filterRoleEl.addEventListener('change', filterAndRenderUsers) instead of loadUsers — filters client-side without refetch
- **Files modified:** public/assets/js/pages/admin.js
- **Committed in:** dd17c00 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 2 - UX completeness)
**Impact on plan:** Filter improvement makes role filter work correctly with search simultaneously. No scope creep.

## Issues Encountered
- None

## Next Phase Readiness
- admin_settings.php endpoint ready — settings.js calls will succeed
- admin.js KPIs will populate from real API data on page load
- Task 3 (browser verification) is the next step: open settings.htmx.html and admin.htmx.html, verify tabs, save persistence, KPI display, user CRUD

---
*Phase: 48-settings-admin-rebuild*
*Completed: 2026-03-22*
