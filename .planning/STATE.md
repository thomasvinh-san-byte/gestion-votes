---
gsd_state_version: 1.0
milestone: v4.0
milestone_name: Clarity & Flow
status: executing
stopped_at: Completed 26-guided-ux-components 26-01-PLAN.md
last_updated: "2026-03-18T14:25:52.679Z"
last_activity: 2026-03-18 — Phase 25 Plan 03 complete (PDF UI integration — hub badges, operator upload, voter bottom-sheet)
progress:
  total_phases: 5
  completed_phases: 1
  total_plans: 6
  completed_plans: 5
  percent: 20
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-18)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v4.0 Clarity & Flow — Roadmap defined, ready for Phase 25 planning

## Current Position

Phase: 25 (complete)
Plan: 03 complete — Phase 25 done
Status: In progress
Last activity: 2026-03-18 — Phase 25 Plan 03 complete (PDF UI integration — hub badges, operator upload, voter bottom-sheet)

```
Progress: [==        ] 20% (1/5 phases, 3/3 plans in phase 25)
```

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases defined | 5 |
| Requirements mapped | 55/55 |
| Plans created | 0 |
| Plans completed | 0 |
| Phase 25-pdf-infrastructure-foundation P01 | 5 | 3 tasks | 11 files |
| Phase 25-pdf-infrastructure-foundation P02 | 18min | 2 tasks | 5 files |
| Phase 26-guided-ux-components P01 | 15 | 2 tasks | 7 files |

## Accumulated Context

(Carried from v3.0 — key decisions that affect v4.0)
- Redis SSE fan-out: per-consumer Redis lists for multi-consumer delivery
- Web Components for shared UI: ag-modal, ag-toast, ag-confirm, ag-popover, ag-searchable-select
- IIFE + var pattern for page scripts, one CSS per page
- Dompdf ^3.1 installed for PDF generation
- All pages aligned to wireframe v3.19.2 as of v3.0 (wireframe retired as reference for v4.0)
- Unwired tour stub buttons confirmed on wizard.htmx.html, postsession.htmx.html, members.htmx.html (Phase 26 target)
- STORAGE_PATH env var exists in .env but controllers still hardcode /tmp/ag-vote (Phase 25 target)
- MeetingAttachmentController has no serve/readfile endpoint — PDF serving missing (Phase 25 P0 blocker)

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

v4.0-specific decisions pending (to be logged as phases complete):
- PDF.js prebuilt viewer via native iframe (defer custom toolbar to v5+)
- Driver.js v1.4.0 (MIT) chosen over Shepherd.js (AGPL) and Intro.js (AGPL)
- Copropriété transformation is vocabulary-only: voting_power, BallotsService, tantième CSV alias all preserved
- [Phase 25-01]: AG_UPLOAD_DIR defaults to /var/agvote/uploads (persistent Docker volume), not /tmp — closes P0 ephemeral storage blocker
- [Phase 25-01]: resolution_document serve route uses 'role'=>'public' with explicit auth inside controller — allows vote token holders to access PDFs
- [Phase 25-01]: meeting_id redundant on resolution_documents (avoids JOIN on tenant access-control check) — matches meeting_attachments pattern
- [Phase 25-02]: ag-pdf-viewer uses CSS :host([mode][open]) attribute selectors for transitions — no JS class toggling needed
- [Phase 25-02]: Download button hidden by attribute absence (allow-download), not CSS — voter mode omits attribute entirely (PDF-10)
- [Phase 25-02]: FilePond revert disabled — deletions handled via custom doc card + AgConfirm.ask dialog
- [Phase 25-03]: Voter document consultation uses ag-pdf-viewer mode=sheet — read-only, no download (PDF-10)
- [Phase 25-03]: Hub badge click opens ag-pdf-viewer mode=panel with allow-download (operator/admin context)
- [Phase 25-03]: Operator upload uses native FormData + fetch (no FilePond CDN dependency in live session console)
- [Phase 25-03]: SSE document.added/removed handled inline in vote.js onEvent without full refresh() — lower latency
- [Phase 26-guided-ux-components]: [Phase 26-01]: ag-empty-state uses light DOM (no attachShadow) so design-system.css .empty-state* classes apply directly
- [Phase 26-guided-ux-components]: [Phase 26-01]: EMPTY_SVG duplicated inline in ag-empty-state.js to avoid window.Shared load-order dependency
- [Phase 26-guided-ux-components]: [Phase 26-01]: Shared.emptyState() retained in table-cell (tr/td) contexts (admin.js, audit.js) — ag-empty-state for div containers only

### Pending Todos

- Phase 27 can run in parallel with Phase 26 if bandwidth allows
- Phase 25 complete — next: Phase 26 (guided tour) or Phase 27 (post-session PV)

### Blockers/Concerns

- Scope creep risk under "top 1% UI" language — measurable done criteria defined in VIS-08 and Phase 29 success criteria

## Session Continuity

Last session: 2026-03-18T14:25:52.673Z
Stopped at: Completed 26-guided-ux-components 26-01-PLAN.md
Resume file: None
Next action: Execute Phase 25 Plan 03 (hub/operator/voter page integrations for PDF viewer)
