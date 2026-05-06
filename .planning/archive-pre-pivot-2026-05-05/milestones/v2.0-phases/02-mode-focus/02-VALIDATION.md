---
phase: 2
slug: mode-focus
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-29
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (existing E2E suite) |
| **Config file** | `tests/e2e/playwright.config.ts` |
| **Quick run command** | `npx playwright test tests/e2e/operator-e2e.spec.js --project=chromium` |
| **Full suite command** | `npx playwright test tests/e2e/ --project=chromium` |
| **Estimated runtime** | ~30 seconds (target file) |
| **Known infra issue** | `libatk-1.0.so.0` missing in dev env — fall back to `node --check` + grep validation when blocked |

---

## Sampling Rate

- **After every task commit:** Run `node --check` on each modified JS file (sub-second feedback)
- **After every plan wave:** Run quick run command (30s); if blocked, run grep-based acceptance checks from plan
- **Before phase verify:** Quick run must be green (or grep checks all PASS if Playwright blocked)
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 2-01-01 | 01 | 1 | FOCUS-01 | — | N/A | grep+visual | grep checks per plan | TBD | pending |
| 2-01-02 | 01 | 1 | FOCUS-01,FOCUS-02 | — | N/A | grep+visual | grep checks per plan | TBD | pending |
| 2-02-01 | 02 | 2 | FOCUS-03 | — | N/A | grep+e2e | `node --check` + grep | TBD | pending |
| 2-02-02 | 02 | 2 | FOCUS-01 | — | N/A | grep+e2e | `node --check` + grep | TBD | pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. Playwright E2E suite already has `operator-e2e.spec.js`. No new tooling needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| 5-zone visual layout at 1080p | FOCUS-01 | Layout perception not assertable in headless test | Open operator page in 1920x1080 viewport, click Focus toggle, verify only 5 zones visible: title, vote result, quorum, chrono, actions |
| Action buttons accessible without scroll | FOCUS-02 | Viewport-dependent | Verify in 1080p that Proclamer/Fermer/Suivante buttons are visible without scrolling |
| Toggle persists across mode switches | FOCUS-03 | Requires multi-step interaction | Activate focus, switch to setup mode, switch back to exec — focus mode should restore |

---

## Validation Sign-Off

- [x] All tasks have automated grep verify
- [x] Sampling continuity: every task has automated check
- [x] Wave 0 covers all MISSING references (none required)
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-04-29
