---
gsd_state_version: 1.0
milestone: v7.0
milestone_name: Production Essentials
status: defining_requirements
stopped_at: null
last_updated: "2026-04-01T14:00:00.000Z"
last_activity: "2026-04-01 — Milestone v7.0 started: Production Essentials"
progress:
  total_phases: 0
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-01)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v7.0 Production Essentials — PV officiel, email queue worker, setup initial, reset password

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-04-01 — Milestone v7.0 started

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

- [v6.1 roadmap]: 2 phases derived from 5 requirements — operator infrastructure first (65), then voter access (66)
- [v6.1 roadmap]: ATTACH-05 (serve endpoint) grouped with operator phase because it must exist before voters can access files
- [v6.1 roadmap]: Reuse ResolutionDocumentController::serve() dual-auth pattern (session OR vote token)
- [v6.1 roadmap]: Reuse ag-pdf-viewer (inline/sheet/panel modes) for voter document viewing
- [v6.1 roadmap]: Mirror wizard step 2 FilePond pattern in step 1 for meeting attachments
- [v6.1 roadmap]: Mirror hub resolution doc badges pattern for meeting attachments section
- [Phase 65-01]: Reuse doc_serve rate limit bucket for meeting_attachment_serve (same use case, 120/60s)
- [Phase 65-01]: serve() uses meetingAttachment() repo path (AG_UPLOAD_DIR/meetings/{meeting_id}/) — not resolutions/
- [Phase 65-02]: Wizard uses FilePond with name='file' for meeting attachments matching MeetingAttachmentController
- [Phase 65-02]: Operator console uses native file input (no FilePond) for attachment upload, mirroring addDocUploadToMotionCard pattern
- [Phase 65-02]: No SSE/EventBroadcaster for attachment changes — pre-session documents do not need real-time broadcast
- [Phase 66-01]: Reuse doc_serve rate limit bucket for meeting_attachments_public (same use case, 120/60s)
- [Phase 66-01]: stored_name excluded from listPublic response — only id, original_name, file_size, created_at exposed
- [Phase 66-01]: getElementById('meetingAttachViewer') used — never querySelector to avoid collision with resoPdfViewer
- [Phase 66-01]: vote page viewer has no allow-download — voter is read-only (PDF-10)
- [Phase 66-01]: loadMeetingAttachments is meeting-scoped, called on meeting context change not per-motion refresh
- [Phase 66-voter-document-access]: Task 3 visual verification deferred by user — all code shipped, verification can be done manually when a meeting with attachments is available

### Existing Infrastructure

- MeetingAttachmentController already exists with upload/list/delete (operator-only)
- ResolutionDocumentController::serve() has dual auth (session OR vote token) — pattern to reuse
- ag-pdf-viewer Web Component exists with inline/sheet/panel modes
- Wizard step 2 has FilePond for resolution documents — mirror in step 1
- Hub shows resolution doc badges — mirror for meeting attachments
- Vote page has "Consulter le document" button — mirror for meeting attachments

### Known Tech Debt Carried Forward

- Controller coverage at 64.6% (3 exit()-based controllers are structural ceiling)
- CI e2e job runs chromium only; mobile-chrome/tablet are local-only
- Migration idempotency check is local-only, not CI-gated

### Pending Todos

None.

### Blockers/Concerns

None.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260331-7s9 | Remove voting weight/ponderation from UI and sample CSV | 2026-03-31 | 7cb5378 | [260331-7s9-remove-voting-weight-ponderation-from-ui](./quick/260331-7s9-remove-voting-weight-ponderation-from-ui/) |
| 260331-854 | Wizard field layout and time input modernization | 2026-03-31 | e655a46 | [260331-854-wizard-field-layout-and-time-input-moder](./quick/260331-854-wizard-field-layout-and-time-input-moder/) |
| 260331-8wf | Modernize project README.md | 2026-03-31 | 868c43a | [260331-8wf-modernize-project-readme-md](./quick/260331-8wf-modernize-project-readme-md/) |
| 260331-901 | Modernize all documentation files | 2026-03-31 | c4e68b1 | [260331-901-modernize-all-docs-rich-french-no-em-das](./quick/260331-901-modernize-all-docs-rich-french-no-em-das/) |
| 260331-ez9 | Fix admin login — double rate limit on auth_login | 2026-03-31 | c3b1add2 | [260331-ez9-fix-admin-login-failure](./quick/260331-ez9-fix-admin-login-failure/) |
| 260331-ffw | Full project audit — gitignore, env, CSS tokens, git hygiene | 2026-03-31 | 4625f6ca | [260331-ffw-full-project-audit-bugs-cleanup-config-i](./quick/260331-ffw-full-project-audit-bugs-cleanup-config-i/) |
| 260331-fya | Second pass audit — remaining CSS tokens, route cleanup | 2026-03-31 | 00fe92f5 | [260331-fya-second-pass-audit-remaining-issues](./quick/260331-fya-second-pass-audit-remaining-issues/) |
| 260331-g8a | Critical path audit — API functional, operator null guards | 2026-03-31 | 3d504fe2 | [260331-g8a-critical-path-audit-functional-visual-on](./quick/260331-g8a-critical-path-audit-functional-visual-on/) |
| 260331-gj6 | Low-priority fixes — null guards, login CSS classes, wizard responsive | 2026-03-31 | 11f18eb4 | [260331-gj6-fix-remaining-low-priority-items-null-gu](./quick/260331-gj6-fix-remaining-low-priority-items-null-gu/) |

## Session Continuity

Last session: 2026-04-01T09:01:46.682Z
Stopped at: Completed 66-01-PLAN.md — Task 3 visual verification deferred by user
Resume file: None
