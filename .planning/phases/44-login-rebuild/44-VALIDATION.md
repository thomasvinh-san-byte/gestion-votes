---
phase: 44
slug: login-rebuild
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-20
---

# Phase 44 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None — manual browser testing |
| **Config file** | n/a |
| **Quick run command** | Open `/login.html` in browser |
| **Full suite command** | Full manual checklist below |
| **Estimated runtime** | ~60 seconds |

---

## Sampling Rate

- **After every task commit:** Open `/login.html` — visual pass + submit one test credential
- **After every plan wave:** Full manual checklist
- **Before `/gsd:verify-work`:** All 8 behaviors verified
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 44-01-01 | 01 | 1 | REB-02 | manual-visual | open browser → inspect layout at 1024px+ | n/a | ⬜ pending |
| 44-01-02 | 01 | 1 | REB-02 | manual-interaction | type in email/password → verify floating labels | n/a | ⬜ pending |
| 44-01-03 | 01 | 1 | REB-02 | manual-visual | observe gradient orb animation for 5s | n/a | ⬜ pending |
| 44-01-04 | 01 | 1 | REB-02 | manual-visual | toggle theme → inspect dark mode parity | n/a | ⬜ pending |
| 44-02-01 | 02 | 1 | WIRE-01 | manual-smoke | submit demo admin credentials → verify redirect | n/a | ⬜ pending |
| 44-02-02 | 02 | 1 | WIRE-01 | manual-interaction | click submit with empty fields → inline errors | n/a | ⬜ pending |
| 44-02-03 | 02 | 1 | WIRE-01 | manual-interaction | submit wrong password → error banner + field highlights | n/a | ⬜ pending |
| 44-02-04 | 02 | 1 | WIRE-01 | manual-smoke | visit /login.html while session active → auto-redirect | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test files to create — all validation is manual browser testing.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Login page renders at 1024px+ without breakage | REB-02 | Visual layout check | Open browser at 1024px viewport, inspect card centering and field alignment |
| Floating labels float on focus and when filled | REB-02 | CSS interaction | Type in email/password fields, verify label animation |
| Gradient orb animates without layout shift | REB-02 | Visual animation | Observe page for 5 seconds, check no content jump |
| Dark mode full parity | REB-02 | Visual comparison | Toggle theme, inspect card/orb/inputs/button |
| Submit form → API → redirect | WIRE-01 | End-to-end auth flow | Use demo admin credentials, verify dashboard landing |
| Empty fields show inline error | WIRE-01 | Interaction test | Click submit with empty fields, check red borders + banner |
| Wrong credentials show error | WIRE-01 | Interaction test | Submit wrong password, check error message + highlights |
| Already-logged-in auto-redirect | WIRE-01 | Session state test | Visit /login.html with active session |

---

## Validation Sign-Off

- [x] All tasks have manual verification procedures
- [x] Sampling continuity: every commit gets browser smoke test
- [x] Wave 0 covers all MISSING references (none needed)
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
