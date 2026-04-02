---
phase: 45
slug: wizard-rebuild
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-22
---

# Phase 45 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None — manual browser testing |
| **Config file** | n/a |
| **Quick run command** | Open browser at `/wizard.htmx.html` |
| **Full suite command** | Complete all 4 steps, submit, verify session appears |
| **Estimated runtime** | ~90 seconds |

---

## Sampling Rate

- **After every task commit:** Open browser, navigate all 4 steps visually
- **After every plan wave:** Full creation flow: fill all fields, submit, verify session in hub
- **Before `/gsd:verify-work`:** All 5 success criteria verified
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 45-01-01 | 01 | 1 | REB-03 | manual-visual | Browser at 1024px → 4 steps fit viewport | n/a | ⬜ pending |
| 45-01-02 | 01 | 1 | REB-03 | manual-visual | Navigate steps → stepper active/done/pending | n/a | ⬜ pending |
| 45-01-03 | 01 | 1 | REB-03 | manual-visual | Click Next/Prev → slide transitions | n/a | ⬜ pending |
| 45-01-04 | 01 | 1 | REB-03 | manual-visual | Step 1: type/date/time on one row | n/a | ⬜ pending |
| 45-02-01 | 02 | 2 | WIRE-01, WIRE-03 | manual-functional | Complete wizard → verify session in hub | n/a | ⬜ pending |
| 45-02-02 | 02 | 2 | WIRE-03 | manual-functional | Submit with network error → error banner | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test files to create — all validation is manual browser testing.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| All 4 steps fit viewport at 1024px | REB-03 | Visual layout check | Set DevTools to 1024px, navigate each step |
| Stepper states (active/done/pending) | REB-03 | Visual check | Navigate forward/back, inspect classes |
| Slide transition animation | REB-03 | Visual animation | Click Next/Prev, observe transition |
| Horizontal field layout | REB-03 | Visual check | Step 1: type/date/time on one row at 1024px |
| Member add single-row layout | REB-03 | Visual check | Step 2: name/email/votes/button on one line |
| Session creation end-to-end | WIRE-03 | Functional test | Fill all steps, submit, verify redirect + DB |
| Error handling with data preservation | WIRE-03 | Functional test | Throttle network, submit, verify error + data retained |
| No dead endpoints | WIRE-01 | Network test | Check Network tab during full flow |

---

## Validation Sign-Off

- [x] All tasks have manual verification procedures
- [x] Sampling continuity: every commit gets browser smoke test
- [x] Wave 0 covers all MISSING references (none needed)
- [x] No watch-mode flags
- [x] Feedback latency < 90s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
