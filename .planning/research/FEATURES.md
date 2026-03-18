# Feature Research

**Domain:** General Assembly voting platform — session lifecycle wiring (v3.0)
**Researched:** 2026-03-16
**Confidence:** HIGH — based on direct codebase inspection + industry research

## Context

This is NOT a greenfield feature research. The v2.0 UI is fully built. The goal is to wire
the session lifecycle end-to-end: real API data, no demo fallbacks, SSE real-time updates.
All features below describe what must be **connected**, not what must be designed.

The codebase already has:
- All UI pages (hub, wizard, operator console, voter view, post-session, etc.)
- Backend services: VoteEngine, MeetingWorkflowService, OfficialResultsService, MeetingReportService
- ~150 API endpoints, many already wired from Phase 14
- SSE infrastructure: EventBroadcaster → Redis → events.php → event-stream.js

What's missing: consistent end-to-end data flow with zero demo fallbacks.

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels broken or fake.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Wizard → real DB session | Creating a session must persist and redirect to hub with real data | LOW | wizard.js already POSTs to `/api/v1/meetings`; hub.js calls `wizard_status` but falls back to demo data when session not found or no meeting_id in URL |
| Hub loads real session state | Hub checklist must reflect actual backend state, not hardcoded demo | LOW | hub.js has SEED_SESSION/SEED_FILES fallback at lines 301-430; `wizard_status` endpoint exists and is called; need to remove fallback, show error on failure |
| Dashboard shows real counts | KPI tiles (séances, en cours, convocations, PV) must come from DB | LOW | dashboard.js calls `/api/v1/dashboard` with fallback at line 127; fallback fires on any API error — should show error state instead |
| Operator console loads real meeting | Meeting must be selected and data loaded from DB, not mock | MEDIUM | operator-tabs.js loads via `loadMeetingContext()` from URL param; this is wired but depends on meeting_id being passed correctly from hub |
| Meeting state transitions work | draft→scheduled→frozen→live→closed→validated state machine must be reliable | MEDIUM | MeetingWorkflowService fully implemented; operator-exec.js calls `/api/v1/meeting_transition.php`; needs end-to-end testing |
| Live vote tally via SSE | Operator KPI strip updates vote counts without refresh when ballots are cast | MEDIUM | EventBroadcaster + Redis + events.php + event-stream.js are all implemented; operator-realtime.js connects SSE; needs integration test across the full path |
| Motions open/close via operator | Operator can open a resolution, voters see it, operator closes it | MEDIUM | operator-motions.js calls `motions_open.php` and `motions_close.php`; voter's vote.js polls `current_motion.php`; all endpoints exist |
| Vote casting works for voter | Voter on vote.htmx.html submits ballot, gets confirmation | MEDIUM | vote.js calls `ballots_cast.php`; server returns idempotency-safe response; voter UI fully built |
| Post-session verification shows results | Step 1 of post-session stepper shows results table with real motion outcomes | MEDIUM | postsession.js calls `/api/v1/meeting_motions.php` — this endpoint does NOT exist (only `motions_for_meeting.php` exists); fallback to `meeting_summary.php` provides partial data only |
| Validation state transition works | Step 2 of post-session moves meeting to `validated` status | LOW | postsession.js calls `/api/v1/meeting_transition.php` with `to_status: 'validated'`; endpoint and workflow service exist |
| PV (procès-verbal) generation | Step 3 of post-session generates the official report PDF | MEDIUM | `MeetingReportService::renderHtml()` exists; `MeetingReportsController::generatePdf()` exists with Dompdf; endpoint `/api/v1/meeting_generate_report_pdf.php` exists; needs functional test |
| Archive meeting | Final step archives the session and locks it | LOW | postsession.js calls `meetings_archive.php`; endpoint exists; `MeetingWorkflowService` blocks backward transitions from archived |
| Quorum status visible | Operator and hub must show whether quorum is met | LOW | `quorum_status.php`, `quorum_card.php` exist; `QuorumEngine.php` fully implemented; `ag-quorum-bar` web component exists |
| Attendance registration | Operator marks members present/remote before freezing session | MEDIUM | operator-attendance.js fully wired to `attendances_upsert.php`, `attendances_bulk.php`; import CSV/XLSX endpoints exist |
| Proxy management | Operator registers proxy delegations before session starts | MEDIUM | `proxies.php`, `proxies_upsert.php`, `proxies_delete.php` all exist; proxies shown in operator attendance tab |

