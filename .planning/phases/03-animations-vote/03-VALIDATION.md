---
phase: 3
slug: animations-vote
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-29
---

# Phase 3 — Validation Strategy

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
| **Known infra issue** | `libatk-1.0.so.0` missing in dev env — fall back to `node --check` + grep validation |

---

## Sampling Rate

- **After every task commit:** `node --check` on each modified JS file (sub-second)
- **After plan complete:** Quick run command (or grep checks if Playwright blocked)
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 3-01-01 | 01 | 1 | ANIM-01,ANIM-03 | — | N/A | grep+visual | `node --check` + grep counter helper | TBD | pending |
| 3-01-02 | 01 | 1 | ANIM-01 | — | N/A | grep | grep bump class + integration | TBD | pending |
| 3-01-03 | 01 | 1 | ANIM-02,ANIM-03 | — | N/A | grep | grep existing bar transition unchanged | TBD | pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new tooling needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Counter tween perception | ANIM-01 | Animation timing not assertable headlessly | Cast a vote in browser, verify counter visibly increments over ~400ms |
| Bar slide perception | ANIM-02 | Animation timing not assertable headlessly | Cast a vote, verify bar width slides smoothly (no jump) |
| Reduced motion respected | ANIM-03 | Requires OS pref toggle | Enable reduced-motion in OS, cast vote, verify instant update (no tween, no bump) |

---

## Validation Sign-Off

- [x] All tasks have automated grep verify
- [x] Sampling continuity: every task has automated check
- [x] Wave 0 covers all MISSING references (none required)
- [x] No watch-mode flags
- [x] Feedback latency < 30s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-04-29
