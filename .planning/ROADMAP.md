# AG-VOTE Roadmap

## Milestones

- ✅ **v1.1 through v1.5** — Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign (Acte Officiel)** — Phases 4-15 (shipped 2026-03-16)
- 🚧 **v3.0 Session Lifecycle** — Phases 16-23 (active)

## Phases

### v3.0 Session Lifecycle (Active)

- [x] **Phase 16: Data Foundation** — Wizard creates real session data; hub loads real state (completed 2026-03-16)
- [x] **Phase 17: Demo Data Removal** — Dashboard and audit show real data; all demo fallbacks removed (completed 2026-03-16)
- [x] **Phase 18: SSE Infrastructure** — Multi-consumer safe SSE; nginx and PHP-FPM configured for long-lived connections (completed 2026-03-16)
- [x] **Phase 19: Operator Console Wiring** — Operator loads real meeting; attendance and motions tabs driven by API (completed 2026-03-16)
- [x] **Phase 20: Live Vote Flow** — End-to-end vote cycle; operator tally updates in real-time via SSE (completed 2026-03-17, human-verify deferred)
- [x] **Phase 20.1: Refonte UI** — Wireframe alignment, reduced cognitive load, FOUC fix (INSERTED) (completed 2026-03-17)
- [x] **Phase 20.2: Deep UI Wireframe Alignment** — Align all component CSS with wireframe v3.19.2 specs (INSERTED) (completed 2026-03-17)
- [x] **Phase 20.3: Page Layout Wireframe Alignment** — Page layouts aligned with wireframe density and composition (INSERTED) (completed 2026-03-18, design issues deferred to 20.4)
- [x] **Phase 20.4: Design System Enforcement** — Systematic design reconstruction: audit each page against wireframe, clean contradictory CSS, rebuild pages to unified design language, fix JS bugs (INSERTED) (completed 2026-03-18)
- [x] **Phase 21: Post-Session & PV** — Stepper completes all 4 steps; PV PDF generated and meeting archived (completed 2026-03-18)
- [x] **Phase 22: Final Audit** — Zero SEED_ constants; every API call has loading/error/empty states (completed 2026-03-18)
- [x] **Phase 23: Integration Wiring Fixes** — Fix hub→operator meeting_id propagation and frozen→live vote transition (gap closure) (completed 2026-03-18)

<details>
<summary>✅ v2.0 UI Redesign (Phases 4-15) — SHIPPED 2026-03-16</summary>

- [x] Phase 4: Design Tokens & Theme (2/2 plans) — completed 2026-03-12
- [x] Phase 5: Shared Components (4/4 plans) — completed 2026-03-12
- [x] Phase 6: Layout & Navigation (3/3 plans) — completed 2026-03-12
- [x] Phase 7: Dashboard & Sessions (3/3 plans) — completed 2026-03-13
- [x] Phase 8: Session Wizard & Hub (3/3 plans) — completed 2026-03-13
- [x] Phase 9: Operator Console (3/3 plans) — completed 2026-03-13
- [x] Phase 10: Live Session Views (2/2 plans) — completed 2026-03-13
- [x] Phase 11: Post-Session & Records (3/3 plans) — completed 2026-03-15
- [x] Phase 12: Analytics & User Management (2/2 plans) — completed 2026-03-15
- [x] Phase 13: Settings & Help (2/2 plans) — completed 2026-03-15
- [x] Phase 14: Integration Bug Fixes + API Wiring (3/3 plans, gap closure) — completed 2026-03-13
- [x] Phase 15: Operator Wiring, Verification & Tech Debt (6/6 plans, gap closure) — completed 2026-03-16

**Full details:** `.planning/milestones/v2.0-ROADMAP.md`

</details>

<details>
<summary>✅ Previous Milestones (v1.1 - v1.5) — Phases 1-3</summary>

### v1.5 — E2E Coverage Expansion & Release (COMPLETE)

- Phase 1: Operator & Dashboard E2E -- done
- Phase 2: Report, Validate & Archives E2E -- done
- Phase 3: Version Bump & Release -- done

### v1.4 — Test Coverage & Final Polish (COMPLETE)

