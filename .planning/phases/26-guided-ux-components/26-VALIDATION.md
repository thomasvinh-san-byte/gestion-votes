---
phase: 26
slug: guided-ux-components
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 26 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (backend) + Playwright 1.50 (E2E) |
| **Config file** | `phpunit.xml` / `playwright.config.ts` |
| **Quick run command** | `grep -rn "ag-empty-state\|ag-tooltip.*disabled" public/` |
| **Full suite command** | `php vendor/bin/phpunit && npx playwright test` |
| **Estimated runtime** | ~30 seconds (grep) + ~60 seconds (Playwright) |

---

## Sampling Rate

- **After every task commit:** Run structural grep checks
- **After every plan wave:** Run `php vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 5 seconds (grep-based)

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 26-01-01 | 01 | 1 | GUX-06 | unit | `grep -q "customElements.define('ag-empty-state'" public/assets/js/components/ag-empty-state.js` | ❌ W0 | ⬜ pending |
| 26-01-02 | 01 | 1 | GUX-02 | grep | `grep -rn "ag-empty-state" public/assets/js/pages/ \| wc -l` | ✅ | ⬜ pending |
| 26-02-01 | 02 | 2 | GUX-01 | grep | `grep -q "statusCta\|nextAction" public/assets/js/pages/dashboard.js` | ✅ | ⬜ pending |
| 26-02-02 | 02 | 2 | GUX-03 | grep | `grep -q "Disponible après" public/assets/js/pages/` | ✅ | ⬜ pending |
| 26-03-01 | 03 | 3 | GUX-04 | grep | `grep -q "ag-popover.*help\|helpPanel" public/assets/js/pages/` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `public/assets/js/components/ag-empty-state.js` — Web Component stub

*Existing ag-tooltip and ag-popover cover all other requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Live session card has pulsing green dot | GUX-01 | CSS animation visual | Open dashboard with a live session, verify green pulse |
| Disabled button tooltip appears on hover | GUX-03 | Hover interaction | Hover over disabled "Figer la séance" button, verify tooltip text |
| Help panel opens on btnTour click | GUX-04 | Click interaction | Click help button on wizard page, verify panel content |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 5s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
