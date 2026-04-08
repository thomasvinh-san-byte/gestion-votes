---
phase: 6
slug: application-design-tokens
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-08
---

# Phase 6 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (E2E) + visual grep checks |
| **Config file** | playwright.config.js |
| **Quick run command** | grep verification commands per task |
| **Full suite command** | npx playwright test tests/e2e/specs/login.spec.js tests/e2e/specs/visual.spec.js |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run task-level grep verification
- **After every plan wave:** Run Playwright visual specs
- **Before verify-work:** Full Playwright suite green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | Status |
|---------|------|------|-------------|-----------|-------------------|--------|
| 06-01-01 | 01 | 1 | DESIGN-01 | layout | grep grid-template-columns login.css | pending |
| 06-02-01 | 02 | 1 | DESIGN-02 | tokens | grep -E '#[0-9a-f]{6}' per-page CSS | pending |
| 06-03-01 | 03 | 1 | DESIGN-03 | loading | grep htmx-indicator HTML | pending |
| 06-04-01 | 04 | 1 | DESIGN-04 | badges | grep badge-success class definitions | pending |

---

## Wave 0 Requirements

- [ ] @layer pages declaration must be added to app.css before per-page overrides
- [ ] Existing Playwright infra covers login + visual specs

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Visual coherence across pages | DESIGN-02 | Subjective design quality | Browse all pages, verify consistent look |
| Loading indicator visible | DESIGN-03 | Timing-dependent | Trigger HTMX action, observe indicator |

---

## Validation Sign-Off

- [ ] All tasks have automated verify or Wave 0 dependencies
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] nyquist_compliant: true set

**Approval:** pending
