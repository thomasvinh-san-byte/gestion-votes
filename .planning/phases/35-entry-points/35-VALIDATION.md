---
phase: 35
slug: entry-points
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-19
---

# Phase 35 — Validation Strategy

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
- **After every plan wave:** Run full suite
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 35-01-01 | 01 | 1 | CORE-01 | grep | `grep "font-mono\|JetBrains" public/assets/css/pages.css` | ✅ | ⬜ pending |
| 35-01-02 | 01 | 1 | CORE-01 | grep | `grep "ag-badge\|ag-tooltip" public/dashboard.htmx.html` | ✅ | ⬜ pending |
| 35-02-01 | 02 | 1 | SEC-01 | grep | `grep "Fraunces\|font-display" public/assets/css/login.css` | ✅ | ⬜ pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| KPI cards visual hierarchy | CORE-01 | Composition requires rendering | View dashboard — KPI numbers should be large mono, labels small |
| Login Clerk-level polish | SEC-01 | Visual quality requires browser | View login — should feel premium, not generic |
| Tooltip coverage | UX-01 | Interaction requires hover | Hover all KPI cards and action buttons — tooltips should appear |
| Dark mode parity | UX-02 | Visual check | Toggle dark mode on both pages — equally polished |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
