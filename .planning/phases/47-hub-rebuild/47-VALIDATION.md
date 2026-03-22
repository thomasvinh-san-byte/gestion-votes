---
phase: 47
slug: hub-rebuild
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-22
---

# Phase 47 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None — manual browser testing |
| **Config file** | n/a |
| **Quick run command** | Open browser at `/hub.htmx.html?id={meeting_id}` |
| **Full suite command** | Manual: load hub, verify checklist, quorum bar, lifecycle actions |
| **Estimated runtime** | ~90 seconds |

---

## Sampling Rate

- **After every task commit:** Open `/hub.htmx.html?id=1`, verify page loads
- **After every plan wave:** Full lifecycle flow: checklist + quorum + actions
- **Before `/gsd:verify-work`:** All 4 success criteria verified
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 47-01-01 | 01 | 1 | REB-05 | manual-visual | Browser → card layout, quorum bar, checklist | n/a | ⬜ pending |
| 47-02-01 | 02 | 2 | WIRE-01 | manual-functional | Load hub → checklist reflects real state | n/a | ⬜ pending |
| 47-02-02 | 02 | 2 | REB-05 | manual-functional | Click lifecycle actions → backend responds | n/a | ⬜ pending |
| 47-02-03 | 02 | 2 | REB-05 | manual-visual | Blocked reasons show inline | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test files to create.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Quorum bar shows real attendance | REB-05 | Requires live data | Load hub with meeting, check count matches DB |
| Checklist reflects session state | REB-05 | State-dependent | Load hub, verify items match meeting status |
| Lifecycle actions trigger backend | REB-05 | End-to-end | Click "send convocation", verify API call succeeds |
| Blocked reasons display | REB-05 | State-dependent | Load hub with incomplete prerequisites, verify reasons |
| No dead endpoints | WIRE-01 | Network check | Check Network tab during all actions |

---

## Validation Sign-Off

- [x] All tasks have manual verification procedures
- [x] Sampling continuity: every commit gets browser smoke test
- [x] Wave 0 covers all MISSING references (none needed)
- [x] No watch-mode flags
- [x] Feedback latency < 90s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