3 phases: 100% controller tests, Permissions-Policy header, dead code audit.

### v1.3 — Code Quality & Frontend Cleanup (COMPLETE)

3 phases: unused vars fixed (142->0), innerHTML triaged safe, CI lint ratchet.

### v1.2 — Security & Resilience Hardening (COMPLETE)

4 phases: tenant isolation, rate limiting, PWA hardening, audit verification.

### v1.1 — Post-Audit Hardening (COMPLETE)

6 phases: E2E suite, CI pipeline, CDN hardening, app shell audit, error handling, accessibility.

</details>

## Phase Details

### Phase 16: Data Foundation
**Goal**: The wizard creates a complete session record in the database and the hub displays the real session state
**Depends on**: Nothing (first phase of v3.0; v2.0 UI shell is in place)
**Requirements**: WIZ-01, WIZ-02, WIZ-03, HUB-01, HUB-02
**Success Criteria** (what must be TRUE):
  1. An operator completes the wizard and the resulting session appears in the hub with the correct title, type, location, and scheduled date loaded from the database
  2. Members selected in wizard step 2 are visible as registered participants when the hub attendance checklist is opened
  3. Resolutions entered in wizard step 3 appear as motions on the hub checklist
  4. When the backend is unreachable, the hub displays an explicit error state instead of demo data or a blank screen
**Plans:** 2/2 plans complete
Plans:
- [x] 16-01-PLAN.md — Extend createMeeting() with atomic member + motion persistence
- [x] 16-02-PLAN.md — Wire wizard redirect with counts and hub real data + error handling

### Phase 17: Demo Data Removal
**Goal**: The dashboard and audit pages show only real data from the database; every demo fallback is eliminated and replaced with a visible error state
**Depends on**: Phase 16 (real session data must exist to verify dashboard counts are non-zero)
**Requirements**: HUB-03, HUB-04, CLN-03
**Success Criteria** (what must be TRUE):
  1. The dashboard session count KPIs reflect the actual number of sessions in the database, not hardcoded demo values
  2. When no sessions exist, the dashboard shows an empty state rather than demo cards
  3. When the backend is unreachable, the dashboard shows an explicit error state instead of fake session data
  4. The audit page shows real audit events from the database; the SEED_EVENTS fallback is gone and an error state is shown when the backend is unreachable
**Plans:** 2/2 plans complete
Plans:
- [ ] 17-01-PLAN.md — Replace dashboard demo fallback with error/empty states
- [ ] 17-02-PLAN.md — Remove SEED_EVENTS from audit.js, fix API integration

### Phase 18: SSE Infrastructure
**Goal**: The SSE pipeline is safe for concurrent consumers and the server configuration supports long-lived SSE connections without resource exhaustion
**Depends on**: Phase 16 (meeting_id must exist for events.php to poll the correct queue)
**Requirements**: SSE-01, SSE-02, SSE-03, SSE-04
**Research flag**: Multi-consumer strategy (per-role Redis keys vs. Redis Pub/Sub blocking subscribe) should be spiked during plan-phase before committing to implementation
**Success Criteria** (what must be TRUE):
  1. When the operator console and voter view connect to the same meeting's SSE stream simultaneously, both clients receive all events — no event is delivered to only one consumer and silently dropped for the other
  2. The nginx configuration includes a dedicated location block for events.php with fastcgi_buffering disabled, so SSE events are pushed to the browser without batch delay
  3. PHP-FPM pool sizing for long-lived SSE connections is documented in the deploy configuration with a concrete max_children recommendation
  4. After a voter casts a ballot, the operator console tally count updates within 3 seconds via SSE without a manual page refresh
**Plans:** 1 plan
Plans:
- [ ] 23-01-PLAN.md — Hub meeting_id propagation + frozen-to-live endpoint branching

