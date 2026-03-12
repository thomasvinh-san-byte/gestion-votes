---
phase: 5
slug: shared-components
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-12
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | E2E specs (Playwright) + grep/diff validation |
| **Config file** | `tests/e2e/playwright.config.js` |
| **Quick run command** | `grep -c "customElements.define" public/assets/js/components/*.js` |
| **Full suite command** | `npx playwright test tests/e2e/specs/ux-interactions.spec.js --reporter=list 2>&1 \| tail -20` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick validation (component registration check + grep for hardcoded hex)
- **After every plan wave:** Run E2E interaction tests
- **Before `/gsd:verify-work`:** Full E2E suite must pass
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 01 | 1 | COMP-01 | grep | `grep "color-surface-raised\|color-backdrop" ag-modal.js` | ✅ | ⬜ pending |
| 05-01-02 | 01 | 1 | COMP-02 | grep | `grep "variant.*danger\|variant.*warn\|variant.*info" ag-confirm.js` | ✅ | ⬜ pending |
| 05-02-01 | 02 | 1 | COMP-03 | grep | `grep "auto-dismiss\|duration" ag-toast.js` | ✅ | ⬜ pending |
| 05-02-02 | 02 | 1 | COMP-05 | grep | `grep "color-danger-subtle\|color-success-subtle" ag-badge.js` | ✅ | ⬜ pending |
| 05-03-01 | 03 | 1 | COMP-04 | grep | `grep "emptyState\|empty-state" public/assets/js/core/shared.js` | ✅ | ⬜ pending |
| 05-03-02 | 03 | 1 | COMP-06 | grep | `grep "progress\|quorum" ag-mini-bar.js` | ✅ | ⬜ pending |
| 05-03-03 | 03 | 1 | COMP-07 | grep | `grep "color-surface-raised\|shadow" ag-popover.js` | ✅ | ⬜ pending |
| 05-04-01 | 04 | 2 | COMP-08 | grep | `grep "ob-banner\|session.*expir" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 05-04-02 | 04 | 2 | COMP-09 | grep | `grep "tour-card\|tour-step\|data-tour" public/assets/css/design-system.css` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

*Existing infrastructure covers all phase requirements — components and E2E tests already exist.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Modal open/close animation smoothness | COMP-01 | CSS animation quality needs human eye | Open any modal, verify fade+scale animation at 150ms |
| Toast position and stacking | COMP-03 | Visual layout in top-right corner | Trigger 4+ toasts, verify max 3 visible, oldest dismissed |
| Dark theme rendering for all components | ALL | Theme switching visual correctness | Toggle dark/light, check every component type |
| Tour highlight positioning | COMP-09 | Element overlay accuracy | Start a tour, verify highlight aligns with target element |

---

## Validation Sign-Off

- [x] All tasks have automated verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 15s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