### Differentiators (Competitive Advantage)

Features that set the product apart. Not required for lifecycle completeness, but valuable.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| SSE with Redis + file fallback | Real-time without WebSocket infrastructure; works on shared hosting | MEDIUM | Already architected; EventBroadcaster auto-falls back to `/tmp/agvote-sse-{id}.json`; needs load testing confirmation |
| Consolidated official results | `OfficialResultsService::consolidateMeeting()` snapshots results at close | MEDIUM | `meeting_consolidate.php` endpoint exists; postsession should call consolidate before validation; currently not explicitly triggered in postsession.js flow |
| Draft PV watermark | PDF carries "BROUILLON" watermark until validated; MeetingReportsController adds it | LOW | Implemented in `generatePdf()` lines 389-400; behavior depends on meeting status at time of generation |
| Weighted voting power | Members can have fractional voting power (copropriété use case) | MEDIUM | `voting_power` field exists in attendance data; VoteEngine handles it; UI reflects it |
| Paper ballot redemption | Hybrid meetings: operator enters physical votes manually | MEDIUM | `paper_ballot_redeem.php` and `manual_vote.php` endpoints exist; operator-motions.js has manual vote UI |
| Device trust system | Voter devices tracked; operator can block misbehaving devices | HIGH | `devices_list.php`, `device_block.php`, `device_heartbeat.php` exist; trust.js and operator-tabs.js implement the UI |
| Multi-tenant isolation | Single installation serves multiple organizations | MEDIUM | Enforced at repository layer via `tenant_id`; AuthMiddleware provides `DEFAULT_TENANT_ID`; no feature work needed |
| Audit trail with hash verification | Every action logged with HMAC chain; audit_verify.php checks integrity | HIGH | Implemented; audit.js has demo fallback (SEED_EVENTS) that needs removal |
| eIDAS-ready signatory workflow | PV step 3 records signataires for electronic signature compliance | MEDIUM | UI in postsession.js step 3 shows signataire/observations fields; backend storage TBD |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Silent demo fallbacks | Appears to work during development | Masks real integration failures in production; operators see fake data without knowing it | Remove all SEED_SESSION/SEED_EVENTS fallbacks; replace with clear error states showing what API call failed |
| Polling as primary real-time | Simpler than SSE to implement | Adds unnecessary DB load; defeats the purpose of SSE infrastructure already built | SSE is already implemented; use it as primary, polling only as last resort |
| Framework migration to wire the frontend | Might seem to simplify reactivity | Destroys existing vanilla JS conventions, breaks all 29 page modules, scope explosion | Use existing `window.api()` + IIFE pattern; Web Components for interactive UI pieces |
| Bulk-delete or cascade-delete on sessions | Operators request convenience | Destroys audit trail; French copropriété law requires PV preservation | Soft-archive only; `archived_immutable` guard already in MeetingWorkflowService |
| Auto-advance state machine | Automatically move draft→scheduled when motions added | Removes deliberate operator control; state transitions must be intentional operator actions | Keep explicit transition buttons with pre-condition checks via `issuesBeforeTransition()` |
| Export correspondance | postsession.js references `/api/v1/export_correspondance.php` | Endpoint does not exist anywhere in the codebase; not a standard French AG requirement | Remove the export link from post-session UI, or implement as a separate future feature |

---

## Feature Dependencies

