---
gsd_state_version: 1.0
milestone: v4.0
milestone_name: Clarity & Flow
status: active
stopped_at: null
last_updated: "2026-03-18T10:00:00.000Z"
last_activity: 2026-03-18 — Roadmap defined, phases 25-29 ready for planning
progress:
  total_phases: 5
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-18)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v4.0 Clarity & Flow — Roadmap defined, ready for Phase 25 planning

## Current Position

Phase: 25 (not started — ready for planning)
Plan: —
Status: Roadmap defined
Last activity: 2026-03-18 — Roadmap v4.0 phases 25-29 created

```
Progress: [          ] 0% (0/5 phases)
```

## Performance Metrics

| Metric | Value |
|--------|-------|
| Phases defined | 5 |
| Requirements mapped | 55/55 |
| Plans created | 0 |
| Plans completed | 0 |

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

### Pending Todos

- Plan Phase 25 (PDF Infrastructure Foundation) — run `/gsd:plan-phase 25`
- Phase 27 can run in parallel with Phase 26 if bandwidth allows

### Blockers/Concerns

- PDF.js CVE-2024-4367: must pin >= 4.2.67 and set isEvalSupported: false in Phase 25 — cannot ship viewer without this
- Scope creep risk under "top 1% UI" language — measurable done criteria defined in VIS-08 and Phase 29 success criteria

## Session Continuity

Last session: 2026-03-18
Stopped at: Roadmap created — phases 25-29 defined
Resume file: None
Next action: `/gsd:plan-phase 25`
