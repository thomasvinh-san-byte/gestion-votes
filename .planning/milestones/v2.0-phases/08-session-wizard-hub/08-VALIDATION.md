---
phase: 8
slug: session-wizard-hub
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None — no test framework in project; manual browser inspection |
| **Config file** | none |
| **Quick run command** | `grep -n 'style="' public/wizard.htmx.html public/hub.htmx.html` |
| **Full suite command** | Manual review of all 4 wizard steps + all hub lifecycle stages |
| **Estimated runtime** | ~2 seconds (grep) + ~10 minutes (manual) |

---

## Sampling Rate

- **After every task commit:** Run `grep -n 'style="' public/wizard.htmx.html public/hub.htmx.html` (should find zero static inline styles)
- **After every plan wave:** Manual browser review of wizard flow + hub lifecycle in both light and dark themes
- **Before `/gsd:verify-work`:** Full manual suite must be green
- **Max feedback latency:** 2 seconds (automated grep)

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 08-01-01 | 01 | 1 | WIZ-01 | manual | Open wizard, advance steps, verify stepper visual | N/A | ⬜ pending |
| 08-01-02 | 01 | 1 | WIZ-02 | manual | Leave title/date empty, click Suivant — should not advance | N/A | ⬜ pending |
| 08-01-03 | 01 | 1 | WIZ-03 | manual | Drag CSV onto drop zone, verify table rows | N/A | ⬜ pending |
| 08-01-04 | 01 | 1 | WIZ-04 | manual | Add 3 resolutions, drag reorder | N/A | ⬜ pending |
| 08-01-05 | 01 | 1 | WIZ-05 | manual | Fill all steps, click Créer, verify redirect + toast | N/A | ⬜ pending |
| 08-01-06 | 01 | 1 | WIZ-05 | manual | Fill Step 1, reload, verify localStorage restore | N/A | ⬜ pending |
| 08-02-01 | 02 | 1 | HUB-01 | manual | Open hub, verify status bar with colored segments | N/A | ⬜ pending |
| 08-02-02 | 02 | 1 | HUB-02 | manual | Change lifecycle step, verify action card CTA changes | N/A | ⬜ pending |
| 08-02-03 | 02 | 1 | HUB-03 | manual | Open hub with session data, verify 4 KPI cards | N/A | ⬜ pending |
| 08-02-04 | 02 | 1 | HUB-04 | manual | Load hub with complete session, verify auto-checked items | N/A | ⬜ pending |
| 08-02-05 | 02 | 1 | HUB-05 | manual | Open hub, verify document list with download buttons | N/A | ⬜ pending |
| 08-XX-XX | all | all | Both | automated | `grep -n 'style="' public/wizard.htmx.html public/hub.htmx.html` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] No test framework — all validation is manual browser inspection
- [ ] `grep` command for inline style detection is the only automated check available

*Existing infrastructure covers automated inline-style detection. All behavioral tests require manual browser inspection.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Stepper done/active/pending states | WIZ-01 | Visual CSS state rendering | Open wizard, advance steps, verify stepper shows correct state per step |
| Required field validation | WIZ-02 | Form interaction behavior | Leave title/date empty, click Suivant — button should stay disabled |
| CSV import populates table | WIZ-03 | File I/O + DOM rendering | Drag CSV file onto drop zone, verify member rows appear in table |
| Resolution drag-and-drop reorder | WIZ-04 | Drag interaction behavior | Add 3 resolutions, drag second to first position, verify new order |
| Créer redirects to hub with toast | WIZ-05 | Navigation + toast component | Fill all wizard steps, click Créer, verify redirect and success toast |
| localStorage draft persistence | WIZ-05 | Browser storage behavior | Fill Step 1, reload page, verify fields are restored |
| Status bar colored segments | HUB-01 | Visual rendering | Open hub, verify horizontal bar with colored stage segments |
| Action card dynamic CTA | HUB-02 | State-dependent rendering | Navigate lifecycle steps, verify CTA text/icon/color change |
| 4 KPI cards with values | HUB-03 | Data-driven rendering | Open hub with known session, verify participants/resolutions/quorum/convocations |
| Checklist auto-check behavior | HUB-04 | Data-conditional logic | Load hub with complete session, verify 4/6 items auto-checked |
| Documents panel download links | HUB-05 | List rendering + links | Open hub, verify document list with working download buttons |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 2s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
