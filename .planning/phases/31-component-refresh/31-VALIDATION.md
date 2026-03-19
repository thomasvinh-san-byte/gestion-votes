---
phase: 31
slug: component-refresh
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-19
---

# Phase 31 — Validation Strategy

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

- **After every task commit:** Run `php vendor/bin/phpunit --testsuite=unit`
- **After every plan wave:** Run `php vendor/bin/phpunit && npx playwright test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 31-01-01 | 01 | 1 | CMP-01 | grep/visual | `grep "min-height" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 31-01-02 | 01 | 1 | CMP-02 | grep/visual | `grep "radius-xl" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 31-01-03 | 01 | 1 | CMP-03 | grep/visual | `grep "font-family.*mono" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 31-01-04 | 01 | 1 | CMP-04 | grep/visual | `grep "shadow-focus" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 31-01-05 | 01 | 1 | CMP-05 | grep/visual | `grep "shadow-xl" public/assets/js/components/ag-modal.js` | ✅ | ⬜ pending |
| 31-01-06 | 01 | 1 | CMP-06 | grep/visual | `grep "var(--" public/assets/js/components/ag-toast.js` | ✅ | ⬜ pending |
| 31-01-07 | 01 | 1 | CMP-07 | grep/visual | `grep "var(--" public/assets/js/components/ag-badge.js` | ✅ | ⬜ pending |
| 31-01-08 | 01 | 1 | CMP-08 | grep/visual | `grep "var(--" public/assets/js/components/ag-stepper.js` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. CSS component changes are verified via grep patterns and visual inspection. No new test framework needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Distinct border-radius visual | CMP-01,02,07,05 | Visual differentiation requires rendering | Place button, card, badge, modal side by side — each must look distinct |
| Card hover elevation | CMP-02 | CSS transition requires mouse interaction | Hover card — shadow should deepen, subtle lift visible |
| Table data density | CMP-03 | Visual density comparison | Compare table to Linear/Jira reference — row height, header, alignment |
| Focus ring visibility | CMP-04 | Requires keyboard navigation | Tab through inputs — double-ring must be visible on all backgrounds |
| Toast slide-in animation | CMP-06 | Animation requires interaction | Trigger success/error toast — slide-in from right, accent stripe visible |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