### Phase 19: Operator Console Wiring
**Goal**: The operator console loads real meeting data and all tabs are driven by live API responses
**Depends on**: Phase 16 (members and motions must exist), Phase 17 (no demo fallbacks masking wiring bugs), Phase 18 (SSE must be consumer-safe before connecting operator SSE)
**Requirements**: OPR-01, OPR-02, OPR-03, OPR-04
**Success Criteria** (what must be TRUE):
  1. Navigating to the operator console with a valid meeting_id URL parameter loads the correct meeting title and status without any hardcoded values
  2. The attendance tab shows the registered participants for the meeting loaded from the API, with quorum calculation reflecting the actual count
  3. The motions tab lists the resolutions for the meeting loaded from the API, in the correct order
  4. The SSE connection is established only after MeetingContext emits a change event with a valid meeting_id, not on bare page load
**Plans:** 1/1 plans complete
Plans:
- [ ] 19-01-PLAN.md — MeetingContext integration, SSE lifecycle wiring, loading/empty/error states for attendance and motions tabs

### Phase 20: Live Vote Flow
**Goal**: The full vote cycle works end-to-end — operator opens a motion, voters cast ballots, results are calculated, and the operator sees the tally update in real-time
**Depends on**: Phase 18 (SSE infrastructure safe for concurrent consumers), Phase 19 (operator context wired)
**Requirements**: VOT-01, VOT-02, VOT-03, VOT-04
**Success Criteria** (what must be TRUE):
  1. An operator opens a motion and the voter view immediately shows the motion as active without a page reload
  2. A voter submits a ballot and receives a confirmation; the ballot is recorded in the database and cannot be submitted a second time
  3. An operator closes a motion and the vote counts are calculated and displayed in the operator console
  4. The full meeting state machine transitions — draft → scheduled → frozen → live → closed → validated — execute without errors and each transition is reflected in the hub checklist status
**Plans:** 2/2 plans complete
Plans:
- [x] 20-01-PLAN.md — Backend broadcast fix, verdict override endpoint, operator frozen-to-live modal, hide breakdown during open vote, projection participation-only display
- [x] 20-02-PLAN.md — Unit tests for new backend behavior + human verification of complete vote cycle (human-verify deferred)

### Phase 20.1: Refonte UI alignement wireframe et reduction charge mentale (INSERTED)

**Goal:** Aligner toutes les pages avec la composition wireframe v3.19.2 pour reduire la charge mentale — grille 2x2 votant, projection gradient Fraunces, dashboard lanceur d'actions, modales operateur simplifiees, FOUC elimine sur les 21 pages
**Requirements**: N/A (inserted urgent phase — UI/UX alignment, no formal requirement IDs)
**Depends on:** Phase 20
**Plans:** 4/4 plans complete

Plans:
- [ ] 20.1-01-PLAN.md — Vote page: grille 2x2, confirmation inline coloree, header minimal
- [ ] 20.1-02-PLAN.md — Projection: gradient, titre Fraunces, quorum en header, clamp responsive
- [ ] 20.1-03-PLAN.md — Dashboard lanceur d'actions + operateur modales simplifiees
- [ ] 20.1-04-PLAN.md — FOUC fix sur les 17 pages restantes + verification humaine

### Phase 20.2: Deep UI Wireframe Alignment (INSERTED)

**Goal:** Align all component CSS (buttons, cards, forms, tables, tags, modals, toasts, tooltips) with wireframe v3.19.2 specifications. Add missing components (scroll-to-top, confirm dialog, tooltip system, theme toggle switch). Fix button gradients, card hover lifts, form field borders, table striping, and all 25 gap categories from the wireframe comparison.
**Requirements**: N/A (inserted phase — CSS alignment with wireframe, no formal requirement IDs)
**Depends on:** Phase 20.1
**Plans:** 4/4 plans complete

Plans:
- [ ] 20.2-01-PLAN.md — Buttons: gradient, shadow, hover lift, active press + Cards: transition, hover, title uppercase
- [ ] 20.2-02-PLAN.md — Forms: 1px border, surface-raised, uppercase labels + Tables: striped, sticky headers + Tags: 5px radius
- [ ] 20.2-03-PLAN.md — Modals: backdrop blur, bouncy animation + Toasts: bottom-right, exit animation + Interactive rows
- [ ] 20.2-04-PLAN.md — New components (scroll-top, tooltip, confirm dialog, theme toggle, kbd hints) + scrollbar + print

### Phase 20.3: Page Layout Wireframe Alignment (INSERTED)

