# Requirements: AG-VOTE v4.3 "Ground-Up Rebuild"

**Defined:** 2026-03-20
**Core Value:** Every critical page is rebuilt from scratch — HTML+CSS+JS together, top 1% design, zero regressions

## v4.3 Requirements

### Stabilization (FIX)

- [x] **FIX-01**: Fix all v4.2 visual regressions — broken layouts, misaligned elements, missing styles across all pages
- [x] **FIX-02**: Fix all v4.2 JS regressions — broken event handlers, DOM selectors, HTMX targets caused by HTML restructuring

### Ground-Up Page Rebuilds (REB)

- [ ] **REB-01**: Dashboard — complete HTML+CSS rewrite, KPIs wired to backend, session list with live data, horizontal-first layout, JS verified
- [x] **REB-02**: Login — complete HTML+CSS rewrite, auth flow wired, field validation, top 1% entry point
- [x] **REB-03**: Wizard — complete HTML+CSS+JS rewrite, all 4 steps fit viewport, form submissions wired, stepper functional, horizontal fields
- [x] **REB-04**: Operator console — complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional, agenda sidebar, tooltips on all actions
- [ ] **REB-05**: Hub — complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional, checklist with real data
- [ ] **REB-06**: Settings/Admin — complete HTML+CSS+JS rewrite, all settings save correctly, admin KPIs wired, user management functional

### Backend Wiring (WIRE)

- [x] **WIRE-01**: Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets
- [x] **WIRE-02**: SSE connections verified on operator and voter pages — live updates flow correctly
- [x] **WIRE-03**: Form submissions verified — wizard creates sessions, settings save, user CRUD works

## v4.4+ Requirements (Deferred)

- **DEF-01**: Secondary page rebuilds (post-session, analytics, meetings, archives, audit, members, users)
- **DEF-02**: Utility page rebuilds (help, email templates, landing, public/projector, report, trust/validate/doc)
- **DEF-03**: AI-assisted PV minutes generation
- **DEF-04**: ClamAV virus scanning for uploaded PDFs

## Out of Scope

| Feature | Reason |
|---------|--------|
| New functionality | v4.3 is rebuild + stabilization — no new features |
| Framework migration | Vanilla stack is the identity |
| Secondary page rebuilds | Deferred to v4.4 — critical pages first |
| Mobile app | PWA approach maintained |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FIX-01 | Phase 42 — Stabilization | Complete (2026-03-20) |
| FIX-02 | Phase 42 — Stabilization | Complete (2026-03-20) |
| REB-01 | Phase 43 — Dashboard Rebuild | Pending |
| REB-02 | Phase 44 — Login Rebuild | Complete |
| REB-03 | Phase 45 — Wizard Rebuild | Complete |
| REB-04 | Phase 46 — Operator Console Rebuild | Complete |
| REB-05 | Phase 47 — Hub Rebuild | Pending |
| REB-06 | Phase 48 — Settings/Admin Rebuild | Pending |
| WIRE-01 | Phases 43–48 (distributed across all rebuilds) | Complete |
| WIRE-02 | Phase 46 — Operator Console Rebuild | Complete |
| WIRE-03 | Phase 45 — Wizard Rebuild | Complete |

**Coverage:**
- v4.3 requirements: 11 total (FIX:2, REB:6, WIRE:3)
- Mapped to phases: 11
- Unmapped: 0

---
*Requirements defined: 2026-03-20*
*Traceability updated: 2026-03-20*
