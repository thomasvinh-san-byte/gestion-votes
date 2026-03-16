# AG-VOTE Roadmap

## Milestones

- ✅ **v1.1 through v1.5** — Phases 1-3 (shipped)
- ✅ **v2.0 UI Redesign (Acte Officiel)** — Phases 4-15 (shipped 2026-03-16)
- 🚧 **v3.0 Session Lifecycle** — Phases 16-22 (active)

## Phases

### v3.0 Session Lifecycle (Active)

- [x] **Phase 16: Data Foundation** — Wizard creates real session data; hub loads real state (completed 2026-03-16)
- [ ] **Phase 17: Demo Data Removal** — Dashboard and audit show real data; all demo fallbacks removed
- [ ] **Phase 18: SSE Infrastructure** — Multi-consumer safe SSE; nginx and PHP-FPM configured for long-lived connections
- [ ] **Phase 19: Operator Console Wiring** — Operator loads real meeting; attendance and motions tabs driven by API
- [ ] **Phase 20: Live Vote Flow** — End-to-end vote cycle; operator tally updates in real-time via SSE
- [ ] **Phase 21: Post-Session & PV** — Stepper completes all 4 steps; PV PDF generated and meeting archived
- [ ] **Phase 22: Final Audit** — Zero DEMO_ constants; every API call has loading/error/empty states

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
  4. The audit page shows real audit events from the database; the DEMO_EVENTS fallback is gone and an error state is shown when the backend is unreachable
**Plans:** 2 plans
Plans:
- [ ] 17-01-PLAN.md — Replace dashboard demo fallback with error/empty states
- [ ] 17-02-PLAN.md — Remove DEMO_EVENTS from audit.js, fix API integration

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
**Plans**: TBD

### Phase 19: Operator Console Wiring
**Goal**: The operator console loads real meeting data and all tabs are driven by live API responses
**Depends on**: Phase 16 (members and motions must exist), Phase 17 (no demo fallbacks masking wiring bugs), Phase 18 (SSE must be consumer-safe before connecting operator SSE)
**Requirements**: OPR-01, OPR-02, OPR-03, OPR-04
**Success Criteria** (what must be TRUE):
  1. Navigating to the operator console with a valid meeting_id URL parameter loads the correct meeting title and status without any hardcoded values
  2. The attendance tab shows the registered participants for the meeting loaded from the API, with quorum calculation reflecting the actual count
  3. The motions tab lists the resolutions for the meeting loaded from the API, in the correct order
  4. The SSE connection is established only after MeetingContext emits a change event with a valid meeting_id, not on bare page load
**Plans**: TBD

### Phase 20: Live Vote Flow
**Goal**: The full vote cycle works end-to-end — operator opens a motion, voters cast ballots, results are calculated, and the operator sees the tally update in real-time
**Depends on**: Phase 18 (SSE infrastructure safe for concurrent consumers), Phase 19 (operator context wired)
**Requirements**: VOT-01, VOT-02, VOT-03, VOT-04
**Success Criteria** (what must be TRUE):
  1. An operator opens a motion and the voter view immediately shows the motion as active without a page reload
  2. A voter submits a ballot and receives a confirmation; the ballot is recorded in the database and cannot be submitted a second time
  3. An operator closes a motion and the vote counts are calculated and displayed in the operator console
  4. The full meeting state machine transitions — draft → scheduled → frozen → live → closed → validated — execute without errors and each transition is reflected in the hub checklist status
**Plans**: TBD

### Phase 21: Post-Session & PV
**Goal**: The post-session stepper completes all four steps and produces a valid PV PDF with the meeting archived
**Depends on**: Phase 20 (votes must have been cast and results must exist for verification step to show real data)
**Requirements**: PST-01, PST-02, PST-03, PST-04
**Success Criteria** (what must be TRUE):
  1. Post-session step 1 displays the verified vote results table for all motions with correct ballot counts loaded from the API (not from a broken endpoint alias)
  2. Post-session step 2 triggers consolidation of official results and transitions the meeting from closed to validated; the hub checklist reflects the validated state
  3. Post-session step 3 generates a downloadable PV PDF via Dompdf containing the meeting details and vote results
  4. Post-session step 4 completes the archive action with no broken links; the export_correspondance link is absent from the UI
**Plans**: TBD

### Phase 22: Final Audit
**Goal**: The codebase contains zero demo constants and every API call site has correct loading, error, and empty states — the full lifecycle can be run end-to-end without encountering any placeholder data
**Depends on**: Phases 16-21 (all wiring must be complete before the audit can confirm coverage)
**Requirements**: CLN-01, CLN-02
**Success Criteria** (what must be TRUE):
  1. A codebase search for DEMO_ returns zero results outside of test fixtures and comments
  2. Every page that makes an API call displays a loading indicator while the request is in flight, a meaningful error message when the request fails, and an appropriate empty state when the response contains no data
**Plans**: TBD

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
| 17. Demo Data Removal | v3.0 | 0/2 | Not started | - |
| 18. SSE Infrastructure | v3.0 | 0/TBD | Not started | - |
| 19. Operator Console Wiring | v3.0 | 0/TBD | Not started | - |
| 20. Live Vote Flow | v3.0 | 0/TBD | Not started | - |
| 21. Post-Session & PV | v3.0 | 0/TBD | Not started | - |
| 22. Final Audit | v3.0 | 0/TBD | Not started | - |
