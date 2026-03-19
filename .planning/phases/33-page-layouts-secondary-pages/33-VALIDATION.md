---
phase: 33
slug: page-layouts-secondary-pages
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-19
---

# Phase 33 — Validation Strategy

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
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 33-01-01 | 01 | 1 | LAY-07 | grep | `grep "grid-template-columns.*220" public/assets/css/hub.css` | ✅ | ⬜ pending |
| 33-01-02 | 01 | 1 | LAY-08 | grep | `grep "max-width.*900" public/assets/css/postsession.css` | ✅ | ⬜ pending |
| 33-01-03 | 01 | 1 | LAY-09 | grep | `grep "minmax.*320" public/assets/css/analytics.css` | ✅ | ⬜ pending |
| 33-02-01 | 02 | 1 | LAY-10 | grep | `grep "padding.*space" public/assets/css/help.css` | ✅ | ⬜ pending |
| 33-02-02 | 02 | 1 | LAY-11 | grep | `grep "grid-template-columns.*400" public/assets/css/email-templates.css` | ✅ | ⬜ pending |
| 33-02-03 | 02 | 1 | LAY-12 | grep | `grep "space-3\|gap" public/assets/css/meetings.css` | ✅ | ⬜ pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. CSS layout changes verified via grep and visual inspection.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Quorum bar visual prominence | LAY-07 | Visual hierarchy requires rendering | View hub — quorum bar should be most prominent element |
| Accordion no layout shift | LAY-10 | Layout shift requires browser interaction | Expand FAQ items — surrounding content should not jump |
| Analytics 2-col at 1024px | LAY-09 | Responsive grid requires viewport test | Set viewport to 1024px — should show 2+ columns |
| Post-session collapsible cards | LAY-08 | Interaction requires click testing | Click result card headers — should collapse/expand smoothly |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
