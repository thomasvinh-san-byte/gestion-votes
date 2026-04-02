---
phase: 46
slug: operator-console-rebuild
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-22
---

# Phase 46 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None — manual browser testing + E2E specs exist (operator.spec.js) |
| **Config file** | n/a |
| **Quick run command** | Open browser at `/operator.htmx.html` |
| **Full suite command** | Manual: select meeting, verify SSE, open/close vote, check all tabs |
| **Estimated runtime** | ~120 seconds |

---

## Sampling Rate

- **After every task commit:** Open `/operator.htmx.html`, verify page loads, select a meeting
- **After every plan wave:** Full operator flow: meeting select → tabs → open vote → SSE → close vote
- **Before `/gsd:verify-work`:** All 5 success criteria verified
- **Max feedback latency:** 120 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 46-01-01 | 01 | 1 | REB-04 | manual-visual | Browser → two-panel layout, meeting bar, tabs | n/a | ⬜ pending |
| 46-01-02 | 01 | 1 | REB-04 | manual-visual | Click motions in sidebar → loads in main panel | n/a | ⬜ pending |
| 46-02-01 | 02 | 2 | WIRE-01, WIRE-02 | manual-functional | SSE connects → indicator green | n/a | ⬜ pending |
| 46-02-02 | 02 | 2 | WIRE-02 | manual-functional | Open vote → live counts update → close vote | n/a | ⬜ pending |
| 46-02-03 | 02 | 2 | REB-04 | manual-visual | Disabled buttons show tooltips | n/a | ⬜ pending |
| 46-02-04 | 02 | 2 | WIRE-02 | manual-visual | Delta badge +N on vote events | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test files to create.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| SSE connection establishes | WIRE-02 | Requires live server | Load page, check indicator turns green |
| Live vote count updates via SSE | WIRE-02 | Real-time event stream | Open vote, cast votes in another tab, observe counts |
| Agenda sidebar lists motions | REB-04 | Visual layout check | Select meeting with motions, verify sidebar populated |
| Click motion loads in main panel | REB-04 | Interaction test | Click motion in sidebar, verify main panel updates |
| Disabled button tooltips | REB-04 | Visual interaction | Hover disabled buttons, verify tooltip text |
| Delta badge increments | WIRE-02 | Real-time visual | Cast votes, observe +N badge animation |
| Open/close vote flow | WIRE-02 | End-to-end functional | Open vote → observe counts → close vote → result recorded |

---

## Validation Sign-Off

- [x] All tasks have manual verification procedures
- [x] Sampling continuity: every commit gets browser smoke test
- [x] Wave 0 covers all MISSING references (none needed)
- [x] No watch-mode flags
- [x] Feedback latency < 120s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
