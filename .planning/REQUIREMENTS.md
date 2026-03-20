# Requirements: AG-VOTE v4.3 "Ground-Up Rebuild"

**Defined:** 2026-03-20
**Core Value:** Every critical page is rebuilt from scratch — HTML+CSS+JS together, top 1% design, zero regressions

## v4.3 Requirements

### Stabilization (FIX)

- [ ] **FIX-01**: Fix all v4.2 visual regressions — broken layouts, misaligned elements, missing styles across all pages
- [ ] **FIX-02**: Fix all v4.2 JS regressions — broken event handlers, DOM selectors, HTMX targets caused by HTML restructuring

### Ground-Up Page Rebuilds (REB)

- [ ] **REB-01**: Dashboard — complete HTML+CSS rewrite, KPIs wired to backend, session list with live data, horizontal-first layout, JS verified
- [ ] **REB-02**: Login — complete HTML+CSS rewrite, auth flow wired, field validation, top 1% entry point
- [ ] **REB-03**: Wizard — complete HTML+CSS+JS rewrite, all 4 steps fit viewport, form submissions wired, stepper functional, horizontal fields
- [ ] **REB-04**: Operator console — complete HTML+CSS+JS rewrite, SSE wired, live vote panel functional, agenda sidebar, tooltips on all actions
- [ ] **REB-05**: Hub — complete HTML+CSS+JS rewrite, session lifecycle wired, quorum bar functional, checklist with real data
- [ ] **REB-06**: Settings/Admin — complete HTML+CSS+JS rewrite, all settings save correctly, admin KPIs wired, user management functional

### Backend Wiring (WIRE)

- [ ] **WIRE-01**: Every rebuilt page has verified API connections — no dead endpoints, no mock data, no broken HTMX targets
- [ ] **WIRE-02**: SSE connections verified on operator and voter pages — live updates flow correctly
- [ ] **WIRE-03**: Form submissions verified — wizard creates sessions, settings save, user CRUD works

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
| FIX-01, FIX-02 | TBD | Pending |
| REB-01 through REB-06 | TBD | Pending |
| WIRE-01 through WIRE-03 | TBD | Pending |

**Coverage:**
- v4.3 requirements: 11 total (FIX:2, REB:6, WIRE:3)
- Mapped to phases: 0
- Unmapped: 11

---
*Requirements defined: 2026-03-20*