**Goal:** Reprendre le layout de chaque page pour correspondre à la composition du wireframe v3.19.2 — densité, placement des sections, espacement entre cartes/blocs — afin que les pages principales tiennent sur un écran sans scroll inutile. Les éléments ajoutés après le wireframe sont conservés mais intégrés dans la grille wireframe.
**Requirements**: N/A (inserted phase — layout density alignment with wireframe, no formal requirement IDs)
**Depends on:** Phase 20.2
**Plans:** 3/4 plans executed

Plans:
- [ ] 20.3-01-PLAN.md — Operator split panel layout (tabs to 2-column with 200px sidebar)
- [ ] 20.3-02-PLAN.md — Settings sidebar nav + wizard pinned footer nav
- [ ] 20.3-03-PLAN.md — Dashboard zero-scroll density + hub compact + table row 8px padding
- [ ] 20.3-04-PLAN.md — Remaining pages density pass + human verification

### Phase 20.4: Design System Enforcement (INSERTED)

**Goal:** Every page matches the wireframe's design language: consistent spacing, color hierarchy, typography, and component usage. JS bugs fixed along the way. All 21 pages verified at 1920x1080.
**Requirements**: N/A (inserted design quality phase — no formal requirement IDs)
**Depends on:** Phase 20.3
**Plans:** 12/12 plans complete

Plans:
- [ ] 20.4-01-PLAN.md — CSS contradiction cleanup (pages.css duplicates, grid gaps, wizard re-definitions)
- [ ] 20.4-02-PLAN.md — Dashboard reconstruction (PageDashboard wireframe alignment)
- [ ] 20.4-03-PLAN.md — Meetings reconstruction (PageSeances wireframe alignment)
- [ ] 20.4-04-PLAN.md — Wizard reconstruction + import button bug fix (PageWizard)
- [ ] 20.4-05-PLAN.md — Operator console reconstruction (PageOperator wireframe alignment)
- [ ] 20.4-06-PLAN.md — Vote + public/projection pages (PageVotant + PageEcran)
- [ ] 20.4-07-PLAN.md — Hub reconstruction (PageHub wireframe alignment)
- [ ] 20.4-08-PLAN.md — Post-session + settings pages (PagePostSession + PageParametres)
- [ ] 20.4-09-PLAN.md — Secondary batch 1: admin, members, analytics, archives
- [ ] 20.4-10-PLAN.md — Secondary batch 2: report, validate, audit, trust
- [ ] 20.4-11-PLAN.md — Secondary batch 3: users, help, docs, email-templates
- [ ] 20.4-12-PLAN.md — Human verification of all 21 pages at 1920x1080

### Phase 21: Post-Session & PV
**Goal**: The post-session stepper completes all four steps and produces a valid PV PDF with the meeting archived
**Depends on**: Phase 20 (votes must have been cast and results must exist for verification step to show real data)
**Requirements**: PST-01, PST-02, PST-03, PST-04
**Success Criteria** (what must be TRUE):
  1. Post-session step 1 displays the verified vote results table for all motions with correct ballot counts loaded from the API (not from a broken endpoint alias)
  2. Post-session step 2 triggers consolidation of official results and transitions the meeting from closed to validated; the hub checklist reflects the validated state
  3. Post-session step 3 generates a downloadable PV PDF via Dompdf containing the meeting details and vote results
  4. Post-session step 4 completes the archive action with no broken links; the export_correspondance link is absent from the UI
**Plans:** 1/1 plans complete
Plans:
- [x] 21-01-PLAN.md — Wire all 4 steps to correct endpoints, install Dompdf, remove dead correspondance link

### Phase 22: Final Audit
**Goal**: The codebase contains zero demo constants and every API call site has correct loading, error, and empty states — the full lifecycle can be run end-to-end without encountering any placeholder data
**Depends on**: Phases 16-21 (all wiring must be complete before the audit can confirm coverage)
**Requirements**: CLN-01, CLN-02
**Success Criteria** (what must be TRUE):
  1. A codebase search for seed constants returns zero results outside of test fixtures and comments
  2. Every page that makes an API call displays a loading indicator while the request is in flight, a meaningful error message when the request fails, and an appropriate empty state when the response contains no data
