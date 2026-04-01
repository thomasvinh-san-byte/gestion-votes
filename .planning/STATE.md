---
gsd_state_version: 1.0
milestone: v6.1
milestone_name: "PDF & Preparation de Seance"
status: ready_to_plan
stopped_at: null
last_updated: "2026-04-01T13:00:00.000Z"
last_activity: "2026-04-01 — Roadmap created for v6.1 (2 phases, 5 requirements)"
progress:
  total_phases: 2
  completed_phases: 0
  total_plans: 2
  completed_plans: 0
  percent: 0
---

# AG-VOTE — Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-01)

**Core value:** Self-hosted voting platform with legal compliance for French general assemblies
**Current focus:** v6.1 PDF & Preparation de Seance — Phase 65 (Attachment Upload & Serve)

## Current Position

Phase: 1 of 2 (Phase 65: Attachment Upload & Serve)
Plan: 0 of 1 in current phase
Status: Ready to plan
Last activity: 2026-04-01 — Roadmap created for v6.1

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

- [v6.1 roadmap]: 2 phases derived from 5 requirements — operator infrastructure first (65), then voter access (66)
- [v6.1 roadmap]: ATTACH-05 (serve endpoint) grouped with operator phase because it must exist before voters can access files
- [v6.1 roadmap]: Reuse ResolutionDocumentController::serve() dual-auth pattern (session OR vote token)
- [v6.1 roadmap]: Reuse ag-pdf-viewer (inline/sheet/panel modes) for voter document viewing
- [v6.1 roadmap]: Mirror wizard step 2 FilePond pattern in step 1 for meeting attachments
- [v6.1 roadmap]: Mirror hub resolution doc badges pattern for meeting attachments section

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

Last session: 2026-04-01
Stopped at: Roadmap created for v6.1, ready to plan Phase 65
Resume file: None
