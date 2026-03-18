---
gsd_state_version: 1.0
milestone: v4.0
milestone_name: Clarity & Flow
status: executing
stopped_at: Completed 29-04-PLAN.md
last_updated: "2026-03-18T18:16:56.189Z"
last_activity: 2026-03-18 — Phase 25 Plan 03 complete (PDF UI integration — hub badges, operator upload, voter bottom-sheet)
progress:
  total_phases: 5
  completed_phases: 4
  total_plans: 18
  completed_plans: 15
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
| Phase 26-guided-ux-components P02 | 3 | 2 tasks | 3 files |
| Phase 26-guided-ux-components P03 | 4 | 2 tasks | 9 files |
| Phase 27-copropriete-transformation P01 | 5 | 2 tasks | 10 files |
| Phase 27-copropriete-transformation P02 | 15 | 2 tasks | 11 files |
| Phase 28-wizard-session-hub-ux-overhaul P02 | 2 | 2 tasks | 2 files |
| Phase 28-wizard-session-hub-ux-overhaul P01 | 5 | 2 tasks | 2 files |
| Phase 28-wizard-session-hub-ux-overhaul P03 | 3 | 2 tasks | 2 files |
| Phase 29-operator-console-voter-view-visual-polish P01 | 2 | 1 tasks | 1 files |
| Phase 29-operator-console-voter-view-visual-polish P03 | 3 | 2 tasks | 3 files |
| Phase 29-operator-console-voter-view-visual-polish P02 | 8 | 2 tasks | 5 files |
| Phase 29-operator-console-voter-view-visual-polish P04 | 2 | 2 tasks | 3 files |

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
- [Phase 26-guided-ux-components]: STATUS_CTA map is the canonical source of CTA labels and hrefs per session lifecycle state on dashboard
- [Phase 26-guided-ux-components]: Dashboard shows all 8 sessions sorted by STATUS_PRIORITY (live first) replacing upcoming-only filter
- [Phase 26-guided-ux-components]: Help panels are user-initiated click popovers — no localStorage dismissal needed (GUX-08 satisfied by design)
- [Phase 26-guided-ux-components]: ag-tooltip text cleared to empty string via closest('ag-tooltip') when button enables — no stale tooltip on active buttons
- [Phase 27-copropriete-transformation]: VoteEngine uses >= threshold; tie test uses threshold 0.501 to model strict majority correctly
- [Phase 27-copropriete-transformation]: Copropriete transformation is vocabulary-only: voting_power, BallotsService, tantieme CSV alias all preserved — value='tantiemes' in select options unchanged
- [Phase 27-copropriete-transformation]: Root ag_vote_wireframe.html is stale copy — vocabulary cleaned to match docs/wireframe; future: remove or symlink
- [Phase 27-copropriete-transformation]: Phase 27 vocabulary transformation complete: zero copropri matches outside ImportService.php CSV aliases and LOT- seed data
- [Phase 28-wizard-session-hub-ux-overhaul]: [Phase 28-02]: ag-quorum-bar wired via setAttribute on current/required/total/label attributes — reactively re-renders on change; quorumRequired defaults to Math.ceil(memberCount*0.5)+1 from quorum_policy presence
- [Phase 28-wizard-session-hub-ux-overhaul]: [Phase 28-02]: hubConvocationSection hidden when convocationsSent=true OR memberCount=0; motions array from data.resolutions first, data.motions fallback
- [Phase 28-01]: Steps 2 (Membres) and 3 (Résolutions) are optional — validateStep n=1/n=2 return true; step 4 shows warnings but does not block creation
- [Phase 28-01]: resoKey (Clé de répartition) removed from wizard — copropriété vocabulary; key hard-coded to Charges générales in setupAddReso()
- [Phase 28-01]: MOTION_TEMPLATES are hardcoded JS objects (3 templates) — not DB-stored (v5+ deferred)
- [Phase 28-wizard-session-hub-ux-overhaul]: [Phase 28-03]: .wiz-step-body scoped field overrides prevent wizard CSS from bleeding to other pages
- [Phase 28-wizard-session-hub-ux-overhaul]: [Phase 28-03]: hub-identity-date uses var(--font-display) Fraunces for semantic heading hierarchy in hub banner
- [Phase 28-wizard-session-hub-ux-overhaul]: [Phase 28-03]: wizFadeIn 150ms translateY(4px) animation on .wiz-step — satisfies WIZ-05 step transition requirement
- [Phase 29-01]: CSS @layer cascade: base (sections 1-4) < components (sections 5-10) < v4 (new Phase 29 additions) < unlayered page CSS — zero regression, page CSS wins automatically
- [Phase 29-01]: color-mix() tints use white in light mode, var(--color-surface) in dark mode — correct dark rendering; 10 token families added to :root and [data-theme=dark] in same commit
- [Phase 29-operator-console-voter-view-visual-polish]: [Phase 29-03]: castVoteOptimistic() replaces confirmation overlay flow — buttons wire directly to optimistic pattern; #confirmationOverlay HTML kept in DOM as accessibility fallback
- [Phase 29-operator-console-voter-view-visual-polish]: [Phase 29-03]: data-vote-state attribute selector pattern for CSS-driven state visibility — waiting/voting/confirmed states driven by setVoteAppState() via refresh() and castVoteOptimistic()
- [Phase 29-02]: setSseIndicator 'reconnecting' fires on onDisconnect; 'offline' fires after 5s timeout if still disconnected — avoids flicker on brief drops
- [Phase 29-02]: Guidance panels go in operator-exec.html partial (exec view), not main HTML — consistent with the exec view architecture
- [Phase 29-04]: [29-04]: resultCardsContainer primary display, hidden table preserved for backward compat
- [Phase 29-04]: [29-04]: CSS --bar-pct inline style sets CSS custom property (VIS-08 compliant), not raw style value

### Pending Todos

- Phase 27 can run in parallel with Phase 26 if bandwidth allows
- Phase 25 complete — next: Phase 26 (guided tour) or Phase 27 (post-session PV)

### Blockers/Concerns

- Scope creep risk under "top 1% UI" language — measurable done criteria defined in VIS-08 and Phase 29 success criteria

## Session Continuity

Last session: 2026-03-18T18:16:56.185Z
Stopped at: Completed 29-04-PLAN.md
Resume file: None
Next action: Execute Phase 25 Plan 03 (hub/operator/voter page integrations for PDF viewer)
