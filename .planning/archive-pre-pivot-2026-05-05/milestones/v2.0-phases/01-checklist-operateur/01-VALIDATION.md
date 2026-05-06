---
phase: 1
slug: checklist-operateur
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (existing E2E suite) |
| **Config file** | `tests/e2e/playwright.config.ts` |
| **Quick run command** | `npx playwright test tests/e2e/operator-e2e.spec.js --project=chromium` |
| **Full suite command** | `npx playwright test tests/e2e/ --project=chromium` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php -l` on modified PHP files (none expected — pure frontend phase)
- **After every plan wave:** Run quick run command
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 1-01-01 | 01 | 1 | CHECK-01 | — | N/A | e2e | `npx playwright test tests/e2e/operator-e2e.spec.js` | TBD | pending |
| 1-01-02 | 01 | 1 | CHECK-02 | — | N/A | e2e | `npx playwright test tests/e2e/operator-e2e.spec.js` | TBD | pending |
| 1-01-03 | 01 | 1 | CHECK-03 | — | N/A | e2e | `npx playwright test tests/e2e/operator-e2e.spec.js` | TBD | pending |
| 1-01-04 | 01 | 1 | CHECK-04 | — | N/A | e2e | `npx playwright test tests/e2e/operator-e2e.spec.js` | TBD | pending |
| 1-01-05 | 01 | 1 | CHECK-05 | — | N/A | visual | Manual inspection | N/A | pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. Playwright E2E suite already has operator-e2e.spec.js.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Alert animation (pulse 3s) | CHECK-05 | CSS animation timing not assertable in Playwright | Verify red flash + icon pulse appears when quorum drops below threshold |
| SSE disconnect banner | CHECK-03 | Requires network interruption | Disconnect SSE, verify "Connexion perdue" banner appears |

---

## Validation Sign-Off

- [ ] All tasks have automated verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