**Plans:** 2/2 plans complete
Plans:
- [x] 22-01-PLAN.md — Total DEMO_ eradication (config, scripts, docs, planning files)
- [x] 22-02-PLAN.md — Loading/error/empty states audit and fixes




### Phase 23: Integration Wiring Fixes
**Goal**: Fix 2 cross-phase integration breaks found by milestone audit: (1) hub action buttons propagate meeting_id to operator page, (2) frozen→live meeting transition fires when operator opens first vote via operator_open_vote.php
**Depends on**: Phase 22 (audit identified these gaps)
**Requirements**: HUB-01, VOT-01, VOT-04
**Gap Closure**: Closes gaps from v3.0 milestone audit
**Success Criteria** (what must be TRUE):
  1. Clicking an action button on the hub page navigates to the operator console with the meeting pre-selected (meeting_id in URL or sessionStorage)
  2. Opening the first vote on a frozen meeting transitions the meeting status to live AND broadcasts meetingStatusChanged via SSE
  3. The PHP shim file public/api/v1/operator_open_vote.php exists and routes to OperatorController::openVote
**Plans:** 1/1 plans complete
Plans:
- [ ] 23-01-PLAN.md — Hub meeting_id propagation + frozen-to-live endpoint branching

### Future Milestones (Backlog)

**v4.0+ Feature Ideas:**
- **Suivi budget & documents PDF pour votants** — Budget tracking with PDF document distribution to voters
- **Votes pour collectivités territoriales** — Voting mode for syndicats, communes, conseils de communes, départements — assemblées générales oui, mais pas copropriété (pas de tantièmes/millièmes)

## Progress

| Phase | Milestone | Plans | Status | Completed |
|-------|-----------|-------|--------|-----------|
| 4. Design Tokens & Theme | v2.0 | 2/2 | Complete | 2026-03-12 |
| 5. Shared Components | v2.0 | 4/4 | Complete | 2026-03-12 |
| 6. Layout & Navigation | v2.0 | 3/3 | Complete | 2026-03-12 |
| 7. Dashboard & Sessions | v2.0 | 3/3 | Complete | 2026-03-13 |
| 8. Session Wizard & Hub | v2.0 | 3/3 | Complete | 2026-03-13 |
| 9. Operator Console | v2.0 | 3/3 | Complete | 2026-03-13 |
| 10. Live Session Views | v2.0 | 2/2 | Complete | 2026-03-13 |
| 11. Post-Session & Records | v2.0 | 3/3 | Complete | 2026-03-15 |
| 12. Analytics & User Management | v2.0 | 2/2 | Complete | 2026-03-15 |
| 13. Settings & Help | v2.0 | 2/2 | Complete | 2026-03-15 |
| 14. Integration + API Wiring | v2.0 | 3/3 | Complete | 2026-03-13 |
| 15. Operator Wiring + Tech Debt | v2.0 | 6/6 | Complete | 2026-03-16 |
| 16. Data Foundation | v3.0 | 2/2 | Complete | 2026-03-16 |
| 17. Demo Data Removal | v3.0 | 2/2 | Complete | 2026-03-16 |
| 18. SSE Infrastructure | v3.0 | 1/1 | Complete | 2026-03-16 |
| 19. Operator Console Wiring | v3.0 | 1/1 | Complete | 2026-03-16 |
| 20. Live Vote Flow | v3.0 | 2/2 | Complete | 2026-03-17 |
| 20.1. Refonte UI | v3.0 | 4/4 | Complete | 2026-03-17 |
| 20.2. Deep UI Wireframe Alignment | v3.0 | 4/4 | Complete | 2026-03-17 |
| 20.3. Page Layout Wireframe Alignment | v3.0 | 4/4 | Complete | 2026-03-18 |
| 20.4. Design System Enforcement | v3.0 | 12/12 | Complete | 2026-03-18 |
| 21. Post-Session & PV | v3.0 | 1/1 | Complete | 2026-03-18 |
| 22. Final Audit | v3.0 | 2/2 | Complete | 2026-03-18 |
| 23. Integration Wiring Fixes | 1/1 | Complete   | 2026-03-18 | - |