```
[Wizard POST creates meeting]
    └──requires──> [meetings.php POST endpoint]  (EXISTS)
    └──produces──> [meeting_id in URL → hub]

[Hub loads session state]
    └──requires──> [wizard_status?meeting_id=X]  (EXISTS, returns real data)
    └──requires──> [meeting_id propagated via MeetingContext]

[Operator console loads meeting]
    └──requires──> [meeting_id from URL param]
    └──requires──> [meetings_index.php or dashboard.php for selection list]

[Attendance registration]
    └──requires──> [meeting in scheduled state]
    └──precedes──> [frozen state transition]

[frozen → live transition]
    └──requires──> [attendance recorded]  (checked by MeetingWorkflowService)
    └──warning if──> [quorum not met]  (non-blocking)

[Motions open/close cycle]
    └──requires──> [meeting in live state]
    └──triggers──> [SSE vote.cast events to operator]

[Vote casting by voter]
    └──requires──> [current open motion via current_motion.php]
    └──requires──> [voter authenticated via token/session]
    └──triggers──> [SSE vote.cast → operator tally update]

[Post-session verification]
    └──requires──> [meeting in closed state]
    └──requires──> [meeting_motions.php or motions_for_meeting.php with results]
    └──BLOCKED: meeting_motions.php does not exist; must alias or create it]

[PV generation]
    └──requires──> [meeting in validated state]
    └──requires──> [MeetingReportService::renderHtml()]  (EXISTS)
    └──requires──> [Dompdf for PDF]  (EXISTS in MeetingReportsController)
    └──recommended──> [consolidate called before validate]

[Archive]
    └──requires──> [validated state]
    └──locks──> [all further transitions (archived_immutable)]

[SSE real-time tally]
    └──requires──> [Redis OR file fallback in /tmp/]
    └──requires──> [EventBroadcaster::voteCast() called after ballot insert]  (EXISTS in flow)
    └──requires──> [event-stream.js EventSource open on operator page]  (EXISTS)

[Dashboard real counts]
    └──requires──> [/api/v1/dashboard.php returns real DB data]  (CONFIRMED wired in Phase 14)
    └──blocked if──> [fallback fires silently on any error]
```

### Dependency Notes

- **post-session verification requires meeting_motions.php:** postsession.js calls `/api/v1/meeting_motions.php` which does not exist. The existing `motions_for_meeting.php` serves the same data. Either create a shim file or update postsession.js to use `motions_for_meeting.php`.
- **consolidate before validate:** `OfficialResultsService::consolidateMeeting()` snapshots final results to a read-only store. postsession.js does not explicitly call `meeting_consolidate.php` before validation. This should be done at close or as a pre-step before the validation button is enabled.
- **MeetingContext propagation:** `operator-tabs.js` reads `meeting_id` from URL param, not from `MeetingContext.get()`. The hub must navigate to operator with `?meeting_id=X` in the URL. If this link is broken, the operator console loads with no meeting selected.
- **export_correspondance.php is missing:** postsession.js line 317 references this non-existent endpoint. The export button will 404. Either remove it from the UI or implement the endpoint.
- **invitations_stats.php exists but may not return complete data:** operator-tabs.js line 2799 has `// Stats endpoint may not exist yet — show placeholders`. The file exists in `/api/v1/` but its completeness is unverified.

---

## MVP Definition

For this milestone (v3.0), MVP means zero demo fallbacks and a working end-to-end session.

### Launch With (v3.0 core)

- [x] Wizard POST creates real session → redirects to hub with meeting_id — **already partially wired in Phase 14**
- [ ] Hub removes SEED_SESSION fallback → shows error state if API fails
- [ ] Dashboard removes showFallback() → shows error state if API fails
- [ ] audit.js removes SEED_EVENTS fallback → shows error state if API fails
- [ ] meeting_motions.php created (shim to motions_for_meeting or new endpoint) — **postsession is blocked without this**
- [ ] export_correspondance.php: remove link from postsession.js UI or create stub
- [ ] Operator console receives live SSE vote tallies → end-to-end test from ballot cast to KPI update
- [ ] Post-session stepper completes: verify → validate → generate PV → send/archive
- [ ] PV PDF generation functional (Dompdf path confirmed working)
- [ ] State machine: all 6 transitions testable (draft→scheduled→frozen→live→closed→validated)

### Add After Validation (v3.x)

- [ ] invitations_stats.php completeness verified, placeholder removed from operator-tabs.js
- [ ] Consolidate explicitly triggered in post-session flow before validation
- [ ] eIDAS signatory workflow (step 3 PV storage in DB, not just UI)
- [ ] SSE file fallback tested with Redis disabled

### Future Consideration (v4+)

