---
phase: 32
slug: page-layouts-core-pages
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-19
---

# Phase 32 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.x + Playwright E2E |
| **Config file** | `phpunit.xml` / `playwright.config.js` |
| **Quick run command** | `php vendor/bin/phpunit --testsuite=unit` |
| **Full suite command** | `php vendor/bin/phpunit && npx playwright test` |
| **Estimated runtime** | ~45 seconds (unit) / ~3 min (E2E) |

---

## Sampling Rate

- **After every task commit:** Run grep verification commands
- **After every plan wave:** Run `php vendor/bin/phpunit && npx playwright test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 32-01-01 | 01 | 1 | LAY-01 | grep/visual | `grep "max-width.*1200" public/assets/css/pages.css` | ✅ | ⬜ pending |
| 32-01-02 | 01 | 1 | LAY-02 | grep/visual | `grep "max-width.*680" public/assets/css/wizard.css` | ✅ | ⬜ pending |
| 32-01-03 | 01 | 1 | LAY-03 | grep/visual | `grep "display.*grid" public/assets/css/operator.css` | ✅ | ⬜ pending |
| 32-02-01 | 02 | 2 | LAY-04 | grep/visual | `grep "table-page" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 32-02-02 | 02 | 2 | LAY-05 | grep/visual | `grep "max-width.*720" public/assets/css/settings.css` | ✅ | ⬜ pending |
| 32-02-03 | 02 | 2 | LAY-06 | grep/visual | `grep "clamp(" public/assets/css/vote.css` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. CSS layout changes are verified via grep patterns and visual inspection. No new test framework needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Three-depth background visual layers | LAY-01 | Three tonal layers require visual comparison | View dashboard — bg/surface/raised should show 3 distinct gray levels |
| Wizard centered form track | LAY-02 | Centering and max-width require visual check | Open wizard — form should be narrow and centered, not full-width |
| Operator CSS grid layout | LAY-03 | Grid areas require layout inspection in DevTools | Open operator — inspect `.app-shell` should show `display: grid` |
| Data table visual consistency | LAY-04 | Cross-page consistency requires side-by-side comparison | Open audit, archives, members, users — toolbar/table/pagination should look identical |
| Mobile voter at 375px | LAY-06 | Viewport behavior requires device/emulator | Set viewport to 375px width — no horizontal scrolling, buttons ≥72px |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
