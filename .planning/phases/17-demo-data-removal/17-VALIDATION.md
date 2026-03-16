---
phase: 17
slug: demo-data-removal
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-16
---

# Phase 17 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (E2E, browser-based) |
| **Config file** | `tests/e2e/playwright.config.js` |
| **Quick run command** | `grep -c 'DEMO_EVENTS\|showFallback' public/assets/js/pages/audit.js public/assets/js/pages/dashboard.js` |
| **Full suite command** | `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js specs/audit-regression.spec.js` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `grep -c 'DEMO_EVENTS\|showFallback' public/assets/js/pages/audit.js public/assets/js/pages/dashboard.js` (expect all zeros)
- **After every plan wave:** Run `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js specs/audit-regression.spec.js`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds (grep) / 15 seconds (Playwright)

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 17-01-01 | 01 | 1 | HUB-03, HUB-04 | static grep | `grep -c 'showFallback' public/assets/js/pages/dashboard.js` | N/A | ⬜ pending |
| 17-01-02 | 01 | 1 | HUB-04 | E2E | `cd tests/e2e && npx playwright test --project=chromium specs/dashboard.spec.js` | ❌ W0 | ⬜ pending |
| 17-02-01 | 02 | 1 | CLN-03 | static grep | `grep -c 'DEMO_EVENTS' public/assets/js/pages/audit.js` | N/A | ⬜ pending |
| 17-02-02 | 02 | 1 | CLN-03 | E2E | `cd tests/e2e && npx playwright test --project=chromium specs/audit-regression.spec.js` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/e2e/specs/dashboard.spec.js` — add test: "KPI values are not hardcoded demo values" and "error banner shown when API fails"
- [ ] `tests/e2e/specs/audit.spec.js` — add smoke test: DEMO_EVENTS strings absent from rendered output, error state renders on API failure

*Existing Playwright infrastructure covers framework needs — only new test cases required.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Empty state visual appearance | HUB-03 | Visual design check | Load dashboard with zero sessions, verify message appears (not zeros or demo cards) |
| Error banner retry flow | HUB-04 | Requires backend down simulation | Stop PHP server, load dashboard, verify error banner appears with Réessayer button |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