- [ ] Electronic signature upload/validation (explicitly deferred in PROJECT.md)
- [ ] export_correspondance.php feature if needed by users
- [ ] Second-call quorum rules (quorum threshold_call2 field exists in quorum policies already)

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Remove hub demo fallback | HIGH | LOW | P1 |
| Remove dashboard demo fallback | HIGH | LOW | P1 |
| Remove audit demo fallback | MEDIUM | LOW | P1 |
| Create meeting_motions.php shim | HIGH | LOW | P1 |
| Remove export_correspondance link | MEDIUM | LOW | P1 |
| End-to-end SSE vote tally | HIGH | MEDIUM | P1 |
| Post-session stepper full flow | HIGH | MEDIUM | P1 |
| PV PDF generation test | HIGH | LOW | P1 |
| State machine all transitions | HIGH | MEDIUM | P1 |
| Operator → meeting_id propagation from hub | HIGH | LOW | P1 |
| Consolidate before validate | MEDIUM | LOW | P2 |
| invitations_stats.php placeholder removal | LOW | LOW | P2 |
| eIDAS signatory storage | MEDIUM | HIGH | P3 |
| export_correspondance implementation | LOW | MEDIUM | P3 |

**Priority key:**
- P1: Must have for v3.0 — blocks "zero demo data" goal
- P2: Should have, clean up after core lifecycle works
- P3: Nice to have, future milestone

---

## Competitor Feature Analysis

| Feature | GetQuorum / Kuorum | EasyQuorum (Wolters Kluwer) | AG-VOTE Approach |
|---------|-------------------|----------------------------|-----------------|
| Session creation wizard | Multi-step web form | Template-based | 4-step accordion wizard, exists |
| Real-time quorum display | Live dashboard | Dashboard panel | ag-quorum-bar + SSE; infrastructure exists |
| Proxy management | Import + manual | Import + manual | import CSV/XLSX + manual; exists |
| PV auto-generation | Automatic at close | Template engine | MeetingReportService + Dompdf; exists |
| Electronic PV signature | Integrated eIDAS | DocuSign integration | Deferred; UI placeholder only |
| Audit trail | Immutable log | Read-only log | HMAC-chained audit log; exists |
| PDF export | Built-in | Built-in | Dompdf; exists |
| Self-hosted deployment | SaaS only | SaaS only | Docker container; differentiator |

---

## Critical Gaps Identified by Codebase Inspection

These are not design gaps but **integration gaps** that will cause 404s or broken flows:

1. **`/api/v1/meeting_motions.php` does not exist.** postsession.js step 1 is blocked. The equivalent data comes from `motions_for_meeting.php`. Create a shim file or rename the call. Cost: 5 minutes.

2. **`/api/v1/export_correspondance.php` does not exist.** postsession.js step 4 export button will 404. Either remove the UI link or create the endpoint. No backend implementation exists.

3. **Three demo fallbacks must be removed:** hub.js (SEED_SESSION + SEED_FILES), dashboard.js (showFallback with hardcoded 2026-02-XX data), audit.js (SEED_EVENTS). These mask real failures.

4. **MeetingContext → operator console URL chain.** The hub's "Go to operator" button must pass `?meeting_id=X` to `operator.htmx.html`. If this link is missing or the param is dropped, the operator console starts with no meeting selected.

5. **Consolidate not triggered in post-session flow.** `OfficialResultsService::consolidateMeeting()` creates the immutable result snapshot that the closed→validated transition checks (`consolidated < closed` check in MeetingWorkflowService line 125). If consolidate was never called, validation will fail. postsession.js must call `meeting_consolidate.php` before or during step 2.

---

## Sources

- Codebase inspection: `/public/assets/js/pages/*.js`, `/app/Services/*.php`, `/app/Controller/*.php`, `/public/api/v1/*.php`
- Architecture: `.planning/codebase/ARCHITECTURE.md`
- Project context: `.planning/PROJECT.md`
- Industry: [Online Voting Process Steps — Voteer](https://www.voteer.com/blog/online-voting-process-steps)
- Industry: [GetQuorum Features](https://www.getquorum.com/features/)
- French regulatory: [PV AG Copropriété — Service Public](https://www.service-public.gouv.fr/particuliers/vosdroits/F2636)
- French regulatory: [EasyQuorum PV generation — Wolters Kluwer](https://www.wolterskluwer.com/en/solutions/easyquorum/features/meeting-minutes)
- SSE patterns: [SSE vs WebSockets vs Long Polling 2025 — DEV Community](https://dev.to/haraf/server-sent-events-sse-vs-websockets-vs-long-polling-whats-best-in-2025-5ep8)
- Quorum management: [Proxy voting for quorum — AssociationVoting](https://www.associationvoting.com/how-to-use-proxy-voting-to-meet-your-quorum-requirements-without-compromise/)

---
*Feature research for: AG-VOTE v3.0 session lifecycle wiring*
*Researched: 2026-03-16*
