---
phase: 5
slug: js-audit-et-wiring-repair
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-07
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright 1.58.2 (E2E) + PHPUnit 10.5 (Unit) |
| **Config file** | playwright.config.js, phpunit.xml |
| **Quick run command** | `npx playwright test --grep @smoke` |
| **Full suite command** | `npx playwright test && timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Estimated runtime** | ~30 seconds (Playwright smoke) |

---

## Sampling Rate

- **After every task commit:** Run `php -l` on changed PHP files + `npx playwright test --grep @smoke`
- **After every plan wave:** Run full Playwright suite
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 01 | 1 | WIRE-01 | audit | grep -r querySelector public/assets/js/ | ✅ | ⬜ pending |
| 05-02-01 | 02 | 1 | WIRE-02 | e2e | npx playwright test --grep @wiring | ❌ W0 | ⬜ pending |
| 05-03-01 | 03 | 2 | WIRE-03 | e2e | npx playwright test --grep @sidebar | ❌ W0 | ⬜ pending |
| 05-04-01 | 04 | 2 | WIRE-04 | e2e | npx playwright test --grep @htmx | ❌ W0 | ⬜ pending |

*Status: ⬜ pending . ✅ green . ❌ red . ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Existing Playwright infrastructure covers base needs
- [ ] waitForHtmxSettled() helper to be created as part of WIRE-04

*Existing infrastructure covers most phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Sidebar visual flash | WIRE-03 | Timing-dependent visual artifact | Open any page, observe sidebar loads without flicker |
| All pages load without JS errors | WIRE-02 | Requires checking console across all 21 pages | Open each page, check browser DevTools console |

---

## Validation Sign-Off

- [ ] All tasks have automated verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] nyquist_compliant: true set in frontmatter

**Approval:** pending
