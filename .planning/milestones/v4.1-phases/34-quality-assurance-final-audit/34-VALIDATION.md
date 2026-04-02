---
phase: 34
slug: quality-assurance-final-audit
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-19
---

# Phase 34 — Validation Strategy

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
| 34-01-01 | 01 | 1 | QA-03 | grep | `grep -r "font-display" public/assets/css/ \| grep -v "h1\|\.page-title\|@font-face\|--font-display"` | ✅ | ⬜ pending |
| 34-01-02 | 01 | 1 | QA-02 | grep | `grep -rL "color-surface-raised" public/assets/css/*.css` | ✅ | ⬜ pending |
| 34-02-01 | 02 | 1 | QA-05 | grep | `grep -rn "style=" public/*.htmx.html \| grep -v "display:none\|hidden"` | ✅ | ⬜ pending |
| 34-02-02 | 02 | 1 | QA-01,QA-05 | grep | `grep -rn "0\\.3s\|300ms\|0\\.5s\|500ms" public/assets/css/*.css` | ✅ | ⬜ pending |

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. QA audit work is verified via grep patterns and visual inspection.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Dark mode visual parity | QA-04 | Visual appearance requires rendering | Toggle dark mode on every page — check for invisible borders, pure black, washed text |
| Three distinct tonal layers | QA-02 | Eyedropper test on screenshots | Take screenshot, use color picker — 3 distinct background values visible |
| Hover transform feel | QA-01 | Interaction requires mouse hover | Hover interactive elements — verify lift/elevation change is perceptible |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
