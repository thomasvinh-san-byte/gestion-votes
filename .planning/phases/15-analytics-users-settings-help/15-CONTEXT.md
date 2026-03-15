---
phase: 15-analytics-users-settings-help
type: context
requirements: [STAT-01, STAT-02, STAT-03, USR-01, USR-02, USR-03, SET-01, SET-02, SET-03, SET-04, FAQ-01, FAQ-02]
---

# Phase 15: Analytics, Users, Settings & Help — Context

**Goal**: Complete the remaining 12 pending requirements across 4 areas: Statistics, Users, Settings, and Help/FAQ.

## Decisions Made

### Area 1: Statistics (STAT-01/02/03) — Restyle Existing
The analytics page (`analytics.htmx.html`) already has:
- 4 KPI cards with trends (Séances, Participation moy., Résolutions votées, Taux d'adoption)
- Donut chart for Pour/Contre/Abstention distribution
- Participation line chart and sessions-by-month bar chart
- Export PDF button (uses `window.print()`)
- Period selector, year filter, tabs, chart export PNGs

**Decision**: Restyle to match wireframe exactly. The analytics page is functionally complete but needs wireframe alignment check. Mark STAT-01/02/03 as complete after verification.

**PDF Export**: Use `window.print()` with print CSS (existing pattern — already has `btnExportPdf`).

### Area 2: Users (USR-01/02/03) — Rework Table
The admin page users tab has:
- Role info panel (USR-01 ✅ — roles-explainer with Admin, Opérateur, Auditeur, Observateur)
- Inline create user form with name/email/password/role
- Search + filter by role
- Users list as `<div class="users-list">` (needs conversion to table)

**Decision**: Rework users section:
- Convert div-based users list to proper `<table>` with columns: avatar, name, email, role tag, status, last login, edit button (USR-02)
- Add pagination controls below table (USR-03)
- Keep inline create form (already works, no modal needed)
- JS: Update `admin.js` to render table rows with avatar initials, last_login formatting, pagination state

### Area 3: Settings (SET-01/02/03/04) — Add Missing Controls
Settings subtabs already exist: Règles de vote, Clés de répartition, Sécurité, Courrier, Général, Accessibilité.

What's missing per requirement:
- **SET-01** (Règles): Double auth toggle ✅, quorum settings ✅ — verify alignment only
- **SET-02** (Communication): Support email ✅ (in General tab), email templates ✅. **Missing**: notification preferences (add toggles for email/push per event type)
- **SET-03** (Sécurité): 2FA toggle ✅. **Missing**: session timeout setting (add numeric input)
- **SET-04** (Accessibilité): Compliance report ✅. **Missing**: text size selector (A/A+/A++), high contrast toggle

**Decision**: Add the missing UI controls to existing subtabs. Light implementation — just the UI controls with localStorage persistence for accessibility settings.

### Area 4: Help/FAQ (FAQ-01/02) — Add Missing Tour Cards
Help page has:
- Accordion FAQ with category filter and search (FAQ-01 ✅)
- 7 tour cards: Séances, Membres, Opérateur, Vote, Post-séance, Audit, Administration
- **Missing 3 tour cards**: Dashboard, Hub (Fiche séance), Statistiques

**Decision**: Add the 3 missing tour launcher cards to the tour grid in help.htmx.html.

## Files to Modify

### Plan 01: Statistics Restyle
- `public/analytics.htmx.html` — Minor wireframe alignment tweaks
- `public/assets/css/analytics.css` — Ensure KPI card styling matches wireframe tokens
- `public/assets/js/pages/analytics-dashboard.js` — Wire up print CSS for PDF export

### Plan 02: Users Table Rework
- `public/admin.htmx.html` — Convert users div list to table with avatar/status/last-login/edit columns, add pagination
- `public/assets/css/admin.css` — Avatar initials circle, pagination styles
- `public/assets/js/pages/admin.js` — Render table rows, pagination logic, edit modal handler

### Plan 03: Settings Missing Controls
- `public/admin.htmx.html` — Add notification prefs to Courrier tab, session timeout to Sécurité, text size + high contrast to Accessibilité
- `public/assets/css/admin.css` — Styles for new controls
- `public/assets/js/pages/admin.js` — localStorage for a11y prefs, apply text size/contrast on load

### Plan 04: Help Tour Cards + Requirements Update
- `public/help.htmx.html` — Add 3 missing tour cards (Dashboard, Hub, Statistiques)
- `.planning/REQUIREMENTS.md` — Mark all 12 requirements as complete
