---
gsd_state_version: 1.0
milestone: v4.3
milestone_name: Ground-Up Rebuild
status: completed
stopped_at: Completed 48-02 (Phase 48 complete — v4.3 Ground-Up Rebuild milestone complete)
last_updated: "2026-03-22T17:46:51.657Z"
last_activity: 2026-03-22 — Completed 47-03 wizard_status API extension + hub.js motions_for_meeting wiring (23cfa56, d1dd434)
progress:
  total_phases: 7
  completed_phases: 7
  total_plans: 14
  completed_plans: 14
  percent: 99
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-20)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v4.3 Ground-Up Rebuild — Phase 47: Hub Rebuild (in progress)

## Current Position

Phase: 47 of 48 (Hub Rebuild)
Plan: 03 of 03 complete
Status: Phase 47 complete
Last activity: 2026-03-22 — Completed 47-03 wizard_status API extension + hub.js motions_for_meeting wiring (23cfa56, d1dd434)

Progress: [██████████] 99%

## Performance Metrics

**Velocity:**
- Total plans completed: 2
- Average duration: 7 min
- Total execution time: 13 min

## Accumulated Context

### Decisions

- **Dashboard HTML taches removed** — #taches ID removed from new HTML; JS null-guards it at line 159
- **Urgent banner hidden by default** — starts with hidden attr; Plan 02 adds JS to set hidden=false when live meeting found
- **KPI value no color modifiers** — color comes from parent kpi-card--N variant via CSS, not modifier classes on value
- **Trust page fix targeted** — only four lines changed across two files; no other code touched to avoid new regressions
- **Ground-up approach** — no more patches; each page gets a complete rewrite of HTML+CSS+JS together
- **JS-first reading** — read existing JS before touching HTML to understand DOM dependencies
- **Backend wiring distributed** — WIRE-01/02/03 verified inside each page rebuild phase, not a separate phase
- **One page = one testable commit** — no broken intermediate states; browser test before marking done
- **Stabilization first** — FIX-01/02 regressions cleared in Phase 42 before any rebuild work begins
- [Phase 43-dashboard-rebuild]: Urgent banner hidden-by-default: HTML hidden attr, JS sets hidden=false on live meeting reveal
- [Phase 43-dashboard-rebuild]: Live meeting banner href targets specific meeting (operator.htmx.html?meeting_id=...)
- [Phase 44]: Password floating label uses JS-assisted .has-value on .field-group (label sibling of .field-input-wrap, not input)
- [Phase 44]: Trust signal and footer moved outside .login-card into <main> — card focus-within glow stays clean
- [Phase 44]: #demoPanel is static hidden div — Plan 02 will removeAttribute('hidden') instead of card.appendChild()
- [Phase Phase 44]: login.js: updateHasValue() applied to both fields for consistency, even though email can use CSS :not(:placeholder-shown) alone
- [Phase Phase 44]: login.js: showDemoHint() targets #demoPanel via getElementById + removeAttribute('hidden') instead of card.appendChild()
- [Phase 44-login-rebuild]: Checkpoint approved: login page fully verified in browser — floating labels, auth flow, dark mode, demo panel all working
- [Phase 45-wizard-rebuild]: wizard.htmx.html: all DOM IDs preserved, step0 active class, no inline display:none on steps, wiz-member-add-row for horizontal member form
- [Phase 45-wizard-rebuild]: wizard.css: wizSlideIn/wizSlideOut translateX keyframes, no overflow-y:auto anywhere, tokens only for dark mode parity
- [Phase 45-wizard-rebuild]: showStep() uses skipAnimation=true on init and draft restore to prevent slide flash
- [Phase 45-wizard-rebuild]: Error banners cleared on every showStep() navigation; populated from errors[] array in each step's validation function
- [Phase 45-wizard-rebuild]: Checkpoint approved — full wizard flow browser-verified: slide transitions, error banners, session creation, dark mode, draft persistence
- [Phase 46-operator-console-rebuild]: All partials inlined: liveTabs bridge div kept data-loaded=true for JS compat; no lazy loading in new HTML
- [Phase 46-operator-console-rebuild]: SSE indicator uses vivid fixed colors (#22c55e / #ef4444 / #f59e0b) intentionally overriding token system — dark mode override keeps them vivid
- [Phase 46-operator-console-rebuild]: op-body is a 280px+1fr CSS grid inside app-shell; app-shell uses 6-row grid override for [data-page-role=operator]
- [Phase Phase 46-operator-console-rebuild]: operator-realtime.js, operator-motions.js, operator-attendance.js, operator-speech.js needed no changes — all DOM IDs preserved in new HTML
- [Phase 46-operator-console-rebuild]: Browser checkpoint approved — full operator console flow verified: SSE, vote lifecycle, agenda sidebar, dark mode, responsive collapse; Phase 46 complete
- [Phase 47-hub-rebuild]: hub-hero card replaces hub-identity: same icon/badges/meta but adds inline CTA buttons (hubMainBtn + hubOperatorBtn)
- [Phase 47-hub-rebuild]: 3-item checklist (convocation/quorum/agenda) replaces 6-step stepper — simpler prerequisite model, hidden attr for JS-toggled sections
- [Phase 47-hub-rebuild]: renderChecklist() updates pre-rendered DOM elements in-place via classList/textContent (not innerHTML rewrite)
- [Phase 47-hub-rebuild]: WIRE-01 fixed: invitations_send_bulk replaces dead /meetings/{id}/convocations endpoint in hub.js
- [Phase 47-hub-rebuild]: Browser checkpoint approved: hub page verified functional with real data, no console errors, WIRE-01 confirmed fixed
- [Phase 47-hub-rebuild]: Motions loaded via separate motions_for_meeting fetch — avoids bloating the lightweight wizard_status polling endpoint
- [Phase 47-hub-rebuild]: scheduled_at formatted in JS with toLocaleDateString fr-FR — raw ISO timestamp kept in API for future callers
- [Phase 47-hub-rebuild]: type_label derived client-side from meeting_type via replace+uppercase — no server-side label table needed
- [Phase 48-settings-admin-rebuild]: settings hidden attr on inactive panels (not display:none), CNIL cards use :has + .selected, admin KPI IDs renamed to semantic names, parse-time JS errors expected until Plan 02
- [Phase 48-settings-admin-rebuild]: SettingsRepository.ensureTable() creates tenant_settings in constructor — no migration runner needed for SQLite
- [Phase 48-settings-admin-rebuild]: admin.js loadAdminKpis() uses Promise.all for 3 parallel fetches: members + meetings + admin_users
- [Phase Phase 48-settings-admin-rebuild]: Browser verification checkpoint approved: settings tabs switch, settings persist, admin KPIs show real counts, user CRUD functional, no console errors on either page

### Pending Todos

None

### Blockers/Concerns

None — v4.2 trust page regressions resolved in 42-01; clean baseline established for Phases 43-48

## Session Continuity

Last session: 2026-03-22T17:46:47.176Z
Stopped at: Completed 48-02 (Phase 48 complete — v4.3 Ground-Up Rebuild milestone complete)
Resume file: None
Next action: /gsd:execute-phase 48 (next phase)
